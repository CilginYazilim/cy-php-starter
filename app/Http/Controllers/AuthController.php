<?php
/**
 * =====================================================================
 *  AuthController – Giriş / kayıt / çıkış
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Flash;
use App\Core\Events\Events;
use App\Core\Ip;
use App\Core\Log\Logger;
use App\Core\Mail\Notifier;
use App\Core\Middleware;
use App\Core\Registration;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Signer;
use App\Core\Throttle;
use App\Core\Validator;
use App\Http\Controller;
use App\Events\UserRegistered;

final class AuthController extends Controller
{
    /**
     * kurulum/database.sql ile birlikte gelen örnek kullanıcılar.
     * Yalnızca geliştirme ortamında (APP_DEBUG=true) giriş ekranında
     * gösterilir — canlıda parolası bilinen hesapların önerilmesi
     * güvenlik açığıdır.
     *
     * @var array<int,array<string,string>>
     */
    private const DEMO_ACCOUNTS = [
        ['identifier' => 'elif.editor', 'password' => 'Demo1234!', 'name' => 'Elif Demir',  'label' => 'Editör',     'variant' => 'editor',  'icon' => 'edit'],
        ['identifier' => 'mehmet.uye',  'password' => 'Demo1234!', 'name' => 'Mehmet Kaya',  'label' => 'Üye',        'variant' => 'member',  'icon' => 'user'],
        ['identifier' => 'ayse.pasif',  'password' => 'Demo1234!', 'name' => 'Ayşe Şahin',   'label' => 'Pasif Üye',  'variant' => 'passive', 'icon' => 'user'],
        ['identifier' => 'can.askida',  'password' => 'Demo1234!', 'name' => 'Can Yıldız',   'label' => 'Askıda Üye', 'variant' => 'hold',    'icon' => 'user'],
    ];

    public function showLogin(Request $request): void
    {
        if (Session::pull('_expired') === true) {
            Flash::warning('Uzun süre işlem yapılmadığı için oturumunuz sonlandırıldı.');
        }

        $this->view('auth/login', [
            'title'        => 'Giriş Yap',
            'errors'       => Flash::errors(),
            'old'          => Flash::old(),
            'scripts'      => ['login.js'],
            'demoAccounts' => $this->demoAccounts(),
        ], 'layouts/site');
    }

    /**
     * Giriş ekranında önerilecek demo hesaplar.
     *
     * ÜÇ KOŞUL BİRDEN aranır: hata ayıklama açık, ortam "production"
     * DEĞİL ve hesap veritabanında GERÇEKTEN var. Eskiden yalnızca
     * APP_DEBUG'a bakılıyordu; kurulum her siteyi debug açık kurduğu
     * için canlı sitelerin giriş ekranı "Demo1234!" parolasını
     * öneriyordu — demo verisi hiç yüklenmemiş olsa bile.
     *
     * @return array<int,array<string,string>>
     */
    private function demoAccounts(): array
    {
        if (!Config::isDebug() || Config::isProduction()) {
            return [];
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT kullanici_adi FROM kullanicilar WHERE kullanici_adi IN (?, ?, ?, ?)'
            );
            $stmt->execute(array_column(self::DEMO_ACCOUNTS, 'identifier'));

            $mevcut = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(
            self::DEMO_ACCOUNTS,
            static fn (array $hesap): bool => in_array($hesap['identifier'], $mevcut, true)
        ));
    }

    public function login(Request $request): void
    {
        $identifier = $request->input('identifier');
        $password   = $request->string('password');

        $errors = [];

        if ($identifier === '') {
            $errors['identifier'] = 'E-posta veya kullanıcı adı boş bırakılamaz.';
        }
        if ($password === '') {
            $errors['password'] = 'Parola alanı boş bırakılamaz.';
        }

        if ($errors !== []) {
            Flash::withInput($errors, ['identifier' => $identifier, 'hatirla' => $request->bool('hatirla') ? '1' : '']);
            Response::redirect(url('giris'));
        }

        $result = Auth::attempt($identifier, $password, $request, $request->bool('hatirla'));

        if (!$result['ok']) {
            $message = $result['message'];

            /* Parolası DOĞRU ama e-postası doğrulanmamış hesap: bağlantıyı
             * yeniden göndeririz (aynı adrese 10 dakikada bir). Ayrı bir
             * "yeniden gönder" formu açmıyoruz; o form, adres girip
             * "kayıtlı mı" diye sormanın bir yolu daha olurdu. */
            if (isset($result['onay']) && Registration::requiresVerification()
                && Notifier::dogrulama($result['onay'], Registration::verificationLink($result['onay']), Registration::LINK_HOURS)) {
                $message .= ' Bağlantıyı e-posta adresinize yeniden gönderdik.';
            }

            Flash::error($message);
            Flash::withInput([], ['identifier' => $identifier]);
            Response::redirect(url('giris'));
        }

        Flash::success($result['message']);

        /* Saklanan yol OKURKEN de doğrulanır (bkz. Middleware::auth):
         * oturuma eski bir sürümden kalmış ya da başka bir yoldan
         * yazılmış bir değer olsa bile yönlendirme site dışına çıkamaz. */
        $intended = Middleware::safeIntended(Session::pull('_intended', ''));

        Response::redirect(url($intended !== '' ? $intended : 'panel'));
    }

    public function showRegister(Request $request): void
    {
        if (!Registration::isOpen()) {
            Flash::warning('Yeni kayıtlar şu anda alınmıyor.');
            Response::redirect(url('giris'));
        }

        $this->view('auth/register', [
            'title'   => 'Kayıt Ol',
            'errors'  => Flash::errors(),
            'old'     => Flash::old(),
            'scripts' => ['register.js'],
        ], 'layouts/site');
    }

    /**
     * Doğrulama açıkken HER başarılı görünen gönderimde verilen yanıt.
     * Adres yeniyse de, zaten kayıtlıysa da aynıdır: ekrandan "bu
     * e-posta üye mi" öğrenilemez. Adresin sahibi farkı mektuptan görür.
     */
    private const KAYIT_ALINDI = 'Kaydınız alındı. Hesabınızı etkinleştirmek için e-posta adresinize gönderdiğimiz bağlantıya tıklayın. Birkaç dakika içinde gelmezse istenmeyen posta klasörüne bakın.';

    public function register(Request $request): void
    {
        if (!Registration::isOpen()) {
            Flash::error('Yeni kayıtlar şu anda alınmıyor.');
            Response::redirect(url('giris'));
        }

        $dogrulama = Registration::requiresVerification();
        $kova      = Ip::bucket($request->ip());
        $alindi    = $dogrulama ? self::KAYIT_ALINDI : 'Kaydınız alındı.';

        /* --- 1) HER GÖNDERİM SAYILIR ---
         * Eskiden yalnızca BAŞARILI kayıtlar sayılıyordu; hatalı
         * gönderimler sınırsızdı ve form "bu kullanıcı adı alınmış mı"
         * diye dilediğince sorgulanabiliyordu. Sayaç kilitli bir dosyada
         * tutulur; aynı anda gelen istekler sınırı birlikte aşamaz. */
        $bekle = Throttle::attempt('kayit-deneme:' . $kova, (int) Config::get('security.register_max_attempts_per_hour', 20), 3600);

        if ($bekle > 0) {
            Logger::security('Kayıt formu: IP saatlik deneme sınırı aşıldı', ['ip' => $request->ip()]);

            Flash::error(sprintf('Bu adresten çok fazla kayıt denemesi yapıldı. Lütfen %d dakika sonra tekrar deneyin.', (int) ceil($bekle / 60)));
            Response::redirect(url('kayit'));
        }

        /* --- 2) OTOMATİK KAYIT KORUMASI ---
         *   · Bal küpü: görünmeyen alan doluysa bot.
         *   · Süre: formun üretildiği an İMZALI bir damgayla gelir
         *     (bkz. Signer::stamp). 3 saniyeden hızlı gönderim insan
         *     işi değildir. Eskiden damga imzasızdı ve alan HİÇ
         *     gönderilmezse kontrol atlanıyordu; artık zorunludur.
         * Bota başarısızlığı belli etmiyoruz; ama İZ bırakıyoruz. */
        $honeypot = trim($request->string('cy_kontrol'));
        $yas      = Signer::stampAge('kayit', $request->string('cy_zaman'));

        if ($honeypot !== '' || $yas === null || $yas < 3) {
            Logger::security('Kayıt formu: bot belirtisi, kayıt elendi', [
                'ip'       => $request->ip(),
                'sebep'    => $honeypot !== '' ? 'bal küpü' : ($yas === null ? 'damga yok/geçersiz' : 'çok hızlı'),
                'tarayici' => $request->userAgent(),
            ]);

            Flash::success($alindi);
            Response::redirect(url('giris'));
        }

        if ($yas > 6 * 3600) {
            Flash::error('Form çok uzun süre açık kaldı. Lütfen bilgilerinizi yeniden gönderin.');
            Flash::withInput([], array_diff_key($_POST, array_flip(['sifre', 'sifre_tekrar', 'csrf_token', 'cy_zaman'])));
            Response::redirect(url('kayit'));
        }

        $validator = new Validator($_POST);
        $validator->name('ad', 'Ad')
                  ->name('soyad', 'Soyad')
                  ->username('kullanici_adi')
                  ->email('eposta')
                  ->password('sifre', true, 'sifre_tekrar');

        /* Kullanıcı adı herkese görünen bir addır; "alınmış" demek
         * kaçınılmazdır (aksi hâlde kişi hangi adı seçeceğini bilemez).
         * E-POSTA İÇİN BÖYLE BİR ŞEY SÖYLENMEZ (aşağıya bakın). */
        if ($validator->passes() && $this->users()->fieldTaken('kullanici_adi', (string) $validator->validated()['kullanici_adi'])) {
            $validator->addError('kullanici_adi', 'Bu kullanıcı adı alınmış; başka bir ad seçin.');
        }

        $mevcut = $validator->passes()
            ? $this->users()->findByEmail((string) $validator->validated()['eposta'])
            : null;

        // Doğrulama kapalıyken hesap hemen açılır; o durumda adresin
        // kayıtlı olduğunu söylemekten başka yol yoktur.
        if ($mevcut !== null && !$dogrulama) {
            $validator->addError('eposta', 'Bu e-posta adresiyle bir hesap zaten var. Giriş yapmayı deneyin.');
        }

        if ($validator->fails()) {
            /* Parolalar "eski girdi" olarak oturuma YAZILMAZ: oturum
             * dosyası diskte düz metin durur. */
            Flash::withInput(
                $validator->errors(),
                array_diff_key($_POST, array_flip(['sifre', 'sifre_tekrar', 'csrf_token', 'cy_zaman']))
            );
            Response::redirect(url('kayit'));
        }

        /* --- 3) ADRES ZATEN KAYITLI ---
         * Ekran YENİ bir kayıtla aynı yanıtı verir; adresin sahibi
         * "bu adresle zaten hesabınız var" mektubunu alır (günde bir). */
        if ($mevcut !== null) {
            Logger::info('Kayıt formu: kayıtlı bir adres yazıldı (yanıt aynı)', ['kullanici' => $mevcut->id], 'auth');

            Notifier::zatenKayitli($mevcut);

            Flash::success($alindi);
            Response::redirect(url('giris'));
        }

        // --- 4) IP başına saatlik BAŞARILI kayıt sınırı ---
        $bekle = Throttle::attempt('kayit:' . $kova, (int) Config::get('security.register_max_per_hour', 5), 3600);

        if ($bekle > 0) {
            Logger::security('Kayıt formu: IP saatlik kayıt sınırı aşıldı', ['ip' => $request->ip()]);

            Flash::error(sprintf('Bu adresten çok fazla kayıt yapıldı. Lütfen %d dakika sonra tekrar deneyin.', (int) ceil($bekle / 60)));
            Response::redirect(url('kayit'));
        }

        $data = $validator->validated();

        // Yeni kayıt olan herkes "uye" rolüyle başlar; kimse formdan
        // rol göndererek kendini yönetici yapamaz (bilerek okunmuyor).
        try {
            $id = $this->users()->create([
                'ad'            => $data['ad'],
                'soyad'         => $data['soyad'],
                'kullanici_adi' => $data['kullanici_adi'],
                'eposta'        => $data['eposta'],
                'sifre'         => $data['sifre'],
                'rol'           => 'uye',
                'durum'         => $dogrulama ? 'onay_bekliyor' : 'aktif',
            ]);
        } catch (\PDOException $e) {
            /* 23000: aynı anda gelen iki istekten diğeri aynı adı ya da
             * adresi bizden önce aldı (benzersiz anahtar). */
            Logger::warning('Kayıt oluşturulamadı: ' . $e->getMessage(), [], 'auth');

            Flash::error((string) $e->getCode() === '23000'
                ? 'Bu bilgilerle hesap açılamadı. Farklı bir kullanıcı adıyla tekrar deneyin.'
                : 'Kayıt şu anda tamamlanamadı. Lütfen daha sonra tekrar deneyin.');
            Flash::withInput([], array_diff_key($_POST, array_flip(['sifre', 'sifre_tekrar', 'csrf_token', 'cy_zaman'])));
            Response::redirect(url('kayit'));
        }

        $user = $this->users()->find($id);

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        if ($dogrulama) {
            /* Hoş geldin mektubu DEĞİL doğrulama mektubu gider; hoş
             * geldin (UserRegistered olayı) hesap etkinleşince yayınlanır
             * (bkz. verify). */
            Notifier::dogrulama($user, Registration::verificationLink($user), Registration::LINK_HOURS);

            Logger::info('Yeni kayıt: e-posta doğrulaması bekleniyor', ['kullanici' => $user->id], 'auth');

            Flash::success($alindi);
            Response::redirect(url('giris'));
        }

        /* Denetleyici karşılama mektubunu KENDİSİ göndermiyor;
         * yalnızca "yeni kullanıcı kaydoldu" diye duyuruyor.
         * Mektubu app/Listeners/HosgeldinMailiGonder.php gönderir.
         * Bir dinleyici hata verse bile kayıt geçerli kalır. */
        Events::dispatch(new UserRegistered($user));

        Auth::login($user);

        Flash::success('Hesabınız oluşturuldu. Hoş geldiniz!');
        Response::redirect(url('panel'));
    }

    /**
     * E-postadaki doğrulama bağlantısı: /kayit/dogrula?k=..&s=..&i=..
     *
     * Hesap etkinleşir ve UserRegistered yayınlanır (hoş geldin
     * mektubu o zaman gider). Bağlantı oturum AÇMAZ: kişi parolasıyla
     * giriş yapar — mektubu ele geçiren biri parolayı bilmeden hesaba
     * giremesin.
     */
    public function verify(Request $request): void
    {
        $id   = $request->int('k', 1);
        $son  = $request->int('s', 1);
        $imza = $request->input('i');

        $user = ($id !== null && $son !== null) ? $this->users()->find($id) : null;

        if ($user === null) {
            Flash::error('Doğrulama bağlantısı geçersiz.');
            Response::redirect(url('giris'));
        }

        if ($user->isActive()) {
            Flash::info('Hesabınız zaten etkin. Giriş yapabilirsiniz.');
            Response::redirect(url('giris'));
        }

        $sonuc = Registration::checkLink($user, $son, $imza);

        if ($sonuc !== 'gecerli' || !$user->isPendingVerification()) {
            Flash::error($sonuc === 'suresi_doldu'
                ? 'Doğrulama bağlantısının süresi dolmuş. E-posta adresiniz ve parolanızla giriş yapmayı deneyin; yeni bağlantı gönderilir.'
                : 'Doğrulama bağlantısı geçersiz.');
            Response::redirect(url('giris'));
        }

        if ($this->users()->activatePending($user->id)) {
            Logger::info('E-posta doğrulandı, hesap etkinleştirildi', ['kullanici' => $user->id], 'auth');

            $etkin = $this->users()->find($user->id);

            if ($etkin !== null) {
                Events::dispatch(new UserRegistered($etkin));
            }
        }

        Flash::success('E-posta adresiniz doğrulandı ve hesabınız etkinleştirildi. Giriş yapabilirsiniz.');
        Response::redirect(url('giris'));
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Session::start();
        Flash::info('Oturumunuz güvenli bir şekilde kapatıldı.');
        Response::redirect(url('giris'));
    }
}
