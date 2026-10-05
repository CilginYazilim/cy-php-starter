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

final class Auth
{
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
     * @return array{ok:bool,message:string,user:?User}
     */
    public static function attempt(string $identifier, string $password, Request $request, bool $remember = false): array
    {
        $db      = Database::connection();
        $users   = new UserRepository($db);
        $limiter = new RateLimiter($db);

        $key = mb_strtolower(trim($identifier)) . '|' . $request->ip();

        $lockedFor = max($limiter->lockedFor($key), $limiter->ipLockedFor($request->ip()));

        if ($lockedFor > 0) {
            Logger::security('Kilitli hesaba/IP\'ye giriş denemesi', [
                'kimlik' => mb_substr(trim($identifier), 0, 60),
                'ip'     => $request->ip(),
                'kalan'  => $lockedFor,
            ]);

            return [
                'ok'      => false,
                'message' => sprintf(
                    'Çok fazla hatalı deneme yaptınız. Lütfen %d dakika sonra tekrar deneyin.',
                    (int) ceil($lockedFor / 60)
                ),
                'user' => null,
            ];
        }

        $user = $users->findForLogin(trim($identifier));

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
            $limiter->hit($key, $request->ip());

            self::logFailure($user === null ? 'bilinmeyen hesap' : 'hatalı parola', $identifier, $request);

            /* "Kalan deneme hakkı" iki durumda da AYNI kurala göre
             * gösterilir. Eskiden yalnızca var olan hesapta çıkıyordu;
             * mesajın görünüp görünmemesi hesabın varlığını ele
             * veriyordu. */
            $remaining = $limiter->remaining($key);
            $message   = self::genericFailure();

            if ($remaining > 0 && $remaining <= 2) {
                $message .= sprintf(' (Kalan deneme hakkı: %d)', $remaining);
            }

            return ['ok' => false, 'message' => $message, 'user' => null];
        }

        /** @var User $user */
        if (!$user->isActive()) {
            Logger::security('Pasif/askıdaki hesapla giriş denemesi', [
                'kullanici' => $user->id,
                'durum'     => $user->durum,
                'ip'        => $request->ip(),
            ]);

            return [
                'ok'      => false,
                'message' => 'Hesabınız şu anda ' . mb_strtolower($user->statusLabel(), 'UTF-8') . '. Yönetici ile iletişime geçin.',
                'user'    => null,
            ];
        }

        $limiter->clear($key);

        if ($user->needsRehash()) {
            $users->updatePasswordHash($user->id, password_hash($password, PASSWORD_DEFAULT));
        }

        self::login($user);
        $users->touchLogin($user->id, $request->ip());

        /* "Beni hatırla" YALNIZCA kullanıcı istediğinde. İşaretlenmemiş
         * bir girişte eski jetonu da temizliyoruz: kullanıcının
         * "bu sefer hatırlama" tercihi, önceki oturumdan kalan çerezi
         * de geçersiz kılmalı. */
        if ($remember) {
            self::rememberUser($user);
        } else {
            self::forgetRemember($user->id);
        }

        Logger::info('Giriş yapıldı', [
            'kullanici' => $user->id,
            'rol'       => $user->rol,
            'ip'        => $request->ip(),
        ], 'auth');

        /* Olay, oturum AÇILDIKTAN sonra yayınlanır: dinleyiciler
         * Auth::user() ile kullanıcıya erişebilir. */
        Events::dispatch(new UserLoggedIn($user, $request->ip()));

        return ['ok' => true, 'message' => 'Hoş geldiniz, ' . $user->ad . '.', 'user' => $user];
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
     * geçersiz kılar; çağıran oturum açık kalır.
     *
     * Parola değişikliğinden sonra ve "diğer cihazlardan çıkış yap"
     * düğmesinde kullanılır.
     */
    public static function logoutOtherDevices(int $userId): void
    {
        $users = new UserRepository(Database::connection());
        $users->bumpSessionVersion($userId);

        self::forgetRemember($userId);

        $fresh = $users->find($userId);

        /* Kendi oturumumuzu yeni sürümle tazeliyoruz; kimlik de
         * yenilenir (eski oturum kimliği çalınmış olabilir). */
        if ($fresh !== null && self::id() === $userId) {
            self::login($fresh);
        }

        Logger::info('Diğer cihazlardaki oturumlar kapatıldı', ['kullanici' => $userId], 'auth');
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
     *   4. Her kullanımda jeton YENİLENİR (rotation): çalınan bir
     *      çerezin ömrü, gerçek kullanıcının bir sonraki ziyaretiyle
     *      sona erer.
     *   5. Parola değişince jeton SİLİNİR (bkz. UserRepository::update).
     *
     *  Parola ya da e-posta ÇEREZE HİÇ YAZILMAZ.
     * ============================================================== */

    private static function rememberCookie(): string
    {
        return self::REMEMBER_PREFIX . substr(Session::appId(), 0, 8);
    }

    private static function fromRememberCookie(): ?User
    {
        $ham = $_COOKIE[self::rememberCookie()] ?? '';

        if (!is_string($ham) || $ham === '' || !preg_match('/^[a-f0-9]{64}$/', $ham)) {
            return null;
        }

        $users = new UserRepository(Database::connection());
        $user  = $users->findByRememberToken(hash('sha256', $ham));

        if ($user === null) {
            /* Geçersiz ya da süresi dolmuş çerez: tarayıcıdan silelim,
             * yoksa her istekte boş yere bir sorgu daha çalışır. */
            self::clearRememberCookie();

            return null;
        }

        self::login($user);

        self::rememberUser($user);

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
        if (headers_sent()) {
            return;
        }

        setcookie(self::rememberCookie(), $value, [
            'expires'  => time() + self::REMEMBER_DAYS * 86400,
            'path'     => Url::base() !== '' ? Url::base() . '/' : '/',
            'secure'   => Session::isHttps(),
            // JavaScript bu çereze ERİŞEMEZ: bir XSS açığı jetonu okuyup
            // saldırgana gönderemesin.
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[self::rememberCookie()] = $value;
    }

    private static function clearRememberCookie(): void
    {
        unset($_COOKIE[self::rememberCookie()]);

        if (headers_sent()) {
            return;
        }

        setcookie(self::rememberCookie(), '', [
            'expires'  => time() - 3600,
            'path'     => Url::base() !== '' ? Url::base() . '/' : '/',
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
