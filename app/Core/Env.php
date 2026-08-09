<?php
/**
 * =====================================================================
 *  Env – ".env" dosyası okuyucu
 * ---------------------------------------------------------------------
 *  Şifre gibi hassas değerleri koda yazmak yerine, depoya gönderilmeyen
 *  ".env" dosyasında tutarız. Kurulum sihirbazı bu dosyayı otomatik
 *  üretir; siz de isterseniz elle oluşturabilirsiniz (.env.example).
 *
 *  Arama sırası:  .env dosyası  →  sunucu ortam değişkeni  →  varsayılan
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Env
{
    /** @var array<string,string>|null */
    private static ?array $values = null;

    public static function load(string $path): void
    {
        self::$values = [];

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if (strlen($value) > 1) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];

                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (self::$values !== null && array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $fromServer = getenv($key);

        return $fromServer !== false ? (string) $fromServer : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $raw = strtolower(trim(self::get($key, $default ? 'true' : 'false')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    /** .env dosyası hiç var mı? (kurulum yapılmış mı sorusunun ilk işareti) */
    public static function exists(string $path): bool
    {
        return is_file($path) && is_readable($path);
    }
}
