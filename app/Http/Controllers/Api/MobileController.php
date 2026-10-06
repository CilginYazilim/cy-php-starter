<?php
/**
 * =====================================================================
 *  MobileController – Mobil uygulama için oturum ve örnek dosya uçları
 * ---------------------------------------------------------------------
 *  Bir mobil uygulamanın ilk ihtiyacı: kullanıcı adı/parola ile giriş,
 *  açık oturumu saklamak, çıkış, cihazlarını görmek ve sunucudaki
 *  dosyaları listeleyip açmak. Hepsi /api/v1 altında, aynı yanıt
 *  zarfıyla ({ "success": true, "data": … }):
 *
 *      POST   /api/v1/oturum          giriş → { token, son_gecerlilik, kullanici }
 *      DELETE /api/v1/oturum          bu cihazdan çıkış (token iptal)
 *      GET    /api/v1/oturumlar       açık oturumlarım (bu_cihaz işaretli)
 *      DELETE /api/v1/oturumlar/{id}  başka bir cihazı kapat
 *      GET    /api/v1/dosyalar        örnek dosyaların listesi
 *      GET    /api/v1/dosyalar/{ad}   dosyanın kendisi (?indir=1 → ek olarak)
 *
 *  Giriş, tarayıcı girişiyle AYNI kapıdan geçer (Auth::verifyCredentials):
 *  kaba kuvvet sayacı, hesap kilidi ve durum denetimi ortaktır. Gövde
 *  form ya da JSON olabilir:
 *
 *      curl -X POST https://site.com/api/v1/oturum \
 *           -H "Content-Type: application/json" \
 *           -d '{"kullanici":"mehmet.uye","parola":"…","cihaz":"Pixel 8"}'
 *
 *  Dönen "token" sonraki her istekte "Authorization: Bearer …" olarak
 *  gönderilir. Kullanıcı panelde Hesabım → Bağlı cihazlar'dan görür.
 *  Ayrıntılar ve örnek akış: docs/MOBIL-API.md
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\AccountDeletion;
use App\Core\Api\ApiGuard;
use App\Core\Api\ApiResponse;
use App\Core\Api\ApiToken;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Log\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Storage\Storage;
use App\Core\Url;
use App\Http\Controller;
use App\Models\User;

final class MobileController extends Controller
{
    /** Örnek dosyaların durduğu klasör (private disk: storage/files/ornek). */
    public const DOSYA_KLASORU = 'ornek';

    /* =================================================================
     *  OTURUM
     * ============================================================== */

    /** POST api/v1/oturum — kullanıcı adı/e-posta + parola → Bearer token */
    public function login(Request $request): void
    {
        $kimlik = trim($request->string('kullanici'));
        $parola = $request->string('parola');
        $cihaz  = trim($request->string('cihaz'));

        $hatalar = [];

        if ($kimlik === '') {
            $hatalar['kullanici'] = 'E-posta veya kullanıcı adı gerekli.';
        }
        if ($parola === '') {
            $hatalar['parola'] = 'Parola gerekli.';
        }
        if ($hatalar !== []) {
            ApiResponse::validationFailed($hatalar);
        }

        $sonuc = Auth::verifyCredentials($kimlik, $parola, $request);

        if (!$sonuc['ok'] || !($sonuc['user'] ?? null) instanceof User) {
            ApiResponse::error($sonuc['message'] !== '' ? $sonuc['message'] : 'Giriş yapılamadı.', 401, 'giris_basarisiz');
        }

        /** @var User $user */
        $user = $sonuc['user'];

        if ($cihaz === '') {
            $cihaz = mb_substr(trim($request->userAgent()), 0, 100) ?: 'Mobil uygulama';
        }

        $gun   = max(1, min(365, (int) Config::get('api.session_days', 30)));
        $kayit = ApiToken::create($user->id, 'Mobil oturum', $gun, ApiToken::YAZMA, ApiToken::TUR_OTURUM, $cihaz);

        (new \App\Repositories\UserRepository($this->db))->touchLogin($user->id, $request->ip());
        $silmeIptal = AccountDeletion::cancel($user->id);

        ApiResponse::created([
            'token'          => $kayit['token'],
            'token_turu'     => 'Bearer',
            'oturum_id'      => $kayit['id'],
            'son_gecerlilik' => date(DATE_ATOM, time() + $gun * 86400),
            'silme_iptal'    => $silmeIptal,
            'kullanici'      => self::userData($user),
        ]);
    }

    /** DELETE api/v1/oturum — bu cihazın token'ı iptal edilir. */
    public function logout(Request $request): void
    {
        $token = ApiGuard::token();
        $user  = Auth::user();

        if ($token === null || $user === null) {
            // Panel oturumuyla gelinmiş: kapatılacak bir mobil oturum yok.
            ApiResponse::error('Bu uç yalnızca Bearer token ile çağrılır.', 400, 'token_yok');
        }

        ApiToken::revokeOwned($user->id, $token['id']);

        ApiResponse::noContent();
    }

    /** GET api/v1/oturumlar — kullanıcının açık mobil oturumları */
    public function sessions(Request $request): void
    {
        $user = Auth::user() ?? ApiResponse::unauthorized();
        $bu   = ApiGuard::token()['id'] ?? null;

        ApiResponse::success(array_map(static fn (array $o): array => [
            'id'             => (int) $o['id'],
            'cihaz'          => (string) (($o['cihaz'] ?? '') !== '' ? $o['cihaz'] : $o['ad']),
            'acildi'         => self::iso($o['created_at'] ?? null),
            'son_kullanim'   => self::iso($o['son_kullanim'] ?? null),
            'son_gecerlilik' => self::iso($o['son_gecerlilik'] ?? null),
            'bu_cihaz'       => (int) $o['id'] === $bu,
        ], ApiToken::forUser($user->id, ApiToken::TUR_OTURUM)));
    }

    /** DELETE api/v1/oturumlar/{id} — kendi oturumlarından birini kapatır. */
    public function revokeSession(Request $request, string $id): void
    {
        $user = Auth::user() ?? ApiResponse::unauthorized();

        if (!ApiToken::revokeOwned($user->id, (int) $id)) {
            ApiResponse::notFound('Oturum bulunamadı.');
        }

        ApiResponse::noContent();
    }

    /* =================================================================
     *  ÖRNEK DOSYALAR
     * ============================================================== */

    /** GET api/v1/dosyalar — storage/files/ornek içindeki dosyalar */
    public function files(Request $request): void
    {
        $disk  = Storage::disk('private');
        $liste = [];

        foreach ($disk->files(self::DOSYA_KLASORU) as $yol) {
            $ad = basename($yol);

            if (str_starts_with($ad, '.')) {
                continue;
            }

            $liste[] = [
                'ad'       => $ad,
                'tur'      => $disk->mime($yol),
                'boyut'    => $disk->size($yol),
                'degisti'  => date(DATE_ATOM, $disk->lastModified($yol)),
                'adres'    => Url::absolute('api/v1/dosyalar/' . rawurlencode($ad)),
            ];
        }

        ApiResponse::success($liste, ['toplam' => count($liste)]);
    }

    /** GET api/v1/dosyalar/{ad} — dosyanın kendisi (görsel/metin satır içi açılır) */
    public function file(Request $request, string $ad): void
    {
        /* Yalnızca düz bir dosya adı: "../" ya da alt klasör yok. Disk de
         * yolu kökün dışına çıkmaya karşı ayrıca denetler. */
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,120}\z/', $ad) !== 1) {
            ApiResponse::notFound('Dosya bulunamadı.');
        }

        $disk = Storage::disk('private');
        $yol  = self::DOSYA_KLASORU . '/' . $ad;

        if (!$disk->exists($yol)) {
            ApiResponse::notFound('Dosya bulunamadı.');
        }

        Logger::info('API: örnek dosya açıldı', ['dosya' => $ad, 'kullanici' => Auth::id()], 'app');

        Response::download($disk, $yol, $ad, $request->bool('indir') ? 'attachment' : 'inline');
    }

    /* =================================================================
     *  YARDIMCILAR
     * ============================================================== */

    /** @return array<string,mixed> */
    public static function userData(User $user): array
    {
        $avatar = $user->avatar !== '' ? $user->avatarUrl() : '';

        return [
            'id'            => $user->id,
            'ad'            => $user->ad,
            'soyad'         => $user->soyad,
            'ad_soyad'      => $user->fullName(),
            'kullanici_adi' => $user->kullaniciAdi,
            'eposta'        => $user->eposta,
            'rol'           => $user->rol,
            'rol_adi'       => $user->roleLabel(),
            'avatar'        => $avatar !== '' && !str_starts_with($avatar, 'http') ? Url::origin() . $avatar : $avatar,
        ];
    }

    private static function iso(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        $zaman = strtotime($value);

        return $zaman === false ? null : date(DATE_ATOM, $zaman);
    }
}
