<?php
/**
 * =====================================================================
 *  YAPILANDIRMA – Çılgın Yazılım PHP Başlangıç Şablonu
 *  cilginyazilim.com
 * ---------------------------------------------------------------------
 *  ► YENİ PROJEDE İLK DEĞİŞTİRECEĞİNİZ DOSYA BUDUR.
 *    En az DB_NAME ve APP_NAME değerlerini güncelleyin.
 *
 *  Bu dosya üç iş yapar:
 *    1. Oturumu (session) başlatır  → CSRF anahtarı için gerekli
 *    2. Ayarları sabit olarak tanımlar
 *    3. Veritabanı bağlantısını ($db) kurar
 * =====================================================================
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
 *  1) OTURUM
 * ------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------------------------------------------------------------
 *  2) UYGULAMA KİMLİĞİ                    ◄── BURAYI DEĞİŞTİRİN
 * ------------------------------------------------------------------ */
define('APP_NAME', 'Yeni Proje');
define('APP_DESCRIPTION', 'Çılgın Yazılım örnek uygulaması');

/* ---------------------------------------------------------------------
 *  3) VERİTABANI AYARLARI                 ◄── BURAYI DEĞİŞTİRİN
 * ---------------------------------------------------------------------
 *  getenv('DB_HOST') ?: '127.0.0.1'
 *      → Ortam değişkeni tanımlıysa onu, değilse varsayılanı kullan.
 *
 *  Şifreyi koda yazıp GitHub'a göndermek en sık yapılan güvenlik
 *  hatasıdır. Canlıda mutlaka ortam değişkeni kullanın.
 * ------------------------------------------------------------------ */
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'yeni_proje');   // ◄── veritabanı adı
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');

// utf8mb4: Türkçe karakterler ve emoji dahil tüm Unicode'u destekler.
// Eski "utf8" (utf8mb3) bazı karakterleri saklayamaz, kullanmayın.
define('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------
 *  4) DOSYA YÜKLEME AYARLARI
 * ---------------------------------------------------------------------
 *  Projenizde dosya yükleme yoksa bu bölüme dokunmanıza gerek yok.
 * ------------------------------------------------------------------ */

// dirname(__DIR__): Bu dosya "system/" içinde olduğu için proje kökünü verir.
define('UPLOAD_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR);

// Tarayıcının göreceği yol (<img src="..."> içinde kullanılır).
define('UPLOAD_URL', 'upload/');

// En büyük dosya boyutu. Artırırsanız php.ini içindeki
// upload_max_filesize ve post_max_size değerlerini de artırın.
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2 MB

/**
 * İzin verilen MIME türleri ve karşılık gelen GÜVENLİ uzantılar.
 *
 * ÖNEMLİ: Yeni dosyanın uzantısı, kullanıcının gönderdiği dosya adından
 * DEĞİL bu listeden alınır. Böylece "virus.php.png" gibi çift uzantılı
 * dosyalar sunucuda çalıştırılabilir hale gelemez.
 */
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
]);

/* ---------------------------------------------------------------------
 *  5) GENEL AYARLAR
 * ------------------------------------------------------------------ */

/**
 * APP_DEBUG
 *   true  → Hata detayları ekranda gösterilir (GELİŞTİRME)
 *   false → Hatalar gizlenir, sadece log'a yazılır (CANLI)
 *
 * CANLIYA ALIRKEN MUTLAKA false YAPIN.
 */
define('APP_DEBUG', true);

// Metin alanları için varsayılan uzunluk sınırları.
define('TEXT_MIN_LENGTH', 2);
define('TEXT_MAX_LENGTH', 150);

error_reporting(APP_DEBUG ? E_ALL : 0);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

/* ---------------------------------------------------------------------
 *  6) VERİTABANI BAĞLANTISI (PDO)
 * ---------------------------------------------------------------------
 *  Projenizde veritabanı yoksa bu bloğu silebilirsiniz.
 * ------------------------------------------------------------------ */
try {
    $db = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [
            // Sorgu hata verince istisna fırlat (varsayılanda sessizce yutulur).
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Sonuçları $row['sutun'] şeklinde isimle döndür.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Gerçek prepared statement kullan (SQL Injection'a karşı en güçlü koruma).
            // DİKKAT: Bu ayar açıkken aynı isimli yer tutucu (:deger) bir
            // sorguda İKİ KEZ kullanılamaz; farklı isimler verin.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');

    echo APP_DEBUG
        ? 'Veritabanı bağlantı hatası: ' . $e->getMessage()
        : 'Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.';

    exit;
}
