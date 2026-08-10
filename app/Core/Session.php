<?php
/**
 * =====================================================================
 *  Session – Sertleştirilmiş oturum yönetimi
 * ---------------------------------------------------------------------
 *  httponly + samesite + secure çerez, tarayıcı parmak izi kontrolü,
 *  hareketsizlik zaman aşımı, periyodik kimlik yenileme.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');

        session_name((string) Config::get('session.name', 'CYSTARTERSESS'));

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::$started = true;

        self::guard();
    }

    private static function guard(): void
    {
        $now         = time();
        $idleTimeout = (int) Config::get('session.idle_timeout', 1800);
        $regenEvery  = (int) Config::get('session.regenerate_every', 900);

        $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|cy-starter');

        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif (!hash_equals((string) $_SESSION['_fingerprint'], $fingerprint)) {
            self::destroy();
            session_start();
            self::$started = true;
            $_SESSION['_fingerprint'] = $fingerprint;
        }

        if (isset($_SESSION['_last_activity'])
            && ($now - (int) $_SESSION['_last_activity']) > $idleTimeout) {

            self::destroy();
            session_start();
            self::$started = true;
            $_SESSION['_expired'] = true;
        }

        $_SESSION['_last_activity'] = $now;

        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = $now;
        } elseif (($now - (int) $_SESSION['_created']) > $regenEvery) {
            self::regenerate();
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();

        /* Bayrağı da indiriyoruz: aksi halde destroy()'dan sonra
         * çağrılan Session::start() "zaten başlamıştı" diye erken
         * dönerdi ve o noktadan sonra $_SESSION'a yazılan her şey
         * (çıkış bildirimi gibi) sessizce kaybolurdu. */
        self::$started = false;
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }
}
