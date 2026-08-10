<?php
/**
 * =====================================================================
 *  ApiGuard – API isteklerinin kapıcısı
 * ---------------------------------------------------------------------
 *  Rota tablosunda ara katman olarak kullanılır:
 *
 *      $router->get('api/v1/urunler', UrunApi::class, 'index',
 *          ['installed', 'api', 'api.can:urun.view']);
 *
 *  ÜÇ İŞ YAPAR
 *    1. Bearer anahtarını doğrular, kullanıcıyı oturuma bağlar
 *    2. Hız sınırını uygular (anahtar ya da IP başına)
 *    3. Yetki denetler
 *
 *  HIZ SINIRI ÖNBELLEKTE TUTULUR. Veritabanına yazmak, korumaya
 *  çalıştığınız yükün ta kendisini üretirdi: her istek bir INSERT
 *  demektir ve saldırı anında bu, veritabanını sizin yerinize
 *  çökertir. Önbellek sayacı çok daha ucuzdur.
 *
 *  ÖNBELLEK "kapali" SÜRÜCÜSÜNDEYSE hız sınırı UYGULANAMAZ; bu
 *  durumda sessizce geçmek yerine bunu log'a yazıyoruz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Api;

use App\Core\Auth;
use App\Core\Cache\Cache;
use App\Core\Config;
use App\Core\Log\Logger;
use App\Core\Request;

final class ApiGuard
{
    /** Doğrulanmış istekte anahtarın sahibi. */
    private static ?int $userId = null;

    /**
     * Ara katman girişi.
     *
     * @param string $rule "api" | "api.can:urun.view" | "api.guest"
     */
    public static function handle(string $rule, Request $request): void
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        match ($name) {
            'api'       => self::authenticate($request),
            'api.guest' => self::throttleOnly($request),
            'api.can'   => self::authorize((string) $parameter),
            default     => null,
        };
    }

    /* =================================================================
     *  KİMLİK
     * ============================================================== */

    private static function authenticate(Request $request): void
    {
        $token = ApiToken::fromRequest();

        if ($token === '') {
            /* Tarayıcıdan gelen ve zaten oturumu olan bir istek de
             * API'yi kullanabilsin (panelin kendi JavaScript'i gibi).
             * Bu durumda CSRF koruması devrededir. */
            if (Auth::check()) {
                self::$userId = Auth::id();
                self::throttle('oturum:' . self::$userId);

                return;
            }

            ApiResponse::unauthorized('Authorization: Bearer <anahtar> başlığı gerekli.');
        }

        $user = ApiToken::resolve($token);

        if ($user === null) {
            Logger::security('Geçersiz API anahtarı ile istek', [
                'ip'  => $request->ip(),
                'yol' => $request->raw('r', ''),
            ]);

            // Hız sınırını GEÇERSİZ anahtarlara da uygularız; aksi
            // halde anahtar deneme saldırısı bedava olurdu.
            self::throttle('ip:' . $request->ip());

            ApiResponse::unauthorized('Erişim anahtarı geçersiz ya da süresi dolmuş.');
        }

        Auth::login($user);
        self::$userId = $user->id;

        self::throttle('anahtar:' . substr(hash('sha256', $token), 0, 16));
    }

    private static function throttleOnly(Request $request): void
    {
        self::throttle('ip:' . $request->ip());
    }

    private static function authorize(string $abilities): void
    {
        foreach (explode('|', $abilities) as $ability) {
            if (Auth::can($ability)) {
                return;
            }
        }

        ApiResponse::forbidden();
    }

    /* =================================================================
     *  HIZ SINIRI
     * ============================================================== */

    private static function throttle(string $key): void
    {
        $limit  = max(1, (int) Config::get('api.rate_limit', 120));
        $window = max(1, (int) Config::get('api.rate_window', 60));

        $store = Cache::store();

        if ($store->name() === 'kapali') {
            // Sessizce geçmiyoruz: koruma yok demek, bilinmesi gereken
            // bir durumdur.
            Logger::warning('API hız sınırı uygulanamıyor: önbellek kapalı.', [], 'security');

            return;
        }

        /* Pencere anahtarı zamanın kendisini içerir; böylece süre
         * dolduğunda sayaç kendiliğinden sıfırlanır ve ayrıca
         * temizlik yapmak gerekmez. */
        $bucket  = 'api:hiz:' . $key . ':' . (int) floor(time() / $window);
        $current = (int) Cache::get($bucket, 0);

        if ($current >= $limit) {
            $retryAfter = $window - (time() % $window);

            if (!headers_sent()) {
                header('Retry-After: ' . $retryAfter);
                header('X-RateLimit-Limit: ' . $limit);
                header('X-RateLimit-Remaining: 0');
            }

            Logger::security('API hız sınırı aşıldı', ['anahtar' => $key, 'limit' => $limit]);

            ApiResponse::error(
                'Çok fazla istek gönderdiniz. ' . $retryAfter . ' saniye sonra tekrar deneyin.',
                429,
                'cok_fazla_istek'
            );
        }

        Cache::put($bucket, $current + 1, $window + 5);

        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $limit);
            header('X-RateLimit-Remaining: ' . max(0, $limit - $current - 1));
        }
    }

    public static function userId(): ?int
    {
        return self::$userId;
    }
}
