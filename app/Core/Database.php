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
    /** MySQL/MariaDB'nin varsayılan kapısı. */
    public const VARSAYILAN_KAPI = 3306;

    private static ?PDO $connection = null;

    private function __construct()
    {
    }

    /**
     * PDO'ya verilecek sunucu adı.
     *
     * "localhost" yazılınca PDO (Linux, macOS, paylaşımlı hosting) TCP
     * yerine Unix soketine bağlanır ve KAPIYI YOK SAYAR: ikinci bir MySQL
     * örneği için yazılan "localhost:3307" fark ettirmeden varsayılan
     * sunucuya bağlanırdı. Varsayılandan farklı bir kapıda 127.0.0.1
     * kullanılır; kapı yalnızca TCP'de anlam taşır.
     *
     * 3306'da "localhost" olduğu gibi kalır: paylaşımlı hostinglerde
     * veritabanı kullanıcısı çoğu zaman yalnızca 'kullanici'@'localhost'
     * olarak tanımlıdır ve TCP ile (127.0.0.1) giremez.
     *
     * kurulum/index.php → split_host() aynı kuralı uygular; tests/unit.php
     * ikisinin aynı sonucu verdiğini denetler.
     */
    public static function host(string $host, int $port): string
    {
        return strtolower($host) === 'localhost' && $port !== self::VARSAYILAN_KAPI ? '127.0.0.1' : $host;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $kapi = (int) Config::get('db.port', self::VARSAYILAN_KAPI);
        $dsn  = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::host((string) Config::get('db.host'), $kapi),
            $kapi,
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

        self::syncTimezone();

        return self::$connection;
    }

    /**
     * Bağlantının saat dilimini PHP'ninkiyle eşitler.
     *
     * NEDEN ŞART? Kod iki saati karıştırıyordu: kayıtlar SQL'in NOW()
     * değeriyle yazılıyor, bazı karşılaştırmalar ise PHP'nin time() /
     * date() değeriyle yapılıyordu. Sunucuda MySQL UTC, PHP İstanbul
     * saatindeyse (VPS ve Docker'da çok yaygın) aradaki 3 saat:
     *   · kaba kuvvet kilidini HİÇ devreye sokmuyordu,
     *   · kuyruktaki işleri 3 saat geç (ya da erken) çalıştırıyordu,
     *   · API anahtarlarının süresini yanlış hesaplıyordu.
     *
     * Adlandırılmış bölge ("Europe/Istanbul") yerine sayısal fark
     * ("+03:00") gönderilir: adlandırılmış bölgeler MySQL'in saat
     * dilimi tablolarının yüklü olmasını ister, paylaşımlı
     * sunucuların çoğunda yüklü değildir.
     *
     * Saat dilimi bağlantıdan SONRA değişirse (index.php, panelden
     * seçilen dilimi ayarlar tablosundan okuyup uygular) bu metot
     * yeniden çağrılır.
     */
    public static function syncTimezone(): void
    {
        if (!self::$connection instanceof PDO) {
            return;
        }

        try {
            self::$connection->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            error_log('[CY] Veritabani saat dilimi ayarlanamadi: ' . $e->getMessage());
        }
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }

    /**
     * Kilitlenme (deadlock, 1213) ya da kilit bekleme zaman aşımı
     * (1205) olursa işlemi kısa bir beklemeyle YENİDEN dener.
     *
     * InnoDB bu iki hatada işlemi kendisi geri alır ve "tekrar dene"
     * der; hata bir programlama yanlışı değil, eşzamanlılığın olağan
     * sonucudur. Eskiden kuyruk işçileri bu hatayla çöküyor, gönderilmiş
     * bir mektup bile "başarısız (Deadlock)" diye işaretlenebiliyordu.
     *
     * @template T
     * @param callable():T $operation
     * @return T
     */
    public static function retry(callable $operation, int $attempts = 3): mixed
    {
        for ($i = 1; ; $i++) {
            try {
                return $operation();
            } catch (PDOException $e) {
                if ($i >= $attempts || !self::isTransient($e)) {
                    throw $e;
                }

                usleep(random_int(20_000, 120_000) * $i);
            }
        }
    }

    /** Yeniden denenebilir bir hata mı? (1213 deadlock, 1205 kilit zaman aşımı) */
    public static function isTransient(\Throwable $e): bool
    {
        if (!$e instanceof PDOException) {
            return false;
        }

        $code = (int) ($e->errorInfo[1] ?? 0);

        return in_array($code, [1213, 1205], true)
            || in_array((string) $e->getCode(), ['40001'], true);
    }

    /** "Bilinmeyen sütun" hatası mı? (migration çalıştırılmamış) */
    public static function isMissingColumn(\Throwable $e): bool
    {
        return $e instanceof PDOException
            && ((int) ($e->errorInfo[1] ?? 0) === 1054 || (string) $e->getCode() === '42S22');
    }
}
