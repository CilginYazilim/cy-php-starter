<?php
/**
 * =====================================================================
 *  Throttle – Basit hız sınırı (sabit pencere sayacı)
 * ---------------------------------------------------------------------
 *      $bekle = Throttle::attempt('kayit:' . $ip, 5, 3600);
 *      if ($bekle > 0) { ... "N saniye sonra tekrar deneyin" ... }
 *
 *  Kayıt formu ve API gibi herkese açık uçlar içindir. Giriş
 *  denemeleri bunun yerine RateLimiter'ı (veritabanı) kullanır.
 *
 *  SAYAÇ NEDEN ÖNBELLEKTE DEĞİL? Eskiden Cache::get + Cache::put ile
 *  tutuluyordu. İkisi arasında başka bir istek araya girebildiği için
 *  aynı anda gönderilen 20 istek sayaca 1 ekleyip hepsi geçiyordu;
 *  "saatte 5 kayıt" sınırı paralel isteklerle aşılabiliyordu.
 *  CACHE_DRIVER=kapali iken de sınır hiç uygulanmıyordu.
 *
 *  Sayaç artık storage/cache/hiz altında, KİLİTLİ (flock) bir dosyada
 *  tutulur: oku-artır-yaz tek parça olur. Önbellek sürücüsünden
 *  bağımsızdır.
 *
 *  ÇOK SUNUCULU KURULUM: Dosya her sunucunun kendi diskindedir; sınır
 *  sunucu başına uygulanır (N sunucuda en fazla N katı).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Log\Logger;

final class Throttle
{
    private static ?string $directory = null;

    /**
     * Sınır aşılmadıysa sayacı ARTIRIR ve 0 döner; aşıldıysa sayaca
     * dokunmadan kalan bekleme süresini (saniye) döner.
     *
     * Kontrol ve artırma TEK kilit altında yapılır.
     */
    public static function attempt(string $key, int $max, int $window): int
    {
        if ($max <= 0 || $window <= 0) {
            return 0;
        }

        return self::locked($key, $window, static function (array $state) use ($max, $window): array {
            if ($state['adet'] >= $max) {
                return [$state, max(1, $state['baslangic'] + $window - time())];
            }

            $state['adet']++;

            return [$state, 0];
        });
    }

    /**
     * Sınır aşıldı mı? Sayaç ARTIRILMAZ.
     *
     * @return int Kalan bekleme süresi (saniye); 0 → izin var
     */
    public static function tooMany(string $key, int $max, int $window): int
    {
        if ($max <= 0 || $window <= 0) {
            return 0;
        }

        return self::locked($key, $window, static function (array $state) use ($max, $window): array {
            return [$state, $state['adet'] >= $max ? max(1, $state['baslangic'] + $window - time()) : 0];
        });
    }

    /** Sayaca bir ekler (sınıra bakmadan). */
    public static function hit(string $key, int $window): void
    {
        if ($window <= 0) {
            return;
        }

        self::locked($key, $window, static function (array $state): array {
            $state['adet']++;

            return [$state, 0];
        });
    }

    /** Pencerede kalan hak (sayaç artırılmaz). */
    public static function remaining(string $key, int $max, int $window): int
    {
        if ($max <= 0 || $window <= 0) {
            return PHP_INT_MAX;
        }

        $used = 0;

        self::locked($key, $window, static function (array $state) use (&$used): array {
            $used = $state['adet'];

            return [$state, 0];
        });

        return max(0, $max - $used);
    }

    /** Testler için klasörü değiştirmek. */
    public static function useDirectory(?string $directory): void
    {
        self::$directory = $directory;
    }

    /**
     * Sayaç dosyasını kilitler, durumu verir, dönen durumu yazar.
     *
     * @param callable(array{adet:int,baslangic:int}):array{0:array{adet:int,baslangic:int},1:int} $change
     */
    private static function locked(string $key, int $window, callable $change): int
    {
        $start = (int) (floor(time() / $window) * $window);
        $file  = self::file($key);

        if ($file === null) {
            return 0;
        }

        $handle = @fopen($file, 'c+');

        if ($handle === false) {
            Logger::warning('Hız sınırı dosyası açılamadı; sınır uygulanamadı', ['dosya' => basename($file)], 'security');

            return 0;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return 0;
            }

            $raw   = stream_get_contents($handle);
            $data  = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            $state = ['adet' => 0, 'baslangic' => $start];

            // Önceki pencereye ait sayaç yeni pencerede sıfırdan başlar.
            if (is_array($data) && (int) ($data['baslangic'] ?? 0) === $start) {
                $state['adet'] = max(0, (int) ($data['adet'] ?? 0));
            }

            [$new, $result] = $change($state);

            if ($new !== $state || !is_array($data) || (int) ($data['baslangic'] ?? 0) !== $start) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, (string) json_encode($new));
                fflush($handle);
            }

            if (random_int(1, 200) === 1) {
                self::prune();
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function file(string $key): ?string
    {
        $directory = self::directory();

        if ($directory === null) {
            return null;
        }

        return $directory . '/' . hash('sha256', $key) . '.json';
    }

    private static function directory(): ?string
    {
        $directory = self::$directory
            ?? (defined('CY_BASE') ? CY_BASE : dirname(__DIR__, 2)) . '/storage/cache/hiz';

        if (!is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            Logger::warning('Hız sınırı uygulanamıyor: klasör yazılamıyor', ['klasor' => $directory], 'security');

            return null;
        }

        return $directory;
    }

    /** Bir günden eski sayaç dosyalarını siler. */
    private static function prune(): void
    {
        $directory = self::directory();

        if ($directory === null) {
            return;
        }

        foreach (glob($directory . '/*.json') ?: [] as $file) {
            if (@filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
    }
}
