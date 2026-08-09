<?php
/**
 * =====================================================================
 *  Auth – Kimlik doğrulama ve yetkilendirme
 * ---------------------------------------------------------------------
 *  Oturumda yalnızca KULLANICI ID'si tutulur. Rol ve durum her
 *  istekte veritabanından TAZE okunur; aksi halde bir yönetici
 *  kullanıcıyı pasife aldığında, o kişi oturumu açık kaldığı sürece
 *  yetkilerini kullanmaya devam ederdi.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Repositories\UserRepository;

final class Auth
{
    private const SESSION_KEY = '_auth_user_id';

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
            return self::$cached = null;
        }

        $user = (new UserRepository(Database::connection()))->find((int) $id);

        if ($user === null || !$user->isActive()) {
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
    public static function attempt(string $identifier, string $password, Request $request): array
    {
        $db      = Database::connection();
        $users   = new UserRepository($db);
        $limiter = new RateLimiter($db);

        $key = mb_strtolower(trim($identifier)) . '|' . $request->ip();

        $lockedFor = $limiter->lockedFor($key);

        if ($lockedFor > 0) {
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

        /* GÜVENLİK: Kullanıcı bulunamasa bile bir kez password_verify
         * çalıştırıyoruz. Aksi halde yanıt SÜRESİ farkından "bu
         * e-posta/kullanıcı adı var mı yok mu" anlaşılabilirdi
         * (timing attack + user enumeration). */
        if ($user === null) {
            password_verify($password, '$2y$12$usercountermeasuredummyhashvalue0000000000000000000000');

            $limiter->hit($key, $request->ip());

            return ['ok' => false, 'message' => self::genericFailure(), 'user' => null];
        }

        if (!$user->verifyPassword($password)) {
            $limiter->hit($key, $request->ip());

            $remaining = $limiter->remaining($key);
            $message   = self::genericFailure();

            if ($remaining > 0 && $remaining <= 2) {
                $message .= sprintf(' (Kalan deneme hakkı: %d)', $remaining);
            }

            return ['ok' => false, 'message' => $message, 'user' => null];
        }

        if (!$user->isActive()) {
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

        return ['ok' => true, 'message' => 'Hoş geldiniz, ' . $user->ad . '.', 'user' => $user];
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
        Csrf::rotate();

        self::$cached   = $user;
        self::$resolved = true;
    }

    public static function logout(): void
    {
        Session::destroy();

        self::$cached   = null;
        self::$resolved = true;
    }

    private static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    private static function genericFailure(): string
    {
        return 'E-posta/kullanıcı adı veya parola hatalı.';
    }
}
