<?php
/**
 * =====================================================================
 *  UYGULAMA AYARLARI (tek merkez)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Şifreleri buraya YAZMAYIN. Kök dizindeki ".env" dosyası kurulum
 *  sihirbazı tarafından otomatik üretilir (bkz. install/).
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    'app' => [
        'name'  => Env::get('APP_NAME', 'Yeni Proje'),
        'brand' => 'Çılgın Yazılım',
        'desc'  => Env::get('APP_DESCRIPTION', 'Çılgın Yazılım örnek uygulaması'),
        'url'   => Env::get('APP_URL', ''),

        'debug' => Env::bool('APP_DEBUG', true),

        'timezone' => 'Europe/Istanbul',
        'locale'   => 'tr_TR',

        // false → index.php?r=panel/kullanicilar   (sunucu ayarı gerekmez)
        // true  → panel/kullanicilar                (mod_rewrite gerekir)
        'pretty_urls' => Env::bool('APP_PRETTY_URLS', false),
    ],

    'db' => [
        'host'    => Env::get('DB_HOST', '127.0.0.1'),
        'port'    => (int) Env::get('DB_PORT', '3306'),
        'name'    => Env::get('DB_NAME', 'yeni_proje'),
        'user'    => Env::get('DB_USER', 'root'),
        'pass'    => Env::get('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'              => 'CYSTARTERSESS',
        'idle_timeout'      => 1800, // 30 dakika
        'regenerate_every'  => 900,  // 15 dakika
    ],

    'upload' => [
        'dir'       => CY_BASE . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR,
        'url'       => 'upload/',
        'max_bytes' => 2 * 1024 * 1024, // 2 MB

        'allowed_types' => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ],

        'max_dimension' => 1200,
    ],

    'security' => [
        'login_max_attempts' => 5,
        'login_lockout'      => 900, // 15 dakika
        'login_window'       => 900,
        'password_min'       => 8,
        'csp_enabled'        => true,
    ],

    'validation' => [
        'name_min' => 2,
        'name_max' => 100,
    ],
];
