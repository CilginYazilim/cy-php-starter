<?php
/**
 * =====================================================================
 *  Middleware – Rota öncesi kontroller
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Middleware
{
    /** @param string $rule "auth" | "guest" | "role:admin" | "can:users.delete" | "csrf" | "installed" */
    public static function handle(string $rule, Request $request): void
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        match ($name) {
            'auth'      => self::auth($request),
            'guest'     => self::guest(),
            'role'      => self::role((string) $parameter, $request),
            'can'       => self::can((string) $parameter, $request),
            'csrf'      => self::csrf($request),
            'installed' => self::installed(),
            default     => null,
        };
    }

    private static function auth(Request $request): void
    {
        if (Auth::check()) {
            return;
        }

        if ($request->isAjax()) {
            Response::error('Oturumunuz sonlandı. Lütfen tekrar giriş yapın.', 401);
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

        if (Auth::user() !== null && in_array(Auth::user()->role, $allowed, true)) {
            return;
        }

        self::deny($request);
    }

    /** "can:users.delete" — birden fazlası "|" ile VEYA anlamına gelir. */
    private static function can(string $abilities, Request $request): void
    {
        foreach (explode('|', $abilities) as $ability) {
            if (Auth::can($ability)) {
                return;
            }
        }

        self::deny($request);
    }

    private static function csrf(Request $request): void
    {
        if (Csrf::check()) {
            return;
        }

        if ($request->isAjax()) {
            Response::error('Oturum doğrulaması başarısız. Lütfen sayfayı yenileyin.', 419);
        }

        Flash::error('Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
        Response::redirect(url(''));
    }

    /**
     * Kurulum tamamlanmadıysa siteyi kapatır, kurulum sihirbazına yönlendirir.
     * install/ klasörü kendi başına çalışır; bu kontrol SADECE ana
     * uygulamanın .env'siz açılmasını (500 hatası yerine) engeller.
     */
    private static function installed(): void
    {
        if (Env::exists(CY_BASE . '/.env')) {
            return;
        }

        Response::redirect('kurulum/');
    }

    private static function deny(Request $request): never
    {
        if ($request->isAjax()) {
            Response::error('Bu işlem için yetkiniz bulunmuyor.', 403);
        }

        http_response_code(403);
        View::render('errors/403', ['title' => 'Yetkisiz Erişim'], Auth::check() ? 'layouts/admin' : 'layouts/site');
        exit;
    }
}
