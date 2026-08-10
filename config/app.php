<?php
/**
 * =====================================================================
 *  UYGULAMA AYARLARI            →  Config::get('app.*')
 * ---------------------------------------------------------------------
 *  Şifreleri buraya YAZMAYIN. Kök dizindeki ".env" dosyası kurulum
 *  sihirbazı tarafından otomatik üretilir (bkz. kurulum/).
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    'name'  => Env::get('APP_NAME', 'Yeni Proje'),
    'brand' => 'Çılgın Yazılım',
    'desc'  => Env::get('APP_DESCRIPTION', 'Çılgın Yazılım örnek uygulaması'),
    'url'   => Env::get('APP_URL', ''),

    /* -----------------------------------------------------------------
     *  ORTAM
     * -----------------------------------------------------------------
     *  "local"      → geliştirme makineniz
     *  "production" → yayındaki sunucu
     *
     *  Bu değer davranışı doğrudan değiştirmez; "debug" ile birlikte
     *  tutarsızsa (production + debug) uyarı üretilir. Modüller de
     *  Config::isProduction() ile kendi kararlarını verebilir.
     * -------------------------------------------------------------- */
    'env' => Env::get('APP_ENV', 'local'),

    /* Hata ayrıntıları ekranda görünsün mü? Yayında MUTLAKA false. */
    'debug' => Env::bool('APP_DEBUG', true),

    'timezone' => Env::get('APP_TIMEZONE', 'Europe/Istanbul'),
    'locale'   => 'tr_TR',

    // false → index.php?r=panel/kullanicilar   (sunucu ayarı gerekmez)
    // true  → panel/kullanicilar                (mod_rewrite gerekir)
    'pretty_urls' => Env::bool('APP_PRETTY_URLS', true),
];
