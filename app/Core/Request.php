<?php
/**
 * =====================================================================
 *  Request – Gelen HTTP isteğini temsil eder
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $body;

    /** @var array<string,mixed> */
    private array $files;

    public function __construct()
    {
        $this->query = $_GET;
        $this->body  = $_POST;
        $this->files = $_FILES;
    }

    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function int(string $key, ?int $min = null, ?int $max = null): ?int
    {
        $raw = $this->body[$key] ?? $this->query[$key] ?? null;

        if ($raw === null || is_array($raw)) {
            return null;
        }

        $options = [];

        if ($min !== null) {
            $options['min_range'] = $min;
        }
        if ($max !== null) {
            $options['max_range'] = $max;
        }

        $value = filter_var((string) $raw, FILTER_VALIDATE_INT, ['options' => $options]);

        return $value === false ? null : $value;
    }

    public function bool(string $key): bool
    {
        $raw = strtolower($this->input($key));

        return in_array($raw, ['1', 'on', 'true', 'yes'], true);
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function hasFile(string $key): bool
    {
        $file = $this->file($key);

        return $file !== null
            && isset($file['error'])
            && !is_array($file['error'])
            && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
