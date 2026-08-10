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

    /**
     * Tek bir ayarı yazar (yoksa oluşturur).
     *
     * @param string $group     Yeni oluşturulacaksa hangi gruba girsin?
     *                          "dahili" grubu ayarlar ekranında GÖRÜNMEZ.
     * @param bool   $editable  false → ayarlar formu bu satıra dokunamaz
     *
     * DİKKAT: $editable=false verilen ayarlar Setting::saveMany() ile
     * yazılamaz (o metot yalnızca duzenlenebilir=1 satırları günceller)
     * ama bu metotla yazılabilir. Sistemin kendi tuttuğu değerler
     * (açık modül listesi gibi) tam olarak böyle olmalıdır: kod
     * değiştirebilsin, form ekranı yanlışlıkla SİLEMESİN.
     */
    public static function set(
        PDO $db,
        string $key,
        ?string $value,
        string $group = 'genel',
        bool $editable = true,
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO ayarlar (anahtar, deger, grup, etiket, duzenlenebilir)
             VALUES (:anahtar, :deger, :grup, :etiket, :duzenlenebilir)
             ON DUPLICATE KEY UPDATE deger = VALUES(deger)'
        );

        $stmt->execute([
            ':anahtar'        => $key,
            ':deger'          => $value,
            ':grup'           => $group,
            ':etiket'         => $key,
            ':duzenlenebilir' => $editable ? 1 : 0,
        ]);

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
    /**
     * Ayarları gruplayarak döndürür (ayarlar ekranı bundan üretilir).
     *
     * YALNIZCA ETİKETİ OLAN GRUPLAR döner. Sistemin kendi tuttuğu
     * değerler ("aktif_moduller" gibi) "dahili" grubundadır ve
     * groupLabels() içinde yer almadığı için ekranda hiç görünmez.
     *
     * Bu filtre bir hatadan doğdu: açık modül listesi "genel" grubunda
     * düzenlenebilir bir metin alanı olarak görünüyordu ve Genel
     * ayarları kaydetmek listeyi siliyordu.
     *
     * @return array<string,array<int,array<string,mixed>>> grup => satırlar
     */
    public static function grouped(PDO $db): array
    {
        $rows   = $db->query('SELECT * FROM ayarlar ORDER BY grup ASC, sira ASC, id ASC')->fetchAll();
        $labels = self::groupLabels();

        $grouped = [];

        foreach ($rows as $row) {
            $grup = (string) $row['grup'];

            if (!array_key_exists($grup, $labels)) {
                continue;
            }

            $grouped[$grup][] = $row;
        }

        return $grouped;
    }

    /** @return array<string,string> */
    public static function groupLabels(): array
    {
        return [
            'genel'    => 'Genel',
            'iletisim' => 'İletişim',
            'eposta'   => 'E-posta',
            'sosyal'   => 'Sosyal Medya',
            'seo'      => 'SEO',
            'sistem'   => 'Sistem',
        ];
    }

    /** Site logosunun tarayıcı adresi; tanımlı değilse varsayılan logo. */
    public static function logoUrl(): string
    {
        $url = Uploader::url(self::get('site_logo'));

        return $url !== '' ? $url : Url::asset('images/logo.png');
    }
}
