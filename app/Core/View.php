<?php
/**
 * =====================================================================
 *  View – Basit şablon motoru
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    /** @param array<string,mixed> $data */
    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        $content = self::capture($view, $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        echo self::capture($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string,mixed> $data */
    public static function capture(string $view, array $data = []): string
    {
        $file = CY_BASE . '/views/' . str_replace(['..', '\\'], '', $view) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('Görünüm bulunamadı: ' . $view);
        }

        extract(array_merge(self::$shared, $data), EXTR_SKIP);

        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $data */
    public static function partial(string $view, array $data = []): void
    {
        echo self::capture($view, $data);
    }
}
