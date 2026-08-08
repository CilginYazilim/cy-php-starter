<?php
/**
 * =====================================================================
 *  KURULUM SİHİRBAZI
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Bu dosya BİLEREK system/config.php'yi DAHİL ETMEZ. Çünkü config.php
 *  sayfa yüklenir yüklenmez veritabanına bağlanmaya çalışır ve
 *  veritabanı henüz yoksa (asıl çözmeye çalıştığımız problem budur)
 *  uygulama burada çökerdi.
 *
 *  Bunun yerine:
 *    1. Kullanıcıdan veritabanı bilgilerini alır
 *    2. Veritabanına bağlanır, yoksa oluşturur
 *    3. Varsa database.sql dosyasını çalıştırır
 *    4. Her şeyi ".env" dosyasına yazar (system/config.php bunu okur)
 *
 *  ► CANLI ORTAMA ÇIKARKEN BU DOSYAYI SİLİN veya erişimi kısıtlayın.
 *    Aşağıda kurulum tamamlandıktan sonra da bir hatırlatma gösterilir.
 * =====================================================================
 */

declare(strict_types=1);

// function.php'nin hiçbir üst düzey yan etkisi yoktur (sadece fonksiyon
// tanımları içerir), bu yüzden config.php olmadan güvenle dahil edilebilir.
require __DIR__ . '/system/function.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ENV_PATH      = __DIR__ . '/.env';
const SCHEMA_PATH    = __DIR__ . '/database.sql';
const IDENTIFIER_RE  = '/^[A-Za-z_][A-Za-z0-9_]*$/';

$csrfToken   = csrf_token();
$envExists   = is_file(ENV_PATH);
$existingEnv = $envExists ? parse_env_file(ENV_PATH) : [];

$errors  = [];
$success = null;

// Formun önceki değerlerle (veya .env'den okunan değerlerle) dolu gelmesi için.
$values = [
    'app_name'        => $existingEnv['APP_NAME'] ?? '',
    'app_description' => $existingEnv['APP_DESCRIPTION'] ?? 'Çılgın Yazılım örnek uygulaması',
    'db_host'         => $existingEnv['DB_HOST'] ?? '127.0.0.1',
    'db_name'         => $existingEnv['DB_NAME'] ?? '',
    'db_user'         => $existingEnv['DB_USER'] ?? 'root',
    'db_pass'         => '', // Güvenlik: mevcut şifre formda asla gösterilmez.
    'import_schema'   => true,
];

