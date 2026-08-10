<?php
/**
 * =====================================================================
 *  Uploader – Dosya yüklemenin ön kapısı
 * ---------------------------------------------------------------------
 *  İKİ KULLANIM VARDIR:
 *
 *  1) GÖRSELLER (avatar, logo, ürün fotoğrafı)
 *         $yol = Uploader::avatar($request->file('avatar'));
 *         $yol = Uploader::image($request->file('foto'), 'urun');
 *
 *     Görseller YENİDEN ÜRETİLİR: GD ile açılıp kaydedilir. Böylece
 *     EXIF verisi, gömülü betikler ve "polyglot" dosyalar (hem geçerli
 *     görsel hem çalıştırılabilir kod olan dosyalar) elenir.
 *
 *  2) GENEL DOSYALAR (pdf, docx, zip…)
 *         $dosya = Uploader::store($request->file('belge'), [
 *             'disk'  => 'private',
 *             'dir'   => 'fatura/2026',
 *             'group' => 'belge',
 *         ]);
 *
 *     Bunlar yeniden üretilemez; güvenlik MIME beyaz listesi, uzantı
 *     kara listesi ve "asla web'den servis etme" kuralına dayanır.
 *
 *  SAVUNMA KATMANLARI
 *    1. PHP'nin yükleme hata kodu denetlenir
 *    2. is_uploaded_file() — dosya gerçekten bu isteğe mi ait?
 *    3. Boyut sınırı
 *    4. GERÇEK MIME (finfo / getimagesize) — uzantıya asla güvenilmez
 *    5. MIME beyaz listesi + uzantı kara listesi
 *    6. Rastgele dosya adı — kullanıcının verdiği ad diske yazılmaz
 *    7. Görseller yeniden üretilir
 *    8. upload/.htaccess PHP çalıştırmayı kapatır
 *    9. Gizli dosyalar private diske, web kökünün dışına yazılır
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Events\Events;
use App\Core\Storage\Storage;
use App\Core\Storage\StorageException;
use App\Core\Storage\StoredFile;
use App\Events\FileUploaded;
use RuntimeException;

final class Uploader
{
    /* =================================================================
     *  GÖRSELLER
     * ============================================================== */

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
     * Site faviconu: KARE kırpılır ve küçültülür.
     *
     * Tarayıcı sekmesindeki alan 16–32 pikseldir; kare olmayan bir
     * görsel orada ezilir. 256 piksel, "ana ekrana ekle" kısayolu
     * dahil her kullanım için fazlasıyla yeter ve dosyayı küçük tutar.
     */
    public static function favicon(array $file): string
    {
        return self::image($file, 'favicon', true, 256);
    }

    /**
     * Görseli doğrular, kaydeder ve yeniden üretir.
     *
     * @param string   $kind   Alt klasör adı ("avatar", "logo"…); boşsa disk köküne.
     * @param bool     $square Doğruysa görsel merkezden kare kırpılır.
     * @param int|null $boyut  En uzun kenar sınırı; null ise türe göre varsayılan.
     * @return string upload/ köküne GÖRELİ yol ("img/avatar/ab12….png")
     */
    public static function image(array $file, string $kind = '', bool $square = false, ?int $boyut = null): string
    {
        $mime = self::validateImage($file);

        $disk      = Storage::disk(self::imageDisk());
        $extension = (string) ((array) Config::get('upload.allowed_types'))[$mime];

        $relative = self::imagePath($kind, $extension);

        try {
            $relative = $disk->putUploadedFile((string) $file['tmp_name'], $relative);
        } catch (StorageException $e) {
            throw new RuntimeException('Görsel kaydedilemedi.', 0, $e);
        }

        $maxDimension = $boyut ?? ($kind === 'avatar'
            ? (int) Config::get('upload.avatar_dimension', 480)
            : (int) Config::get('upload.max_dimension', 1200));

        self::sanitize($disk->path($relative, true), $mime, $square, $maxDimension);

        return $relative;
    }

    /** "img/avatar/ab12….png" — çakışma olmayacak bir yol üretir. */
    private static function imagePath(string $kind, string $extension): string
    {
        // "kind" hiçbir zaman kullanıcı girdisi olmamalı; yine de
        // savunma amaçlı yalnızca harf/rakam/tireye izin veriyoruz.
        $kind = preg_replace('/[^a-z0-9\-]/', '', strtolower($kind)) ?? '';
        $disk = Storage::disk(self::imageDisk());

        do {
            $name     = Storage::randomName($extension);
            $relative = $kind !== '' ? 'img/' . $kind . '/' . $name : $name;
        } while ($disk->exists($relative));

        return $relative;
    }

    private static function imageDisk(): string
    {
        return (string) Config::get('upload.image_disk', 'public');
    }

    /* =================================================================
     *  GENEL DOSYALAR
     * ============================================================== */

    /**
     * Herhangi bir dosyayı doğrular ve diske yazar.
     *
     * @param array<string,mixed> $file  $_FILES girdisi
     * @param array{disk?:string,dir?:string,group?:string,mimes?:array<int,string>,max_bytes?:int} $options
     *
     * @throws RuntimeException Doğrulama başarısızsa (mesaj kullanıcıya gösterilebilir)
     */
    public static function store(array $file, array $options = []): StoredFile
    {
        $diskName = (string) ($options['disk'] ?? 'private');
        $disk     = Storage::disk($diskName);

        $maxBytes = (int) ($options['max_bytes'] ?? Config::get('upload.max_bytes', 2097152));

        self::checkUploadError($file, $maxBytes);

        $size = (int) $file['size'];
        $mime = self::detectMime((string) $file['tmp_name']);

        $allowed = self::resolveAllowedMimes($options);

        if ($allowed !== [] && !array_key_exists($mime, $allowed)) {
            throw new RuntimeException('Bu dosya türü kabul edilmiyor.');
        }

        $extension = $allowed[$mime] ?? self::extensionFromName((string) ($file['name'] ?? ''));

        self::rejectDangerousExtension($extension);

        $directory = trim((string) ($options['dir'] ?? ''), '/');
        $name      = Storage::randomName($extension);
        $relative  = $directory !== '' ? $directory . '/' . $name : $name;

        try {
            $relative = $disk->putUploadedFile((string) $file['tmp_name'], $relative);
        } catch (StorageException $e) {
            throw new RuntimeException('Dosya kaydedilemedi.', 0, $e);
        }

        /* Görsel yüklendiyse yine de yeniden üretiyoruz: "belge"
         * grubuna sızmış bir polyglot dosya da temizlensin. */
        if (str_starts_with($mime, 'image/') && array_key_exists($mime, (array) Config::get('upload.allowed_types', []))) {
            self::sanitize($disk->path($relative, true), $mime, false, (int) Config::get('upload.max_dimension', 1200));
        }

        $stored = new StoredFile(
            yol:   $relative,
            disk:  $diskName,
            ad:    Storage::safeDisplayName((string) ($file['name'] ?? 'dosya')),
            mime:  $mime,
            boyut: $size,
        );

        /* Dosya diskte ve doğrulandı. Bundan sonrasını modüller
         * üstlenebilir: virüs taraması, küçük resim üretimi, belge
         * yönetiminde kayıt açma… Çekirdek bunların hiçbirini bilmez. */
        Events::dispatch(new FileUploaded($stored, Auth::id()));

        return $stored;
    }

    /**
     * İzin verilen MIME → uzantı eşlemesi.
     *
     * @param array{group?:string,mimes?:array<int,string>} $options
     * @return array<string,string>
     */
    private static function resolveAllowedMimes(array $options): array
    {
        /** @var array<string,array<string,string>> $groups */
        $groups = (array) Config::get('upload.groups', []);

        if (isset($options['mimes']) && is_array($options['mimes'])) {
            $map = [];

            foreach ($options['mimes'] as $mime) {
                $map[$mime] = self::extensionForMime((string) $mime, $groups);
            }

            return $map;
        }

        $group = (string) ($options['group'] ?? '');

        if ($group === '') {
            // Grup verilmediyse TÜM tanımlı gruplar birleştirilir;
            // tanımsız bir tür yine de reddedilir.
            $all = [];

            foreach ($groups as $mimes) {
                $all += $mimes;
            }

            return $all;
        }

        if (!array_key_exists($group, $groups)) {
            throw new RuntimeException('Tanımsız dosya grubu: ' . $group);
        }

        return $groups[$group];
    }

    /** @param array<string,array<string,string>> $groups */
    private static function extensionForMime(string $mime, array $groups): string
    {
        foreach ($groups as $mimes) {
            if (array_key_exists($mime, $mimes)) {
                return $mimes[$mime];
            }
        }

        return 'bin';
    }

    private static function extensionFromName(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
    }

    /**
     * Çalıştırılabilir uzantıları kesin olarak reddeder.
     *
     * Beyaz liste zaten var; bu ikinci kilit, yapılandırmaya
     * yanlışlıkla eklenen bir türün ("text/x-php" gibi) felakete
     * dönüşmesini engeller.
     */
    private static function rejectDangerousExtension(string $extension): void
    {
        $blocked = array_map(
            'strtolower',
            (array) Config::get('upload.blocked_extensions', [])
        );

        if (in_array(strtolower($extension), $blocked, true)) {
            throw new RuntimeException('Bu uzantıya sahip dosyalar yüklenemez.');
        }
    }

    /* =================================================================
     *  DOĞRULAMA
     * ============================================================== */

    /** Geriye dönük uyum: eski kod Uploader::validate() çağırıyor. */
    public static function validate(array $file): string
    {
        return self::validateImage($file);
    }

    /**
     * Görsel doğrulaması.
     *
     * getimagesize() dosyayı GERÇEKTEN çözmeye çalışır; başarısız
     * olursa dosya görsel değildir — uzantısı ne derse desin.
     *
     * @return string Tespit edilen MIME
     */
    public static function validateImage(array $file): string
    {
        $maxBytes = (int) Config::get('upload.max_bytes', 2097152);

        self::checkUploadError($file, $maxBytes);

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

    /** PHP'nin yükleme hata kodu, boyut ve kaynak denetimi. */
    private static function checkUploadError(array $file, int $maxBytes): void
    {
        if (!isset($file['error'], $file['tmp_name'], $file['size']) || is_array($file['error'])) {
            throw new RuntimeException('Geçersiz dosya yükleme isteği.');
        }

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

        if ((int) $file['size'] <= 0) {
            throw new RuntimeException('Dosya boş görünüyor.');
        }

        if ((int) $file['size'] > $maxBytes) {
            throw new RuntimeException(
                'Dosya boyutu en fazla ' . Storage::humanSize($maxBytes) . ' olabilir.'
            );
        }

        /* KRİTİK: Dosyanın gerçekten bu isteğin yüklemesi olduğunu
         * doğrular. Bu kontrol olmadan saldırgan tmp_name alanına
         * "/etc/passwd" yazıp sunucudaki herhangi bir dosyayı
         * kopyalatabilirdi. */
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Geçersiz dosya kaynağı.');
        }
    }

    /** Dosyanın ilk baytlarına bakarak GERÇEK türünü bulur. */
    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        // finfo eklentisi kapalıysa görselleri yine de tanıyabiliriz.
        $info = @getimagesize($path);

        if ($info !== false && isset($info['mime'])) {
            return strtolower((string) $info['mime']);
        }

        throw new RuntimeException('Dosya türü belirlenemedi.');
    }

    /* =================================================================
     *  SİLME / ADRES  (geriye dönük uyumlu)
     * ============================================================== */

    public static function delete(?string $relative, ?string $disk = null): void
    {
        if ($relative === null || trim($relative) === '') {
            return;
        }

        Storage::disk($disk ?? self::imageDisk())->delete($relative);
    }

    public static function url(?string $relative, ?string $disk = null): string
    {
        if ($relative === null || trim($relative) === '') {
            return '';
        }

        return Storage::disk($disk ?? self::imageDisk())->url($relative);
    }

    /* =================================================================
     *  GÖRSEL TEMİZLEME
     * ============================================================== */

    /**
     * Görseli yeniden üreterek gömülü veriyi (EXIF, olası kötücül kod)
     * atar. $square true ise (avatarlar) önce ORTADAN kare kırpılır,
     * sonra $maxDimension'a küçültülür.
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
            $srcWidth = $srcHeight = $cropSize;

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
