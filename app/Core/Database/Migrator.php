<?php
/**
 * =====================================================================
 *  Migrator – Şema değişikliklerini sırayla uygular ve geri alır
 * ---------------------------------------------------------------------
 *  DOSYA ADI = SIRA
 *      database/migrations/2026_08_10_120000_urunler_tablosu.php
 *                          └──── zaman damgası ────┘ └── açıklama ──┘
 *
 *  Dosyalar ada göre sıralanır; zaman damgası önde olduğu için bu
 *  aynı zamanda kronolojik sıradır.
 *
 *  PARTİ (batch) KAVRAMI
 *  Bir "php cy migrate" çağrısında çalışan tüm migration'lar aynı
 *  parti numarasını alır. "php cy migrate:rollback" son partiyi bir
 *  bütün olarak geri alır — böylece üç dosyalık bir dağıtımı tek
 *  komutla geri sarabilirsiniz.
 *
 *  "kurulum/database.sql" İLE İLİŞKİSİ
 *  İlk kurulum hâlâ SQL dökümüyle yapılır (hızlı ve tek adım).
 *  Migration'lar ondan SONRAKİ değişiklikler içindir. İkisi çakışmaz:
 *  migrasyonlar tablosu yalnızca burada çalıştırılanları bilir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Log\Logger;
use PDO;
use RuntimeException;
use Throwable;

final class Migrator
{
    private const TABLE = 'migrasyonlar';

    /**
     * @param string               $path       Uygulamanın migration klasörü
     * @param array<string,string> $modulePaths Modül adı => klasör
     */
    public function __construct(
        private readonly PDO $db,
        private readonly string $path,
        private readonly array $modulePaths = [],
    ) {
    }

    /**
     * Tüm kaynaklar: '' => uygulama, 'Ornek' => modül.
     *
     * @return array<string,string>
     */
    private function sources(): array
    {
        return ['' => $this->path] + $this->modulePaths;
    }

    /**
     * Migration adını dosya yoluna çevirir.
     *
     * Modül migration'ları "Ornek/2026_..." biçiminde adlandırılır;
     * böylece iki modül aynı dosya adını kullansa bile kayıt
     * tablosunda çakışmazlar.
     */
    private function fileFor(string $name): ?string
    {
        if (str_contains($name, '/')) {
            [$module, $file] = explode('/', $name, 2);

            $dir = $this->modulePaths[$module] ?? '';

            if ($dir === '') {
                return null;
            }

            return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . basename($file) . '.php';
        }

        return rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . basename($name) . '.php';
    }

    /* =================================================================
     *  KAYIT TABLOSU
     * ============================================================== */

    /** Kayıt tablosu yoksa oluşturur. Her işlemden önce çağrılır. */
    public function ensureTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `dosya`      VARCHAR(255) NOT NULL,
                `parti`      INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migrasyon_dosya` (`dosya`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci'
        );
    }

    /* =================================================================
     *  DURUM
     * ============================================================== */

    /**
     * Diskteki tüm migration dosyaları (sıralı).
     *
     * @return array<int,string> Uzantısız dosya adları
     */
    public function available(): array
    {
        $names = [];

        foreach ($this->sources() as $label => $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
                $base = basename($file, '.php');

                $names[] = $label === '' ? $base : $label . '/' . $base;
            }
        }

        /* Zaman damgasına göre sıralarız — modül öneki değil.
         * Böylece bir modülün tablosu, ona bağımlı olduğu çekirdek
         * tablosundan önce oluşturulmaz. */
        usort($names, static function (string $a, string $b): int {
            $ka = str_contains($a, '/') ? explode('/', $a, 2)[1] : $a;
            $kb = str_contains($b, '/') ? explode('/', $b, 2)[1] : $b;

            return $ka === $kb ? strcmp($a, $b) : strcmp($ka, $kb);
        });

        return $names;
    }

    /**
     * Çalıştırılmış migration'lar → parti numarası.
     *
     * @return array<string,int>
     */
    public function completed(): array
    {
        $this->ensureTable();

        $rows = $this->db->query(
            'SELECT dosya, parti FROM `' . self::TABLE . '` ORDER BY id ASC'
        )->fetchAll();

        $completed = [];

        foreach ($rows as $row) {
            $completed[(string) $row['dosya']] = (int) $row['parti'];
        }

        return $completed;
    }

    /** @return array<int,string> Henüz çalıştırılmamış dosyalar */
    public function pending(): array
    {
        $completed = $this->completed();

        return array_values(array_filter(
            $this->available(),
            static fn (string $name): bool => !array_key_exists($name, $completed)
        ));
    }

    /* =================================================================
     *  ÇALIŞTIRMA
     * ============================================================== */

    /**
     * Bekleyen migration'ları sırayla çalıştırır.
     *
     * @param callable(string):void|null $onEach Her dosyadan önce bilgi vermek için
     * @return array<int,string> Çalıştırılanlar
     */
    public function run(?callable $onEach = null): array
    {
        $pending = $this->pending();

        if ($pending === []) {
            return [];
        }

        $batch = $this->nextBatch();
        $done  = [];

        foreach ($pending as $name) {
            if ($onEach !== null) {
                $onEach($name);
            }

            $this->runOne($name, 'up');
            $this->markCompleted($name, $batch);

            $done[] = $name;
        }

        Logger::info('Migration çalıştırıldı', ['parti' => $batch, 'dosyalar' => $done], 'app');

        return $done;
    }

    /**
     * Son partiyi (ya da $steps kadar partiyi) geri alır.
     *
     * @param callable(string):void|null $onEach
     * @return array<int,string> Geri alınanlar
     */
    public function rollback(int $steps = 1, ?callable $onEach = null): array
    {
        $this->ensureTable();

        $batches = $this->batchesToRollback(max(1, $steps));

        if ($batches === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($batches), '?'));

        $statement = $this->db->prepare(
            'SELECT dosya FROM `' . self::TABLE . '`
              WHERE parti IN (' . $placeholders . ')
              ORDER BY id DESC'
        );
        $statement->execute($batches);

        $undone = [];

        foreach ($statement->fetchAll() as $row) {
            $name = (string) $row['dosya'];

            if ($onEach !== null) {
                $onEach($name);
            }

            $this->runOne($name, 'down');
            $this->markRolledBack($name);

            $undone[] = $name;
        }

        Logger::warning('Migration geri alındı', ['dosyalar' => $undone], 'app');

        return $undone;
    }

    /**
     * TÜM migration'ları geri alır. Yıkıcıdır.
     *
     * @param callable(string):void|null $onEach
     * @return array<int,string>
     */
    public function reset(?callable $onEach = null): array
    {
        $this->ensureTable();

        $total = count(array_unique(array_values($this->completed())));

        return $total === 0 ? [] : $this->rollback($total, $onEach);
    }

    /* =================================================================
     *  İÇ İŞLEYİŞ
     * ============================================================== */

    /**
     * Bir migration dosyasını yükler ve up/down çalıştırır.
     *
     * @param 'up'|'down' $direction
     */
    private function runOne(string $name, string $direction): void
    {
        $migration = $this->resolve($name);

        $migration->setConnection($this->db);

        /* DDL komutları MySQL'de örtük commit yapar; işlem yalnızca
         * migration bunu açıkça istediğinde (veri taşıyan
         * migration'lar) açılır. Bkz. Migration::useTransaction(). */
        $transactional = $migration->useTransaction() && !$this->db->inTransaction();

        if ($transactional) {
            $this->db->beginTransaction();
        }

        try {
            $migration->{$direction}();

            if ($transactional) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($transactional && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            Logger::critical('Migration başarısız: ' . $name, [
                'yon'  => $direction,
                'hata' => $e->getMessage(),
            ], 'error');

            throw new RuntimeException(
                sprintf('"%s" migration\'ı (%s) başarısız oldu: %s', $name, $direction, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /** Dosyayı yükler ve döndürdüğü nesneyi doğrular. */
    private function resolve(string $name): Migration
    {
        $file = $this->fileFor($name);

        if ($file === null || !is_file($file)) {
            throw new RuntimeException('Migration dosyası bulunamadı: ' . $name . '.php');
        }

        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new RuntimeException(
                $name . '.php bir Migration nesnesi döndürmüyor. '
                . 'Dosya "return new class extends App\Core\Database\Migration { ... };" ile bitmelidir.'
            );
        }

        return $migration;
    }

    private function nextBatch(): int
    {
        $this->ensureTable();

        $max = $this->db->query('SELECT MAX(parti) FROM `' . self::TABLE . '`')->fetchColumn();

        return (int) $max + 1;
    }

    /** @return array<int,int> Geri alınacak parti numaraları (büyükten küçüğe) */
    private function batchesToRollback(int $steps): array
    {
        $statement = $this->db->prepare(
            'SELECT DISTINCT parti FROM `' . self::TABLE . '` ORDER BY parti DESC LIMIT ' . $steps
        );
        $statement->execute();

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function markCompleted(string $name, int $batch): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO `' . self::TABLE . '` (dosya, parti) VALUES (:dosya, :parti)'
        );
        $statement->execute([':dosya' => $name, ':parti' => $batch]);
    }

    private function markRolledBack(string $name): void
    {
        $statement = $this->db->prepare('DELETE FROM `' . self::TABLE . '` WHERE dosya = :dosya');
        $statement->execute([':dosya' => $name]);
    }
}
