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
 *  HIZ SINIRI VERİTABANINDA TUTULMAZ. Veritabanına yazmak, korumaya
 *  çalıştığınız yükün ta kendisini üretirdi: her istek bir INSERT
 *  demektir ve saldırı anında bu, veritabanını sizin yerinize
 *  çökertir. Sayaç Throttle'ın kilitli dosyasındadır (bkz.
 *  app/Core/Throttle.php); önbellek sürücüsünden bağımsızdır.
 *
 *  BAKIM MODUNDA API de 503 döner; bakımı aşma yetkisi olan
 *  kullanıcının anahtarı çalışmaya devam eder.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Api;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Log\Logger;
use App\Core\Request;
use App\Core\Setting;
use App\Core\Throttle;

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
            // Bilinmeyen ad sessizce geçmez (bkz. Middleware::handle).
            default     => throw new \LogicException('Bilinmeyen API ara katmanı: "' . $rule . '"'),
        };

        // Kimlik belli olduktan SONRA: bakımı aşma yetkisi kullanıcıya bağlı.
        if ($name !== 'api.can') {
            self::maintenance();
        }
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
             *
             * ÇEREZLE GELEN İSTEK CSRF'E AÇIKTIR: tarayıcı çerezi başka
             * bir sitenin formuyla da gönderir. Bu yüzden veri
             * değiştiren yöntemlerde (POST/PUT/PATCH/DELETE) CSRF
             * jetonu ZORUNLUDUR. Eskiden bu açıklama "CSRF devrede"
             * diyordu ama hiçbir yerde denetlenmiyordu. */
            if (Auth::check()) {
                if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && !\App\Core\Csrf::check()) {
                    ApiResponse::error('Güvenlik doğrulaması başarısız (CSRF).', 419, 'csrf');
                }

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
                'yol' => \App\Core\Url::current(),
            ]);

            // Hız sınırını GEÇERSİZ anahtarlara da uygularız; aksi
            // halde anahtar deneme saldırısı bedava olurdu.
            self::throttle('ip:' . $request->ip());

            ApiResponse::unauthorized('Erişim anahtarı geçersiz ya da süresi dolmuş.');
        }

        /* DURUMSUZ: kullanıcı yalnızca BU İSTEK için tanınır.
         * Eskiden Auth::login() çağrılıyordu; anahtarla gelen istek
         * oturum açıp bir oturum çerezi döndürüyor, anahtar iptal
         * edildikten sonra bile istemci o çerezle girmeye devam
         * edebiliyordu. */
        Auth::actingAs($user);
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

        /* Sayaç Throttle'da, kilitli bir dosyada tutulur: kontrol ve
         * artırma tek parçadır. Eskiden önbellekte oku-yaz yapılıyordu;
         * aynı anda gelen istekler sınırı aşabiliyor, CACHE_DRIVER=kapali
         * iken sınır hiç uygulanmıyordu. */
        $retryAfter = Throttle::attempt('api:' . $key, $limit, $window);

        if ($retryAfter > 0) {
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

        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $limit);
            header('X-RateLimit-Remaining: ' . Throttle::remaining('api:' . $key, $limit, $window));
        }
    }

    /**
     * BAKIM MODU API'Yİ DE KAPATIR.
     *
     * Eskiden bakım modu yalnızca site sayfalarına örtü çekiyordu;
     * /api/v1/... uçları bakımda da veri vermeye devam ediyordu.
     * Bakımı aşma yetkisi olan kullanıcı (anahtarı ya da oturumuyla)
     * yine erişebilir.
     */
    private static function maintenance(): void
    {
        if (!Setting::bool('sistem_bakim_modu', false) || Auth::can('maintenance.bypass')) {
            return;
        }

        if (!headers_sent()) {
            header('Retry-After: 600');
        }

        ApiResponse::error('Site bakımda. Lütfen daha sonra tekrar deneyin.', 503, 'bakim');
    }

    public static function userId(): ?int
    {
        return self::$userId;
    }
}