/* =====================================================================
 *  FORM GÖNDERİLDİYSE İŞLE
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Oturum doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    }

    $values['app_name']        = trim((string) ($_POST['app_name'] ?? ''));
    $values['app_description'] = trim((string) ($_POST['app_description'] ?? ''));
    $values['db_host']         = trim((string) ($_POST['db_host'] ?? ''));
    $values['db_name']         = trim((string) ($_POST['db_name'] ?? ''));
    $values['db_user']         = trim((string) ($_POST['db_user'] ?? ''));
    $values['db_pass']         = (string) ($_POST['db_pass'] ?? '');
    $values['import_schema']   = isset($_POST['import_schema']);

    if ($envExists && !isset($_POST['confirm_overwrite'])) {
        $errors[] = '.env dosyası zaten var. Üzerine yazmak istediğinizi onaylamak için aşağıdaki kutuyu işaretleyin.';
    }

    if ($values['app_name'] === '') {
        $errors[] = 'Uygulama adı boş bırakılamaz.';
    }
    if ($values['db_host'] === '') {
        $errors[] = 'Veritabanı sunucusu boş bırakılamaz.';
    }
    if ($values['db_name'] === '') {
        $errors[] = 'Veritabanı adı boş bırakılamaz.';
    } elseif (!preg_match(IDENTIFIER_RE, $values['db_name'])) {
        // Veritabanı adı SQL'e prepared statement ile bind edilemez
        // (CREATE DATABASE bir yer tutucu kabul etmez). Bu yüzden
        // sadece harf/rakam/alt çizgiye izin vererek SQL Injection'ı
        // baştan imkânsız kılıyoruz.
        $errors[] = 'Veritabanı adı yalnızca harf, rakam ve alt çizgi (_) içerebilir, rakamla başlayamaz.';
    }
    if ($values['db_user'] === '') {
        $errors[] = 'Veritabanı kullanıcı adı boş bırakılamaz.';
    }

    if ($errors === []) {
        try {
            $pdo = connect_without_database($values['db_host'], $values['db_user'], $values['db_pass']);

            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                $values['db_name']
            ));
            $pdo->exec(sprintf('USE `%s`', $values['db_name']));

            $importedStatements = 0;
            if ($values['import_schema'] && is_file(SCHEMA_PATH)) {
                $importedStatements = import_schema($pdo, SCHEMA_PATH);
            }

            write_env_file(ENV_PATH, [
                'APP_NAME'        => $values['app_name'],
                'APP_DESCRIPTION' => $values['app_description'],
                'DB_HOST'         => $values['db_host'],
                'DB_NAME'         => $values['db_name'],
                'DB_USER'         => $values['db_user'],
                'DB_PASS'         => $values['db_pass'],
            ]);

            $success = [
                'db_name'    => $values['db_name'],
                'statements' => $importedStatements,
            ];
        } catch (PDOException $e) {
            $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

/* =====================================================================
 *  SİSTEM KONTROLLERİ (her zaman gösterilir)
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
        'label' => 'Proje klasörüne yazma izni (.env için)',
        'ok'    => is_writable(__DIR__),
        'note'  => is_writable(__DIR__) ? '' : 'Klasör izinlerini kontrol edin.',
    ],
    [
        'label' => 'upload/ klasörü yazılabilir (opsiyonel)',
        'ok'    => !is_dir(__DIR__ . '/upload') || is_writable(__DIR__ . '/upload'),
        'note'  => 'Görsel yükleme kullanmıyorsanız önemli değil.',
    ],
];
$allChecksOk = !in_array(false, array_column($checks, 'ok'), true);


/* =====================================================================
 *  YARDIMCI FONKSİYONLAR
 * ================================================================== */

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

/** Veritabanı seçmeden sadece sunucuya bağlanır (henüz veritabanı yok diye varsayıyoruz). */
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
 * yukarıda kullanıcının seçtiği adla oluşturduk ve seçtik. Şema
 * dosyasındaki isim (örn. varsayılan "yeni_proje") farklı olsa bile
 * sorun çıkmaz.
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
 * okuyup tırnak durumunu takip eder. Saklı yordam (stored procedure)
 * gibi gövdesinde ";" barındıran karmaşık yapılar için tasarlanmadı —
 * bu şablonun ürettiği basit şema dosyaları için yeterlidir.
 *
 * @return string[]
 */
