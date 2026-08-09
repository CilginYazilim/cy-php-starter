<?php
/**
 * =====================================================================
 *  Setting – "ayarlar" tablosuna tipli erişim
 * ---------------------------------------------------------------------
 *  KULLANIMI:
 *      echo Setting::get('site_adi');
 *      if (Setting::bool('sistem_bakim_modu')) { ... }
 *
 *  PERFORMANS: Ayarlar sayfa başına SADECE BİR KEZ okunur ve bellekte
 *  tutulur. Setting::get() yüz kere çağrılsa da veritabanına bir kez
 *  gidilir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Setting
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /** Ayarları veritabanından okur ve önbelleğe alır. */
    public static function load(PDO $db): void
    {
        try {
            $rows = $db->query('SELECT anahtar, deger FROM ayarlar')->fetchAll();
        } catch (PDOException) {
            // Tablo henüz yoksa (kurulum yapılmamış) uygulamayı çökertme.
            self::$cache = [];
            return;
        }

        $values = [];

        foreach ($rows as $row) {
            $values[(string) $row['anahtar']] = (string) $row['deger'];
        }

        self::$cache = $values;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::$cache ?? [];
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();

        if (!array_key_exists($key, $all) || $all[$key] === '') {
            return $default;
        }

        return $all[$key];
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $all = self::all();

        if (!array_key_exists($key, $all) || $all[$key] === '') {
            return $default;
        }

        return $all[$key] === '1';
    }

    /** Tek bir ayarı yazar (yoksa oluşturur). */
    public static function set(PDO $db, string $key, ?string $value): void
    {
        $stmt = $db->prepare(
            'INSERT INTO ayarlar (anahtar, deger, etiket) VALUES (:anahtar, :deger, :etiket)
             ON DUPLICATE KEY UPDATE deger = VALUES(deger)'
        );
        $stmt->execute([':anahtar' => $key, ':deger' => $value, ':etiket' => $key]);

        self::flush();
        self::load($db);
    }

    /**
     * Birden fazla ayarı tek işlemde (transaction) yazar.
     * duzenlenebilir = 0 olan ayarlar bilerek atlanır.
     *
     * @param array<string,?string> $values
     */
    public static function saveMany(PDO $db, array $values): void
    {
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'UPDATE ayarlar SET deger = :deger WHERE anahtar = :anahtar AND duzenlenebilir = 1'
            );

            foreach ($values as $key => $value) {
                $stmt->execute([':deger' => $value, ':anahtar' => $key]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        self::flush();
        self::load($db);
    }

    /**
     * Ayarları yönetim panelinde göstermek için gruplara ayırarak,
     * tüm sütunlarıyla döndürür. Ayarlar formu bu veriden OTOMATİK üretilir.
     *
     * @return array<string,array<int,array<string,mixed>>> grup => satırlar
     */
    public static function grouped(PDO $db): array
    {
        $rows = $db->query('SELECT * FROM ayarlar ORDER BY grup ASC, sira ASC, id ASC')->fetchAll();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(string) $row['grup']][] = $row;
        }

        return $grouped;
    }

    /** @return array<string,string> */
    public static function groupLabels(): array
    {
        return [
            'genel'    => 'Genel',
            'iletisim' => 'İletişim',
            'sosyal'   => 'Sosyal Medya',
            'seo'      => 'SEO',
            'sistem'   => 'Sistem',
        ];
    }

    /** Site logosunun tarayıcı adresi; tanımlı değilse varsayılan logo. */
    public static function logoUrl(): string
    {
        $logo = self::get('site_logo');

        if ($logo !== '' && is_file((string) Config::get('upload.dir') . basename($logo))) {
            return (string) Config::get('upload.url') . rawurlencode($logo);
        }

        return 'assets/images/logo.png';
    }
}
