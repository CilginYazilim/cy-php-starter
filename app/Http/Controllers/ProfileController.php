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

use App\Core\AccountDeletion;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Events\Events;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploader;
use App\Core\Validator;
use App\Events\AccountDeletionScheduled;
use App\Events\EmailChanged;
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

    /** Panelden seçilebilen geçerlilik süreleri (gün). */
    public const TOKEN_DAYS = [30, 90, 180, 365];

    /** Hatalı parola için ortak işaret (mesajı her form kendisi yazar). */
    private const WRONG_PASSWORD = \App\Core\PasswordConfirm::WRONG;

    public function index(Request $request): void
    {
        $user = Auth::user();

        $this->view('profile/index', [
            'title'     => 'Hesabım',
            'subtitle'  => 'Kişisel bilgilerinizi ve parolanızı buradan güncelleyin.',
            'user'      => $user,
            'errors'    => Flash::errors(),
            'old'       => Flash::old(),
            'apiTokens' => $user !== null && Auth::can('profile.api') ? $this->tokens($user->id, ApiToken::TUR_ANAHTAR) : null,
            // Mobil uygulamadan açılan oturumlar (POST api/v1/oturum); her rol görür.
            'mobilOturumlar' => $user !== null ? $this->tokens($user->id, ApiToken::TUR_OTURUM) : [],
            // Demo hesapları ortaktır: silme bölümü hiç gösterilmez (istek de Demo::guard'a takılır).
            'hesapSilme'     => $user !== null && AccountDeletion::enabled() && AccountDeletion::allowedFor($user)
                                && !(\App\Core\Demo::enabled() && \App\Core\Demo::isDemoUser($user)),
            /* Yeni anahtarın AÇIK HALİ yalnızca bir kez, üretildiği
             * isteğin hemen ardından gösterilir; sonra oturumdan silinir. */
            'yeniAnahtar' => Session::pull('_yeni_api_anahtari'),
        ]);
    }

    /** @return array<int,array<string,mixed>> Tablo henüz yoksa boş */
    private function tokens(int $userId, ?string $tur = null): array
    {
        try {
            return ApiToken::forUser($userId, $tur);
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
            $hata = $this->checkPassword($user->id, $request->string('eposta_sifre'));

            if ($hata !== null) {
                $validator->addError('eposta_sifre', $hata === self::WRONG_PASSWORD
                    ? 'E-posta adresinizi değiştirmek için mevcut parolanızı doğru girin.'
                    : $hata);
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

        // Adres değiştiyse ESKİ adrese bilgi (bkz. Listeners\EpostaDegistiBildir).
        if ($yeniEposta !== mb_strtolower($user->eposta)) {
            Events::dispatch(new EmailChanged($user->id, $user->eposta, (string) $data['eposta'], EmailChanged::PROFIL));
            Flash::success('Bilgileriniz güncellendi. Eski e-posta adresinize değişiklikle ilgili bilgi gönderildi.');
            Response::redirect(url('panel/hesabim'));
        }

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

        $validator = new Validator($_POST);
        $validator->password('yeni_sifre', true, 'yeni_sifre_tekrar');

        $hata = $this->checkPassword($user->id, $current);

        if ($hata !== null) {
            $validator->addError('mevcut_sifre', $hata === self::WRONG_PASSWORD ? 'Mevcut parolanız hatalı.' : $hata);
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
        $anahtarSayisi = $this->tokenCount($user->id);

        /* update() API anahtarlarını da siler (bkz. UserRepository). */
        $this->users()->update($user->id, ['sifre' => (string) $validator->validated()['yeni_sifre']]);

        /* Bu cihaz açık kalır: yeni sürümle yeniden oturum açılır
         * (oturum kimliği ve CSRF jetonu da yenilenir); "beni hatırla"
         * açıksa bu cihaz için yeni bir jeton yazılır. */
        Auth::refreshCurrentDevice($user->id);

        if (!Auth::check()) {
            Session::regenerate();
            Csrf::rotate();
        }

        /* Parolanın kendisi olayda TAŞINMAZ. Bir dinleyici "parolanız
         * değişti" bilgilendirmesi gönderebilir — hesabı çalınan
         * kullanıcının fark etmesinin tek yolu genelde budur. */
        Events::dispatch(new PasswordChanged($user->id, kendisi: true));

        Flash::success('Parolanız güncellendi. Diğer cihazlardaki oturumlarınız kapatıldı'
            . ($anahtarSayisi > 0 ? ' ve ' . $anahtarSayisi . ' API anahtarınız iptal edildi.' : '.'));
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

        /* API anahtarları ayrı bir onay kutusuyla iptal edilir (varsayılan
         * işaretli). Eskiden ekranda "diğer oturumlar kapatıldı" yazarken
         * çalınmış bir anahtar çalışmaya devam ediyordu; öte yandan bir
         * sunucu entegrasyonunu habersizce kesmek de istenmeyebilir. */
        $iptal = Auth::logoutOtherDevices($user->id, $request->bool('api_anahtarlari'));

        Flash::success('Bu cihaz dışındaki tüm oturumlarınız ve "beni hatırla" kayıtlarınız kapatıldı'
            . ($iptal > 0 ? '; ' . $iptal . ' API anahtarınız iptal edildi.' : '.'));
        Response::redirect(url('panel/hesabim'));
    }

    /**
     * Mevcut parolayı doğrular (hız sınırlı; bkz. App\Core\PasswordConfirm).
     *
     * @return string|null null → doğru; WRONG_PASSWORD → yanlış; başka metin → hız sınırı mesajı
     */
    private function checkPassword(int $userId, string $plain): ?string
    {
        return \App\Core\PasswordConfirm::check($userId, $plain);
    }

    private function tokenCount(int $userId): int
    {
        try {
            return ApiToken::countForUser($userId);
        } catch (Throwable) {
            return 0;
        }
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

        $ad     = trim($request->input('anahtar_adi'));
        $gun    = $request->int('anahtar_gun');
        $kapsam = $request->input('anahtar_kapsam') === ApiToken::YAZMA ? ApiToken::YAZMA : ApiToken::OKUMA;

        if ($ad === '' || mb_strlen($ad) > 100) {
            Flash::error('Anahtara 1-100 karakterlik bir ad verin (örn. "Mobil uygulama").');
            Response::redirect(url('panel/hesabim'));
        }

        /* Süre yalnızca listeden seçilir. Eskiden 0-3650 arası her sayı
         * kabul ediliyor, aralık dışındaki bir değer (99999) sessizce
         * SÜRESİZ anahtara dönüşüyordu. Süresiz anahtar panelden
         * üretilemez; gerekiyorsa sunucuda "php cy api:token --suresiz". */
        if (!in_array($gun, self::TOKEN_DAYS, true)) {
            Flash::error('Geçerlilik süresi olarak listedeki seçeneklerden birini seçin.');
            Response::redirect(url('panel/hesabim'));
        }

        /* Anahtar üretmek MEVCUT PAROLAYI ister: açık bırakılmış bir
         * oturumu ele geçiren kişi kendine uzun ömürlü, oturumdan
         * bağımsız bir erişim yolu açamasın. */
        $hata = $this->checkPassword($user->id, $request->string('anahtar_sifre'));

        if ($hata !== null) {
            Flash::error($hata === self::WRONG_PASSWORD ? 'API anahtarı üretmek için mevcut parolanızı doğru girin.' : $hata);
            Response::redirect(url('panel/hesabim'));
        }

        try {
            if (ApiToken::countForUser($user->id, ApiToken::TUR_ANAHTAR) >= self::MAX_TOKENS) {
                Flash::error('En fazla ' . self::MAX_TOKENS . ' anahtarınız olabilir. Kullanmadığınız bir anahtarı iptal edin.');
                Response::redirect(url('panel/hesabim'));
            }

            $sonuc = ApiToken::create($user->id, $ad, $gun, $kapsam);

            /* Sayım ile ekleme arasında aynı anda gelen başka bir istek
             * de eklemiş olabilir; sınır aşıldıysa yeni anahtar geri alınır. */
            if (ApiToken::countForUser($user->id, ApiToken::TUR_ANAHTAR) > self::MAX_TOKENS) {
                ApiToken::revokeOwned($user->id, $sonuc['id']);
                Flash::error('En fazla ' . self::MAX_TOKENS . ' anahtarınız olabilir. Kullanmadığınız bir anahtarı iptal edin.');
                Response::redirect(url('panel/hesabim'));
            }
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

    /** Hesabım → Bağlı cihazlar: tek bir mobil oturumu kapatır. */
    public function revokeSession(Request $request): void
    {
        $user = Auth::user();
        $id   = $request->int('oturum_id', 1);

        if ($user === null || $id === null) {
            Response::redirect(url('panel/hesabim'));
        }

        ApiToken::revokeOwned($user->id, $id)
            ? Flash::success('Cihazdaki oturum kapatıldı; uygulama yeniden giriş isteyecek.')
            : Flash::error('Oturum bulunamadı.');

        Response::redirect(url('panel/hesabim'));
    }

    /**
     * Hesabımı sil: parola ile onaylanır, 7 gün sonra silinir; bu sürede
     * giriş yapmak silmeyi iptal eder (bkz. App\Core\AccountDeletion).
     */
    public function deleteAccount(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        if (!AccountDeletion::enabled()) {
            Flash::error('Hesap silme bu sitede kapalı. Site yöneticisiyle iletişime geçin.');
            Response::redirect(url('panel/hesabim'));
        }

        if (!AccountDeletion::allowedFor($user)) {
            Flash::error('Sistemdeki son yönetici hesabı silinemez. Önce başka bir yönetici atayın.');
            Response::redirect(url('panel/hesabim'));
        }

        $hata = $this->checkPassword($user->id, $request->string('silme_sifre'));

        if ($hata !== null) {
            Flash::withInput(['silme_sifre' => $hata === self::WRONG_PASSWORD ? 'Parolanız hatalı.' : $hata], []);
            Response::redirect(url('panel/hesabim'));
        }

        $tarih = AccountDeletion::schedule($user);

        if ($tarih === null) {
            Flash::error('Hesap silme şu anda planlanamadı. Lütfen daha sonra tekrar deneyin.');
            Response::redirect(url('panel/hesabim'));
        }

        // Çıkıştan ÖNCE: dinleyici kullanıcıyı ve adresini okur.
        Events::dispatch(new AccountDeletionScheduled($user->id, $tarih));

        Auth::logout();
        Session::start();

        Flash::info('Hesabınız ' . $tarih . ' tarihinde silinecek. Vazgeçerseniz o tarihe kadar giriş yapmanız yeterli.');
        Response::redirect(url('giris'));
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
