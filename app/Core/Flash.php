<?php
/**
 * =====================================================================
 *  Flash – Tek seferlik bildirim mesajları (POST → Redirect → GET)
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    private const KEY = '_flash';

    public static function add(string $type, string $message): void
    {
        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }

    public static function success(string $message): void { self::add('success', $message); }
    public static function error(string $message): void   { self::add('danger', $message); }
    public static function warning(string $message): void { self::add('warning', $message); }
    public static function info(string $message): void    { self::add('info', $message); }

    /** @return array<int,array{type:string,message:string}> */
    public static function pull(): array
    {
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);

        return is_array($messages) ? $messages : [];
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed>  $old
     */
    public static function withInput(array $errors, array $old = []): void
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old']    = self::withoutSecrets($old);
    }

    /**
     * Formu yeniden doldurmak için saklanan girdiden PAROLA ALANLARINI
     * ayıklar.
     *
     * Çağıranların çoğu buraya doğrudan $_POST veriyor. Parola alanı
     * zaten hiçbir formda geri basılmaz; saklandığında tek yaptığı
     * kullanıcının düz metin parolasını oturum dosyasında —
     * sunucunun geçici klasöründe, düz metin olarak — bırakmaktır.
     *
     * @param array<string,mixed> $old
     * @return array<string,mixed>
     */
    private static function withoutSecrets(array $old): array
    {
        foreach (array_keys($old) as $key) {
            $name = mb_strtolower((string) $key);

            if (str_contains($name, 'sifre') || str_contains($name, 'password') || str_contains($name, 'parola')) {
                unset($old[$key]);
            }
        }

        return $old;
    }

    /** @return array<string,string> */
    public static function errors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);

        return is_array($errors) ? $errors : [];
    }

    /** @return array<string,mixed> */
    public static function old(): array
    {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);

        return is_array($old) ? $old : [];
    }
}
