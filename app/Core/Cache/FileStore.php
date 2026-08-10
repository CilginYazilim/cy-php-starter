<?php
/**
 * =====================================================================
 *  FileStore – Diske yazan önbellek (varsayılan sürücü)
 * ---------------------------------------------------------------------
 *  Hiçbir kurulum gerektirmez: Redis yok, ek tablo yok. Paylaşımlı
 *  hostingde bile çalışır. Tek bir sunucu için fazlasıyla yeterlidir.
 *
 *  DOSYA BİÇİMİ
 *      İlk 10 karakter → son geçerlilik zamanı (unix, 0 = süresiz)
 *      Kalanı          → serialize() edilmiş değer
 *
 *  Sabit uzunluklu başlık sayesinde süresi geçmiş bir kaydı okumak
 *  için tüm dosyayı belleğe almak gerekmez; ilk 10 baytı okuyup
 *  vazgeçebiliriz.
 *
 *  KLASÖR DÜZENİ
 *  Anahtar sha1'lenir ve ilk dört karakteri iki alt klasöre dönüşür:
 *      ab/cd/abcd1234….cache
 *  Tek bir klasörde on binlerce dosya biriktiğinde dosya sistemleri
 *  yavaşlar; bu bölme onu önler.
 *
 *  GÜVENLİK NOTU: unserialize() yalnızca BİZİM yazdığımız dosyalarda
 *  çalışır. storage/ klasörü .htaccess ile web'e kapalıdır ve oraya
 *  yazma yetkisi olan bir saldırganın zaten çok daha büyük imkânları
 *  vardır. Yine de önbellek klasörünü asla paylaşılan/yazılabilir bir
 *  yere taşımayın.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Cache;

use Throwable;

final class FileStore implements CacheStore
{
    private const HEADER_LENGTH = 10;

    public function __construct(private readonly string $directory)
    {
    }

    public function name(): string
    {
        return 'dosya';
    }

    public function get(string $key): mixed
    {
        try {
            $file = $this->path($key);

            if (!is_file($file)) {
                return null;
            }

            $handle = @fopen($file, 'rb');

            if ($handle === false) {
                return null;
            }

            $expires = (int) fread($handle, self::HEADER_LENGTH);

            // TEMBEL TEMİZLİK: süresi geçmiş kaydı okurken siliyoruz.
            // Böylece purgeExpired() çalışmasa bile önbellek kendini
            // zamanla toparlar.
            if ($expires !== 0 && $expires < time()) {
                fclose($handle);
                @unlink($file);

                return null;
            }

            $payload = stream_get_contents($handle);
            fclose($handle);

            if ($payload === false || $payload === '') {
                return null;
            }

            return unserialize($payload);
        } catch (Throwable) {
            // Bozuk bir önbellek dosyası "yok" demektir.
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
            $file = $this->path($key);

            if (!$this->ensureDirectory(dirname($file))) {
                return false;
            }

            $expires = $seconds > 0 ? time() + $seconds : 0;
            $payload = str_pad((string) $expires, self::HEADER_LENGTH, '0', STR_PAD_LEFT)
                     . serialize($value);

            return @file_put_contents($file, $payload, LOCK_EX) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function forget(string $key): bool
    {
        $file = $this->path($key);

        return is_file($file) && @unlink($file);
    }

    public function flush(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        foreach ($this->allFiles() as $file) {
            @unlink($file);
        }

        // Boşalan alt klasörleri de topluyoruz; aksi halde binlerce
        // boş klasör kalır.
        foreach (glob($this->directory . '/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
            @rmdir($dir);
        }

        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            @rmdir($dir);
        }

        return true;
    }

    public function purgeExpired(): int
    {
        $now     = time();
        $deleted = 0;

        foreach ($this->allFiles() as $file) {
            $handle = @fopen($file, 'rb');

            if ($handle === false) {
                continue;
            }

            $expires = (int) fread($handle, self::HEADER_LENGTH);
            fclose($handle);

            if ($expires !== 0 && $expires < $now && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /* =================================================================
     *  İÇ İŞLEYİŞ
     * ============================================================== */

    /** @return array<int,string> */
    private function allFiles(): array
    {
        return glob($this->directory . '/*/*/*.cache') ?: [];
    }

    /**
     * Anahtardan dosya yolu üretir.
     *
     * Anahtar HASH'LENİR: içinde "/" ya da ".." olan bir anahtar
     * (ör. kullanıcıdan gelen bir değerden türetilmiş) dosya sistemine
     * dokunamaz. Bu, yol geçişine karşı tek ve yeterli savunmadır.
     */
    private function path(string $key): string
    {
        $hash = sha1($key);

        return $this->directory
             . DIRECTORY_SEPARATOR . substr($hash, 0, 2)
             . DIRECTORY_SEPARATOR . substr($hash, 2, 2)
             . DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    private function ensureDirectory(string $path): bool
    {
        return is_dir($path) || @mkdir($path, 0755, true) || is_dir($path);
    }
}
