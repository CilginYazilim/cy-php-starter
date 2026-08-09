<?php
/**
 * =====================================================================
 *  Autoloader – Sınıfları otomatik yükleyici (PSR-4 mantığı)
 * ---------------------------------------------------------------------
 *  Composer kullanmıyoruz; bu şablon bilerek SIFIR BAĞIMLILIKLIDIR,
 *  indirip doğrudan çalıştırabilmeniz için.
 *
 *      App\Models\User   →  app/Models/User.php
 *      App\Core\Database →  app/Core/Database.php
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    public static function register(string $prefix, string $baseDir): void
    {
        $prefix  = rtrim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            /* GÜVENLİK: realpath() ile dosyanın gerçekten kendi
             * klasörümüzün içinde olduğunu doğruluyoruz (path
             * traversal koruması). */
            $real = realpath($file);

            if ($real !== false && str_starts_with($real, realpath($baseDir) ?: $baseDir)) {
                require $real;
            }
        });
    }
}
