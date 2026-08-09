<?php
/**
 * =====================================================================
 *  Response – JSON / yönlendirme / güvenlik başlıkları
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        header_remove('X-Powered-By');

        // HTML sayfaları önbelleğe alınmasın (çıkıştan sonra "geri" ile
        // özel içerik görünmesin; ayrıca geliştirmede eski sayfa takılı kalmaz).
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        if (Config::get('security.csp_enabled', true)) {
            header(
                "Content-Security-Policy: "
                . "default-src 'self'; "
                . "script-src 'self'; "
                . "style-src 'self' 'unsafe-inline'; "
                . "img-src 'self' data:; "
                . "font-src 'self' data:; "
                . "connect-src 'self'; "
                . "form-action 'self'; "
                . "frame-ancestors 'none'; "
                . "base-uri 'self'; "
                . "object-src 'none'"
            );
        }
    }

    /** @param array<string,mixed> $payload */
    public static function json(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @param array<string,mixed> $extra */
    public static function success(string $description, array $extra = []): never
    {
        self::json(array_merge([
            'success'     => true,
            'type'        => 'success',
            'description' => $description,
        ], $extra));
    }

    /** @param array<string,mixed> $extra */
    public static function error(string $description, int $status = 400, array $extra = []): never
    {
        self::json(array_merge([
            'success'     => false,
            'type'        => 'danger',
            'description' => $description,
        ], $extra), $status);
    }

    public static function redirect(string $url, int $status = 302): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Location: ' . $url);
        }

        exit;
    }
}
