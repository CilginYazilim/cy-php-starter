<?php
/**
 * =====================================================================
 *  Middleware – Rota öncesi kontroller
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

final class Middleware
{
    /** @param string $rule "auth" | "guest" | "role:admin" | "can:users.delete" | "csrf" | "installed" | "bakim" */
    public static function handle(string $rule, Request $request): void
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        // API kuralları ayrı bir kapıcıya devredilir (Bearer anahtarı,
        // hız sınırı, JSON hata biçimi). Bkz. App\Core\Api\ApiGuard.
        if (str_starts_with($rule, 'api')) {
            \App\Core\Api\ApiGuard::handle($rule, $request);

            return;
        }

        match ($name) {
            'auth'      => self::auth($request),
            'guest'     => self::guest(),
            'role'      => self::role((string) $parameter, $request),
            'can'       => self::can((string) $parameter, $request),
            'csrf'      => self::csrf($request),
            'installed' => self::installed(),
            'bakim'     => self::maintenance(),
            default     => null,
        };
    }

    private static function auth(Request $request): void
    {
        if (Auth::check()) {
            return;
        }

        // AJAX isteği yönlendirilemez: tarayıcı giriş sayfasının HTML'ini
        // JSON sanıp çöker. 401 döner, JavaScript sayfayı yeniler.
        if ($request->isAjax()) {
            throw HttpException::unauthorized();
        }

        Session::set('_intended', $request->raw('r', ''));
        Response::redirect(url('giris'));
    }

    private static function guest(): void
    {
        if (Auth::check()) {
            Response::redirect(url('panel'));
        }
    }

    /** "role:admin" veya "role:admin|editor" */
    private static function role(string $roles, Request $request): void
    {
        $allowed = explode('|', $roles);

        if (Auth::user() !== null && in_array(Auth::user()->rol, $allowed, true)) {
            return;
        }

        self::deny($request, 'rol:' . $roles);
    }

    /** "can:users.delete" — birden fazlası "|" ile VEYA anlamına gelir. */
    private static function can(string $abilities, Request $request): void
    {
        foreach (explode('|', $abilities) as $ability) {
            if (Auth::can($ability)) {
                return;
            }
        }

        self::deny($request, $abilities);
    }

    private static function csrf(Request $request): void
    {
        if (Csrf::check()) {
            return;
        }

        // Bağlamı istisnaya koyuyoruz; kaydı ErrorHandler tek satırda
        // "security" kanalına yazar (bkz. ErrorHandler::log).
        throw HttpException::pageExpired([
            'yontem'  => $request->method(),
            'referer' => mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 200),
        ]);
    }

    /**
     * Kurulum tamamlanmadıysa siteyi kapatır, kurulum sihirbazına yönlendirir.
     * kurulum/ klasörü kendi başına çalışır; bu kontrol SADECE ana
     * uygulamanın .env'siz açılmasını (500 hatası yerine) engeller.
     */
    private static function installed(): void
    {
        if (Env::exists(CY_BASE . '/.env')) {
            return;
        }

        Response::redirect(Url::base() . '/kurulum/');
    }

    /**
     * Bakım modu açıkken herkese açık YAZMA uçlarını kapatır.
     *
     * Bakım modu uzun süre yalnızca bir GÖRSEL ÖRTÜYDÜ: layouts/site
     * içeriği gizliyordu ama form gönderimleri JSON uçlarına gidip
     * layout'a hiç uğramadığı için iletişim mesajı kaydedilmeye,
     * yeni üyeler kaydolmaya devam ediyordu. Kapıyı rotanın önüne
     * koyuyoruz.
     *
     * Panele erişebilenler (dashboard.view) muaftır: bakım
     * sırasında sistemi düzeltebilmeleri gerekir.
     */
    private static function maintenance(): void
    {
        if (!Setting::bool('sistem_bakim_modu', false) || Auth::can('dashboard.view')) {
            return;
        }

        throw HttpException::maintenance();
    }

    /**
     * Yetkisiz erişim. Yanıtın nasıl görüneceğine (JSON mu, hata
     * sayfası mı) burada değil ErrorHandler karar verir; biz yalnızca
     * "bu istek 403 ile bitmeli" deriz.
     */
    private static function deny(Request $request, string $ability = ''): never
    {
        throw HttpException::forbidden(
            ability: $ability,
            context: ['kullanici' => Auth::id() ?? 'konuk']
        );
    }
}