function split_sql_statements(string $sql): array
{
    // Tek satırlık "-- ..." yorumlarını temizle.
    $sql = (string) preg_replace('/^--.*$/m', '', $sql);

    $statements = [];
    $current    = '';
    $inString   = false;
    $quoteChar  = '';
    $length     = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];

        if ($inString) {
            $current .= $char;
            if ($char === $quoteChar && $sql[$i - 1] !== '\\') {
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

    $written = file_put_contents($path, implode("\n", $lines) . "\n");
    if ($written === false) {
        throw new RuntimeException('.env dosyası yazılamadı. Klasör izinlerini kontrol edin.');
    }
}
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
            </div>

            <div class="cy-card__body">

                <?php if ($success !== null): ?>
                    <!-- ===================== BAŞARI EKRANI ===================== -->
                    <div class="alert alert-success" role="alert">
                        <strong>Kurulum tamamlandı.</strong>
                        "<?= e($success['db_name']) ?>" veritabanı hazır.
                        <?php if ($success['statements'] > 0): ?>
                            <?= (int) $success['statements'] ?> SQL komutu başarıyla çalıştırıldı.
                        <?php endif; ?>
                    </div>

                    <div class="alert alert-warning" role="alert">
                        <strong>Önemli:</strong> Canlı ortama geçmeden önce bu dosyayı
                        (<code>install.php</code>) silin veya sunucu düzeyinde erişimini kısıtlayın.
                        Başkasının veritabanınızı yeniden yapılandırmasını istemezsiniz.
                    </div>

                    <a href="index.php" class="btn cy-btn cy-btn--primary">Uygulamayı Aç →</a>

                <?php else: ?>
                    <!-- ===================== SİSTEM KONTROLLERİ ===================== -->
                    <h2 class="h6 text-uppercase cy-muted mb-3">Sistem Kontrolleri</h2>
                    <ul class="list-unstyled mb-4">
                        <?php foreach ($checks as $check): ?>
                            <li class="mb-2 d-flex align-items-start gap-2">
                                <span style="width:1.4em; flex-shrink:0;">
                                    <?= $check['ok'] ? '<span style="color:#16a34a">&#10003;</span>' : '<span style="color:#dc2626">&#10007;</span>' ?>
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

                    <?php if (!$allChecksOk): ?>
                        <div class="alert alert-danger" role="alert">
                            Yukarıdaki sorunları giderin, sonra sayfayı yenileyin.
                        </div>
                    <?php else: ?>

                        <?php if ($envExists): ?>
                            <div class="alert alert-warning" role="alert">
                                <strong>Bu proje zaten yapılandırılmış görünüyor</strong> (<code>.env</code> dosyası bulundu).
                                Mevcut veritabanı: <strong><?= e($existingEnv['DB_NAME'] ?? '?') ?></strong>.
                                Devam ederseniz <code>.env</code> dosyasının üzerine yazılır.
                            </div>
                        <?php endif; ?>

                        <?php if ($errors !== []): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                            <h2 class="h6 text-uppercase cy-muted mb-3 mt-2">Uygulama</h2>
                            <div class="mb-3">
                                <label for="app_name" class="form-label">Uygulama Adı</label>
                                <input type="text" name="app_name" id="app_name" class="form-control"
                                       placeholder="Örn: Not Listesi" maxlength="150"
                                       value="<?= e($values['app_name']) ?>">
                            </div>
                            <div class="mb-3">
                                <label for="app_description" class="form-label">Açıklama</label>
                                <input type="text" name="app_description" id="app_description" class="form-control"
                                       maxlength="255" value="<?= e($values['app_description']) ?>">
                            </div>

                            <h2 class="h6 text-uppercase cy-muted mb-3 mt-4">Veritabanı</h2>
                            <div class="row g-3 mb-3">
                                <div class="col-sm-8">
                                    <label for="db_host" class="form-label">Sunucu</label>
                                    <input type="text" name="db_host" id="db_host" class="form-control"
                                           value="<?= e($values['db_host']) ?>">
                                </div>
                                <div class="col-sm-4">
                                    <label for="db_name" class="form-label">Veritabanı Adı</label>
                                    <input type="text" name="db_name" id="db_name" class="form-control"
                                           placeholder="not_listesi" value="<?= e($values['db_name']) ?>">
                                </div>
                                <div class="col-sm-6">
                                    <label for="db_user" class="form-label">Kullanıcı Adı</label>
                                    <input type="text" name="db_user" id="db_user" class="form-control"
                                           value="<?= e($values['db_user']) ?>">
                                </div>
                                <div class="col-sm-6">
                                    <label for="db_pass" class="form-label">Parola</label>
                                    <input type="password" name="db_pass" id="db_pass" class="form-control"
                                           placeholder="<?= $envExists ? 'Değiştirmemek için boş bırakın' : '(genellikle boş)' ?>">
                                </div>
                            </div>

                            <?php if (is_file(SCHEMA_PATH)): ?>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="import_schema"
                                           id="import_schema" <?= $values['import_schema'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="import_schema">
                                        <code>database.sql</code> dosyasını içe aktar
                                        <br><small class="cy-muted">Tabloları oluşturur ve örnek verileri ekler. Mevcut tablolar varsa üzerine yazılır (DROP TABLE).</small>
                                    </label>
                                </div>
                            <?php endif; ?>

                            <?php if ($envExists): ?>
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" name="confirm_overwrite" id="confirm_overwrite" required>
                                    <label class="form-check-label" for="confirm_overwrite">
                                        Mevcut <code>.env</code> dosyasının üzerine yazılacağını onaylıyorum.
                                    </label>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn cy-btn cy-btn--primary">Kurulumu Başlat</button>
                        </form>

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
