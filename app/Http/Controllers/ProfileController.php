<?php
/**
 * =====================================================================
 *  ProfileController – "Hesabım" sayfası
 * ---------------------------------------------------------------------
 *  KRİTİK GÜVENLİK NOKTASI: Güncellenecek kayıt ID'si formdan DEĞİL,
 *  oturumdan alınır (Auth::id()). "rol" ve "durum" alanları burada
 *  HİÇ okunmaz; kullanıcı kendini yönetici yapamaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Events\Events;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploader;
use App\Core\Validator;
use App\Events\FileUploaded;
use App\Events\PasswordChanged;
use App\Http\Controller;
use App\Core\Api\ApiToken;
use RuntimeException;
use Throwable;

final class ProfileController extends Controller
{
    /** Bir kullanıcının aynı anda sahip olabileceği en fazla API anahtarı. */
    private const MAX_TOKENS = 10;

    public function index(Request $request): void
    {
        $user = Auth::user();

        $this->view('profile/index', [
            'title'     => 'Hesabım',
            'subtitle'  => 'Kişisel bilgilerinizi ve parolanızı buradan güncelleyin.',
            'user'      => $user,
            'errors'    => Flash::errors(),
            'old'       => Flash::old(),
            'apiTokens' => $user !== null && Auth::can('profile.api') ? $this->tokens($user->id) : null,
            /* Yeni anahtarın AÇIK HALİ yalnızca bir kez, üretildiği
             * isteğin hemen ardından gösterilir; sonra oturumdan silinir. */
            'yeniAnahtar' => Session::pull('_yeni_api_anahtari'),
        ]);
    }

    /** @return array<int,array<string,mixed>> Tablo henüz yoksa boş */
    private function tokens(int $userId): array
    {
        try {
            return ApiToken::forUser($userId);
        } catch (Throwable) {
            return [];
        }
    }

    public function update(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        $validator = new Validator($_POST);
        $validator->name('ad', 'Ad')
                  ->name('soyad', 'Soyad')
                  ->email('eposta')
                  ->phone('telefon')
                  ->text('hakkinda', 'Hakkımda', 0, 1000);

        if ($validator->passes() && $this->users()->fieldTaken('eposta', (string) $validator->validated()['eposta'], $user->id)) {
            $validator->addError('eposta', 'Bu e-posta adresi başka bir hesapta kayıtlı.');
        }

        /* E-POSTA DEĞİŞİKLİĞİ MEVCUT PAROLAYI İSTER.
         *
         * E-posta adresi hesabın kurtarma kanalıdır ("parolamı unuttum",
         * bildirimler). Açık bırakılmış bir oturumu ele geçiren kişi
         * eskiden adresi tek tıkla kendi adresine çevirip hesabı kalıcı
         * olarak alabiliyordu. */
        $yeniEposta = mb_strtolower((string) ($validator->validated()['eposta'] ?? ''));

        if ($validator->passes() && $yeniEposta !== mb_strtolower($user->eposta)) {
            $hesap = $this->users()->findWithPassword($user->id);

            if ($hesap === null || !$hesap->verifyPassword($request->string('eposta_sifre'))) {
                $validator->addError('eposta_sifre', 'E-posta adresinizi değiştirmek için mevcut parolanızı doğru girin.');
            }
        }

        if ($validator->fails()) {
            Flash::error('Lütfen formdaki hataları düzeltin.');
            Flash::withInput($validator->errors(), array_diff_key($_POST, array_flip(['eposta_sifre', 'csrf_token'])));
            Response::redirect(url('panel/hesabim'));
        }

        $data = $validator->validated();

        $this->users()->update($user->id, [
            'ad'       => $data['ad'],
            'soyad'    => $data['soyad'],
            'eposta'   => $data['eposta'],
            'telefon'  => $data['telefon'] ?? '',
            'hakkinda' => $data['hakkinda'] ?? '',
        ]);

        Flash::success('Bilgileriniz güncellendi.');
        Response::redirect(url('panel/hesabim'));
    }

    public function password(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        $current = $request->string('mevcut_sifre');

        /* Auth::user() parola özetini TAŞIMAZ (UserRepository::find()
         * "sifre" sütununu okumaz). Doğrulamayı onun üzerinden yapmak
         * her denemenin "mevcut parolanız hatalı" ile bitmesine yol
         * açar; özeti bu iş için ayrıca okuyoruz. */
        $hesap = $this->users()->findWithPassword($user->id);

        $validator = new Validator($_POST);
        $validator->password('yeni_sifre', true, 'yeni_sifre_tekrar');

        if ($hesap === null || !$hesap->verifyPassword($current)) {
            $validator->addError('mevcut_sifre', 'Mevcut parolanız hatalı.');
        }
        if ($current !== '' && $current === $request->string('yeni_sifre')) {
            $validator->addError('yeni_sifre', 'Yeni parola, mevcut parolanızdan farklı olmalıdır.');
        }

        if ($validator->fails()) {
            Flash::error('Parola güncellenemedi.');
            Flash::withInput($validator->errors(), []);
            Response::redirect(url('panel/hesabim'));
        }

        /* Parola değişti. UserRepository::update() aynı sorguda oturum
         * sürümünü artırır ve "beni hatırla" jetonunu siler: DİĞER
         * cihazlardaki oturumlar ve çalınmış olabilecek çerezler bir
         * sonraki istekte düşer. Parolasını değiştirmenin bir nedeni
         * "birileri hesabıma girmiş olabilir" şüphesidir; eskiden
         * yalnızca BU oturumun kimliği yenileniyor, diğerleri açık
         * kalıyordu. */
        $this->users()->update($user->id, ['sifre' => (string) $validator->validated()['yeni_sifre']]);

        /* Bu cihaz açık kalır: yeni sürümle yeniden oturum açılır
         * (oturum kimliği ve CSRF jetonu da yenilenir). */
        $guncel = $this->users()->find($user->id);

        if ($guncel !== null) {
            Auth::login($guncel);
        } else {
            Session::regenerate();
            Csrf::rotate();
        }

        /* Parolanın kendisi olayda TAŞINMAZ. Bir dinleyici "parolanız
         * değişti" bilgilendirmesi gönderebilir — hesabı çalınan
         * kullanıcının fark etmesinin tek yolu genelde budur. */
        Events::dispatch(new PasswordChanged($user->id, kendisi: true));

        Flash::success('Parolanız güncellendi. Diğer cihazlardaki oturumlarınız kapatıldı.');
        Response::redirect(url('panel/hesabim'));
    }

    /**
     * "Diğer cihazlardaki oturumları kapat".
     *
     * Parola değiştirmeden de kullanılabilir: kullanıcı ortak bir
     * bilgisayarda çıkış yapmayı unuttuğunu fark ettiğinde.
     */
    public function logoutOthers(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        Auth::logoutOtherDevices($user->id);

        Flash::success('Bu cihaz dışındaki tüm oturumlarınız ve "beni hatırla" kayıtlarınız kapatıldı.');
        Response::redirect(url('panel/hesabim'));
    }

    /* =================================================================
     *  API ANAHTARLARI
     * ============================================================== */

    public function createToken(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        $ad  = trim($request->input('anahtar_adi'));
        $gun = $request->int('anahtar_gun', 0, 3650) ?? 0;

        if ($ad === '' || mb_strlen($ad) > 100) {
            Flash::error('Anahtara 1-100 karakterlik bir ad verin (örn. "Mobil uygulama").');
            Response::redirect(url('panel/hesabim'));
        }

        try {
            if (ApiToken::countForUser($user->id) >= self::MAX_TOKENS) {
                Flash::error('En fazla ' . self::MAX_TOKENS . ' anahtarınız olabilir. Kullanmadığınız bir anahtarı iptal edin.');
                Response::redirect(url('panel/hesabim'));
            }

            $sonuc = ApiToken::create($user->id, $ad, $gun > 0 ? $gun : null);
        } catch (\PDOException) {
            Flash::error('API anahtarı tablosu bulunamadı. Sunucuda "php cy migrate" çalıştırın.');
            Response::redirect(url('panel/hesabim'));
        }

        /* Açık anahtar veritabanına YAZILMAZ (yalnızca özeti); bu yüzden
         * kullanıcıya bir kez gösterilmesi gerekir. Yönlendirme sonrası
         * sayfada göstermek için oturumda BİR İSTEKLİK tutulur. */
        Session::set('_yeni_api_anahtari', $sonuc['token']);

        Flash::success('API anahtarı üretildi. Şimdi kopyalayın — bir daha gösterilmeyecek.');
        Response::redirect(url('panel/hesabim'));
    }

    public function revokeToken(Request $request): void
    {
        $user = Auth::user();
        $id   = $request->int('anahtar_id', 1);

        if ($user === null || $id === null) {
            Response::redirect(url('panel/hesabim'));
        }

        ApiToken::revokeOwned($user->id, $id)
            ? Flash::success('API anahtarı iptal edildi; onu kullanan istemciler artık erişemez.')
            : Flash::error('Anahtar bulunamadı.');

        Response::redirect(url('panel/hesabim'));
    }

    /**
     * Açık/koyu tema tercihini kaydeder (üst çubuktaki düğmeden AJAX ile
     * çağrılır). Böylece kullanıcı farklı bir cihaz/tarayıcıdan giriş
     * yaptığında da kendi seçtiği temayı görür — yalnızca çereze değil,
     * hesaba bağlıdır.
     */
    public function updateTheme(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::error('Oturum bulunamadı.', 401);
        }

        $tema = $request->input('tema') === 'koyu' ? 'koyu' : 'acik';

        $this->users()->updateTheme($user->id, $tema);

        Response::success('Tema tercihi kaydedildi.', ['tema' => $tema]);
    }

    public function uploadAvatar(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        if (!$request->hasFile('avatar')) {
            Flash::error('Dosya seçilmedi.');
            Response::redirect(url('panel/hesabim'));
        }

        try {
            $newAvatar = Uploader::avatar((array) $request->file('avatar'));
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect(url('panel/hesabim'));
        }

        $old = $user->avatar;

        $this->users()->update($user->id, ['avatar' => $newAvatar]);

        if ($old !== '' && $old !== $newAvatar) {
            Uploader::delete($old);
        }

        Flash::success('Profil fotoğrafı güncellendi.');
        Response::redirect(url('panel/hesabim'));
    }

    public function removeAvatar(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        if ($user->avatar === '') {
            Flash::error('Kaldırılacak profil fotoğrafı yok.');
            Response::redirect(url('panel/hesabim'));
        }

        $this->users()->update($user->id, ['avatar' => '']);
        Uploader::delete($user->avatar);

        Flash::success('Profil fotoğrafı kaldırıldı.');
        Response::redirect(url('panel/hesabim'));
    }
}
