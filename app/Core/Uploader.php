<?php
/**
 * =====================================================================
 *  Uploader – Güvenli görsel yükleme (avatar / logo)
 * ---------------------------------------------------------------------
 *  1. İçerik doğrulanır (getimagesize)  2. Ad/uzantı sunucu belirler
 *  3. Rastgele adla kaydedilir          4. upload/.htaccess PHP kapatır
 *  5. Görsel yeniden üretilerek gömülü veri (EXIF, kod) atılır
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Uploader
{
    public static function image(array $file): string
    {
        $mime = self::validate($file);

        $dir       = (string) Config::get('upload.dir');
        $extension = (string) Config::get('upload.allowed_types')[$mime];

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
        }

        do {
            $name = bin2hex(random_bytes(16)) . '.' . $extension;
        } while (file_exists($dir . $name));

        $target = $dir . $name;

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new RuntimeException('Görsel kaydedilemedi.');
        }

        @chmod($target, 0644);
        self::sanitize($target, $mime);

        return $name;
    }

    public static function validate(array $file): string
    {
        if (!isset($file['error'], $file['tmp_name'], $file['size']) || is_array($file['error'])) {
            throw new RuntimeException('Geçersiz dosya yükleme isteği.');
        }

        $maxBytes = (int) Config::get('upload.max_bytes');
        $maxMb    = (int) ($maxBytes / 1024 / 1024);

        switch ((int) $file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('Dosya boyutu sunucu limitini aşıyor.');
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('Dosya seçilmedi.');
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                throw new RuntimeException('Sunucu dosyayı geçici olarak kaydedemedi.');
            default:
                throw new RuntimeException('Dosya yüklenirken bir hata oluştu.');
        }

        if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
            throw new RuntimeException('Görsel boyutu en fazla ' . $maxMb . ' MB olabilir.');
        }

        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Geçersiz dosya kaynağı.');
        }

        $info = @getimagesize((string) $file['tmp_name']);

        if ($info === false) {
            throw new RuntimeException('Yüklenen dosya geçerli bir görsel değil.');
        }

        if ((int) $info[0] < 1 || (int) $info[1] < 1 || (int) $info[0] > 10000 || (int) $info[1] > 10000) {
            throw new RuntimeException('Görsel boyutları desteklenmiyor.');
        }

        $mime    = strtolower((string) ($info['mime'] ?? ''));
        $allowed = (array) Config::get('upload.allowed_types');

        if (!array_key_exists($mime, $allowed)) {
            throw new RuntimeException('Yalnızca JPG, PNG, GIF ve WEBP formatları desteklenir.');
        }

        return $mime;
    }

    public static function delete(?string $filename): void
    {
        $filename = basename(trim((string) $filename));

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return;
        }

        $dir  = (string) Config::get('upload.dir');
        $path = $dir . $filename;

        $realDir  = realpath($dir);
        $realPath = realpath($path);

        if ($realDir === false || $realPath === false || !str_starts_with($realPath, $realDir)) {
            return;
        }

        if (is_file($realPath)) {
            @unlink($realPath);
        }
    }

    public static function url(?string $filename): string
    {
        $filename = basename(trim((string) $filename));

        if ($filename === '' || !is_file((string) Config::get('upload.dir') . $filename)) {
            return '';
        }

        return (string) Config::get('upload.url') . rawurlencode($filename);
    }

    private static function sanitize(string $path, string $mime): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

        $maxDimension = (int) Config::get('upload.max_dimension', 1200);

        $source = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            'image/png'  => function_exists('imagecreatefrompng')  ? @imagecreatefrompng($path)  : false,
            'image/gif'  => function_exists('imagecreatefromgif')  ? @imagecreatefromgif($path)  : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default      => false,
        };

        if ($source === false) {
            return;
        }

        $width  = imagesx($source);
        $height = imagesy($source);
        $scale  = min(1, $maxDimension / max($width, $height));

        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime !== 'image/jpeg') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match ($mime) {
            'image/jpeg' => @imagejpeg($target, $path, 85),
            'image/png'  => @imagepng($target, $path, 6),
            'image/gif'  => @imagegif($target, $path),
            'image/webp' => @imagewebp($target, $path, 85),
            default      => null,
        };

        imagedestroy($source);
        imagedestroy($target);
    }
}
