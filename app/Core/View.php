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

    /**
     * Görünüm dosyasının tam yolunu bulur.
     *
     * İKİ BİÇİM VARDIR:
     *      'panel/ayarlar'   → views/panel/ayarlar.php
     *      'Ornek::liste'    → modules/Ornek/views/liste.php
     *
     * Modül öneki sayesinde bir modül, çekirdeğin views/ klasörünü
     * kirletmeden kendi ekranlarını taşıyabilir.
     */
    public static function resolve(string $view): string
    {
        // ".." ve ters bölü her iki biçimde de temizlenir.
        $clean = str_replace(['..', '\\'], '', $view);

        if (str_contains($clean, '::')) {
            [$module, $path] = explode('::', $clean, 2);

            $module = preg_replace('/[^A-Za-z0-9_]/', '', $module) ?? '';
            $path   = trim($path, '/');

            return CY_BASE . '/modules/' . $module . '/views/' . $path . '.php';
        }

        return CY_BASE . '/views/' . $clean . '.php';
    }

    /** @param array<string,mixed> $data */
    public static function capture(string $view, array $data = []): string
    {
        $file = self::resolve($view);

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
