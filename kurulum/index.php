<?php
/**
 * =====================================================================
 *  KURULUM SİHİRBAZI (adım adım)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  ADIMLAR:
 *    1) Gereksinimler  → sistem kontrolleri
 *    2) Veritabanı     → bilgiler girilir ve BAĞLANTI HEMEN TEST EDİLİR
 *    3) Site Ayarları  → site adı, açıklama, adres
 *    4) Yönetici       → admin hesabı; bu adımda kurulum çalıştırılır
 *    5) Tamamlandı     → özet + KURULUM KLASÖRÜNÜ SİL butonu
 *
 *  NEDEN AYRI BİR KLASÖR VE BAĞIMSIZ KOD?
 *  Bu dosya BİLEREK app/ klasöründeki hiçbir sınıfı YÜKLEMEZ. Sebebi:
 *  Autoloader ve Config, projenin geri kalanı gibi ".env" dosyasının
 *  ve dolayısıyla VERİTABANININ var olduğunu varsayar — ki kurulumun
 *  amacı tam olarak bunları henüz oluşturmaktır. Bu yüzden gereken
 *  birkaç yardımcı fonksiyon (doğrulama, parola özetleme) bu dosyanın
 *  içinde ayrıca ve bağımsız olarak tanımlanmıştır.
 *
 *  İş bittiğinde son adımdaki tek bir butonla "kurulum/" klasörünün
 *  tamamı silinir; proje kökünde kuruluma ait hiçbir dosya kalmaz.
 *
 *  ► Adımlar arasında veri $_SESSION içinde taşınır. Hiçbir şey
 *    veritabanına yazılmaz; yazma işlemi SADECE son adımda, tüm
 *    bilgiler toplandıktan sonra tek seferde yapılır.
 *
 *  ► POST-Redirect-GET deseni: kullanıcı sayfayı yenilediğinde "formu
 *    tekrar gönder" uyarısı almaz, aynı işlem iki kez çalışmaz.
 *
 *  ► CANLI ORTAMA ÇIKARKEN BU KLASÖRÜ SİLİN. Kurulum tamamlandıysa
 *    sihirbaz kendini otomatik kilitler, ama silmek en güvenlisidir.
 * =====================================================================
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Proje kökü — .env ve upload/ burada. */
const ROOT_PATH     = __DIR__ . '/..';
const ENV_PATH      = __DIR__ . '/../.env';
const SCHEMA_PATH   = __DIR__ . '/database.sql';
const IDENTIFIER_RE = '/^[A-Za-z_][A-Za-z0-9_]*$/';

/** Sihirbazın adımları: anahtar => ekranda görünen başlık. */
const ADIMLAR = [
    'gereksinimler' => 'Gereksinimler',
    'veritabani'    => 'Veritabanı',
    'site'          => 'Site Ayarları',
    'yonetici'      => 'Yönetici',
    'tamam'         => 'Tamamlandı',
];


/* =====================================================================
 *  BAĞIMSIZ YARDIMCI FONKSİYONLAR
 * ---------------------------------------------------------------------
 *  Bunlar app/Core/Validator.php ve app/Core/Auth.php içindeki
 *  mantığın küçük, bağımsız kopyalarıdır — kurulum bittikten sonra
 *  app/ klasörü buradan bağımsız çalışmaya devam eder.
 * ================================================================== */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** @return array{0:string,1:?string} */
function validate_text(?string $value, string $label, int $min = 2, int $max = 150): array
{
    $value = (string) $value;

    if (!mb_check_encoding($value, 'UTF-8')) {
        return ['', $label . ' geçersiz karakterler içeriyor.'];
    }

    $value = trim((string) preg_replace('/\s+/u', ' ', $value));

    if ($value === '') {
        return ['', $label . ' alanı boş bırakılamaz.'];
    }

    $length = mb_strlen($value, 'UTF-8');

    if ($length < $min) {
        return [$value, $label . ' en az ' . $min . ' karakter olmalıdır.'];
    }
    if ($length > $max) {
        return [$value, $label . ' en fazla ' . $max . ' karakter olabilir.'];
    }

    return [$value, null];
}

