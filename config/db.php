<?php
/**
 * =====================================================================
 *  VERİTABANI                   →  Config::get('db.*')
 * ---------------------------------------------------------------------
 *  Değerler ".env" dosyasından gelir; parola ASLA bu dosyaya yazılmaz.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    'host'    => Env::get('DB_HOST', '127.0.0.1'),
    'port'    => Env::int('DB_PORT', 3306),
    'name'    => Env::get('DB_NAME', 'yeni_proje'),
    'user'    => Env::get('DB_USER', 'root'),
    'pass'    => Env::get('DB_PASS', ''),
    'charset' => 'utf8mb4',

    /* -----------------------------------------------------------------
     *  ŞEMA DEĞİŞİKLİKLERİ
     * -----------------------------------------------------------------
     *  İlk kurulum kurulum/database.sql ile yapılır (tek adım, hızlı).
     *  Migration'lar ondan SONRAKİ değişiklikler içindir:
     *      php cy migrate · migrate:rollback · migrate:status
     * -------------------------------------------------------------- */
    'migrations' => CY_BASE . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
    'seeders'    => CY_BASE . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders',
];
