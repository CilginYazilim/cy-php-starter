<?php
/**
 * =====================================================================
 *  Throttle – Basit hız sınırı (sabit pencere sayacı)
 * ---------------------------------------------------------------------
 *      if (Throttle::tooMany('kayit:' . $ip, 5, 3600) > 0) { ... }
 *      Throttle::hit('kayit:' . $ip, 3600);
 *
 *  Kayıt formu gibi herkese açık uçlar içindir. Giriş denemeleri
 *  bunun yerine RateLimiter'ı (veritabanı) kullanır: orada sayacın
 *  çerez silinerek ya da önbellek boşaltılarak sıfırlanamaması
 *  kritiktir.
 *
 *  SAYAÇ ÖNBELLEKTE TUTULUR. Her istekte veritabanına INSERT yapmak,
 *  korunmaya çalışılan yükün kendisini üretirdi. Önbellek "kapali"
 *  sürücüsündeyse sınır UYGULANAMAZ; bu durum sessizce geçilmez,
 *  güvenlik kanalına yazılır (bkz. ApiGuard'daki aynı gerekçe).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Cache\Cache;
use App\Core\Log\Logger;

final class Throttle
{
    /**
     * Sınır aşıldı mı? Sayaç ARTIRILMAZ.
     *
     * @return int Kalan bekleme süresi (saniye); 0 → izin var
     */
    public static function tooMany(string $key, int $max, int $window): int
    {
        if ($max <= 0 || $window <= 0 || !self::available()) {
            return 0;
        }

        [$bucket, $start] = self::bucket($key, $window);

        if ((int) Cache::get($bucket, 0) < $max) {
            return 0;
        }

        return max(1, $start + $window - time());
    }

    /** Sayaca bir ekler. */
    public static function hit(string $key, int $window): void
    {
        if ($window <= 0 || !self::available()) {
            return;
        }

        [$bucket] = self::bucket($key, $window);

        Cache::put($bucket, (int) Cache::get($bucket, 0) + 1, $window + 5);
    }

    /** @return array{0:string,1:int} [önbellek anahtarı, pencerenin başlangıcı] */
    private static function bucket(string $key, int $window): array
    {
        $start = (int) (floor(time() / $window) * $window);

        return ['hiz:' . hash('sha256', $key) . ':' . $start, $start];
    }

    private static function available(): bool
    {
        if (Cache::store()->name() !== 'kapali') {
            return true;
        }

        Logger::warning('Hız sınırı uygulanamıyor: önbellek kapalı (CACHE_DRIVER=kapali).', [], 'security');

        return false;
    }
}
