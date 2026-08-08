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
 *    5) Tamamlandı     → özet ve güvenlik uyarısı
 *
 *  TASARIM NOTLARI:
 *
 *  ► Bu dosya BİLEREK system/config.php'yi DAHİL ETMEZ. config.php
 *    açılışta veritabanına bağlanmaya çalışır; veritabanı henüz yokken
 *    (asıl çözmeye çalıştığımız durum) uygulama burada çökerdi.
 *
 *  ► Adımlar arasında veri $_SESSION içinde taşınır. Hiçbir şey
 *    veritabanına yazılmaz; yazma işlemi SADECE son adımda, tüm
 *    bilgiler toplandıktan sonra tek seferde yapılır. Böylece kurulum
 *    yarıda kalırsa ortada yarım bir veritabanı bırakmayız.
 *
 *  ► POST-Redirect-GET deseni: Form gönderilip doğrulandıktan sonra
 *    bir sonraki adıma YÖNLENDİRME yapılır. Bu sayede kullanıcı
 *    sayfayı yenilediğinde "formu tekrar gönder" uyarısı almaz ve
 *    aynı işlem iki kez çalışmaz.
 *
 *  ► CANLI ORTAMA ÇIKARKEN BU DOSYAYI SİLİN. Kurulum tamamlandıysa
 *    dosya kendini otomatik kilitler, ama silmek en güvenlisidir.
 * =====================================================================
 */

declare(strict_types=1);

// Bu iki dosyanın üst düzey yan etkisi yoktur (sadece fonksiyon/sabit
// tanımı içerirler), config.php olmadan güvenle yüklenebilirler.
require_once __DIR__ . '/system/function.php';
require_once __DIR__ . '/system/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ENV_PATH      = __DIR__ . '/.env';
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

$csrfToken = csrf_token();

// Adımlar arası taşınan veri.
$_SESSION['kurulum'] = $_SESSION['kurulum'] ?? [];
$kurulum = &$_SESSION['kurulum'];

$errors = [];


/* =====================================================================
 *  KURULUM ZATEN TAMAMLANDI MI?
 * ---------------------------------------------------------------------
 *  .env varsa VE veritabanında en az bir yönetici hesabı varsa kurulum
 *  bitmiş demektir. Bu durumda sihirbazı kilitliyoruz — aksi halde
 *  siteye giren herkes veritabanınızı sıfırlayıp kendini yönetici
 *  yapabilirdi.
 * ------------------------------------------------------------------ */
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
        $alreadyInstalled = false;   // Bağlanamadı/tablo yok → kurulum bitmemiş
    }
}

// Bilerek yeniden kurmak isteyen geliştirici için: install.php?yeniden=1
$forceReinstall = isset($_GET['yeniden']);

// Hangi adımdayız?
$adim = (string) ($_GET['adim'] ?? 'gereksinimler');
if (!array_key_exists($adim, ADIMLAR)) {
    $adim = 'gereksinimler';
}

/* Kurulum AZ ÖNCE bu oturumda tamamlandıysa, son adımı göstermeliyiz.
 * Bu kontrol olmazsa şöyle bir tuhaflık oluşur: kurulum başarıyla
 * biter, "?adim=tamam" adresine yönlendiriliriz, ama artık
 * $alreadyInstalled true olduğu için sihirbaz kendini kilitler ve
 * kullanıcı yönetici bilgilerini gördüğü özet ekranını HİÇ göremez. */
$yeniBitti = ($adim === 'tamam' && !empty($_SESSION['kurulum_sonuc']));

$kilitli = $alreadyInstalled && !$forceReinstall && !$yeniBitti;


/* =====================================================================
 *  SİSTEM KONTROLLERİ
 * ================================================================== */
