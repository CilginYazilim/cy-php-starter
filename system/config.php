<?php
/**
 * =====================================================================
 *  YAPILANDIRMA – Çılgın Yazılım PHP Başlangıç Şablonu
 *  cilginyazilim.com
 * ---------------------------------------------------------------------
 *  ► YENİ PROJEDE NE YAPMALIYIM?
 *    En kolay yol: tarayıcıda install.php dosyasını açın. Kurulum
 *    sihirbazı veritabanını oluşturur, şemayı içe aktarır ve tüm
 *    ayarları ".env" dosyasına yazar — bu dosyayı ELLE DEĞİŞTİRMENİZE
 *    gerek kalmaz.
 *
 *    Sihirbazı kullanmak istemiyorsanız: aşağıdaki APP_NAME ve
 *    DB_NAME değerlerini elle güncelleyin.
 *
 *  Bu dosya dört iş yapar:
 *    1. Varsa ".env" dosyasını okur (install.php tarafından üretilir)
 *    2. Oturumu (session) başlatır  → CSRF anahtarı için gerekli
 *    3. Ayarları sabit olarak tanımlar
 *    4. Veritabanı bağlantısını ($db) kurar
 * =====================================================================
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
 *  1) .env DOSYASINI YÜKLE (varsa)
 * ---------------------------------------------------------------------
 *  Composer'daki vlucas/phpdotenv gibi bir paket kurmadan, aynı işi
 *  yapan minik bir yükleyici. install.php bu dosyayı otomatik üretir;
 *  siz de isterseniz elle oluşturabilirsiniz (bkz. .env.example).
 *
 *  ÖNEMLİ: putenv() sadece .env dosyasında olup GERÇEK ortam
 *  değişkeni olarak TANIMLI OLMAYAN anahtarlar için çağrılır. Yani
 *  sunucunuzda (örn. Docker, hosting paneli) gerçek bir ortam
 *  değişkeni tanımladıysanız, o her zaman .env dosyasından ÖNCELİKLİDİR.
 * ------------------------------------------------------------------ */
function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        // Boş satırları ve # ile başlayan yorum satırlarını atla.
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // "değer" veya 'değer' şeklinde tırnaklanmışsa tırnakları temizle.
        if (strlen($value) >= 2) {
            $firstChar = $value[0];
            $lastChar  = $value[strlen($value) - 1];
            if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Gerçek bir ortam değişkeni zaten tanımlıysa .env dosyasını es geç.
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

load_env_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

/* ---------------------------------------------------------------------
 *  2) OTURUM
 * ------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------------------------------------------------------------
 *  3) UYGULAMA KİMLİĞİ
 * ---------------------------------------------------------------------
 *  install.php kullandıysanız bu değerler .env dosyasından otomatik
 *  gelir. Sihirbazı kullanmadıysanız aşağıdaki varsayılanları elle
 *  değiştirebilirsiniz.                    ◄── BURAYI DEĞİŞTİRİN
 * ------------------------------------------------------------------ */
define('APP_NAME', getenv('APP_NAME') ?: 'Yeni Proje');
define('APP_DESCRIPTION', getenv('APP_DESCRIPTION') ?: 'Çılgın Yazılım örnek uygulaması');

/* ---------------------------------------------------------------------
 *  4) VERİTABANI AYARLARI                 ◄── BURAYI DEĞİŞTİRİN
 * ---------------------------------------------------------------------
 *  getenv('DB_HOST') ?: '127.0.0.1'
 *      → Ortam değişkeni veya .env dosyasında tanımlıysa onu,
 *        değilse varsayılanı kullanır.
 *
 *  Şifreyi koda yazıp GitHub'a göndermek en sık yapılan güvenlik
 *  hatasıdır. install.php'yi kullanın veya elle .env dosyası oluşturun.
 * ------------------------------------------------------------------ */
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'yeni_proje');   // ◄── veritabanı adı
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');

// utf8mb4: Türkçe karakterler ve emoji dahil tüm Unicode'u destekler.
// Eski "utf8" (utf8mb3) bazı karakterleri saklayamaz, kullanmayın.
define('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------
 *  5) DOSYA YÜKLEME AYARLARI
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
 *  6) GENEL AYARLAR
 * ------------------------------------------------------------------ */

/**
 * APP_DEBUG
 *   true  → Hata detayları ekranda gösterilir (GELİŞTİRME)
 *   false → Hatalar gizlenir, sadece log'a yazılır (CANLI)
 *
 * CANLIYA ALIRKEN MUTLAKA false YAPIN.
 */
define('APP_DEBUG', (getenv('APP_DEBUG') ?: 'true') !== 'false');

// Metin alanları için varsayılan uzunluk sınırları.
define('TEXT_MIN_LENGTH', 2);
define('TEXT_MAX_LENGTH', 150);

error_reporting(APP_DEBUG ? E_ALL : 0);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

/* ---------------------------------------------------------------------
 *  7) VERİTABANI BAĞLANTISI (PDO)
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
    header('Content-Type: text/html; charset=utf-8');

    // MySQL hata kodu 1049 = "Unknown database". En sık karşılaşılan
    // durum budur: proje ilk kez indirildi, veritabanı henüz yok.
    // Ham hata mesajı yerine kullanıcıyı doğrudan kurulum sihirbazına
    // yönlendiriyoruz.
    $isUnknownDatabase = $e->getCode() === 1049
        || str_contains($e->getMessage(), 'Unknown database');

    if ($isUnknownDatabase) {
        $installUrl = rtrim(str_replace(basename($_SERVER['SCRIPT_NAME'] ?? ''), '', $_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/install.php';
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
            . '<title>Kurulum gerekli</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#f2f7fd;color:#0f172a;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
            . '.box{background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(6,19,33,.12);'
            . 'padding:2rem 2.5rem;max-width:440px;text-align:center}'
            . 'a{display:inline-block;margin-top:1rem;background:#0b5cb5;color:#fff;'
            . 'text-decoration:none;padding:.65rem 1.4rem;border-radius:8px;font-weight:600}</style>'
            . '</head><body><div class="box"><h1 style="font-size:1.25rem">Veritabanı bulunamadı</h1>'
            . '<p>"' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '" adlı veritabanı henüz oluşturulmamış. '
            . 'Kurulum sihirbazını çalıştırarak birkaç saniyede hazırlayabilirsiniz.</p>'
            . '<a href="' . htmlspecialchars($installUrl, ENT_QUOTES, 'UTF-8') . '">Kurulum Sihirbazını Aç</a>'
            . '</div></body></html>';
        exit;
    }

    echo '<pre>' . htmlspecialchars(
        APP_DEBUG
            ? 'Veritabanı bağlantı hatası: ' . $e->getMessage()
            : 'Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.',
        ENT_QUOTES,
        'UTF-8'
    ) . '</pre>';

    exit;
}
