<?php
/**
 * =====================================================================
 *  Queue – İşleri sıraya koyar ve işçiye dağıtır
 * ---------------------------------------------------------------------
 *      Queue::push(new RaporUret(3));           // sıraya koy
 *      Queue::later(new RaporUret(3), 600);     // 10 dakika sonra
 *
 *  Ve sunucuda:
 *      php cy queue:work
 *
 *  SÜRÜCÜ: veritabanı ("isler" tablosu). Redis gibi bir kuyruk
 *  eklemek isterseniz bu sınıfın genel metotları sözleşmedir;
 *  bugünden arayüz açmıyoruz (bkz. Storage'daki aynı gerekçe).
 *
 *  İŞ NASIL "AYRILIR" (reserve)?
 *  İki işçi aynı işi almasın diye önce bir UPDATE ile kayıt
 *  kilitlenir (ayrildi_at damgalanır), sonra okunur. UPDATE tek bir
 *  atomik işlemdir; rowCount() 1 dönen işçi işi kapmıştır.
 *
 *  ÇÖKEN İŞÇİ NE OLUR? Ayrılmış ama uzun süredir bitmemiş işler
 *  (varsayılan 300 sn) yeniden serbest bırakılır. Bu yüzden işlerin
 *  tekrar çalışabilir olması şarttır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Queue;

use App\Core\Config;
use App\Core\Database;
use App\Core\Log\Logger;
use PDO;
use Throwable;

final class Queue
{
    public const BEKLIYOR   = 'bekliyor';
    public const CALISIYOR  = 'calisiyor';
    public const BASARISIZ  = 'basarisiz';

    private static ?PDO $db = null;

    private static function db(): PDO
    {
        return self::$db ??= Database::connection();
    }

    /** Testlerde bağlantıyı değiştirmek için. */
    public static function useConnection(?PDO $db): void
    {
        self::$db = $db;
    }

    private static function table(): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', (string) Config::get('queue.table', 'isler')) ?: 'isler';
    }

    /* =================================================================
     *  SIRAYA KOYMA
     * ============================================================== */

    public static function push(Job $job): int
    {
        return self::later($job, 0);
    }

    /** @param int $delay Kaç saniye sonra çalışsın? */
    public static function later(Job $job, int $delay): int
    {
        $statement = self::db()->prepare(
            'INSERT INTO `' . self::table() . '`
                (kuyruk, sinif, veri, durum, deneme, max_deneme, hazir_at)
             VALUES
                (:kuyruk, :sinif, :veri, :durum, 0, :max_deneme, :hazir_at)'
        );

        $statement->execute([
            ':kuyruk'     => $job->queue(),
            ':sinif'      => $job::class,
            ':veri'       => json_encode($job->toPayload(), JSON_UNESCAPED_UNICODE),
            ':durum'      => self::BEKLIYOR,
            ':max_deneme' => max(1, $job->tries()),
            ':hazir_at'   => date('Y-m-d H:i:s', time() + max(0, $delay)),
        ]);

        $id = (int) self::db()->lastInsertId();

        Logger::debug('İş kuyruğa alındı', ['is' => $id, 'sinif' => $job::class], 'queue');

        return $id;
    }

    /* =================================================================
     *  İŞLEME
     * ============================================================== */

    /**
     * Sıradaki işi alır ve çalıştırır.
     *
     * @return bool false → yapılacak iş yoktu
     */
    public static function runNext(string $queue = ''): bool
    {
        self::releaseStuck();

        $row = self::reserve($queue);

        if ($row === null) {
            return false;
        }

        $id = (int) $row['id'];

        try {
            $job = self::rebuild($row);

            $job->handle();

            self::db()->prepare('DELETE FROM `' . self::table() . '` WHERE id = :id')
                ->execute([':id' => $id]);

            Logger::info('İş tamamlandı', ['is' => $id, 'sinif' => $row['sinif']], 'queue');
        } catch (Throwable $e) {
            self::handleFailure($row, $e);
        }

        return true;
    }

    /**
     * Sıradaki işi ATOMİK olarak kendine ayırır.
     *
     * @return array<string,mixed>|null
     */
    private static function reserve(string $queue): ?array
    {
        $filter = $queue !== '' ? ' AND kuyruk = :kuyruk' : '';

        /* Önce sıradaki işin numarasını buluruz, sonra SADECE hâlâ
         * bekliyorsa kendimize ayırırız. İkinci sorgunun rowCount'u
         * 1 değilse başka bir işçi bizden önce davranmıştır. */
        $find = self::db()->prepare(
            'SELECT id FROM `' . self::table() . '`
              WHERE durum = :durum AND hazir_at <= NOW()' . $filter . '
              ORDER BY id ASC LIMIT 1'
        );

        $params = [':durum' => self::BEKLIYOR];

        if ($queue !== '') {
            $params[':kuyruk'] = $queue;
        }

        $find->execute($params);
        $id = $find->fetchColumn();

        if ($id === false) {
            return null;
        }

        $claim = self::db()->prepare(
            'UPDATE `' . self::table() . '`
                SET durum = :yeni, ayrildi_at = NOW(), deneme = deneme + 1
              WHERE id = :id AND durum = :eski'
        );
        $claim->execute([':yeni' => self::CALISIYOR, ':id' => $id, ':eski' => self::BEKLIYOR]);

        if ($claim->rowCount() !== 1) {
            return null; // başka işçi kaptı
        }

        $read = self::db()->prepare('SELECT * FROM `' . self::table() . '` WHERE id = :id');
        $read->execute([':id' => $id]);

        $row = $read->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $row */
    private static function rebuild(array $row): Job
    {
        $class = (string) $row['sinif'];

        if (!class_exists($class) || !is_subclass_of($class, Job::class)) {
            throw new QueueException('İş sınıfı bulunamadı ya da Job değil: ' . $class);
        }

        $payload = json_decode((string) $row['veri'], true);

        return $class::fromPayload(is_array($payload) ? $payload : []);
    }

    /** @param array<string,mixed> $row */
    private static function handleFailure(array $row, Throwable $e): void
    {
        $id        = (int) $row['id'];
        $deneme    = (int) $row['deneme'];
        $maxDeneme = (int) $row['max_deneme'];

        Logger::error('İş başarısız: ' . $e->getMessage(), [
            'is'     => $id,
            'sinif'  => $row['sinif'],
            'deneme' => $deneme . '/' . $maxDeneme,
        ], 'queue');

        if ($deneme >= $maxDeneme) {
            self::db()->prepare(
                'UPDATE `' . self::table() . '` SET durum = :durum, hata = :hata WHERE id = :id'
            )->execute([
                ':durum' => self::BASARISIZ,
                ':hata'  => mb_substr($e->getMessage(), 0, 500, 'UTF-8'),
                ':id'    => $id,
            ]);

            /* failed() geri çağırımı da patlayabilir; onun hatası
             * işçiyi durdurmamalı. */
            try {
                self::rebuild($row)->failed($e);
            } catch (Throwable $inner) {
                Logger::error('İşin failed() metodu da hata verdi: ' . $inner->getMessage(), ['is' => $id], 'queue');
            }

            return;
        }

        // Katlanarak artan bekleme: 60, 120, 240…
        $job     = null;
        $backoff = 60;

        try {
            $job     = self::rebuild($row);
            $backoff = max(1, $job->backoff());
        } catch (Throwable) {
        }

        $bekle = $backoff * (2 ** max(0, $deneme - 1));

        self::db()->prepare(
            'UPDATE `' . self::table() . '`
                SET durum = :durum, hata = :hata, hazir_at = :hazir, ayrildi_at = NULL
              WHERE id = :id'
        )->execute([
            ':durum' => self::BEKLIYOR,
            ':hata'  => mb_substr($e->getMessage(), 0, 500, 'UTF-8'),
            ':hazir' => date('Y-m-d H:i:s', time() + $bekle),
            ':id'    => $id,
        ]);
    }

    /**
     * Çöken bir işçinin elinde kalmış işleri serbest bırakır.
     * Bu olmadan tek bir çökme, o işi sonsuza dek kilitler.
     */
    public static function releaseStuck(): int
    {
        $timeout = max(30, (int) Config::get('queue.timeout', 300));

        $statement = self::db()->prepare(
            'UPDATE `' . self::table() . '`
                SET durum = :bekliyor, ayrildi_at = NULL
              WHERE durum = :calisiyor
                AND ayrildi_at IS NOT NULL
                AND ayrildi_at < (NOW() - INTERVAL :saniye SECOND)'
        );

        $statement->bindValue(':bekliyor', self::BEKLIYOR);
        $statement->bindValue(':calisiyor', self::CALISIYOR);
        $statement->bindValue(':saniye', $timeout, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }

    /* =================================================================
     *  DURUM
     * ============================================================== */

    /** @return array{bekleyen:int,calisan:int,basarisiz:int} */
    public static function stats(): array
    {
        $rows = self::db()->query(
            'SELECT durum, COUNT(*) adet FROM `' . self::table() . '` GROUP BY durum'
        )->fetchAll();

        $stats = ['bekleyen' => 0, 'calisan' => 0, 'basarisiz' => 0];

        foreach ($rows as $row) {
            $stats[match ($row['durum']) {
                self::CALISIYOR => 'calisan',
                self::BASARISIZ => 'basarisiz',
                default         => 'bekleyen',
            }] += (int) $row['adet'];
        }

        return $stats;
    }

    /**
     * Başarısız işlerin listesi (panelde göstermek için, en yeniden eskiye).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function failed(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $stmt = self::db()->query(
            'SELECT id, kuyruk, sinif, hata, deneme, max_deneme, hazir_at, created_at
               FROM `' . self::table() . '`
              WHERE durum = \'' . self::BASARISIZ . '\'
              ORDER BY id DESC
              LIMIT ' . $limit
        );

        return $stmt->fetchAll();
    }

    /** Başarısız işleri yeniden kuyruğa alır. */
    public static function retryFailed(): int
    {
        $statement = self::db()->prepare(
            'UPDATE `' . self::table() . '`
                SET durum = :bekliyor, deneme = 0, hata = NULL, hazir_at = NOW(), ayrildi_at = NULL
              WHERE durum = :basarisiz'
        );
        $statement->execute([':bekliyor' => self::BEKLIYOR, ':basarisiz' => self::BASARISIZ]);

        return $statement->rowCount();
    }

    public static function purgeFailed(): int
    {
        $statement = self::db()->prepare(
            'DELETE FROM `' . self::table() . '` WHERE durum = :durum'
        );
        $statement->execute([':durum' => self::BASARISIZ]);

        return $statement->rowCount();
    }
}
