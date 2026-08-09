<?php
/**
 * =====================================================================
 *  Csrf – Sahte istek (Cross-Site Request Forgery) koruması
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function check(?string $token = null): bool
    {
        $token ??= (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if ($token === '' || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::SESSION_KEY], $token);
    }

    public static function rotate(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        self::token();
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }
}