/** @return array{0:string,1:?string} */
function validate_name(?string $value, string $label): array
{
    [$value, $error] = validate_text($value, $label, 2, 100);

    if ($error !== null) {
        return [$value, $error];
    }
    if (!preg_match("/^[\p{L}\p{M}\s.'-]+$/u", $value)) {
        return [$value, $label . ' yalnızca harf, boşluk, nokta, kesme işareti ve tire içerebilir.'];
    }

    return [$value, null];
}

/** @return array{0:string,1:?string} */
function validate_username(?string $value): array
{
    $value = trim((string) $value);

    if ($value === '') {
        return ['', 'Kullanıcı adı boş bırakılamaz.'];
    }
    if (mb_strlen($value, 'UTF-8') < 3) {
        return [$value, 'Kullanıcı adı en az 3 karakter olmalıdır.'];
    }
    if (mb_strlen($value, 'UTF-8') > 50) {
        return [$value, 'Kullanıcı adı en fazla 50 karakter olabilir.'];
    }
    if (!preg_match('/^[a-zA-Z0-9._]+$/', $value)) {
        return [$value, 'Kullanıcı adı yalnızca İngilizce harf, rakam, nokta ve alt çizgi içerebilir.'];
    }

    return [$value, null];
}

/** @return array{0:string,1:?string} */
function validate_email(?string $value): array
{
    $value = trim((string) $value);

    if ($value === '') {
        return ['', 'E-posta alanı boş bırakılamaz.'];
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return [$value, 'Geçerli bir e-posta adresi girin.'];
    }

    return [$value, null];
}

function validate_password(string $plain): ?string
{
    if (mb_strlen($plain, 'UTF-8') < 8) {
        return 'Parola en az 8 karakter olmalıdır.';
    }
    if (!preg_match('/[A-Za-zÇĞİÖŞÜçğıöşü]/u', $plain)) {
        return 'Parola en az bir harf içermelidir.';
    }
    if (!preg_match('/[0-9]/', $plain)) {
        return 'Parola en az bir rakam içermelidir.';
    }

    return null;
}

function hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

/** Sitenin kök adresini istekten tahmin eder (form için varsayılan). */
function guess_site_url(): string
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return '';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir    = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    // "/kurulum" son eki tahmin edilen adreste görünmesin.
    $dir    = preg_replace('#/kurulum$#', '', $dir) ?? $dir;

    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir;
}

/** .env dosyasını basitçe okuyup KEY => VALUE dizisine çevirir. */
function parse_env_file(string $path): array
{
    $result = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);

        if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
            $value = substr($value, 1, -1);
        }

        $result[trim($key)] = $value;
    }

    return $result;
}

/** Veritabanı seçmeden sadece sunucuya bağlanır (henüz veritabanı yok). */
function connect_without_database(string $host, string $user, string $pass): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;charset=utf8mb4', $host),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

/**
 * database.sql dosyasını okuyup çalıştırır.
 * CREATE DATABASE / USE satırları BİLEREK atlanır: veritabanı zaten
 * kullanıcının seçtiği adla oluşturulup seçildi.
 */
function import_schema(PDO $pdo, string $path): int
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('database.sql dosyası okunamadı.');
    }

    $count = 0;
    foreach (split_sql_statements($sql) as $statement) {
        if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $statement)) {
            continue;
        }
        $pdo->exec($statement);
        $count++;
    }

    return $count;
}

/**
 * Basit bir SQL dosyasını tek tek çalıştırılabilir komutlara ayırır.
 * Tırnak içindeki noktalı virgülleri (ör. bir metin değerinde ";")
 * yanlışlıkla komut sonu saymamak için karakter karakter okuyup
 * tırnak durumunu takip eder.
 *
 * @return string[]
 */
