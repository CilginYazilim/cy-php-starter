<?php
/**
 * =====================================================================
 *  Storage – Disklere erişimin ön kapısı
 * ---------------------------------------------------------------------
 *      Storage::disk('private')->put('fatura/2026-03.pdf', $icerik);
 *      Storage::disk('public')->url($yol);
 *      Storage::delete($yol);                 // varsayılan disk
 *
 *  Diskler config/storage.php içinde tanımlanır.
 *
 *  HANGİ DİSKİ SEÇMELİ?
 *    public  → herkesin görebileceği şeyler: avatar, logo, ürün görseli
 *    private → görmemesi gerekenler: fatura, sözleşme, dışa aktarım,
 *              kimlik fotokopisi, yedek
 *
 *  Şüphedeyseniz private seçin. Bir dosyayı sonradan public yapmak
 *  kolaydır; yanlışlıkla herkese açılmış bir belgeyi geri almak
 *  imkânsızdır — adres bir kez paylaşıldıysa iş işten geçmiştir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Storage;

use App\Core\Config;

final class Storage
{
    /** @var array<string,Disk> */
    private static array $disks = [];

    public static function disk(?string $name = null): Disk
    {
        $name ??= (string) Config::get('storage.default', 'public');

        if (isset(self::$disks[$name])) {
            return self::$disks[$name];
        }

        /** @var array<string,array{root:string,url?:string}> $configured */
        $configured = (array) Config::get('storage.disks', []);

        if (!array_key_exists($name, $configured)) {
            throw new StorageException('Tanımsız disk: ' . $name);
        }

        $settings = $configured[$name];

        return self::$disks[$name] = new Disk(
            name: $name,
            root: (string) ($settings['root'] ?? ''),
            url:  (string) ($settings['url'] ?? ''),
        );
    }

    /** Testlerde diski değiştirmek için. */
    public static function useDisk(string $name, ?Disk $disk): void
    {
        if ($disk === null) {
            unset(self::$disks[$name]);

            return;
        }

        self::$disks[$name] = $disk;
    }

    /* =================================================================
     *  VARSAYILAN DİSK KISAYOLLARI
     * ============================================================== */

    public static function exists(string $path): bool
    {
        return self::disk()->exists($path);
    }

    public static function get(string $path): string
    {
        return self::disk()->get($path);
    }

    public static function put(string $path, string $contents): string
    {
        return self::disk()->put($path, $contents);
    }

    public static function delete(string $path): bool
    {
        return self::disk()->delete($path);
    }

    public static function url(string $path): string
    {
        return self::disk()->url($path);
    }

    /* =================================================================
     *  YARDIMCILAR
     * ============================================================== */

    /**
     * Tahmin edilemez dosya adı üretir.
     *
     * Kullanıcının verdiği adı ASLA kullanmayız: "../../index.php",
     * "resim.php.jpg" ya da 300 karakterlik bir ad hepsi sorundur.
     * Görünen adı veritabanında saklayın, diskte rastgele ad kullanın.
     */
    public static function randomName(string $extension = ''): string
    {
        $name = bin2hex(random_bytes(16));

        $extension = strtolower(trim($extension, '. '));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

        return $extension === '' ? $name : $name . '.' . $extension;
    }

    /**
     * Kullanıcıya gösterilecek dosya adını temizler.
     *
     * Diske YAZILMAZ (onun için randomName kullanılır); yalnızca
     * veritabanında saklanacak ve indirme başlığında görünecek
     * "görünen ad" için.
     */
    public static function safeDisplayName(string $name): string
    {
        $name = str_replace(['\\', '/', "\0"], '', trim($name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name, '. ');

        if ($name === '') {
            return 'dosya';
        }

        return mb_substr($name, 0, 150, 'UTF-8');
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1073741824, 2) . ' GB';
    }
}
