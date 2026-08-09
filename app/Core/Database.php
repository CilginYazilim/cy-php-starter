<?php
/**
 * =====================================================================
 *  Database – PDO bağlantısını tek noktadan yönetir (tembel tekil)
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    private function __construct()
    {
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) Config::get('db.host'),
            (int) Config::get('db.port', 3306),
            (string) Config::get('db.name'),
            (string) Config::get('db.charset', 'utf8mb4')
        );

        try {
            self::$connection = new PDO(
                $dsn,
                (string) Config::get('db.user'),
                (string) Config::get('db.pass'),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_PERSISTENT         => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('[CY] Veritabani baglanti hatasi: ' . $e->getMessage());

            throw new RuntimeException(
                Config::get('app.debug')
                    ? 'Veritabanı bağlantı hatası: ' . $e->getMessage()
                    : 'Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.',
                0,
                $e
            );
        }

        return self::$connection;
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }
}