$checks = [
    [
        'label' => 'PHP sürümü 8.0 veya üzeri',
        'ok'    => version_compare(PHP_VERSION, '8.0.0', '>='),
        'note'  => 'Mevcut sürüm: ' . PHP_VERSION,
    ],
    [
        'label' => 'pdo_mysql eklentisi yüklü',
        'ok'    => extension_loaded('pdo_mysql'),
        'note'  => extension_loaded('pdo_mysql') ? '' : 'php.ini içinde extension=pdo_mysql satırını açın.',
    ],
    [
        'label' => 'mbstring eklentisi yüklü',
        'ok'    => extension_loaded('mbstring'),
        'note'  => extension_loaded('mbstring') ? '' : 'Türkçe karakter işlemleri için gereklidir.',
    ],
    [
        'label' => 'Proje klasörüne yazma izni (.env için)',
        'ok'    => is_writable(__DIR__),
        'note'  => is_writable(__DIR__) ? '' : 'Klasör izinlerini kontrol edin.',
    ],
    [
        'label' => 'database.sql dosyası mevcut',
        'ok'    => is_file(SCHEMA_PATH),
        'note'  => is_file(SCHEMA_PATH) ? '' : 'Şema dosyası bulunamadı.',
    ],
    [
        'label' => 'upload/ klasörü yazılabilir',
        'ok'    => !is_dir(__DIR__ . '/upload') || is_writable(__DIR__ . '/upload'),
        'note'  => 'Avatar ve görsel yükleme için gerekli.',
    ],
];
$allChecksOk = !in_array(false, array_column($checks, 'ok'), true);


