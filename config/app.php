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

    /* Marka adı ARTIK AYARDADIR (Ayarlar → Genel → Marka / Kurum Adı;
     * bkz. site_brand()). Koda gömülü "Çılgın Yazılım" her projenin
     * panelinde ve künyesinde görünüyordu. */

    /* -----------------------------------------------------------------
     *  ŞABLON SÜRÜMÜ  —  TEK DOĞRU KAYNAK
     * -----------------------------------------------------------------
     *  Sürüm KODLA BİRLİKTE gelir; bu yüzden yeri koddur.
     *
     *  Eskiden veritabanındaki "sistem_surum" ayarında duruyordu ve iki
     *  numara birbirini tutmuyordu: kurulum/database.sql "2.1.0",
     *  Sistem Bilgisi sayfasının varsayılanı "1.0.0", GitHub sürümü ise
     *  "v1.0.0" diyordu. Üstelik veritabanındaki bir değer, şablonu
     *  güncelleyen kişi migration çalıştırmadıkça ESKİ SÜRÜMÜ göstermeye
     *  devam ederdi — yani tam olarak yanıltması en kolay yerdeydi.
     *
     *  Numaralandırma GitHub sürüm etiketleriyle aynı çizgidedir
     *  (github.com/CilginYazilim/cy-php-starter/releases) ve anlamsal
     *  sürümleme kullanır: BÜYÜK.KÜÇÜK.YAMA
     * -------------------------------------------------------------- */
    'version' => '1.6.0',

    /* Birim testi sayısı — ana sayfadaki {test} yer tutucusu. tests/unit.php
     * gerçek sayıyla karşılaştırır; test eklenip burası güncellenmezse kırılır. */
    'test_sayisi' => 306,

    'desc'  => Env::get('APP_DESCRIPTION', ''),
    'url'   => Env::get('APP_URL', ''),

    /* Kuruluma özel gizli anahtar (64 onaltılık karakter). Kurulum
     * sihirbazı üretir. Oturumu bu kuruluma bağlar (bkz.
     * App\Core\Session::appId) ve imzalarda kullanılır (cihaz çerezi,
     * e-posta doğrulama bağlantısı, form damgası — bkz. App\Core\Signer).
     * Değiştirirseniz açık oturumlar ve gönderilmiş doğrulama
     * bağlantıları geçersiz olur; başka bir şey bozulmaz. */
    'key' => Env::get('APP_KEY', ''),

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
    'env' => Env::get('APP_ENV', 'production'),

    /* Hata ayrıntıları ekranda görünsün mü? Yayında MUTLAKA false.
     *
     * VARSAYILAN KAPALIDIR: .env'de satır unutulmuşsa ya da ortam
     * değişkeni hiç verilmemişse güvenli tarafta kalırız. Eskiden
     * varsayılan "true" idi; eksik bir .env yayındaki sitede dosya
     * yollarını ve SQL hatalarını ziyaretçiye gösteriyordu. */
    'debug' => Env::bool('APP_DEBUG', false),

    /* DEMO MODU — herkese açık deneme kurulumları için. Açıkken giriş
     * ekranı örnek hesapları (Yönetici, Editör, Üye) tek tıkla giriş
     * için listeler ve o hesaplara hesap/ayar/kullanıcı/e-posta
     * işlemleri kapanır (bkz. App\Core\Demo). Gerçek bir sitede
     * MUTLAKA false. Panelden değiştirilemez: demo yöneticisi demo
     * modunu kendisi kapatamasın diye. */
    'demo' => Env::bool('APP_DEMO', false),

    'timezone' => Env::get('APP_TIMEZONE', 'Europe/Istanbul'),
    'locale'   => 'tr_TR',

    // false → index.php?r=panel/kullanicilar   (sunucu ayarı gerekmez)
    // true  → panel/kullanicilar                (mod_rewrite gerekir)
    'pretty_urls' => Env::bool('APP_PRETTY_URLS', true),
];