function split_sql_statements(string $sql): array
{
    $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);

    $statements = [];
    $current    = '';
    $inString   = false;
    $quoteChar  = '';
    $length     = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];

        if ($inString) {
            $current .= $char;
            if ($char === $quoteChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $inString  = true;
            $quoteChar = $char;
            $current  .= $char;
            continue;
        }

        if ($char === ';') {
            $trimmed = trim($current);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $current = '';
            continue;
        }

        $current .= $char;
    }

    $trimmed = trim($current);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

/** Ayarları ".env" dosyasına yazar (app/Core/Env.php formatıyla uyumlu). */
function write_env_file(string $path, array $values): void
{
    $lines = [
        '# Bu dosya kurulum sihirbazı tarafından otomatik oluşturuldu.',
        '# app/Core/Env.php tarafından okunur. .gitignore içinde olduğu',
        '# için Git\'e gönderilmez — şifreniz burada güvende kalır.',
        '',
    ];

    foreach ($values as $key => $value) {
        $needsQuotes = $value === '' || preg_match('/\s|[#"\']/', $value);
        $safeValue   = str_replace('"', '\\"', $value);
        $lines[]     = $key . '=' . ($needsQuotes ? '"' . $safeValue . '"' : $safeValue);
    }

    if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
        throw new RuntimeException('.env dosyası yazılamadı. Klasör izinlerini kontrol edin.');
    }
}

/**
 * Bir klasörü içindekilerle birlikte siler (recursive).
 *
 * GÜVENLİK: Sembolik bağlantıların (symlink) İÇİNE girmeyiz.
 * WINDOWS NOTU: Çalışan betiğin kendi dosyası "silinmek üzere
 * işaretlenir" ama tutamak kapanana kadar klasör listesinden düşmez;
 * bu yüzden rmdir() başarısız olursa işi register_shutdown_function
 * ile betik bittikten sonra tekrar deniyoruz.
 */
function remove_directory(string $path): bool
{
    if (!is_dir($path)) {
        return false;
    }

    $basarili = true;

    foreach (scandir($path) ?: [] as $ad) {
        if ($ad === '.' || $ad === '..') {
            continue;
        }

        $tam = $path . DIRECTORY_SEPARATOR . $ad;

        if (is_dir($tam) && !is_link($tam)) {
            if (!remove_directory($tam)) {
                $basarili = false;
            }
            continue;
        }

        if (!@unlink($tam)) {
            $basarili = false;
        }
    }

    if (!$basarili) {
        return false;
    }

    if (@rmdir($path)) {
        return true;
    }

    register_shutdown_function(static function () use ($path): void {
        @rmdir($path);
    });

    return true;
}

/** Formda önceki değeri göstermek için: POST > oturum > varsayılan. */
function eski(string $field, array $store, string $default = ''): string
{
    if (isset($_POST[$field])) {
        return (string) $_POST[$field];
    }
    return (string) ($store[$field] ?? $default);
}


/* =====================================================================
 *  DURUM
 * ================================================================== */
$csrfToken = csrf_token();

$_SESSION['kurulum'] = $_SESSION['kurulum'] ?? [];
$kurulum = &$_SESSION['kurulum'];

$errors = [];

/* Kurulum zaten tamamlandı mı? .env varsa VE veritabanında en az bir
 * yönetici hesabı varsa kurulum bitmiş demektir; sihirbazı kilitleriz. */
$alreadyInstalled = false;
$existingEnv      = is_file(ENV_PATH) ? parse_env_file(ENV_PATH) : [];

