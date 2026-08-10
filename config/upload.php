<?php
/**
 * =====================================================================
 *  DOSYA YÜKLEME KURALLARI      →  Config::get('upload.*')
 * ---------------------------------------------------------------------
 *  Buradaki ayarlar "neyin girmesine izin var" sorusunu yanıtlar.
 *  Dosyanın NEREYE yazılacağı config/storage.php'de tanımlıdır.
 *
 *  MIME → UZANTI eşlemesi kritiktir: uzantıyı KULLANICININ gönderdiği
 *  dosya adından değil, sunucunun tespit ettiği gerçek içerik
 *  türünden belirleriz (bkz. app/Core/Uploader.php).
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    'max_bytes'  => Env::int('UPLOAD_MAX_MB', 2) * 1024 * 1024,
    'image_disk' => 'public',

    /* -----------------------------------------------------------------
     *  GÖRSELLER (avatar, logo, ürün fotoğrafı)
     * -----------------------------------------------------------------
     *  Bu liste Uploader::image() içindir. Görseller GD ile yeniden
     *  üretildiği için yalnızca GD'nin açabildiği türler olabilir.
     * -------------------------------------------------------------- */
    'allowed_types' => [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ],

    'max_dimension'    => 1200, // logo ve genel görseller
    'avatar_dimension' => 480,  // profil fotoğrafları (kare kırpılır)

    /* -----------------------------------------------------------------
     *  GENEL DOSYA GRUPLARI (Uploader::store)
     * -----------------------------------------------------------------
     *      Uploader::store($file, ['group' => 'belge', 'disk' => 'private']);
     *
     *  Grup vermezseniz TÜM gruplar birleştirilir; listede olmayan bir
     *  tür her hâlükârda reddedilir.
     * -------------------------------------------------------------- */
    'groups' => [
        'gorsel' => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ],

        'belge' => [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'text/csv'   => 'csv',
        ],

        'arsiv' => [
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-7z-compressed'  => '7z',
            'application/gzip' => 'gz',
        ],
    ],

    /* -----------------------------------------------------------------
     *  KARA LİSTE (ikinci kilit)
     * -----------------------------------------------------------------
     *  Beyaz liste zaten var; bu, yapılandırmaya yanlışlıkla eklenen
     *  bir türün felakete dönüşmesini engeller.
     *
     *  NOT: SVG bilerek "gorsel" grubundadır ama içine <script>
     *  gömülebilir. SVG kabul edecekseniz PRIVATE diske yazın ya da
     *  "gorsel" grubundan çıkarın.
     * -------------------------------------------------------------- */
    'blocked_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'htaccess', 'htpasswd', 'ini', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash',
        'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'so', 'jar', 'jsp', 'asp', 'aspx',
    ],
];
