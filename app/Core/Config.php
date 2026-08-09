<?php
/**
 * =====================================================================
 *  Config – Ayarlara nokta notasyonuyla erişim
 * ---------------------------------------------------------------------
 *      Config::load(CY_BASE . '/config/config.php');
 *      Config::get('db.host');
 *      Config::get('upload.max_bytes');
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    public static function load(string $file): void
    {
        $data = require $file;

        self::$items = is_array($data) ? $data : [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref      = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref = $value;
    }
}