if ($existingEnv !== []) {
    try {
        $probe = new PDO(
            sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $existingEnv['DB_HOST'] ?? '127.0.0.1',
                $existingEnv['DB_NAME'] ?? ''
            ),
            $existingEnv['DB_USER'] ?? 'root',
            $existingEnv['DB_PASS'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $alreadyInstalled = (int) $probe->query(
            "SELECT COUNT(*) FROM kullanicilar WHERE rol = 'admin'"
        )->fetchColumn() > 0;
    } catch (PDOException $e) {
        $alreadyInstalled = false;
    }
}

$forceReinstall = isset($_GET['yeniden']);

$adim = (string) ($_GET['adim'] ?? 'gereksinimler');
if (!array_key_exists($adim, ADIMLAR)) {
    $adim = 'gereksinimler';
}

$yeniBitti = ($adim === 'tamam' && !empty($_SESSION['kurulum_sonuc']));
$kilitli   = $alreadyInstalled && !$forceReinstall && !$yeniBitti;


/* =====================================================================
 *  TEMİZLİK: KURULUM KLASÖRÜNÜ SİL
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'temizle') {

    $token = $_POST['csrf_token'] ?? '';
    $tokenGecerli = is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);

    if (!$tokenGecerli) {
        $errors[] = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } elseif (!$alreadyInstalled && !$yeniBitti) {
        $errors[] = 'Kurulum tamamlanmadan kurulum klasörü silinemez.';
    } else {
        unset($_SESSION['kurulum'], $_SESSION['kurulum_sonuc']);

        if (remove_directory(__DIR__)) {
            header('Location: ../index.php?kurulum=temizlendi');
            exit;
        }

        $errors[] = 'Klasör silinemedi. Dosya izinlerini kontrol edin veya "kurulum" klasörünü FTP/dosya yöneticisi ile elle silin.';
    }
}


/* =====================================================================
 *  SİSTEM KONTROLLERİ
 * ================================================================== */
$checks = [
    ['label' => 'PHP sürümü 8.1 veya üzeri', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>='), 'note' => 'Mevcut sürüm: ' . PHP_VERSION],
    ['label' => 'pdo_mysql eklentisi yüklü', 'ok' => extension_loaded('pdo_mysql'), 'note' => extension_loaded('pdo_mysql') ? '' : 'php.ini içinde extension=pdo_mysql satırını açın.'],
    ['label' => 'mbstring eklentisi yüklü', 'ok' => extension_loaded('mbstring'), 'note' => extension_loaded('mbstring') ? '' : 'Türkçe karakter işlemleri için gereklidir.'],
    ['label' => 'Proje klasörüne yazma izni (.env için)', 'ok' => is_writable(ROOT_PATH), 'note' => is_writable(ROOT_PATH) ? '' : 'Klasör izinlerini kontrol edin.'],
    ['label' => 'kurulum/database.sql dosyası mevcut', 'ok' => is_file(SCHEMA_PATH), 'note' => is_file(SCHEMA_PATH) ? '' : 'Şema dosyası bulunamadı.'],
    ['label' => 'upload/ klasörü yazılabilir', 'ok' => !is_dir(ROOT_PATH . '/upload') || is_writable(ROOT_PATH . '/upload'), 'note' => 'Avatar ve görsel yükleme için gerekli.'],
    ['label' => 'kurulum/ klasörü silinebilir', 'ok' => is_writable(ROOT_PATH) && is_writable(__DIR__), 'note' => 'Kurulum bitince klasörü tek tıkla silebilmek için gerekli.'],
];
$allChecksOk = !in_array(false, array_column($checks, 'ok'), true);


/* =====================================================================
 *  FORM İŞLEME
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$kilitli && ($_POST['islem'] ?? '') !== 'temizle') {

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Oturum doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {

        /* ---------- ADIM 2: VERİTABANI ---------- */
        if ($adim === 'veritabani') {
            $db_host = trim((string) ($_POST['db_host'] ?? ''));
            $db_name = trim((string) ($_POST['db_name'] ?? ''));
            $db_user = trim((string) ($_POST['db_user'] ?? ''));
            $db_pass = (string) ($_POST['db_pass'] ?? '');

            if ($db_host === '') {
                $errors[] = 'Veritabanı sunucusu boş bırakılamaz.';
            }
            if ($db_name === '') {
                $errors[] = 'Veritabanı adı boş bırakılamaz.';
            } elseif (!preg_match(IDENTIFIER_RE, $db_name)) {
                $errors[] = 'Veritabanı adı yalnızca harf, rakam ve alt çizgi (_) içerebilir, rakamla başlayamaz.';
            }
            if ($db_user === '') {
                $errors[] = 'Veritabanı kullanıcı adı boş bırakılamaz.';
            }

            if ($errors === []) {
                try {
                    connect_without_database($db_host, $db_user, $db_pass);
                } catch (PDOException $e) {
                    $errors[] = 'Veritabanı sunucusuna bağlanılamadı: ' . $e->getMessage();
                }
            }

            if ($errors === []) {
                $kurulum['db'] = compact('db_host', 'db_name', 'db_user', 'db_pass');
                header('Location: index.php?adim=site');
                exit;
            }
        }

        /* ---------- ADIM 3: SİTE AYARLARI ---------- */
        if ($adim === 'site') {
            [$site_adi, $siteHata] = validate_text($_POST['site_adi'] ?? '', 'Site adı', 2, 150);
            $site_aciklama = trim((string) ($_POST['site_aciklama'] ?? ''));
            $site_url      = trim((string) ($_POST['site_url'] ?? ''));

            if ($siteHata !== null) {
                $errors[] = $siteHata;
            }

            if ($errors === []) {
                $kurulum['site'] = compact('site_adi', 'site_aciklama', 'site_url');
                header('Location: index.php?adim=yonetici');
                exit;
            }
        }

        /* ---------- ADIM 4: YÖNETİCİ + KURULUMU ÇALIŞTIR ---------- */
        if ($adim === 'yonetici') {
            [$admin_ad, $adHata]         = validate_name($_POST['admin_ad'] ?? '', 'Ad');
            [$admin_soyad, $soyadHata]   = validate_name($_POST['admin_soyad'] ?? '', 'Soyad');
            [$admin_kadi, $kadiHata]     = validate_username($_POST['admin_kadi'] ?? '');
            [$admin_eposta, $epostaHata] = validate_email($_POST['admin_eposta'] ?? '');
            $admin_sifre = (string) ($_POST['admin_sifre'] ?? '');

            foreach ([$adHata, $soyadHata, $kadiHata, $epostaHata] as $fieldError) {
                if ($fieldError !== null) {
                    $errors[] = $fieldError;
                }
            }

            $sifreHata = validate_password($admin_sifre);
            if ($sifreHata !== null) {
                $errors[] = $sifreHata;
            }

            if (empty($kurulum['db']) || empty($kurulum['site'])) {
                header('Location: index.php?adim=veritabani');
                exit;
            }

            if ($errors === []) {
                try {
                    $db   = $kurulum['db'];
                    $site = $kurulum['site'];

                    $pdo = connect_without_database($db['db_host'], $db['db_user'], $db['db_pass']);

                    $pdo->exec(sprintf(
                        'CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci',
                        $db['db_name']
                    ));
                    $pdo->exec(sprintf('USE `%s`', $db['db_name']));

                    $statements = import_schema($pdo, SCHEMA_PATH);

                    $settingsStmt = $pdo->prepare('UPDATE ayarlar SET deger = :deger WHERE anahtar = :anahtar');
                    foreach ([
                        'site_adi'        => $site['site_adi'],
                        'site_aciklama'   => $site['site_aciklama'],
                        'site_url'        => $site['site_url'],
                        'iletisim_eposta' => $admin_eposta,
                    ] as $anahtar => $deger) {
                        $settingsStmt->execute([':deger' => $deger, ':anahtar' => $anahtar]);
                    }

                    $pdo->prepare(
                        'INSERT INTO kullanicilar (ad, soyad, kullanici_adi, eposta, sifre, rol, durum)
                         VALUES (:ad, :soyad, :kadi, :eposta, :sifre, :rol, :durum)'
                    )->execute([
                        ':ad'     => $admin_ad,
                        ':soyad'  => $admin_soyad,
                        ':kadi'   => $admin_kadi,
                        ':eposta' => $admin_eposta,
                        ':sifre'  => hash_password($admin_sifre),
                        ':rol'    => 'admin',
                        ':durum'  => 'aktif',
                    ]);

                    write_env_file(ENV_PATH, [
                        'APP_NAME'         => $site['site_adi'],
                        'APP_DESCRIPTION'  => $site['site_aciklama'],
                        'APP_URL'          => $site['site_url'],
                        'APP_DEBUG'        => 'true',
                        'APP_PRETTY_URLS'  => 'false',
                        'DB_HOST'          => $db['db_host'],
                        'DB_PORT'          => '3306',
                        'DB_NAME'          => $db['db_name'],
                        'DB_USER'          => $db['db_user'],
                        'DB_PASS'          => $db['db_pass'],
                    ]);

                    $_SESSION['kurulum_sonuc'] = [
                        'db_name'    => $db['db_name'],
                        'statements' => $statements,
                        'kadi'       => $admin_kadi,
                        'eposta'     => $admin_eposta,
                        'site_adi'   => $site['site_adi'],
                    ];
                    unset($_SESSION['kurulum']);

                    header('Location: index.php?adim=tamam');
                    exit;

                } catch (PDOException $e) {
                    $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

if (!$kilitli) {
    if ($adim === 'site' && empty($kurulum['db'])) {
        $adim = 'veritabani';
    }
    if ($adim === 'yonetici' && (empty($kurulum['db']) || empty($kurulum['site']))) {
        $adim = empty($kurulum['db']) ? 'veritabani' : 'site';
    }
    if ($adim === 'tamam' && empty($_SESSION['kurulum_sonuc'])) {
        $adim = 'gereksinimler';
    }
}

$sonuc = $_SESSION['kurulum_sonuc'] ?? null;

$adimAnahtarlari = array_keys(ADIMLAR);
$aktifIndeks     = array_search($adim, $adimAnahtarlari, true);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Kurulum Sihirbazı | Çılgın Yazılım</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/cilginyazilim.css">
    <style>
        body.cy-app { min-height: 100vh; display: flex; align-items: center; padding: 2rem 0;
            background:
                radial-gradient(480px 260px at 15% 0%, var(--cy-brand-50), transparent 60%),
                radial-gradient(420px 260px at 100% 100%, var(--cy-brand-100), transparent 55%); }

        .kurulum-wizard { box-shadow: var(--cy-shadow-sm); }

        .kurulum-wizard .cy-card__header {
            background: var(--cy-gradient);
            border-bottom: 0;
            border-radius: var(--cy-radius-lg) var(--cy-radius-lg) 0 0;
        }
        .kurulum-wizard .cy-card__header img { background: #fff; border-radius: 8px; padding: 3px; }
        .kurulum-wizard .cy-card__header strong { color: #fff; font-size: .95rem; }

        .kurulum-steps { display: flex; gap: .35rem; list-style: none; padding: 0; margin: 1rem 0 0; flex-wrap: wrap; }
        .kurulum-steps li { flex: 1 1 0; min-width: 90px; font-size: .74rem; font-weight: 600; letter-spacing: .03em;
            text-transform: uppercase; color: rgba(255,255,255,.65); padding-top: .55rem; border-top: 3px solid rgba(255,255,255,.25);
            transition: color .2s, border-color .2s; }
        .kurulum-steps li.is-active,
        .kurulum-steps li.is-done { color: #fff; border-top-color: #fff; }

        .kurulum-summary { background: var(--cy-surface-soft); border: 1px solid var(--cy-border); border-radius: var(--cy-radius);
            padding: 1rem; font-family: var(--cy-font-mono, monospace); font-size: .8125rem; line-height: 1.8; }

        @media (max-width: 575.98px) {
            .kurulum-steps li { min-width: 72px; font-size: .65rem; }
        }
    </style>
</head>
<body class="cy-app">
<div class="container" style="max-width: 720px">

    <div class="cy-card kurulum-wizard">
        <div class="cy-card__header">
            <div class="w-100">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <img src="../assets/images/logo.png" alt="" style="width:32px;height:32px">
                    <strong>Kurulum Sihirbazı</strong>
                </div>
                <ul class="kurulum-steps">
                    <?php foreach (ADIMLAR as $key => $label): ?>
                        <?php $index = array_search($key, $adimAnahtarlari, true); ?>
                        <li class="<?= $index === $aktifIndeks ? 'is-active' : ($index < $aktifIndeks ? 'is-done' : '') ?>">
                            <?= e($label) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="cy-card__body">

            <?php foreach ($errors as $error): ?>
                <div class="cy-alert cy-alert--danger mb-3"><?= e($error) ?></div>
            <?php endforeach; ?>

            <?php if ($kilitli): ?>
                <!-- ============ KİLİTLİ: kurulum zaten tamamlanmış ============ -->
                <div class="cy-alert cy-alert--info mb-3">
                    Kurulum daha önce tamamlanmış. Yeniden kurmak isterseniz
                    <code>?yeniden=1</code> ekleyerek zorlayabilirsiniz (verileriniz üzerine yazılabilir, dikkatli olun).
                </div>
                <a class="btn cy-btn cy-btn--primary" href="../index.php">Siteye Git</a>

            <?php elseif ($adim === 'gereksinimler'): ?>
                <!-- ============ ADIM 1 ============ -->
                <h2 class="cy-title mb-1">Gereksinimler</h2>
                <p class="cy-subtitle mb-3">Kuruluma başlamadan önce sunucunuzun aşağıdaki koşulları sağlaması gerekir.</p>

                <div class="cy-timeline mb-3">
                    <?php foreach ($checks as $check): ?>
                        <div class="cy-timeline__item">
                            <span class="cy-timeline__icon cy-timeline__icon--<?= $check['ok'] ? 'success' : 'warning' ?>">
                                <?= $check['ok'] ? '✓' : '!' ?>
                            </span>
                            <div class="cy-timeline__body">
                                <p class="cy-timeline__text"><strong><?= e($check['label']) ?></strong></p>
                                <?php if ($check['note'] !== ''): ?><span class="cy-timeline__meta"><?= e($check['note']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($allChecksOk): ?>
                    <a class="btn cy-btn cy-btn--primary" href="index.php?adim=veritabani">Devam Et →</a>
                <?php else: ?>
                    <button type="button" class="btn cy-btn cy-btn--ghost" disabled>Önce yukarıdaki sorunları giderin</button>
                <?php endif; ?>

            <?php elseif ($adim === 'veritabani'): ?>
                <!-- ============ ADIM 2 ============ -->
                <h2 class="cy-title mb-1">Veritabanı Bilgileri</h2>
                <p class="cy-subtitle mb-3">Bağlantı formu gönderildiğinde hemen test edilir.</p>

                <form method="post" action="index.php?adim=veritabani" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="db_host">Sunucu</label>
                            <input type="text" class="form-control" id="db_host" name="db_host" value="<?= e(eski('db_host', $kurulum['db'] ?? [], '127.0.0.1')) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="db_name">Veritabanı Adı</label>
                            <input type="text" class="form-control" id="db_name" name="db_name" value="<?= e(eski('db_name', $kurulum['db'] ?? [], 'yeni_proje')) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="db_user">Kullanıcı Adı</label>
                            <input type="text" class="form-control" id="db_user" name="db_user" value="<?= e(eski('db_user', $kurulum['db'] ?? [], 'root')) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="db_pass">Parola</label>
                            <input type="password" class="form-control" id="db_pass" name="db_pass" value="<?= e(eski('db_pass', $kurulum['db'] ?? [])) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn cy-btn cy-btn--primary mt-3">Bağlantıyı Test Et ve Devam Et →</button>
                </form>

            <?php elseif ($adim === 'site'): ?>
                <!-- ============ ADIM 3 ============ -->
                <h2 class="cy-title mb-1">Site Ayarları</h2>
                <p class="cy-subtitle mb-3">Bu bilgileri daha sonra yönetim panelinden değiştirebilirsiniz.</p>

                <form method="post" action="index.php?adim=site" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="site_adi">Site Adı</label>
                            <input type="text" class="form-control" id="site_adi" name="site_adi" value="<?= e(eski('site_adi', $kurulum['site'] ?? [], 'Yeni Proje')) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="site_aciklama">Site Açıklaması</label>
                            <textarea class="form-control" id="site_aciklama" name="site_aciklama" rows="2"><?= e(eski('site_aciklama', $kurulum['site'] ?? [], 'Çılgın Yazılım örnek uygulaması')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="site_url">Site Adresi</label>
                            <input type="url" class="form-control" id="site_url" name="site_url" value="<?= e(eski('site_url', $kurulum['site'] ?? [], guess_site_url())) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn cy-btn cy-btn--primary mt-3">Devam Et →</button>
                </form>

            <?php elseif ($adim === 'yonetici'): ?>
                <!-- ============ ADIM 4 ============ -->
                <h2 class="cy-title mb-1">Yönetici Hesabı</h2>
                <p class="cy-subtitle mb-3">Bu bilgilerle panele giriş yapacaksınız. Bu adım kurulumu ÇALIŞTIRIR.</p>

                <form method="post" action="index.php?adim=yonetici" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="admin_ad">Ad</label>
                            <input type="text" class="form-control" id="admin_ad" name="admin_ad" value="<?= e($_POST['admin_ad'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="admin_soyad">Soyad</label>
                            <input type="text" class="form-control" id="admin_soyad" name="admin_soyad" value="<?= e($_POST['admin_soyad'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="admin_kadi">Kullanıcı Adı</label>
                            <input type="text" class="form-control" id="admin_kadi" name="admin_kadi" value="<?= e($_POST['admin_kadi'] ?? 'admin') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="admin_eposta">E-posta</label>
                            <input type="email" class="form-control" id="admin_eposta" name="admin_eposta" value="<?= e($_POST['admin_eposta'] ?? '') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="admin_sifre">Parola</label>
                            <input type="password" class="form-control" id="admin_sifre" name="admin_sifre" required>
                            <div class="form-text">En az 8 karakter; harf ve rakam içermelidir.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn cy-btn cy-btn--primary mt-3">Kurulumu Tamamla</button>
                </form>

            <?php elseif ($adim === 'tamam' && $sonuc !== null): ?>
                <!-- ============ ADIM 5 ============ -->
                <div class="cy-alert cy-alert--success mb-3">Kurulum başarıyla tamamlandı!</div>

                <div class="kurulum-summary mb-3">
                    Veritabanı: <?= e($sonuc['db_name']) ?> (<?= (int) $sonuc['statements'] ?> ifade çalıştırıldı)<br>
                    Site: <?= e($sonuc['site_adi']) ?><br>
                    Yönetici: <?= e($sonuc['kadi']) ?> · <?= e($sonuc['eposta']) ?>
                </div>

                <div class="cy-alert cy-alert--warning mb-3">
                    <strong>Son adım:</strong> Güvenlik için "kurulum/" klasörünü şimdi silin.
                    Silmezseniz herkes bu sihirbaza erişip veritabanınızı sıfırlamayı deneyebilir.
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="islem" value="temizle">
                        <button type="submit" class="btn cy-btn cy-btn--danger">Kurulum Klasörünü Sil</button>
                    </form>
                    <a class="btn cy-btn cy-btn--ghost" href="../index.php?r=giris">Kurulum klasörünü elle sileceğim, girişe git</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
