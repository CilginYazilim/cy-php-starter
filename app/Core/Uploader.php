<?php
/**
 * =====================================================================
 *  Uploader – Güvenli görsel yükleme (avatar / logo)
 * ---------------------------------------------------------------------
 *  1. İçerik doğrulanır (getimagesize)  2. Ad/uzantı sunucu belirler
 *  3. Rastgele adla kaydedilir          4. upload/.htaccess PHP kapatır
 *  5. Görsel yeniden üretilerek gömülü veri (EXIF, kod) atılır
 *
 *  KLASÖR DÜZENİ
 *    upload/img/avatar/  → kullanıcı profil görselleri (kare kırpılır)
 *    upload/img/logo/    → site logosu (oranı korunur)
 *    upload/             → "kind" verilmezse (genel amaçlı, geriye dönük uyum)
 *
 *  Veritabanında dosya adı yerine upload/ köküne GÖRELİ yol saklanır
 *  (örn. "img/avatar/ab12….png"); böylece eski kayıtlar (yalın dosya
 *  adı) de çalışmaya devam eder.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Uploader
{
    /** Profil fotoğrafı: kare kırpılır, küçük boyutta tutulur. */
    public static function avatar(array $file): string
    {
        return self::image($file, 'avatar', true);
    }

    /** Site logosu: en/boy oranı korunur, kırpılmaz. */
    public static function logo(array $file): string
    {
        return self::image($file, 'logo', false);
    }

    /**
     * @param string $kind   Alt klasör adı ("avatar", "logo"…); boşsa upload/ köküne kaydeder.
     * @param bool   $square Doğruysa görsel merkezden kare kırpılır (avatarlar için).
     */
    public static function image(array $file, string $kind = '', bool $square = false): string
    {
        $mime = self::validate($file);

        $dir       = self::resolveDir($kind);
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

        $maxDimension = $kind === 'avatar'
            ? (int) Config::get('upload.avatar_dimension', 480)
            : (int) Config::get('upload.max_dimension', 1200);

        self::sanitize($target, $mime, $square, $maxDimension);

        $relative = $kind !== '' ? 'img/' . $kind . '/' . $name : $name;

        return $relative;
    }

    private static function resolveDir(string $kind): string
    {
        $root = rtrim((string) Config::get('upload.dir'), '/\\') . DIRECTORY_SEPARATOR;

        if ($kind === '') {
            return $root;
        }

        // Yalnızca harf/rakam/tire: "kind" hiçbir zaman dışarıdan
        // gelen kullanıcı girdisi olmamalı, ama yine de savunma amaçlı.
        $kind = preg_replace('/[^a-z0-9\-]/', '', strtolower($kind)) ?? '';

        return $root . 'img' . DIRECTORY_SEPARATOR . $kind . DIRECTORY_SEPARATOR;
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

    public static function delete(?string $relative): void
    {
        $relative = self::sanitizeRelativePath((string) $relative);

        if ($relative === null) {
            return;
        }

        $dir  = (string) Config::get('upload.dir');
        $path = $dir . $relative;

        // "realpath" hem sembolik bağlantıları hem de "kalan" .. parçalarını
        // çözer; sonucun kök klasörün İÇİNDE kaldığını doğrulamak, dizin
        // dışına çıkmayı engelleyen ASIL katmandır (üstteki string kontrolü
        // sadece ilk elemedir).
        $realDir  = realpath($dir);
        $realPath = realpath($path);

        if ($realDir === false || $realPath === false || !str_starts_with($realPath, $realDir)) {
            return;
        }

        if (is_file($realPath)) {
            @unlink($realPath);
        }
    }

    public static function url(?string $relative): string
    {
        $relative = self::sanitizeRelativePath((string) $relative);

        if ($relative === null || !is_file((string) Config::get('upload.dir') . $relative)) {
            return '';
        }

        // Yolun her SEGMENTİ ayrı ayrı kodlanır; rawurlencode'u doğrudan
        // tüm yola uygulamak "/" karakterini de kodlayıp adresi bozar.
        $encoded = implode('/', array_map('rawurlencode', explode('/', $relative)));

        return (string) Config::get('upload.url') . $encoded;
    }

    /**
     * "img/avatar/ab12….png" gibi upload/ köküne göreli bir yolu güvenle
     * doğrular. Dizin dışına çıkma teşebbüsü (".." veya mutlak yol) her
     * zaman reddedilir — dönen değer yalnızca bu doğrulamadan geçmiş,
     * normalize edilmiş bir yoldur (nihai güvenlik denetimi yine de
     * realpath ile yapılır, bkz. delete()/url()).
     */
    private static function sanitizeRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', trim($path)), '/');

        if ($path === '') {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }

    /**
     * Görseli yeniden üreterek gömülü veriyi (EXIF, olası kötücül kod)
     * atar. $square true ise (avatarlar) önce ORTADAN kare kırpılır,
     * sonra $maxDimension'a küçültülür — böylece hangi oranda
     * yüklenirse yüklensin, sonuç her zaman düzgün bir kare olur.
     */
    private static function sanitize(string $path, string $mime, bool $square = false, int $maxDimension = 1200): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

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

        if ($square) {
            $cropSize = min($width, $height);
            $srcX     = (int) round(($width - $cropSize) / 2);
            $srcY     = (int) round(($height - $cropSize) / 2);
            $srcWidth  = $srcHeight = $cropSize;

            $newWidth = $newHeight = max(1, min($cropSize, $maxDimension));
        } else {
            $srcX = $srcY = 0;
            $srcWidth  = $width;
            $srcHeight = $height;

            $scale     = min(1, $maxDimension / max($width, $height));
            $newWidth  = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
        }

        $target = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime !== 'image/jpeg') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, $newWidth, $newHeight, $srcWidth, $srcHeight);

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
