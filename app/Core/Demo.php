<?php
/**
 * =====================================================================
 *  Demo – Herkese açık deneme kurulumları için demo modu
 * ---------------------------------------------------------------------
 *  .env içinde APP_DEMO=true iken:
 *
 *    1) Giriş ekranı örnek hesapları (Yönetici, Editör, Üye) parolasıyla
 *       birlikte listeler; bir satıra tıklamak doğrudan giriş yapar.
 *    2) O hesaplarla giriş yapan ziyaretçi, demoyu herkes için bozacak
 *       işleri YAPAMAZ: kendi hesabını ve parolasını değiştirmek,
 *       kullanıcı eklemek/düzenlemek/silmek, site ayarları, sistem
 *       işlemleri ve e-posta gönderimi kilitlidir.
 *
 *  NEDEN .env, NEDEN PANEL AYARI DEĞİL? Demo yöneticisi her yetkiye
 *  sahiptir (bkz. Role::can). Anahtar panelde olsaydı ilk ziyaretçi
 *  demo modunu kapatıp kilitleri kaldırabilirdi.
 *
 *  KİLİT YALNIZCA ÖRNEK HESAPLARA UYGULANIR. Kurulumda kendi
 *  parolanızla açtığınız yönetici hesabı kısıtlanmaz; demoyu o hesapla
 *  yönetirsiniz.
 *
 *  İçerik işleri (sayfa yazmak, mesajları okumak/silmek, Ornek
 *  modülüne kayıt eklemek) bilerek AÇIK kalır: demonun amacı bunları
 *  denetmektir. Herkese açık bir demoyu belirli aralıklarla sıfırdan
 *  kurmanız önerilir.
 *
 *  Örnek hesaplar kurulum/demo.sql ile gelir; parolaları aynıdır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use PDO;
use Throwable;

final class Demo
{
    /** kurulum/demo.sql'deki bütün örnek hesapların parolası. */
    public const PAROLA = 'Demo1234!';

    /**
     * kurulum/demo.sql ile gelen hesaplar. Anahtar kullanıcı adıdır.
     *
     * "herkese" → demo modunda giriş ekranında listelenir. Pasif ve
     * askıdaki hesaplar bilerek giriş yapamaz; yalnızca geliştirme
     * ortamında, durum kontrolünü denemek için gösterilir.
     *
     * @var array<string,array{ad:string,eposta:string,etiket:string,variant:string,icon:string,herkese:bool}>
     */
    public const HESAPLAR = [
        'ali.yonetici' => ['ad' => 'Ali Yılmaz',  'eposta' => 'ali.demo@ornek.com',    'etiket' => 'Yönetici',   'variant' => 'admin',   'icon' => 'shield', 'herkese' => true],
        'elif.editor'  => ['ad' => 'Elif Demir',  'eposta' => 'elif.demo@ornek.com',   'etiket' => 'Editör',     'variant' => 'editor',  'icon' => 'edit',   'herkese' => true],
        'mehmet.uye'   => ['ad' => 'Mehmet Kaya', 'eposta' => 'mehmet.demo@ornek.com', 'etiket' => 'Üye',        'variant' => 'member',  'icon' => 'user',   'herkese' => true],
        'ayse.pasif'   => ['ad' => 'Ayşe Şahin',  'eposta' => 'ayse.demo@ornek.com',   'etiket' => 'Pasif Üye',  'variant' => 'passive', 'icon' => 'user',   'herkese' => false],
        'can.askida'   => ['ad' => 'Can Yıldız',  'eposta' => 'can.demo@ornek.com',    'etiket' => 'Askıda Üye', 'variant' => 'hold',    'icon' => 'user',   'herkese' => false],
    ];

    /**
     * Örnek hesaplara kapalı YAZMA uçları. Sonu "/" ile bitenler önek,
     * diğerleri tam yoldur.
     *
     * @var array<string,string> yol => kullanıcıya gösterilecek gerekçe
     */
    private const KILITLI = [
        'panel/hesabim/'          => 'Demo hesabının bilgileri, parolası, görseli ve API anahtarları değiştirilemez.',
        'panel/ayarlar/'          => 'Demo modunda site ayarları değiştirilemez.',
        'panel/sistem/'           => 'Demo modunda sistem işlemleri (kuyruk, migration) kapalıdır.',
        'api/kullanicilar/save'   => 'Demo modunda kullanıcı eklenemez ve düzenlenemez.',
        'api/kullanicilar/delete' => 'Demo modunda kullanıcı silinemez.',
        'api/kullanicilar/status' => 'Demo modunda kullanıcı durumu değiştirilemez.',
        'api/eposta/gonder'       => 'Demo modunda e-posta gönderilemez.',
        'api/eposta/isle'         => 'Demo modunda e-posta gönderilemez.',
        'api/eposta/tekrar'       => 'Demo modunda e-posta gönderilemez.',
        'api/eposta/sinama'       => 'Demo modunda e-posta gönderilemez.',
        'api/eposta/baglanti'     => 'Demo modunda e-posta ayarları sınanamaz.',
        'api/eposta/sil'          => 'Demo modunda e-posta kayıtları silinemez.',
        'api/eposta/temizle'      => 'Demo modunda e-posta kayıtları silinemez.',
    ];

    public static function enabled(): bool
    {
        return (bool) Config::get('app.demo', false);
    }

    public static function isDemoUser(?User $user): bool
    {
        return $user !== null && isset(self::HESAPLAR[$user->kullaniciAdi]);
    }

    /**
     * Bu istek örnek hesaba kapalı mı? Kapalıysa gerekçeyi döndürür.
     * Okuma (GET/HEAD) hiçbir zaman kilitlenmez.
     */
    public static function blockReason(string $method, string $path): ?string
    {
        if (in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
            return null;
        }

        $path = trim($path, '/');

        foreach (self::KILITLI as $yol => $gerekce) {
            $eslesti = str_ends_with($yol, '/')
                ? str_starts_with($path . '/', $yol)
                : $path === $yol;

            if ($eslesti) {
                return $gerekce;
            }
        }

        return null;
    }

    /**
     * "auth" ara katmanından çağrılır. Demo modu kapalıysa ya da giriş
     * yapan örnek hesap değilse hiçbir şey yapmaz.
     */
    public static function guard(Request $request): void
    {
        if (!self::enabled() || !self::isDemoUser(Auth::user())) {
            return;
        }

        $path   = Url::current();
        $reason = self::blockReason($request->method(), $path);

        if ($reason === null) {
            return;
        }

        // AJAX: app.js "description" alanını bildirim olarak gösterir.
        if ($request->isAjax()) {
            throw Exceptions\HttpException::forbidden($reason, context: ['demo' => Auth::user()?->kullaniciAdi]);
        }

        Flash::error($reason);
        Response::redirect(url(self::backPath($path)));
    }

    /** Kilitlenen formdan sonra dönülecek sayfa: "panel/ayarlar/genel" → "panel/ayarlar". */
    public static function backPath(string $path): string
    {
        $parcalar = explode('/', trim($path, '/'));

        return ($parcalar[0] ?? '') === 'panel' && isset($parcalar[1])
            ? 'panel/' . $parcalar[1]
            : 'panel';
    }

    /**
     * Giriş ekranında gösterilecek hesaplar.
     *
     * Demo modunda Yönetici, Editör ve Üye; geliştirme ortamında
     * (debug açık, ortam yayın değil) pasif ve askıdakiler de. Yayındaki
     * sıradan bir sitede hiçbiri — parolası bilinen hesap önermek
     * güvenlik açığıdır. Hesap ancak veritabanında demo.sql'deki
     * e-postasıyla GERÇEKTEN varsa listelenir.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function loginAccounts(PDO $db): array
    {
        $demo       = self::enabled();
        $gelistirme = Config::isDebug() && !Config::isProduction();

        if (!$demo && !$gelistirme) {
            return [];
        }

        return self::visibleAccounts($demo, self::existing($db));
    }

    /**
     * @param array<string,string> $mevcut kullanıcı adı => e-posta (veritabanındaki)
     * @return array<int,array<string,mixed>>
     */
    public static function visibleAccounts(bool $demo, array $mevcut): array
    {
        $liste = [];

        foreach (self::HESAPLAR as $kadi => $hesap) {
            if ($demo && !$hesap['herkese']) {
                continue;
            }
            if (($mevcut[$kadi] ?? null) !== $hesap['eposta']) {
                continue;
            }

            $liste[] = ['kullanici_adi' => $kadi, 'parola' => self::PAROLA] + $hesap;
        }

        return $liste;
    }

    /**
     * Veritabanında duran örnek hesaplar (kullanıcı adı => e-posta).
     * Panel uyarısı da bunu kullanır: demo modu kapalı bir sitede
     * parolası herkesçe bilinen hesap kalmamalı.
     *
     * @return array<string,string>
     */
    public static function existing(PDO $db): array
    {
        $adlar = array_keys(self::HESAPLAR);

        try {
            $stmt = $db->prepare(
                'SELECT kullanici_adi, eposta FROM kullanicilar WHERE kullanici_adi IN ('
                . implode(', ', array_fill(0, count($adlar), '?')) . ')'
            );
            $stmt->execute($adlar);

            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable) {
            return [];
        }
    }
}