/* =====================================================================
 *  FORM İŞLEME
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$kilitli) {

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
                /* Veritabanı adı CREATE DATABASE ifadesine prepared
                 * statement ile bind EDİLEMEZ (yer tutucu kabul etmez).
                 * Sadece harf/rakam/alt çizgiye izin vererek SQL
                 * Injection'ı baştan imkânsız kılıyoruz. */
                $errors[] = 'Veritabanı adı yalnızca harf, rakam ve alt çizgi (_) içerebilir, rakamla başlayamaz.';
            }
            if ($db_user === '') {
                $errors[] = 'Veritabanı kullanıcı adı boş bırakılamaz.';
            }

            // BAĞLANTIYI HEMEN TEST ET — kullanıcı en baştan bilsin.
            if ($errors === []) {
                try {
                    connect_without_database($db_host, $db_user, $db_pass);
                } catch (PDOException $e) {
                    $errors[] = 'Veritabanı sunucusuna bağlanılamadı: ' . $e->getMessage();
                }
            }

            if ($errors === []) {
                $kurulum['db'] = compact('db_host', 'db_name', 'db_user', 'db_pass');
                header('Location: install.php?adim=site');
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
                header('Location: install.php?adim=yonetici');
                exit;
            }
        }

        /* ---------- ADIM 4: YÖNETİCİ + KURULUMU ÇALIŞTIR ---------- */
        if ($adim === 'yonetici') {
            [$admin_ad, $adHata]        = validate_name($_POST['admin_ad'] ?? '', 'Ad');
            [$admin_soyad, $soyadHata]  = validate_name($_POST['admin_soyad'] ?? '', 'Soyad');
            [$admin_kadi, $kadiHata]    = validate_username($_POST['admin_kadi'] ?? '');
            [$admin_eposta, $epostaHata] = validate_email($_POST['admin_eposta'] ?? '', 'E-posta');
            $admin_sifre = (string) ($_POST['admin_sifre'] ?? '');

            foreach ([$adHata, $soyadHata, $kadiHata, $epostaHata] as $fieldError) {
                if ($fieldError !== null) {
                    $errors[] = $fieldError;
                }
            }

            $sifreHata = validate_password($admin_sifre, 'Parola');
            if ($sifreHata !== null) {
                $errors[] = $sifreHata;
            }

            // Önceki adımlar atlanmışsa (doğrudan URL ile gelinmişse) geri gönder.
            if (empty($kurulum['db']) || empty($kurulum['site'])) {
                header('Location: install.php?adim=veritabani');
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

                    // 1) Şemayı kur (tablolar + varsayılan ayarlar)
                    $statements = import_schema($pdo, SCHEMA_PATH);

                    // 2) Formdan gelen site ayarlarını yaz
                    $settingsStmt = $pdo->prepare('UPDATE ayarlar SET deger = :deger WHERE anahtar = :anahtar');
                    foreach ([
                        'site_adi'        => $site['site_adi'],
                        'site_aciklama'   => $site['site_aciklama'],
                        'site_url'        => $site['site_url'],
                        'iletisim_eposta' => $admin_eposta,
                    ] as $anahtar => $deger) {
                        $settingsStmt->execute([':deger' => $deger, ':anahtar' => $anahtar]);
                    }

                    // 3) Yönetici hesabını oluştur
                    $pdo->prepare(
                        'INSERT INTO kullanicilar (ad, soyad, kullanici_adi, eposta, sifre, rol, durum)
                         VALUES (:ad, :soyad, :kadi, :eposta, :sifre, :rol, :durum)'
                    )->execute([
                        ':ad'     => $admin_ad,
                        ':soyad'  => $admin_soyad,
                        ':kadi'   => $admin_kadi,
                        ':eposta' => $admin_eposta,
                        // Parolanın KENDİSİ değil, geri döndürülemez özeti saklanır.
                        ':sifre'  => hash_password($admin_sifre),
                        ':rol'    => 'admin',
                        ':durum'  => 'aktif',
                    ]);

                    // 4) .env dosyasını yaz
                    write_env_file(ENV_PATH, [
                        'APP_NAME'        => $site['site_adi'],
                        'APP_DESCRIPTION' => $site['site_aciklama'],
                        'DB_HOST'         => $db['db_host'],
                        'DB_NAME'         => $db['db_name'],
                        'DB_USER'         => $db['db_user'],
                        'DB_PASS'         => $db['db_pass'],
                        'APP_DEBUG'       => 'true',
                    ]);

                    // Özeti sakla, hassas verileri oturumdan temizle.
                    $_SESSION['kurulum_sonuc'] = [
                        'db_name'    => $db['db_name'],
                        'statements' => $statements,
                        'kadi'       => $admin_kadi,
                        'eposta'     => $admin_eposta,
                        'site_adi'   => $site['site_adi'],
                    ];
                    unset($_SESSION['kurulum']);

                    header('Location: install.php?adim=tamam');
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

/* Doğrudan URL ile ileri adıma atlanmasını engelle. */
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


/* =====================================================================
 *  YARDIMCI FONKSİYONLAR
 * ================================================================== */

/** Sitenin kök adresini istekten tahmin eder (form için varsayılan). */
function guess_site_url(): string
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return '';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir    = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

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
 *
 * CREATE DATABASE / USE satırları BİLEREK atlanır: veritabanını zaten
 * kullanıcının seçtiği adla oluşturup seçtik. Şema dosyasındaki isim
 * (varsayılan "yeni_proje") farklı olsa bile sorun çıkmaz.
 *
 * @throws RuntimeException Dosya okunamazsa
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
 *
 * Tırnak içindeki noktalı virgülleri (örn. bir INSERT değerinde ";"
 * geçmesi) yanlışlıkla komut sonu saymamak için karakter karakter
 * okuyup tırnak durumunu takip eder. Saklı yordam gibi gövdesinde ";"
 * barındıran karmaşık yapılar için tasarlanmadı — bu şablonun ürettiği
 * şema dosyaları için yeterlidir.
 *
 * @return string[]
 */
function split_sql_statements(string $sql): array
{
    // Tek satırlık "-- ..." yorumlarını temizle.
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

/** Ayarları ".env" dosyasına yazar. */
function write_env_file(string $path, array $values): void
{
    $lines = [
        '# Bu dosya install.php tarafından otomatik oluşturuldu.',
        '# system/config.php tarafından okunur. .gitignore içinde olduğu',
        '# için Git\'e gönderilmez — şifreniz burada güvende kalır.',
        '',
    ];

    foreach ($values as $key => $value) {
        // Değerde boşluk/özel karakter varsa çift tırnak içine al.
        $needsQuotes = $value === '' || preg_match('/\s|[#"\']/', $value);
        $safeValue   = str_replace('"', '\\"', $value);
        $lines[]     = $key . '=' . ($needsQuotes ? '"' . $safeValue . '"' : $safeValue);
    }

    if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
        throw new RuntimeException('.env dosyası yazılamadı. Klasör izinlerini kontrol edin.');
    }
}

/** Formda önceki değeri göstermek için: POST > oturum > varsayılan. */
function eski(string $field, array $store, string $default = ''): string
{
    if (isset($_POST[$field])) {
        return (string) $_POST[$field];
    }
    return (string) ($store[$field] ?? $default);
}

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
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/cilginyazilim.css">
    <style>
        /* Adım göstergesi — sihirbaza özel, tasarım kalıbını kirletmesin
           diye burada duruyor. */
        .kurulum-adimlar {
            display: flex;
            gap: .35rem;
            list-style: none;
            padding: 0;
            margin: 1rem 0 0;
            flex-wrap: wrap;
        }
        .kurulum-adimlar li {
            flex: 1 1 0;
            min-width: 90px;
            font-size: .74rem;
            font-weight: 600;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .65);
            padding-top: .55rem;
            border-top: 3px solid rgba(255, 255, 255, .25);
            transition: color .18s ease, border-color .18s ease;
        }
        .kurulum-adimlar li.tamamlandi { color: rgba(255,255,255,.9); border-top-color: rgba(255,255,255,.7); }
        .kurulum-adimlar li.aktif      { color: #fff; border-top-color: #fff; }
        .kurulum-adimlar .numara {
            display: inline-block;
            min-width: 1.25rem;
            opacity: .8;
        }
    </style>
</head>
<body class="cy-app">
    <div class="cy-topbar"></div>

    <div class="container py-4 py-lg-5" style="max-width: 720px;">
        <div class="cy-card">

            <div class="cy-card__header">
                <div class="cy-brand">
                    <span class="cy-brand__mark">
                        <img src="assets/images/logo.png" alt="Çılgın Yazılım logosu">
                    </span>
                    <div>
                        <h1 class="cy-brand__title">Kurulum Sihirbazı</h1>
                        <p class="cy-brand__subtitle">Çılgın Yazılım PHP Başlangıç Şablonu</p>
                    </div>
                </div>

                <?php if (!$kilitli): ?>
                    <!-- Adım göstergesi -->
                    <ol class="kurulum-adimlar">
                        <?php foreach (ADIMLAR as $anahtar => $baslik): ?>
                            <?php
                            $indeks = array_search($anahtar, $adimAnahtarlari, true);
                            $sinif  = $indeks === $aktifIndeks ? 'aktif'
                                    : ($indeks < $aktifIndeks ? 'tamamlandi' : '');
                            ?>
                            <li class="<?= $sinif ?>">
                                <span class="numara"><?= (int) $indeks + 1 ?>.</span><?= e($baslik) ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <div class="cy-card__body">

            <?php if ($kilitli): ?>
                <!-- ============ KİLİTLİ ============ -->
                <div class="alert alert-success" role="alert">
                    <strong>Bu proje zaten kurulmuş.</strong>
                    Veritabanı bağlantısı çalışıyor ve en az bir yönetici hesabı mevcut.
                </div>
                <div class="alert alert-warning" role="alert">
                    Güvenliğiniz için kurulum sihirbazı kilitlendi.
                    <strong>Bu dosyayı (<code>install.php</code>) silmenizi öneririz.</strong>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="giris.php" class="btn cy-btn cy-btn--primary">Giriş Yap</a>
                    <a href="index.php" class="btn btn-outline-secondary cy-btn">Siteyi Gör</a>
                    <a href="install.php?yeniden=1&adim=gereksinimler" class="btn btn-outline-danger cy-btn btn-sm ms-auto">
                        Yine de yeniden kur
                    </a>
                </div>

            <?php else: ?>

                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($forceReinstall && $alreadyInstalled && $adim !== 'tamam'): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong>Dikkat:</strong> Bu proje zaten kurulu. Devam ederseniz
                        <code>ayarlar</code> ve <code>kullanicilar</code> tabloları
                        <u>silinip yeniden oluşturulur</u> — mevcut tüm kullanıcılar
                        ve ayarlar kaybolur.
                    </div>
                <?php endif; ?>


                <?php if ($adim === 'gereksinimler'): ?>
                    <!-- ============ ADIM 1 ============ -->
                    <p class="cy-muted">
                        Kuruluma başlamadan önce sunucunuzun gereksinimleri karşıladığını kontrol edelim.
                    </p>

                    <ul class="list-unstyled my-4">
                        <?php foreach ($checks as $check): ?>
                            <li class="mb-2 d-flex align-items-start gap-2">
                                <span style="width:1.4em; flex-shrink:0;">
                                    <?= $check['ok']
                                        ? '<span style="color:#16a34a">&#10003;</span>'
                                        : '<span style="color:#dc2626">&#10007;</span>' ?>
                                </span>
                                <span>
                                    <?= e($check['label']) ?>
                                    <?php if ($check['note'] !== ''): ?>
                                        <br><small class="cy-muted"><?= e($check['note']) ?></small>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($allChecksOk): ?>
                        <a href="install.php?adim=veritabani<?= $forceReinstall ? '&yeniden=1' : '' ?>"
                           class="btn cy-btn cy-btn--primary">Kuruluma Başla →</a>
                    <?php else: ?>
                        <div class="alert alert-danger mb-3" role="alert">
                            Yukarıdaki sorunları giderin, sonra sayfayı yenileyin.
                        </div>
                        <a href="install.php" class="btn btn-outline-secondary cy-btn">Yeniden Kontrol Et</a>
                    <?php endif; ?>


                <?php elseif ($adim === 'veritabani'): ?>
                    <!-- ============ ADIM 2 ============ -->
                    <p class="cy-muted">
                        Veritabanı bilgilerinizi girin. Veritabanı yoksa sizin için oluşturulur.
                        <strong>Devam etmeden önce bağlantı test edilir.</strong>
                    </p>

                    <form method="post" action="install.php?adim=veritabani<?= $forceReinstall ? '&yeniden=1' : '' ?>" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="row g-3 mb-4">
                            <div class="col-sm-7">
                                <label for="db_host" class="form-label">Sunucu</label>
                                <input type="text" name="db_host" id="db_host" class="form-control"
                                       value="<?= e(eski('db_host', $kurulum['db'] ?? [], '127.0.0.1')) ?>">
                            </div>
                            <div class="col-sm-5">
                                <label for="db_name" class="form-label">Veritabanı Adı <span class="text-danger">*</span></label>
                                <input type="text" name="db_name" id="db_name" class="form-control"
                                       placeholder="ornek_proje"
                                       value="<?= e(eski('db_name', $kurulum['db'] ?? [])) ?>">
                                <div class="form-text">Yoksa oluşturulur.</div>
                            </div>
                            <div class="col-sm-7">
                                <label for="db_user" class="form-label">Kullanıcı Adı</label>
                                <input type="text" name="db_user" id="db_user" class="form-control"
                                       value="<?= e(eski('db_user', $kurulum['db'] ?? [], 'root')) ?>">
                            </div>
                            <div class="col-sm-5">
                                <label for="db_pass" class="form-label">Parola</label>
                                <input type="password" name="db_pass" id="db_pass" class="form-control"
                                       value="<?= e(eski('db_pass', $kurulum['db'] ?? [])) ?>"
                                       placeholder="(XAMPP'ta genellikle boş)">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="install.php?adim=gereksinimler<?= $forceReinstall ? '&yeniden=1' : '' ?>"
                               class="btn btn-outline-secondary cy-btn">← Geri</a>
                            <button type="submit" class="btn cy-btn cy-btn--primary">Bağlantıyı Test Et ve Devam →</button>
                        </div>
                    </form>


                <?php elseif ($adim === 'site'): ?>
                    <!-- ============ ADIM 3 ============ -->
                    <div class="alert alert-success py-2" role="alert">
                        <small>
                            Veritabanı bağlantısı doğrulandı:
                            <strong><?= e($kurulum['db']['db_user']) ?>@<?= e($kurulum['db']['db_host']) ?></strong>
                            &rarr; <strong><?= e($kurulum['db']['db_name']) ?></strong>
                        </small>
                    </div>

                    <p class="cy-muted">
                        Sitenizin temel bilgileri. Diğer tüm ayarları (sosyal medya, SEO,
                        bakım modu…) kurulumdan sonra yönetim panelinden düzenleyebilirsiniz.
                    </p>

                    <form method="post" action="install.php?adim=site<?= $forceReinstall ? '&yeniden=1' : '' ?>" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="mb-3">
                            <label for="site_adi" class="form-label">Site Adı <span class="text-danger">*</span></label>
                            <input type="text" name="site_adi" id="site_adi" class="form-control"
                                   placeholder="Örn: Çılgın Blog" maxlength="150"
                                   value="<?= e(eski('site_adi', $kurulum['site'] ?? [])) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="site_aciklama" class="form-label">Site Açıklaması</label>
                            <textarea name="site_aciklama" id="site_aciklama" class="form-control" rows="2"
                                      maxlength="255"><?= e(eski('site_aciklama', $kurulum['site'] ?? [], 'Çılgın Yazılım örnek uygulaması')) ?></textarea>
                            <div class="form-text">Arama motorlarında görünen kısa tanıtım.</div>
                        </div>

                        <div class="mb-4">
                            <label for="site_url" class="form-label">Site Adresi</label>
                            <input type="url" name="site_url" id="site_url" class="form-control"
                                   value="<?= e(eski('site_url', $kurulum['site'] ?? [], guess_site_url())) ?>">
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="install.php?adim=veritabani<?= $forceReinstall ? '&yeniden=1' : '' ?>"
                               class="btn btn-outline-secondary cy-btn">← Geri</a>
                            <button type="submit" class="btn cy-btn cy-btn--primary">Devam →</button>
                        </div>
                    </form>


                <?php elseif ($adim === 'yonetici'): ?>
                    <!-- ============ ADIM 4 ============ -->
                    <p class="cy-muted">
                        Son adım. Bu hesap <strong>yönetici (admin)</strong> rolüyle oluşturulur;
                        ayarları ve diğer kullanıcıları yönetebilir.
                    </p>

                    <form method="post" action="install.php?adim=yonetici<?= $forceReinstall ? '&yeniden=1' : '' ?>" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label for="admin_ad" class="form-label">Ad <span class="text-danger">*</span></label>
                                <input type="text" name="admin_ad" id="admin_ad" class="form-control"
                                       maxlength="100" value="<?= e(eski('admin_ad', [])) ?>">
                            </div>
                            <div class="col-sm-6">
                                <label for="admin_soyad" class="form-label">Soyad <span class="text-danger">*</span></label>
                                <input type="text" name="admin_soyad" id="admin_soyad" class="form-control"
                                       maxlength="100" value="<?= e(eski('admin_soyad', [])) ?>">
                            </div>
                            <div class="col-sm-6">
                                <label for="admin_kadi" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                                <input type="text" name="admin_kadi" id="admin_kadi" class="form-control"
                                       maxlength="50" value="<?= e(eski('admin_kadi', [], 'admin')) ?>">
                                <div class="form-text">Harf, rakam, nokta ve alt çizgi.</div>
                            </div>
                            <div class="col-sm-6">
                                <label for="admin_eposta" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" name="admin_eposta" id="admin_eposta" class="form-control"
                                       maxlength="190" value="<?= e(eski('admin_eposta', [])) ?>">
                            </div>
                            <div class="col-12">
                                <label for="admin_sifre" class="form-label">Parola <span class="text-danger">*</span></label>
                                <input type="password" name="admin_sifre" id="admin_sifre" class="form-control"
                                       autocomplete="new-password">
                                <div class="form-text">En az 8 karakter, en az bir harf ve bir rakam içermeli.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="install.php?adim=site<?= $forceReinstall ? '&yeniden=1' : '' ?>"
                               class="btn btn-outline-secondary cy-btn">← Geri</a>
                            <button type="submit" class="btn cy-btn cy-btn--primary">Kurulumu Tamamla ✓</button>
                        </div>
                    </form>


                <?php elseif ($adim === 'tamam' && $sonuc !== null): ?>
                    <!-- ============ ADIM 5 ============ -->
                    <div class="alert alert-success" role="alert">
                        <strong>Kurulum tamamlandı.</strong>
                        "<?= e($sonuc['db_name']) ?>" veritabanı hazır,
                        <?= (int) $sonuc['statements'] ?> SQL komutu çalıştırıldı.
                    </div>

                    <h2 class="h6 text-uppercase cy-muted mb-3">Yönetici Hesabınız</h2>
                    <dl class="cy-detail mb-4">
                        <dt>Site</dt>
                        <dd><?= e($sonuc['site_adi']) ?></dd>
                        <dt>Kullanıcı adı</dt>
                        <dd><?= e($sonuc['kadi']) ?></dd>
                        <dt>E-posta</dt>
                        <dd><?= e($sonuc['eposta']) ?></dd>
                        <dt>Parola</dt>
                        <dd>Kurulumda belirlediğiniz parola</dd>
                    </dl>

                    <div class="alert alert-danger" role="alert">
                        <strong>Son adım — güvenlik:</strong> Bu dosyayı
                        (<code>install.php</code>) şimdi silin. Sunucuda kalırsa,
                        veritabanınızı sıfırlayıp kendini yönetici yapmak isteyen
                        birine kapı açık kalır.
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a href="giris.php" class="btn cy-btn cy-btn--primary">Giriş Yap →</a>
                        <a href="index.php" class="btn btn-outline-secondary cy-btn">Siteyi Gör</a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            </div>

            <div class="cy-card__footer d-flex flex-wrap justify-content-between gap-2">
                <span>Çılgın Yazılım PHP Başlangıç Şablonu</span>
                <span>cilginyazilim.com</span>
            </div>
        </div>
    </div>
</body>
</html>
