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
use App\Core\Log\Logger;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Setting;
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
            Flash::error($result['message']);
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
        if (!Setting::bool('sistem_kayit_acik', false)) {
            Flash::warning('Yeni kayıtlar şu anda kapalı.');
            Response::redirect(url('giris'));
        }

        $this->view('auth/register', [
            'title'   => 'Kayıt Ol',
            'errors'  => Flash::errors(),
            'old'     => Flash::old(),
            'scripts' => ['register.js'],
        ], 'layouts/site');
    }

    public function register(Request $request): void
    {
        if (!Setting::bool('sistem_kayit_acik', false)) {
            Flash::error('Yeni kayıtlar şu anda kapalı.');
            Response::redirect(url('giris'));
        }

        /* --- OTOMATİK KAYIT KORUMASI ---
         * Kayıt formu herkese açıktır; eskiden hiçbir sınır yoktu ve
         * bir betik dakikada yüzlerce hesap açabiliyordu. Üç katman:
         *   1. Bal küpü: görünmeyen alan doluysa bot.
         *   2. Süre: form üretildikten sonraki 3 saniyede gelen
         *      gönderim insan işi değildir.
         *   3. IP başına saatlik sınır (security.register_max_per_hour).
         * İlk ikisinde bota başarısızlığı belli etmiyoruz; ama İZ
         * bırakıyoruz (bkz. ContactController'daki aynı gerekçe). */
        $honeypot  = trim($request->string('cy_kontrol'));
        $formZaman = (int) $request->string('cy_zaman');

        if ($honeypot !== '' || ($formZaman > 0 && (time() - $formZaman) < 3)) {
            Logger::security('Kayıt formu: bot belirtisi, kayıt elendi', [
                'ip'       => $request->ip(),
                'sebep'    => $honeypot !== '' ? 'bal küpü' : 'çok hızlı',
                'tarayici' => $request->userAgent(),
            ]);

            Flash::success('Kaydınız alındı.');
            Response::redirect(url('giris'));
        }

        $sinir = (int) Config::get('security.register_max_per_hour', 5);
        $bekle = Throttle::tooMany('kayit:' . $request->ip(), $sinir, 3600);

        if ($bekle > 0) {
            Logger::security('Kayıt formu: IP saatlik sınırı aşıldı', ['ip' => $request->ip()]);

            Flash::error(sprintf('Bu adresten çok fazla kayıt yapıldı. Lütfen %d dakika sonra tekrar deneyin.', (int) ceil($bekle / 60)));
            Response::redirect(url('kayit'));
        }

        $validator = new Validator($_POST);
        $validator->name('ad', 'Ad')
                  ->name('soyad', 'Soyad')
                  ->username('kullanici_adi')
                  ->email('eposta')
                  ->password('sifre', true, 'sifre_tekrar');

        if ($validator->passes() && $this->users()->fieldTaken('eposta', (string) $validator->validated()['eposta'])) {
            $validator->addError('eposta', 'Bu e-posta adresi zaten kayıtlı.');
        }
        if ($validator->passes() && $this->users()->fieldTaken('kullanici_adi', (string) $validator->validated()['kullanici_adi'])) {
            $validator->addError('kullanici_adi', 'Bu kullanıcı adı zaten alınmış.');
        }

        if ($validator->fails()) {
            /* Parolalar "eski girdi" olarak oturuma YAZILMAZ: oturum
             * dosyası diskte düz metin durur. */
            Flash::withInput(
                $validator->errors(),
                array_diff_key($_POST, array_flip(['sifre', 'sifre_tekrar', 'csrf_token']))
            );
            Response::redirect(url('kayit'));
        }

        $data = $validator->validated();

        Throttle::hit('kayit:' . $request->ip(), 3600);

        // Yeni kayıt olan herkes "uye" rolüyle başlar; kimse formdan
        // rol göndererek kendini yönetici yapamaz (bilerek okunmuyor).
        $id = $this->users()->create([
            'ad'            => $data['ad'],
            'soyad'         => $data['soyad'],
            'kullanici_adi' => $data['kullanici_adi'],
            'eposta'        => $data['eposta'],
            'sifre'         => $data['sifre'],
            'rol'           => 'uye',
            'durum'         => 'aktif',
        ]);

        $user = $this->users()->find($id);

        if ($user !== null) {
            /* Denetleyici karşılama mektubunu KENDİSİ göndermiyor;
             * yalnızca "yeni kullanıcı kaydoldu" diye duyuruyor.
             * Mektubu app/Listeners/HosgeldinMailiGonder.php gönderir.
             * Bir dinleyici hata verse bile kayıt geçerli kalır. */
            Events::dispatch(new UserRegistered($user));

            Auth::login($user);
        }

        Flash::success('Hesabınız oluşturuldu. Hoş geldiniz!');
        Response::redirect(url('panel'));
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Session::start();
        Flash::info('Oturumunuz güvenli bir şekilde kapatıldı.');
        Response::redirect(url('giris'));
    }
}
