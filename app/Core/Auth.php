<?php
/**
 * =====================================================================
 *  Auth – Kimlik doğrulama ve yetkilendirme
 * ---------------------------------------------------------------------
 *  Oturumda yalnızca KULLANICI ID'si ve OTURUM SÜRÜMÜ tutulur. Rol ve
 *  durum her istekte veritabanından TAZE okunur; aksi halde bir
 *  yönetici kullanıcıyı pasife aldığında, o kişi oturumu açık kaldığı
 *  sürece yetkilerini kullanmaya devam ederdi.
 *
 *  OTURUM SÜRÜMÜ ("kullanicilar.oturum_surumu")
 *  Parola değiştiğinde (ya da "diğer cihazlardan çıkış yap" denince)
 *  bir artar. Oturumdaki sürüm veritabanındakiyle tutmuyorsa oturum
 *  geçersizdir. Eskiden parola değişse bile başka cihazdaki oturumlar
 *  ve çalınmış bir "beni hatırla" çerezi açık kalıyordu — parolasını
 *  "hesabım ele geçirildi" şüphesiyle değiştiren kullanıcı hiçbir şeyi
 *  düzeltmemiş oluyordu.
 *
 *  GÜVENİLEN CİHAZ ÇEREZİ
 *  Başarılı girişten sonra tarayıcıya imzalı bir "bu cihaz bu hesaba
 *  daha önce girdi" çerezi yazılır. O çerezle gelen deneme, IP geneli
 *  yavaşlatmaya takılmaz ve kimlik kilidi o cihaza AYRI sayılır: aynı
 *  IP'yi paylaşan (ya da aynı vekilin arkasındaki) bir saldırgan
 *  yöneticiyi dışarıda bırakamaz. Çerez parolayı ASLA atlatmaz; yalnızca
 *  hangi sayaca yazılacağını belirler. Parola değişince geçersizdir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Events\Events;
use App\Core\Log\Logger;
use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Models\User;
use App\Repositories\UserRepository;
use Throwable;

final class Auth
{
    /** Bakım modunda bakımı atlayamayan role (üye) giriş ekranında ve girişte gösterilir. */
    public const BAKIM_MESAJI = 'Site bakımda; şu an yalnızca yöneticiler ve editörler girebilir.';

    private const SESSION_KEY     = '_auth_user_id';
    private const SESSION_VERSION = '_auth_ver';

    /**
     * "Beni hatırla" çerezi.
     *
     * Oturum çerezi tarayıcı kapanınca (ya da session.gc_maxlifetime
     * dolunca) ölür. Bu çerez ondan bağımsızdır ve KULLANICI AÇIKÇA
     * İSTEDİĞİNDE yazılır — varsayılan olarak asla.
     *
     * Adı kuruluma özeldir (bkz. rememberCookie()): aynı alan adındaki
     * iki kurulum birbirinin çerezini okuyup her istekte boş yere
     * sorgu çalıştırmasın.
     */
    private const REMEMBER_PREFIX = 'cy_remember_';
    private const REMEMBER_DAYS   = 30;

    /** Güvenilen cihaz çerezi (bkz. sınıf açıklaması). */
    private const DEVICE_PREFIX = 'cy_cihaz_';
    private const DEVICE_DAYS   = 180;

    /**
     * Kullanıcı BULUNAMADIĞINDA doğrulanan sahte bcrypt özetleri.
     *
     * Amaç, var olan ve olmayan hesaba verilen yanıtın AYNI SÜREDE
     * dönmesidir. Eski sahte özet hem geçersizdi (61 karakter) hem de
     * maliyeti 12'ydi; gerçek parolalar ise 10 maliyetle saklanıyordu.
     * Sonuç tersine döndü: olmayan hesapta yanıt ~260 ms, var olanda
     * ~60 ms sürüyor, kullanıcı listesini dışarıdan çıkarmayı
     * KOLAYLAŞTIRIYORDU. PHP 8.4'te varsayılan maliyet 12'ye çıktığı
     * için iki sürüm tutulur; seçim PHP sürümüne göre yapılır.
     */
    private const DUMMY_HASHES = [
        10 => '$2y$10$S/o173KtNzPcunEZOGQoneAIbhLYBMe5A9Ya68lkX.jxLGIjCjslK',
        12 => '$2y$12$GgkvxTSZZkRdB34u31bU..2y.zIUmDHDBoPyT2Eb8YTB8NxQI1tcK',
    ];

    private static ?User $cached = null;
    private static bool  $resolved = false;

    public static function user(): ?User
    {
        if (self::$resolved) {
            return self::$cached;
        }

        self::$resolved = true;

        $id = Session::get(self::SESSION_KEY);

        if (!is_int($id) && !is_numeric($id)) {
            /* Oturumda kimlik yok — "beni hatırla" çerezi var mı?
             * Oturum hiç açılmamışsa (durumsuz API isteği) çereze
             * bakmayız: o yol oturum yazmayı gerektirir. */
            return self::$cached = session_status() === PHP_SESSION_ACTIVE
                ? self::fromRememberCookie()
                : null;
        }

        $user = (new UserRepository(Database::connection()))->find((int) $id);

        if ($user === null || !$user->isActive()) {
            self::forget();

            return self::$cached = null;
        }

        /* Parola başka bir cihazdan değiştirildiyse bu oturum artık
         * geçersizdir. Eski sürümden kalan, sürüm bilgisi taşımayan
         * oturumlar 0 kabul edilir (sütunun varsayılanı). */
        if ((int) Session::get(self::SESSION_VERSION, 0) !== $user->oturumSurumu) {
            Logger::info('Oturum sürümü eşleşmedi; oturum kapatıldı', ['kullanici' => $user->id], 'auth');

            self::forget();

            return self::$cached = null;
        }

        return self::$cached = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()?->id;
    }

    public static function can(string $ability): bool
    {
        return self::user()?->can($ability) ?? false;
    }

    public static function isSelf(int $userId): bool
    {
        return self::id() === $userId;
    }

    /**
     * Kimlik bilgilerini doğrular ve oturumu açar.
     *
     * Dönen dizideki "onay" anahtarı, parolası DOĞRU ama e-postası
     * henüz doğrulanmamış hesabı taşır (denetleyici doğrulama mektubunu
     * yeniden gönderebilsin diye). Parola yanlışsa asla doldurulmaz;
     * hesabın durumu böylece yalnızca parolayı bilene görünür.
     *
     * @return array{ok:bool,message:string,user:?User,onay?:User}
     */
    public static function attempt(string $identifier, string $password, Request $request, bool $remember = false): array
    {
        $sonuc = self::verifyCredentials($identifier, $password, $request);

        if (!$sonuc['ok']) {
            return $sonuc;
        }

        /** @var User $user */
        $user  = $sonuc['user'];
        $ip    = $request->ip();
        $users = new UserRepository(Database::connection());

        /* Bakım modunda yalnızca bakımı atlayabilen roller (yönetici,
         * editör) girer. Eskiden üye giriş yapıyor, ardından panel 503
         * dönüyordu. Mesaj yalnızca parolayı bilene görünür (hesap
         * durumu gibi). */
        if (Setting::bool('sistem_bakim_modu', false) && !$user->can('maintenance.bypass')) {
            return ['ok' => false, 'message' => self::BAKIM_MESAJI, 'user' => null];
        }

        self::login($user);
        $users->touchLogin($user->id, $ip);

        /* "Beni hatırla" YALNIZCA kullanıcı istediğinde. İşaretlenmemiş
         * bir girişte eski jetonu da temizliyoruz: kullanıcının
         * "bu sefer hatırlama" tercihi, önceki oturumdan kalan çerezi
         * de geçersiz kılmalı. */
        if ($remember) {
            self::rememberUser($user);
        } else {
            self::forgetRemember($user->id);
        }

        self::issueDevice($user, $sonuc['cihaz']);

        Logger::info('Giriş yapıldı', [
            'kullanici' => $user->id,
            'rol'       => $user->rol,
            'ip'        => $ip,
        ], 'auth');

        /* Olay, oturum AÇILDIKTAN sonra yayınlanır: dinleyiciler
         * Auth::user() ile kullanıcıya erişebilir. */
        Events::dispatch(new UserLoggedIn($user, $ip));

        $mesaj = 'Hoş geldiniz, ' . $user->ad . '.';

        // Silinmeyi bekleyen hesap: giriş yapmak silmeyi iptal eder (bkz. AccountDeletion).
        if (AccountDeletion::cancel($user->id)) {
            $mesaj .= ' Hesap silme isteğiniz iptal edildi.';
        }

        return ['ok' => true, 'message' => $mesaj, 'user' => $user];
    }

    /**
     * Kimlik bilgilerini DOĞRULAR ama oturum AÇMAZ.
     *
     * Kaba kuvvet sayacı, kilit, zamanlama eşitlemesi ve hesap durumu
     * denetimi buradadır. Tarayıcı girişi (attempt) ve mobil API girişi
     * (POST api/v1/oturum) aynı kapıdan geçer; ikinci bir "daha gevşek"
     * giriş yolu olmaz.
     *
     * @return array{ok:bool,message:string,user:?User,cihaz?:?string,onay?:User}
     */
    public static function verifyCredentials(string $identifier, string $password, Request $request): array
    {
        $db      = Database::connection();
        $users   = new UserRepository($db);
        $limiter = new RateLimiter($db);

        $kimlik = mb_strtolower(trim($identifier));
        $ip     = $request->ip();

        $user  = $users->findForLogin(trim($identifier));
        $cihaz = self::trustedDevice($user);
        $scope = $cihaz !== null ? 'cihaz:' . $cihaz : Ip::bucket($ip);

        /* Deneme ÖNCE yazılır, sonra yalnızca KENDİSİNDEN ÖNCEKİ
         * denemeler sayılır (bkz. RateLimiter). Aynı anda gönderilen
         * yüzlerce istek artık sınırı birlikte aşamaz. */
        $attemptId = $limiter->begin($kimlik, $scope);

        $lockedFor = $limiter->lockedFor($kimlik, $scope, $attemptId);

        if ($cihaz === null) {
            $lockedFor = max($lockedFor, $limiter->ipLockedFor($scope, $attemptId));
        }

        if ($lockedFor > 0) {
            // Kilide takılan deneme sayılmaz; yoksa her deneme kilidi uzatırdı.
            $limiter->cancel($attemptId);

            Logger::security('Kilitli hesaba/IP\'ye giriş denemesi', [
                'kimlik' => mb_substr(trim($identifier), 0, 60),
                'ip'     => $ip,
                'kalan'  => $lockedFor,
            ]);

            return [
                'ok'      => false,
                'message' => $lockedFor < 60
                    ? sprintf('Çok fazla hatalı deneme yapıldı. Lütfen %d saniye sonra tekrar deneyin.', $lockedFor)
                    : sprintf('Çok fazla hatalı deneme yapıldı. Lütfen %d dakika sonra tekrar deneyin.', (int) ceil($lockedFor / 60)),
                'user' => null,
            ];
        }

        /* GÜVENLİK: Kullanıcı bulunamasa bile AYNI MALİYETTE bir
         * password_verify çalıştırırız. Aksi halde yanıt SÜRESİ
         * farkından "bu e-posta/kullanıcı adı var mı yok mu"
         * anlaşılabilirdi (zamanlama saldırısı + kullanıcı tespiti). */
        if ($user === null) {
            password_verify($password, self::dummyHash());
            $passwordOk = false;
        } else {
            $passwordOk = $user->verifyPassword($password);
        }

        if (!$passwordOk) {
            // Deneme satırı kalır: hatalı deneme sayıldı.
            self::logFailure($user === null ? 'bilinmeyen hesap' : 'hatalı parola', $identifier, $request);

            /* "Kalan deneme hakkı" iki durumda da AYNI kurala göre
             * gösterilir. Eskiden yalnızca var olan hesapta çıkıyordu;
             * mesajın görünüp görünmemesi hesabın varlığını ele
             * veriyordu. */
            $remaining = $limiter->remaining($kimlik, $scope);
            $message   = self::genericFailure();

            if ($remaining > 0 && $remaining <= 2) {
                $message .= sprintf(' (Kalan deneme hakkı: %d)', $remaining);
            }

            return ['ok' => false, 'message' => $message, 'user' => null];
        }

        /** @var User $user */
        // Parola doğru: bu deneme hatalı sayılmaz.
        $limiter->clear($kimlik);

        if (!$user->isActive()) {
            Logger::security('Pasif/askıdaki hesapla giriş denemesi', [
                'kullanici' => $user->id,
                'durum'     => $user->durum,
                'ip'        => $ip,
            ]);

            if ($user->isPendingVerification()) {
                return [
                    'ok'      => false,
                    'message' => 'Hesabınız e-posta doğrulaması bekliyor. Kayıtta gönderdiğimiz bağlantıya tıklayın.',
                    'user'    => null,
                    'onay'    => $user,
                ];
            }

            return [
                'ok'      => false,
                'message' => 'Hesabınız şu anda ' . mb_strtolower($user->statusLabel(), 'UTF-8') . '. Yönetici ile iletişime geçin.',
                'user'    => null,
            ];
        }

        if ($user->needsRehash()) {
            $users->updatePasswordHash($user->id, password_hash($password, PASSWORD_DEFAULT));
        }

        return ['ok' => true, 'message' => '', 'user' => $user, 'cihaz' => $cihaz];
    }

    /**
     * Sahte özet — PHP'nin varsayılan bcrypt maliyetiyle aynı.
     *
     * Kurulumda ve kayıtta parolalar PASSWORD_DEFAULT ile özetlenir;
     * maliyeti PHP 8.4'e kadar 10, sonrasında 12'dir.
     */
    private static function dummyHash(): string
    {
        return PHP_VERSION_ID >= 80400 ? self::DUMMY_HASHES[12] : self::DUMMY_HASHES[10];
    }

    /**
     * Başarısız girişi kaydeder.
     *
     * DİKKAT: Parola ASLA loglanmaz; denenen kimlik de kırpılarak
     * yazılır. Amaç "kim, nereden, kaç kez denedi" sorusuna yanıt
     * vermek — kimlik bilgisi toplamak değil.
     */
    private static function logFailure(string $reason, string $identifier, Request $request): void
    {
        Logger::security('Başarısız giriş denemesi', [
            'sebep'  => $reason,
            'kimlik' => mb_substr(trim($identifier), 0, 60),
            'ip'     => $request->ip(),
            'tarayici' => $request->userAgent(),
        ]);
    }

    /**
     * Kullanıcıyı oturuma yazar.
     *
     * session_regenerate_id ŞART: saldırgan giriş öncesi kurbana bir
     * oturum kimliği kabul ettirmişse ("session fixation"), giriş
     * anında kimliği değiştirerek onu işe yaramaz hale getiririz.
     */
    public static function login(User $user): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, $user->id);
        Session::set(self::SESSION_VERSION, $user->oturumSurumu);
        Csrf::rotate();

        self::$cached   = $user;
        self::$resolved = true;
    }

    /**
     * Kullanıcıyı YALNIZCA BU İSTEK için tanır; oturuma hiçbir şey
     * yazmaz, çerez göndermez.
     *
     * API anahtarıyla gelen istekler için. Eskiden ApiGuard login()
     * çağırıyordu: anahtarla gelen istek oturum açıyor ve bir oturum
     * çerezi dönüyordu; anahtar iptal edildikten sonra bile istemci o
     * çerezle erişmeye devam edebiliyordu.
     */
    public static function actingAs(User $user): void
    {
        self::$cached   = $user;
        self::$resolved = true;
    }

    /**
     * Bu kullanıcının TÜM oturumlarını ve "beni hatırla" jetonunu
     * geçersiz kılar; çağıran oturum (ve bu cihazın "beni hatırla"
     * kaydı) açık kalır.
     *
     * @param bool $revokeTokens true → API anahtarları da iptal edilir
     * @return int İptal edilen API anahtarı sayısı
     */
    public static function logoutOtherDevices(int $userId, bool $revokeTokens = false): int
    {
        $users = new UserRepository(Database::connection());
        $users->bumpSessionVersion($userId);

        $iptal = $revokeTokens ? $users->revokeApiTokens($userId) : 0;

        self::refreshCurrentDevice($userId);

        Logger::info('Diğer cihazlardaki oturumlar kapatıldı', ['kullanici' => $userId, 'api_anahtari' => $iptal], 'auth');

        return $iptal;
    }

    /**
     * Oturum sürümü değiştikten sonra (parola değişimi, "diğer
     * cihazlardan çıkış") BU cihazı açık tutar.
     *
     * Kullanıcı kendi parolasını değiştirdiğinde ya da diğer cihazları
     * kapattığında kendisi de dışarı atılmamalı. Eskiden:
     *   · yönetici kendi parolasını Kullanıcılar ekranından değiştirince
     *     kendi oturumu da kapanıyordu,
     *   · "diğer cihazlardan çıkış" bu cihazın "beni hatırla" kaydını
     *     da siliyordu.
     *
     * Oturum yeni sürümle tazelenir (kimlik de yenilenir: eski oturum
     * kimliği çalınmış olabilir); bu cihazda "beni hatırla" açıksa yeni
     * bir jeton, güvenilen cihaz çereziyse yeni sürümle yeniden yazılır.
     * Çağıran başka bir kullanıcıysa (yönetici başkasının parolasını
     * değiştirdi) hiçbir şey yapmaz.
     */
    public static function refreshCurrentDevice(int $userId): void
    {
        /* user()'ı burada ÇAĞIRMIYORUZ: sürüm az önce arttıysa oturumu
         * "eski sürüm" diye kapatırdı. Kimin oturumu olduğuna bakmak
         * yeterli. */
        $current = self::$resolved ? self::$cached?->id : Session::get(self::SESSION_KEY);

        if ((int) $current !== $userId) {
            return;
        }

        $fresh = (new UserRepository(Database::connection()))->find($userId);

        if ($fresh === null) {
            return;
        }

        $hadRemember = self::hasRememberCookie();

        self::login($fresh);

        if ($hadRemember) {
            self::rememberUser($fresh);
        }

        self::issueDevice($fresh);
    }

    public static function logout(): void
    {
        /* Oturum YOK EDİLMEDEN ÖNCE: dinleyiciler oturumdaki veriye
         * hâlâ erişebilmeli. */
        if (self::$cached !== null) {
            Logger::info('Çıkış yapıldı', ['kullanici' => self::$cached->id], 'auth');

            /* Çıkış, "beni hatırla" jetonunu da İPTAL ETMELİDİR.
             * Etmeseydi çıkış yapan kullanıcı bir sonraki istekte
             * çerez sayesinde yeniden içeri alınırdı — ortak
             * bilgisayarda tam bir güvenlik açığı. */
            self::forgetRemember(self::$cached->id);

            Events::dispatch(new UserLoggedOut(self::$cached->id));
        }

        Session::destroy();

        self::$cached   = null;
        self::$resolved = true;
    }

    /* =================================================================
     *  "BENİ HATIRLA"
     * -----------------------------------------------------------------
     *  ÇALIŞMA BİÇİMİ
     *   1. Giriş anında rastgele 32 baytlık bir jeton üretilir.
     *   2. HAM jeton çereze, SHA-256 ÖZETİ veritabanına yazılır.
     *   3. Sonraki ziyarette çerezdeki jetonun özeti aranır; eşleşen
     *      ve süresi dolmamış AKTİF bir kullanıcı varsa oturum açılır.
     *   4. Her kullanımda jeton YENİLENİR (rotation) — KOŞULLU bir
     *      UPDATE ile: jeton bu arada değiştiyse ya da parola
     *      değiştiyse yenileme olmaz ve oturum AÇILMAZ (bkz.
     *      UserRepository::rotateRememberToken).
     *   5. Parola değişince jeton SİLİNİR (bkz. UserRepository::update).
     *
     *  Parola ya da e-posta ÇEREZE HİÇ YAZILMAZ.
     * ============================================================== */

    private static function rememberCookie(): string
    {
        return self::REMEMBER_PREFIX . substr(Session::appId(), 0, 8);
    }

    private static function hasRememberCookie(): bool
    {
        $ham = $_COOKIE[self::rememberCookie()] ?? '';

        return is_string($ham) && preg_match('/\A[a-f0-9]{64}\z/', $ham) === 1;
    }

    private static function fromRememberCookie(): ?User
    {
        if (!self::hasRememberCookie()) {
            return null;
        }

        $eski  = hash('sha256', (string) $_COOKIE[self::rememberCookie()]);
        $users = new UserRepository(Database::connection());
        $user  = $users->findByRememberToken($eski);

        if ($user === null) {
            /* Geçersiz ya da süresi dolmuş çerez: tarayıcıdan silelim,
             * yoksa her istekte boş yere bir sorgu daha çalışır. */
            self::clearRememberCookie();

            return null;
        }

        $ham = bin2hex(random_bytes(32));

        if (!$users->rotateRememberToken($user->id, $eski, hash('sha256', $ham), $user->oturumSurumu, self::REMEMBER_DAYS)) {
            /* Yarışı kaybettik: aynı çerezle gelen başka bir istek jetonu
             * bizden önce yeniledi ya da parola tam şu an değişti.
             * Çerezi SİLMİYORUZ (diğer isteğin yazdığı yeni çerezi ezer);
             * yalnızca bu istekte oturum açmıyoruz. */
            Logger::info('"Beni hatırla" jetonu yenilenemedi (eşzamanlı istek ya da parola değişimi)', ['kullanici' => $user->id], 'auth');

            return null;
        }

        self::login($user);
        self::setRememberCookie($ham);

        Logger::info('Oturum "beni hatırla" çereziyle açıldı', ['kullanici' => $user->id], 'auth');

        return $user;
    }

    private static function rememberUser(User $user): void
    {
        $ham = bin2hex(random_bytes(32));

        (new UserRepository(Database::connection()))
            ->setRememberToken($user->id, hash('sha256', $ham), self::REMEMBER_DAYS);

        self::setRememberCookie($ham);
    }

    private static function forgetRemember(int $userId): void
    {
        (new UserRepository(Database::connection()))->clearRememberToken($userId);

        self::clearRememberCookie();
    }

    private static function setRememberCookie(string $value): void
    {
        self::writeCookie(self::rememberCookie(), $value, time() + self::REMEMBER_DAYS * 86400);

        $_COOKIE[self::rememberCookie()] = $value;
    }

    private static function clearRememberCookie(): void
    {
        unset($_COOKIE[self::rememberCookie()]);

        self::writeCookie(self::rememberCookie(), '', time() - 3600);
    }

    /* =================================================================
     *  GÜVENİLEN CİHAZ
     * -----------------------------------------------------------------
     *  Değer: <kullanıcı>.<cihaz>.<oturum sürümü>.<bitiş>.<imza>
     *  İmza APP_KEY ile atılır (bkz. Signer). Oturum sürümü değerin
     *  içinde olduğu için parola değişince (ya da "diğer cihazlardan
     *  çıkış" denince) eski cihaz çerezleri kendiliğinden geçersizdir.
     * ============================================================== */

    private static function deviceCookie(): string
    {
        return self::DEVICE_PREFIX . substr(Session::appId(), 0, 8);
    }

    /**
     * Çerez geçerliyse VE giriş yapılmak istenen hesaba aitse cihaz
     * kimliği; değilse null.
     */
    private static function trustedDevice(?User $user): ?string
    {
        $raw = $_COOKIE[self::deviceCookie()] ?? '';

        if ($user === null || !is_string($raw)
            || preg_match('/\A(\d{1,10})\.([a-f0-9]{16})\.(\d{1,10})\.(\d{9,11})\.([a-f0-9]{64})\z/', $raw, $m) !== 1) {
            return null;
        }

        [, $kullanici, $cihaz, $surum, $bitis, $imza] = $m;

        try {
            $gecerli = Signer::check('cihaz|' . $kullanici . '|' . $cihaz . '|' . $surum . '|' . $bitis, $imza);
        } catch (Throwable) {
            return null;
        }

        if (!$gecerli || (int) $kullanici !== $user->id || (int) $surum !== $user->oturumSurumu || (int) $bitis < time()) {
            return null;
        }

        return $cihaz;
    }

    private static function issueDevice(User $user, ?string $cihaz = null): void
    {
        $cihaz ??= bin2hex(random_bytes(8));
        $bitis   = time() + self::DEVICE_DAYS * 86400;
        $govde   = $user->id . '|' . $cihaz . '|' . $user->oturumSurumu . '|' . $bitis;

        try {
            $imza = Signer::sign('cihaz|' . $govde);
        } catch (Throwable $e) {
            Logger::warning('Güvenilen cihaz çerezi yazılamadı: ' . $e->getMessage(), [], 'auth');

            return;
        }

        self::writeCookie(self::deviceCookie(), str_replace('|', '.', $govde) . '.' . $imza, $bitis);
    }

    /**
     * Çerezi uygulamanın yoluna, HttpOnly + SameSite=Lax olarak yazar.
     * JavaScript bu çerezlere ERİŞEMEZ: bir XSS açığı onları okuyup
     * saldırgana gönderemesin.
     */
    private static function writeCookie(string $name, string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => Url::cookiePath(),
            'secure'   => Session::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::SESSION_VERSION);
    }

    private static function genericFailure(): string
    {
        return 'E-posta/kullanıcı adı veya parola hatalı.';
    }
}
