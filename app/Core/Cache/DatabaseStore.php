<?php
/**
 * =====================================================================
 *  DatabaseStore – "onbellek" tablosuna yazan sürücü
 * ---------------------------------------------------------------------
 *  NE ZAMAN DOSYA YERİNE BUNU SEÇMELİ?
 *  Birden fazla web sunucusu aynı uygulamayı çalıştırıyorsa. Her
 *  sunucunun kendi diski olduğu için FileStore'da bir sunucunun
 *  yazdığını diğeri göremez; veritabanı ortaktır.
 *
 *  Tek sunucuda dosya sürücüsü DAHA HIZLIDIR — her okuma için
 *  veritabanına gitmek, kaçınmaya çalıştığınız maliyetin ta kendisidir.
 *
 *  Tablo migration ile gelir:
 *      database/migrations/*_onbellek_tablosu.php
 *
 *  Değer MEDIUMBLOB olarak saklanır: serialize() çıktısı ikili veri
 *  içerebilir ve TEXT sütunları karakter kümesi dönüşümüyle onu bozar.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Cache;

use PDO;
use Throwable;

final class DatabaseStore implements CacheStore
{
    public function __construct(
        private readonly PDO $db,
        private readonly string $table = 'onbellek',
    ) {
    }

    public function name(): string
    {
        return 'veritabani';
    }

    public function get(string $key): mixed
    {
        try {
            $statement = $this->db->prepare(
                'SELECT deger, son_gecerlilik FROM `' . $this->safeTable() . '`
                  WHERE anahtar = :anahtar LIMIT 1'
            );
            $statement->execute([':anahtar' => $this->normalize($key)]);

            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            $expires = (int) $row['son_gecerlilik'];

            if ($expires !== 0 && $expires < time()) {
                // Tembel temizlik: süresi geçeni okurken siliyoruz.
                $this->forget($key);

                return null;
            }

            return unserialize((string) $row['deger']);
        } catch (Throwable) {
            return null;
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function put(string $key, mixed $value, int $seconds = 0): bool
    {
        try {
            $statement = $this->db->prepare(
                'INSERT INTO `' . $this->safeTable() . '` (anahtar, deger, son_gecerlilik)
                 VALUES (:anahtar, :deger, :son)
                 ON DUPLICATE KEY UPDATE deger = VALUES(deger), son_gecerlilik = VALUES(son_gecerlilik)'
            );

            $statement->bindValue(':anahtar', $this->normalize($key));
            $statement->bindValue(':deger', serialize($value), PDO::PARAM_LOB);
            $statement->bindValue(':son', $seconds > 0 ? time() + $seconds : 0, PDO::PARAM_INT);

            return $statement->execute();
        } catch (Throwable) {
            return false;
        }
    }

    public function forget(string $key): bool
    {
        try {
            $statement = $this->db->prepare(
                'DELETE FROM `' . $this->safeTable() . '` WHERE anahtar = :anahtar'
            );
            $statement->execute([':anahtar' => $this->normalize($key)]);

            return $statement->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function flush(): bool
    {
        try {
            // TRUNCATE değil DELETE: TRUNCATE örtük commit yapar ve
            // devam eden bir işlemi (transaction) sessizce kapatır.
            $this->db->exec('DELETE FROM `' . $this->safeTable() . '`');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function purgeExpired(): int
    {
        try {
            $statement = $this->db->prepare(
                'DELETE FROM `' . $this->safeTable() . '`
                  WHERE son_gecerlilik <> 0 AND son_gecerlilik < :simdi'
            );
            $statement->execute([':simdi' => time()]);

            return $statement->rowCount();
        } catch (Throwable) {
            return 0;
        }
    }

    /* =================================================================
     *  İÇ İŞLEYİŞ
     * ============================================================== */

    /**
     * Anahtarı sütuna sığdırır.
     *
     * "anahtar" sütunu VARCHAR(191) ve BENZERSİZ. Uzun bir anahtar
     * sessizce kırpılsaydı iki farklı anahtar aynı kayda düşerdi —
     * bulunması çok zor bir hata. Bu yüzden uzun anahtarları
     * kırpmıyor, hash'liyoruz.
     */
    private function normalize(string $key): string
    {
        return strlen($key) <= 191 ? $key : 'sha1:' . sha1($key);
    }

    /** Tablo adı yapılandırmadan gelir; yine de savunma amaçlı süzülür. */
    private function safeTable(): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $this->table) ?: 'onbellek';
    }
}
