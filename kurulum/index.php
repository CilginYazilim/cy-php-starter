<?php
/**
 * =====================================================================
 *  KURULUM SİHİRBAZI
 *  cilginyazilim.com – CY PHP Starter (PHP Başlangıç Şablonu)
 * ---------------------------------------------------------------------
 *  İKİ EKRAN + BİTİŞ:
 *    1) Veritabanı        → gereksinimler tek satırda; bilgiler girilir,
 *                           "Bağlantıyı dene" anında sonuç verir
 *    2) Site ve yönetici  → site adı/adresi, yönetici hesabı, seçenekler
 *                           (örnek veri, demo modu, geliştirme, PWA,
 *                           modüller). "Kur" kurulumu ÇALIŞTIRIR.
 *    3) Hazır             → yapılan adımların listesi, siteye/panele
 *                           git, kurulum klasörünü sil, demo hesaplar
 *
 *  KURULUM SONUNDA TEK BİR EKSİK KALMAZ:
 *    · veritabanını oluşturur ve database.sql'i içeri aktarır
 *    · yönetici hesabını açar, .env'i yazar, kurulumu kilitler
 *    · database/migrations altındaki ek tabloları ve 1.6 ayarlarını kurar
 *    · seçilen modülleri açar
 *    · istenmişse örnek veriyi yükler (App\Core\DemoData — tek kaynak)
 *
 *  GÜVENLİK:
 *    · KİLİT KOŞULSUZDUR: .env ya da storage/installed.lock varken
 *      hiçbir form işlenmez (veritabanına sorulmaz; bkz. is_installed).
 *    · KURULUM ANAHTARI: sihirbaz yerel bir bilgisayardan açılmıyorsa
 *      storage/kurulum-anahtari.txt dosyasındaki kod istenir. Eskiden
 *      sunucuya ilk ulaşan kişi kurulumu tamamlayıp yönetici olabiliyordu.
 *    · KLASÖRÜ SİLMEK yalnızca kurulumu YAPAN oturumda mümkündür; sonra
 *      Panel → Sistem Bilgisi'nden (yönetici). Eskiden giriş yapmamış
 *      bir ziyaretçi de tetikleyebiliyordu.
 *    · Veritabanı hataları sınıflandırılmış mesajla gösterilir; ham PDO
 *      metni yalnızca yerel bilgisayarda (ayrıntı olarak) görünür.
 *
 *  NEDEN BAĞIMSIZ KOD? Bu dosya .env yazılana KADAR app/ sınıflarını
 *  yüklemez: uygulama .env'in ve veritabanının var olduğunu varsayar.
 *  .env yazıldıktan sonra migration ve örnek veri için uygulamanın kendi
 *  önyüklemesi güvenle kullanılır (bkz. run_migrations).
 *
 *  ► Adımlar arasında veri $_SESSION'da taşınır; veritabanına yalnızca
 *    son adımda, bütün bilgiler toplandıktan sonra yazılır.
 *  ► POST-Redirect-GET: sayfa yenilenince işlem iki kez çalışmaz.
 * =====================================================================
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Proje kökü — .env ve upload/ burada. */
const ROOT_PATH     = __DIR__ . '/..';
const ENV_PATH      = __DIR__ . '/../.env';
const LOCK_PATH     = __DIR__ . '/../storage/installed.lock';
const KEY_PATH      = __DIR__ . '/../storage/kurulum-anahtari.txt';
const SCHEMA_PATH   = __DIR__ . '/database.sql';
const IDENTIFIER_RE = '/^[A-Za-z_][A-Za-z0-9_]*$/';

/** Sihirbazın adımları: anahtar => ilerleme göstergesindeki başlık. */
const ADIMLAR = [
    'veritabani' => 'Veritabanı',
    'kur'        => 'Site ve yönetici',
    'tamam'      => 'Hazır',
];


/* =====================================================================
 *  BAĞIMSIZ YARDIMCI FONKSİYONLAR
 * ---------------------------------------------------------------------
 *  app/Core/Validator.php ve app/Core/Auth.php mantığının küçük,
 *  bağımsız kopyaları — kurulum bittikten sonra app/ klasörü buradan
 *  bağımsız çalışmaya devam eder.
 * ================================================================== */

/* app/Support/helpers.php içindeki karşılıklarıyla AYNI imzayla
 * tanımlanırlar. Kurulumun son adımında uygulamanın önyüklemesi
 * yüklenir; oradaki tanımlar function_exists ile korunduğu için
 * çakışma olmaz, bu sürümler geçerli kalır. */
function e(mixed $value): string
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
    if (!preg_match("/^[\p{L}\p{M}\s.'-]+\z/u", $value)) {
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
    if (!preg_match('/^[a-zA-Z0-9._]+\z/', $value)) {
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
    /* bcrypt ilk 72 BAYTTAN sonrasını yok sayar: daha uzun bir parola
     * sessizce kısaltılmış olurdu (Türkçe harfler 2 bayttır). */
    if (strlen($plain) > 72) {
        return 'Parola en fazla 72 bayt olabilir (yaklaşık 36–72 karakter).';
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

/**
 * Kurulum tamamlanmış mı?
 *
 * YALNIZCA DOSYA VARLIĞINA BAKAR — veritabanına sormaz. Kilit bir
 * sorguya bağlı olsaydı veritabanının kısa bir kesintisi (ya da .env
 * içindeki bozuk bir parola) sihirbazı herkese yeniden açardı.
 */
function is_installed(): bool
{
    return file_exists(ENV_PATH) || file_exists(LOCK_PATH);
}

/**
 * "sunucu" ya da "sunucu:kapı" biçimindeki girdiyi ayırır.
 *
 * @return array{0:string,1:int} [sunucu, kapı]
 */
function split_host(string $host): array
{
    [$sunucu, $kapi] = preg_match('/^(.+):(\d{1,5})\z/', $host, $m) === 1 && !str_contains($m[1], ':')
        ? [$m[1], (int) $m[2]]
        : [$host, 3306];

    /* "localhost" ile PDO Unix soketine bağlanır ve kapıyı yok sayar:
     * "localhost:3399" yanlış kapıya rağmen "Bağlantı başarılı" derdi.
     * Kapı varsayılan değilse TCP (127.0.0.1); .env'e de böyle yazılır.
     * App\Core\Database::host() ile aynı kural. */
    if (strtolower($sunucu) === 'localhost' && $kapi !== 3306) {
        $sunucu = '127.0.0.1';
    }

    return [$sunucu, $kapi];
}

/** Veritabanı seçmeden sadece sunucuya bağlanır (henüz veritabanı yok). */
function connect_without_database(string $host, string $user, string $pass): PDO
{
    [$sunucu, $kapi] = split_host($host);

    return new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $sunucu, $kapi),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

/**
 * Seçilen veritabanında kaç tablo var? (Veritabanı yoksa 0.)
 *
 * Kurulum şeması DROP TABLE içerir. Kullanıcı yanlışlıkla canlı bir
 * veritabanının adını yazarsa her şey silinirdi; bu sayı sıfır değilse
 * sihirbaz açık bir onay ister.
 */
function count_tables(PDO $pdo, string $dbName): int
{
    return count(existing_tables($pdo, $dbName));
}

/** @return array<int,string> Veritabanındaki tablo adları */
function existing_tables(PDO $pdo, string $dbName): array
{
    $stmt = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME'
    );
    $stmt->execute([':db' => $dbName]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Şemanın SİLİP yeniden oluşturduğu tablolar (database.sql'deki
 * "DROP TABLE IF EXISTS" satırlarından okunur; liste tek yerde kalsın).
 *
 * @return array<int,string>
 */
function schema_drop_tables(string $schemaPath): array
{
    $sql = is_file($schemaPath) ? (string) file_get_contents($schemaPath) : '';

    preg_match_all('/^\s*DROP\s+TABLE\s+IF\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s*;/mi', $sql, $m);

    return array_values(array_unique($m[1]));
}

/**
 * Silinecek tablolara BAŞKA (silinmeyecek) tablolardan verilmiş yabancı
 * anahtarlar.
 *
 * NEDEN? Kendi tablonuz "kullanicilar"a yabancı anahtar veriyorsa
 * DROP TABLE hata verir. Eskiden bu, şema yarıdayken olurdu: beş tablo
 * silinmiş, .env yazılmamış, site ne eski ne yeni hâliyle çalışır
 * durumda kalıyordu. Artık hiçbir şeye dokunmadan ÖNCE denetlenir.
 *
 * @param array<int,string> $dropTables
 * @return array<int,string> "tablo (kısıt) → hedef" satırları
 */
function blocking_foreign_keys(PDO $pdo, string $dbName, array $dropTables): array
{
    if ($dropTables === []) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($dropTables), '?'));

    $stmt = $pdo->prepare(
        "SELECT DISTINCT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = ?
            AND REFERENCED_TABLE_SCHEMA = ?
            AND REFERENCED_TABLE_NAME IN ($marks)
            AND TABLE_NAME NOT IN ($marks)
          ORDER BY TABLE_NAME"
    );
    $stmt->execute(array_merge([$dbName, $dbName], $dropTables, $dropTables));

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = $row['TABLE_NAME'] . ' (' . $row['CONSTRAINT_NAME'] . ') → ' . $row['REFERENCED_TABLE_NAME'];
    }

    return $rows;
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

/**
 * database/migrations altındaki EK tabloları kurar.
 *
 * NEDEN BURADA? Şablonun temel tabloları database.sql ile gelir, ama
 * sonradan eklenen tablolar (onbellek, isler, api_anahtarlari)
 * migration dosyalarındadır. Bunları elle "php cy migrate" ile
 * çalıştırmak gerekseydi, komut satırı olmayan bir paylaşımlı
 * hostingde şablon EKSİK kurulurdu — önbellek sürücüsü "veritabani"
 * seçildiğinde ya da API anahtarı üretilmek istendiğinde hata verirdi.
 *
 * Aynı SQL'i database.sql içine KOPYALAMIYORUZ: bir tablo tek bir
 * yerde tanımlanmalıdır. Bunun yerine uygulamanın kendi Migrator'ını
 * çağırıyoruz — böylece kayıt tablosu da doğru doldurulur ve daha
 * sonra çalıştırılan "php cy migrate" bunları tekrar denemez.
 *
 * .env YAZILDIKTAN SONRA çağrılmalıdır.
 *
 * @return array{0:int,1:?string} [kurulan tablo sayısı, hata mesajı]
 */
function run_migrations(): array
{
    $root = realpath(ROOT_PATH);

    if ($root === false || !is_file($root . '/app/bootstrap.php')) {
        return [0, 'Uygulama dosyaları bulunamadı; migration adımı atlandı.'];
    }

    if (!defined('CY_BASE')) {
        define('CY_BASE', $root);
    }
    if (!defined('CY_START')) {
        define('CY_START', microtime(true));
    }

    try {
        require_once CY_BASE . '/app/bootstrap.php';

        $migrator = new App\Core\Database\Migrator(
            App\Core\Database::connection(),
            (string) App\Core\Config::get('db.migrations')
        );

        /* TEMEL PARTİ (0): kurulumla gelen tablolar "php cy
         * migrate:rollback" ile geri alınamaz. Eskiden bu migration'lar
         * 1. partiye yazılıyordu; kurulumdan hemen sonra çalıştırılan
         * bir rollback "sayfalar" tablosunu düşürüp ayar satırlarını
         * siliyordu. */
        return [count($migrator->run(null, true)), null];
    } catch (Throwable $e) {
        /* Migration hatası kurulumu ÇÖKERTMEZ: temel tablolar ve
         * yönetici hesabı zaten hazır, site açılır. Kullanıcıya son
         * ekranda "php cy migrate" çalıştırması söylenir. */
        return [0, $e->getMessage()];
    }
}

/**
 * modules/ klasöründeki modüller: ad => künye.
 *
 * Sihirbaz bu noktada uygulamayı yüklemediği için künye doğrudan
 * module.json'dan okunur; klasör adı kuralı App\Core\Modules\Modules::all()
 * ile aynıdır. module.json'da "kurulumda_acik": true olan modül
 * Site Ayarları adımında İŞARETLİ gelir (Ornek böyledir).
 *
 * @return array<string,array{baslik:string,aciklama:string,varsayilan:bool}>
 */
function discover_modules(): array
{
    $bulunan = [];

    foreach (glob(ROOT_PATH . '/modules/*', GLOB_ONLYDIR) ?: [] as $klasor) {
        $ad = basename($klasor);

        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*\z/', $ad) !== 1) {
            continue;
        }

        $kunye = json_decode((string) @file_get_contents($klasor . '/module.json'), true);
        $kunye = is_array($kunye) ? $kunye : [];

        $bulunan[$ad] = [
            'baslik'     => (string) ($kunye['baslik'] ?? $ad),
            'aciklama'   => (string) ($kunye['aciklama'] ?? ''),
            'varsayilan' => ($kunye['kurulumda_acik'] ?? false) === true,
        ];
    }

    ksort($bulunan);

    return $bulunan;
}

/**
 * Seçilen modülleri açar ve tablolarını kurar — "php cy module
 * --enable=Ad" + "php cy migrate"in sihirbazdaki karşılığı. Sonradan
 * Panel → Sistem Bilgisi → Modüller'den açılıp kapatılabilir.
 *
 * run_migrations()'tan SONRA çağrılır (uygulama orada yüklendi).
 * Modül migration'ları TEMEL PARTİYE yazılmaz: çekirdekten farklı
 * olarak "php cy migrate:rollback" ile geri alınabilmeleri gerekir.
 * Hata kurulumu çökertmez; son ekranda gösterilir.
 *
 * @param array<int,string> $adlar
 * @return array{0:array<int,string>,1:?string} [açılan modüller, hata mesajı]
 */
function enable_modules(array $adlar): array
{
    if ($adlar === [] || !class_exists(App\Core\Modules\Modules::class)) {
        return [[], null];
    }

    $acilan = [];

    try {
        $db = App\Core\Database::connection();
        App\Core\Setting::load($db);

        foreach ($adlar as $ad) {
            if (App\Core\Modules\Modules::enable($ad)) {
                $acilan[] = $ad;
            }
        }

        App\Core\Modules\Modules::forget();
        App\Core\Modules\Modules::migrator($db, (string) App\Core\Config::get('db.migrations'))->run();

        return [$acilan, null];
    } catch (Throwable $e) {
        return [$acilan, $e->getMessage()];
    }
}

/**
 * Açık modüllerin örnek verisini yükler (modules/Ad/seeders/*.php).
 *
 * Yalnızca "Örnek verileri de yükle" seçildiyse ve enable_modules()'tan
 * SONRA çağrılır: modül sınıfları ancak modül açılıp boot edilince
 * yüklenebilir. Hata kurulumu çökertmez; son ekranda gösterilir.
 *
 * @return ?string hata mesajı (yoksa null)
 */
function run_module_seeders(): ?string
{
    if (!class_exists(App\Core\Modules\Modules::class)) {
        return null;
    }

    try {
        $db = App\Core\Database::connection();
        App\Core\Modules\Modules::boot();

        foreach (App\Core\Modules\Modules::seederFiles() as $dosya) {
            $seeder = require $dosya;

            if ($seeder instanceof App\Core\Database\Seeder) {
                $seeder->setConnection($db);
                $seeder->run();
            }
        }

        return null;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/**
 * Bir değeri .env satırına güvenle yazılabilir hale getirir.
 *
 * Okuyucu (App\Core\Env) ile YAZICI aynı dosyada durur; ikisi ayrı
 * yerlerde olduğunda biri değişip diğeri unutuluyordu. Eskiden
 * sihirbaz yalnızca " karakterini kaçışlıyordu: "\t" ya da "${"
 * içeren bir veritabanı parolası okunurken değişiyor, site
 * veritabanına bağlanamıyordu. Env.php bağımlılıksızdır; .env
 * yazılmadan önce yüklenmesi güvenlidir.
 */
function env_value(string $value): string
{
    require_once ROOT_PATH . '/app/Core/Env.php';

    return App\Core\Env::quote($value);
}

/**
 * Ayarları ".env" dosyasına yazar (app/Core/Env.php formatıyla uyumlu).
 *
 * Dosya "x" kipiyle açılır: zaten varsa yazma BAŞARISIZ olur. İki kişi
 * aynı anda kurulumu bitirmeye çalışırsa ikincisi ilkinin .env'ini
 * ezemez.
 */
function write_env_file(string $path, array $values): void
{
    $lines = [
        '# Bu dosya kurulum sihirbazı tarafından otomatik oluşturuldu.',
        '# app/Core/Env.php tarafından okunur. .gitignore içinde olduğu',
        '# için Git\'e gönderilmez — şifreniz burada güvende kalır.',
        '',
    ];

    foreach ($values as $key => $value) {
        // "# ..." anahtarı bir bölüm başlığıdır; değeri yazılmaz.
        if (str_starts_with((string) $key, '#')) {
            $lines[] = '';
            $lines[] = (string) $key;
            continue;
        }

        $lines[] = $key . '=' . env_value((string) $value);
    }

    $handle = @fopen($path, 'x');

    if ($handle === false) {
        throw new RuntimeException(
            file_exists($path)
                ? '.env dosyası zaten var; kurulum başka bir oturumda tamamlanmış olabilir.'
                : '.env dosyası yazılamadı. Klasör izinlerini kontrol edin.'
        );
    }

    $ok = fwrite($handle, implode("\n", $lines) . "\n") !== false;
    fclose($handle);

    if (!$ok) {
        @unlink($path);

        throw new RuntimeException('.env dosyası yazılamadı. Disk dolu ya da izinler yetersiz olabilir.');
    }

    // Sunucudaki diğer kullanıcılar veritabanı parolasını okumasın.
    @chmod($path, 0640);
}

/**
 * Kurulumun bittiğini kalıcı olarak işaretler.
 *
 * .env silinse bile (örneğin ortam değişkenlerine taşınırken) sihirbaz
 * kilitli kalır. Yeniden kurmak için BU dosyanın da elle silinmesi
 * gerekir.
 */
function write_lock_file(): void
{
    $dir = dirname(LOCK_PATH);

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    @file_put_contents(
        LOCK_PATH,
        'Kurulum ' . date('c') . " tarihinde tamamlandı.\n"
        . "Sihirbazı yeniden açmak için bu dosyayı VE .env'i sunucudan silin.\n"
    );
}

/**
 * kurulum/ klasörünü web'e kapatır.
 *
 * Klasör silinemediğinde (izinler) ikinci savunma hattıdır: PHP kilidi
 * zaten devrededir, ama sihirbazın hiç açılmaması daha iyidir.
 */
function deny_web_access(): void
{
    @file_put_contents(
        __DIR__ . '/.htaccess',
        "# Kurulum tamamlandı: bu klasör web'e kapatıldı.\n"
        . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
    );
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

/**
 * POST'tan METİN okur. Dizi gönderilmişse ("ad[]=x") boş döner.
 *
 * (string) dönüşümü bir diziyle karşılaşınca uyarı üretir ve sıkı
 * türlü fonksiyonlara geçince TypeError fırlatır; yani tek bir bozuk
 * istek sihirbazı 500 ile çökertebiliyordu.
 */
function post_str(string $field, string $default = ''): string
{
    $value = $_POST[$field] ?? $default;

    return is_string($value) ? $value : $default;
}


/**
 * Sihirbaz YEREL bir bilgisayardan mı açıldı?
 *
 * İKİ KOŞUL BİRDEN: istemci döngü adresinden (127.0.0.1, ::1) gelmeli
 * VE alan adı yerel olmalı (localhost, *.test, *.local). Yalnızca IP'ye
 * bakmak yetmezdi: aynı makinedeki bir ters vekilin (nginx → Apache)
 * arkasındaki yayın sunucusunda her istek 127.0.0.1'den gelir. Yalnızca
 * alan adına bakmak da yetmezdi: Host başlığı istemcinin elindedir.
 */
function is_local_request(): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if ($ip !== '::1' && !str_starts_with($ip, '127.')) {
        return false;
    }

    $host = strtolower((string) preg_replace('/:\d+\z/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $host = trim($host, '[]');

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    foreach (['.localhost', '.test', '.local'] as $sonek) {
        if (str_ends_with($host, $sonek)) {
            return true;
        }
    }

    return false;
}

/**
 * KURULUM ANAHTARI. Sihirbaz yerel değilse ilk ziyarette sunucuya
 * storage/kurulum-anahtari.txt yazılır; formda bu kod istenir. Kodu
 * yalnızca sunucunun dosyalarına erişebilen kişi (sitenin sahibi)
 * okuyabilir. storage/ web'e kapalıdır (storage/.htaccess).
 */
function install_key(): string
{
    if (is_file(KEY_PATH)) {
        return trim((string) file_get_contents(KEY_PATH));
    }

    $harfler = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // karışan 0/O, 1/I yok
    $kod     = '';

    for ($i = 0; $i < 12; $i++) {
        $kod .= $harfler[random_int(0, strlen($harfler) - 1)];
        $kod .= ($i % 4 === 3 && $i < 11) ? '-' : '';
    }

    @mkdir(dirname(KEY_PATH), 0755, true);
    @file_put_contents(KEY_PATH, $kod . "\n");

    return $kod;
}

function key_required(): bool
{
    return !is_local_request() && empty($_SESSION['kurulum_anahtar_ok']);
}

/**
 * Girilen kurulum anahtarını denetler; doğruysa oturuma işaretler.
 * Oturum başına 10 deneme.
 *
 * @return string|null hata mesajı
 */
function check_install_key(string $girilen): ?string
{
    $_SESSION['kurulum_anahtar_deneme'] = (int) ($_SESSION['kurulum_anahtar_deneme'] ?? 0) + 1;

    if ($_SESSION['kurulum_anahtar_deneme'] > 10) {
        return 'Çok fazla hatalı deneme. Tarayıcıyı kapatıp yeniden açın.';
    }

    $beklenen = strtoupper(str_replace([' ', '-'], '', install_key()));
    $girilen  = strtoupper(str_replace([' ', '-'], '', $girilen));

    if ($girilen === '' || !hash_equals($beklenen, $girilen)) {
        return 'Kurulum anahtarı hatalı. Sunucudaki storage/kurulum-anahtari.txt dosyasındaki kodu girin.';
    }

    $_SESSION['kurulum_anahtar_ok'] = true;

    return null;
}

/**
 * Veritabanı hatasını SINIFLANDIRILMIŞ, anlaşılır bir cümleye çevirir.
 * Ham PDO metni (sunucu adı, kullanıcı, iç yollar) bilgi sızdırır;
 * yalnızca yerel bilgisayarda ayrıntı olarak gösterilir.
 */
function db_error_message(PDOException $e): string
{
    $kod = (int) ($e->errorInfo[1] ?? $e->getCode());

    return match (true) {
        $kod === 1045                         => 'Erişim reddedildi: veritabanı kullanıcı adı ya da parolası hatalı.',
        /* Ubuntu/Debian'da root parolayla değil auth_socket ile girer. */
        $kod === 1698                         => 'Bu kullanıcı parolayla bağlanamıyor (sunucu auth_socket kullanıyor). Hosting panelinizden ayrı bir veritabanı kullanıcısı açın ya da MySQL root parolası tanımlayın.',
        $kod === 1044                         => 'Bu kullanıcının bu veritabanına erişim yetkisi yok.',
        in_array($kod, [2002, 2003, 2005, 2006], true) => 'Veritabanı sunucusuna ulaşılamadı. Sunucu adresini ve kapıyı (port) kontrol edin; MySQL/MariaDB çalışıyor mu?',
        $kod === 1049                         => 'Veritabanı bulunamadı.',
        default                               => 'Veritabanına bağlanılamadı (hata ' . $kod . ').',
    };
}

/** Yerel bilgisayarda ham hata ayrıntısı; yayında boş. */
function db_error_detail(PDOException $e): string
{
    return is_local_request() ? $e->getMessage() : '';
}

/**
 * "Ali Rıza Çelik" → ['Ali Rıza', 'Çelik']. Son kelime soyad sayılır.
 *
 * @return array{0:string,1:string,2:?string} [ad, soyad, hata]
 */
function split_full_name(string $tam): array
{
    $tam = trim((string) preg_replace('/\s+/u', ' ', $tam));

    if ($tam === '') {
        return ['', '', 'Ad soyad boş bırakılamaz.'];
    }

    $parcalar = explode(' ', $tam);

    if (count($parcalar) < 2) {
        return [$tam, '', 'Adınızı ve soyadınızı birlikte yazın (örn. Evren Çılgın).'];
    }

    $soyad = array_pop($parcalar);
    $ad    = implode(' ', $parcalar);

    foreach ([[$ad, 'Ad'], [$soyad, 'Soyad']] as [$deger, $etiket]) {
        [, $hata] = validate_name($deger, $etiket);

        if ($hata !== null) {
            return [$ad, $soyad, $hata];
        }
    }

    return [$ad, $soyad, null];
}

/**
 * Örnek veriyi yükler (App\Core\DemoData — sihirbaz, "php cy db:seed"
 * ve canlı demo sıfırlaması AYNI kaynağı kullanır). run_migrations()'tan
 * SONRA çağrılır; uygulama orada yüklendi.
 *
 * @return ?string hata mesajı
 */
function run_demo_seed(): ?string
{
    if (!class_exists(App\Core\DemoData::class)) {
        return 'Uygulama yüklenemedi; örnek veri atlandı.';
    }

    try {
        (new App\Core\DemoData(App\Core\Database::connection()))->seed();

        return null;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}


/* =====================================================================
 *  DURUM
 * ================================================================== */
$csrfToken = csrf_token();

$_SESSION['kurulum'] = $_SESSION['kurulum'] ?? [];
$kurulum = &$_SESSION['kurulum'];

$errors      = [];   // formun üstünde gösterilen genel hatalar
$fieldErrors = [];   // alan => mesaj (alanın hemen altında)
$hataAyrinti = '';   // yalnızca yerelde: ham veritabanı hatası

$alreadyInstalled = is_installed();
$yerel            = is_local_request();

$adim = is_string($_GET['adim'] ?? null) ? $_GET['adim'] : 'veritabani';
if (!array_key_exists($adim, ADIMLAR)) {
    $adim = 'veritabani';
}

/* Kurulumu AZ ÖNCE bu oturumda bitiren kişi bitiş ekranını görebilir.
 * Sonuç yalnızca o oturumun $_SESSION'ında durur. */
$yeniBitti = ($adim === 'tamam' && !empty($_SESSION['kurulum_sonuc']));
$kilitli   = $alreadyInstalled && !$yeniBitti;

if (!$alreadyInstalled && !$yerel) {
    install_key(); // anahtar dosyası ilk ziyarette oluşsun
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$islem  = post_str('islem');

/** CSRF jetonu geçerli mi? */
$tokenGecerli = static function (): bool {
    $token = $_POST['csrf_token'] ?? '';

    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
};


/* =====================================================================
 *  KURULUM KLASÖRÜNÜ SİL — yalnızca kurulumu YAPAN oturum
 * ================================================================== */
if ($isPost && $islem === 'temizle') {
    if (!$tokenGecerli()) {
        $errors[] = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } elseif (empty($_SESSION['kurulum_sonuc']) || !$alreadyInstalled) {
        $errors[] = 'Kurulum klasörünü yalnızca kurulumu yapan tarayıcı oturumu silebilir. Panele yönetici olarak girip Sistem Bilgisi sayfasından silin.';
    } else {
        unset($_SESSION['kurulum'], $_SESSION['kurulum_sonuc']);
        @unlink(KEY_PATH);

        if (remove_directory(__DIR__)) {
            header('Location: ../?kurulum=temizlendi');
            exit;
        }

        /* Silinemedi: en azından web'e kapatalım. */
        deny_web_access();

        $errors[] = 'Klasör silinemedi; web erişimine kapatıldı. Yine de "kurulum" klasörünü FTP/dosya yöneticisiyle silin.';
        $adim = 'tamam';
    }
}


/* =====================================================================
 *  SİSTEM KONTROLLERİ — zorunlu olanlar kurulumu durdurur
 * ================================================================== */
$checks = [
    ['label' => 'PHP 8.1 veya üzeri', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>='), 'note' => 'Mevcut: PHP ' . PHP_VERSION, 'zorunlu' => true],
    ['label' => 'pdo_mysql eklentisi', 'ok' => extension_loaded('pdo_mysql'), 'note' => 'php.ini içinde extension=pdo_mysql satırını açın.', 'zorunlu' => true],
    ['label' => 'mbstring eklentisi', 'ok' => extension_loaded('mbstring'), 'note' => 'Türkçe karakter işlemleri için gereklidir.', 'zorunlu' => true],
    ['label' => 'Proje klasörüne yazma izni (.env)', 'ok' => is_writable(ROOT_PATH), 'note' => 'Proje klasörünün izinlerini kontrol edin.', 'zorunlu' => true],
    ['label' => 'kurulum/database.sql mevcut', 'ok' => is_file(SCHEMA_PATH), 'note' => 'Şema dosyası bulunamadı; dosyaları yeniden yükleyin.', 'zorunlu' => true],
    ['label' => 'storage/ yazılabilir', 'ok' => !is_dir(ROOT_PATH . '/storage') || is_writable(ROOT_PATH . '/storage'), 'note' => 'Günlükler, önbellek ve kuyruk buraya yazılır.', 'zorunlu' => true],
    ['label' => 'upload/ yazılabilir', 'ok' => !is_dir(ROOT_PATH . '/upload') || is_writable(ROOT_PATH . '/upload'), 'note' => 'Avatar, logo ve görseller için.', 'zorunlu' => true],
    ['label' => 'gd eklentisi', 'ok' => extension_loaded('gd'), 'note' => 'Yüklenen görseller güvenlik için yeniden kodlanır; gd olmadan görsel yüklenemez.', 'zorunlu' => false],
    ['label' => 'fileinfo eklentisi', 'ok' => extension_loaded('fileinfo'), 'note' => 'Yüklenen dosyanın gerçek türünü anlamak için.', 'zorunlu' => false],
    ['label' => 'kurulum/ klasörü silinebilir', 'ok' => is_writable(ROOT_PATH) && is_writable(__DIR__), 'note' => 'Bitince klasörü tek tıkla silmek için; değilse elle silersiniz.', 'zorunlu' => false],
];
$eksikZorunlu  = array_filter($checks, static fn (array $c): bool => $c['zorunlu'] && !$c['ok']);
$uyarilar      = array_filter($checks, static fn (array $c): bool => !$c['zorunlu'] && !$c['ok']);
$gereksinimTamam = $eksikZorunlu === [];


/* =====================================================================
 *  BAĞLANTIYI DENE — "Bağlantıyı dene" düğmesi (fetch, JSON)
 * ================================================================== */
if ($isPost && $islem === 'dene') {
    header('Content-Type: application/json; charset=utf-8');

    $yanit = static function (bool $ok, string $mesaj, string $ayrinti = ''): never {
        echo json_encode(['ok' => $ok, 'mesaj' => $mesaj, 'ayrinti' => $ayrinti], JSON_UNESCAPED_UNICODE);
        exit;
    };

    if ($alreadyInstalled) {
        $yanit(false, 'Kurulum zaten tamamlanmış.');
    }
    if (!$tokenGecerli()) {
        $yanit(false, 'Oturum doğrulaması başarısız. Sayfayı yenileyin.');
    }
    if (key_required() && ($hata = check_install_key(post_str('kurulum_anahtari'))) !== null) {
        $yanit(false, $hata);
    }

    $host = trim(post_str('db_host'));
    $ad   = trim(post_str('db_name'));

    if ($host === '' || preg_match('/^[A-Za-z0-9._\-\[\]:]+\z/', $host) !== 1) {
        $yanit(false, 'Sunucu adresi geçersiz. Örn: localhost ya da 127.0.0.1:3307');
    }
    if ($ad === '' || !preg_match(IDENTIFIER_RE, $ad)) {
        $yanit(false, 'Veritabanı adı yalnızca harf, rakam ve alt çizgi içerebilir.');
    }

    try {
        $pdo      = connect_without_database($host, trim(post_str('db_user')), post_str('db_pass'));
        $surum    = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $tablolar = count_tables($pdo, $ad);

        $yanit(true, $tablolar === 0
            ? sprintf('Bağlantı başarılı (%s). "%s" boş ya da henüz yok; kurulum oluşturacak.', $surum, $ad)
            : sprintf('Bağlantı başarılı (%s). "%s" içinde %d tablo var; devam edince onay istenecek.', $surum, $ad, $tablolar));
    } catch (PDOException $e) {
        $yanit(false, db_error_message($e), db_error_detail($e));
    }
}


/* =====================================================================
 *  FORM İŞLEME
 * ================================================================== */
/* İKİ KİLİT BİRDEN: $kilitli bitiş ekranı için gevşer, ama hiçbir kurulum
 * adımı .env varken çalışmamalıdır — dosya kontrolü burada ayrıca yapılır. */
if ($isPost && !$alreadyInstalled && !in_array($islem, ['temizle', 'dene'], true)) {

    if (!$tokenGecerli()) {
        $errors[] = 'Oturum doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    } elseif (key_required() && ($hata = check_install_key(post_str('kurulum_anahtari'))) !== null) {
        $fieldErrors['kurulum_anahtari'] = $hata;
    } else {

        /* ---------- 1) VERİTABANI ---------- */
        if ($adim === 'veritabani') {
            $db_host = trim(post_str('db_host'));
            $db_name = trim(post_str('db_name'));
            $db_user = trim(post_str('db_user'));
            $db_pass = post_str('db_pass');
            $db_ustune_yaz = isset($_POST['db_ustune_yaz']);

            if (!$gereksinimTamam) {
                $errors[] = 'Sunucu gereksinimleri karşılanmıyor; ayrıntılara bakın.';
            }
            if ($db_host === '') {
                $fieldErrors['db_host'] = 'Sunucu boş bırakılamaz.';
            } elseif (preg_match('/^[A-Za-z0-9._\-\[\]:]+\z/', $db_host) !== 1) {
                $fieldErrors['db_host'] = 'Geçersiz karakter. Örn: localhost ya da 127.0.0.1:3307';
            }
            if ($db_name === '') {
                $fieldErrors['db_name'] = 'Veritabanı adı boş bırakılamaz.';
            } elseif (!preg_match(IDENTIFIER_RE, $db_name)) {
                $fieldErrors['db_name'] = 'Yalnızca harf, rakam ve alt çizgi; rakamla başlayamaz.';
            }
            if ($db_user === '') {
                $fieldErrors['db_user'] = 'Kullanıcı adı boş bırakılamaz.';
            }

            $mevcutTablolar = [];
            $engelleyenler  = [];
            $silinecekler   = schema_drop_tables(SCHEMA_PATH);

            if ($errors === [] && $fieldErrors === []) {
                try {
                    $baglanti       = connect_without_database($db_host, $db_user, $db_pass);
                    $mevcutTablolar = existing_tables($baglanti, $db_name);
                    $engelleyenler  = blocking_foreign_keys($baglanti, $db_name, $silinecekler);
                } catch (PDOException $e) {
                    $errors[]    = db_error_message($e);
                    $hataAyrinti = db_error_detail($e);
                }
            }

            /* YABANCI ANAHTAR ENGELİ: onay kutusu işaretli olsa bile kurulum
             * başlamaz; aksi hâlde şema yarıda kalırdı. */
            if ($errors === [] && $fieldErrors === [] && $engelleyenler !== []) {
                $errors[] = 'Bu veritabanındaki bazı tablolar, kurulumun yeniden oluşturacağı tablolara yabancı anahtarla bağlı. '
                    . 'Hiçbir şeye dokunulmadı. Boş bir veritabanı kullanın ya da şu kısıtları kaldırın: ' . implode('; ', $engelleyenler);
            }

            /* DOLU VERİTABANI KORUMASI: şema DROP TABLE içerir. Silinecek
             * tablolar tek tek listelenir ve açık onay istenir. */
            if ($errors === [] && $fieldErrors === [] && $mevcutTablolar !== [] && !$db_ustune_yaz) {
                $dbDoluUyarisi = [
                    'silinecek' => array_values(array_intersect($mevcutTablolar, $silinecekler)),
                    'kalacak'   => array_values(array_diff($mevcutTablolar, $silinecekler)),
                ];
            }

            if ($errors === [] && $fieldErrors === [] && empty($dbDoluUyarisi)) {
                $kurulum['db'] = compact('db_host', 'db_name', 'db_user', 'db_pass');
                header('Location: index.php?adim=kur');
                exit;
            }
        }

        /* ---------- 2) SİTE + YÖNETİCİ → KURULUMU ÇALIŞTIR ---------- */
        if ($adim === 'kur') {
            if (empty($kurulum['db'])) {
                header('Location: index.php?adim=veritabani');
                exit;
            }

            [$site_adi, $hata] = validate_text(post_str('site_adi'), 'Site adı', 2, 150);
            if ($hata !== null) {
                $fieldErrors['site_adi'] = $hata;
            }

            /* SİTE ADRESİ ZORUNLU: boş kalınca e-postalardaki bağlantılar
             * göreli olup kırılıyor, canonical ve site haritası adresleri
             * Host başlığından (istemcinin elinden) üretiliyordu. */
            $site_url = rtrim(trim(post_str('site_url')), '/');
            if ($site_url === '') {
                $fieldErrors['site_url'] = 'Site adresi zorunludur (örn. https://ornek.com).';
            } elseif (filter_var($site_url, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $site_url)) {
                $fieldErrors['site_url'] = 'http:// ya da https:// ile başlayan geçerli bir adres yazın.';
            }

            $site_aciklama = trim((string) preg_replace('/\s+/u', ' ', post_str('site_aciklama')));
            if (mb_strlen($site_aciklama, 'UTF-8') > 300) {
                $fieldErrors['site_aciklama'] = 'Açıklama en fazla 300 karakter olabilir.';
            }

            [$admin_ad, $admin_soyad, $hata] = split_full_name(post_str('admin_ad_soyad'));
            if ($hata !== null) {
                $fieldErrors['admin_ad_soyad'] = $hata;
            }

            [$admin_kadi, $hata] = validate_username(post_str('admin_kadi'));
            if ($hata !== null) {
                $fieldErrors['admin_kadi'] = $hata;
            }

            [$admin_eposta, $hata] = validate_email(post_str('admin_eposta'));
            if ($hata !== null) {
                $fieldErrors['admin_eposta'] = $hata;
            }

            $admin_sifre = post_str('admin_sifre');
            if (($hata = validate_password($admin_sifre)) !== null) {
                $fieldErrors['admin_sifre'] = $hata;
            } elseif (!hash_equals($admin_sifre, post_str('admin_sifre2'))) {
                $fieldErrors['admin_sifre2'] = 'Parolalar eşleşmiyor.';
            }

            $ornek_veri = isset($_POST['ornek_veri']);
            $demo_modu  = isset($_POST['demo_modu']);
            $gelistirme = isset($_POST['gelistirme']);
            $pwa_aktif  = isset($_POST['pwa_aktif']);

            /* Demo modu örnek hesaplar olmadan anlamsızdır: giriş ekranı
             * listelenecek hesap bulamazdı. Seçildiyse örnek veri de gelir. */
            if ($demo_modu) {
                $ornek_veri = true;
            }

            // Yalnızca diskte GERÇEKTEN bulunan modül adları kabul edilir.
            $gonderilen = is_array($_POST['moduller'] ?? null) ? array_filter($_POST['moduller'], 'is_string') : [];
            $moduller   = array_values(array_intersect(array_keys(discover_modules()), $gonderilen));

            // Hata sonrası form değerlerini koru (parolalar hariç).
            $kurulum['form'] = compact('site_adi', 'site_url', 'site_aciklama', 'admin_kadi', 'admin_eposta', 'ornek_veri', 'demo_modu', 'gelistirme', 'pwa_aktif', 'moduller')
                + ['admin_ad_soyad' => post_str('admin_ad_soyad')];

            if ($fieldErrors === []) {
                $db      = $kurulum['db'];
                $yapilan = [];   // [başlık, başarılı mı, ayrıntı]

                try {
                    $pdo = connect_without_database($db['db_host'], $db['db_user'], $db['db_pass']);

                    $pdo->exec(sprintf(
                        'CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci',
                        $db['db_name']
                    ));
                    $pdo->exec(sprintf('USE `%s`', $db['db_name']));
                    $yapilan[] = ['Veritabanı hazırlandı', true, $db['db_name']];

                    $komut     = import_schema($pdo, SCHEMA_PATH);
                    $yapilan[] = ['Şema kuruldu', true, $komut . ' SQL ifadesi'];

                    $pdo->prepare(
                        'INSERT INTO kullanicilar (ad, soyad, kullanici_adi, eposta, sifre, rol, durum)
                         VALUES (:ad, :soyad, :kadi, :eposta, :sifre, \'admin\', \'aktif\')'
                    )->execute([
                        ':ad'     => $admin_ad,
                        ':soyad'  => $admin_soyad,
                        ':kadi'   => $admin_kadi,
                        ':eposta' => $admin_eposta,
                        ':sifre'  => hash_password($admin_sifre),
                    ]);
                    $yapilan[] = ['Yönetici hesabı açıldı', true, $admin_kadi];

                    [$dbSunucu, $dbKapi] = split_host($db['db_host']);

                    /* .env, .env.example ile AYNI anahtarları taşır. SESSION_NAME
                     * ve APP_KEY HER KURULUMA ÖZELDİR (bkz. App\Core\Session). */
                    write_env_file(ENV_PATH, [
                        'APP_NAME'         => $site_adi,
                        'APP_DESCRIPTION'  => $site_aciklama,
                        'APP_URL'          => $site_url,
                        '# --- Ortam (yayında: production + false) ---' => null,
                        'APP_ENV'          => $gelistirme ? 'local' : 'production',
                        'APP_DEBUG'        => $gelistirme ? 'true' : 'false',
                        '# --- Demo modu: giriş ekranı örnek hesapları listeler (gerçek sitede false) ---' => null,
                        'APP_DEMO'         => $demo_modu ? 'true' : 'false',
                        '# --- Kuruluma özel gizli anahtar: DEĞİŞTİRMEYİN, başka kuruluma kopyalamayın ---' => null,
                        'APP_KEY'          => bin2hex(random_bytes(32)),
                        'APP_PRETTY_URLS'  => 'true',
                        'APP_TIMEZONE'     => 'Europe/Istanbul',
                        '# --- Veritabanı ---' => null,
                        'DB_HOST'          => $dbSunucu,
                        'DB_PORT'          => (string) $dbKapi,
                        'DB_NAME'          => $db['db_name'],
                        'DB_USER'          => $db['db_user'],
                        'DB_PASS'          => $db['db_pass'],
                        '# --- Oturum ---' => null,
                        'SESSION_NAME'     => 'CYS_' . bin2hex(random_bytes(5)),
                        'SESSION_IDLE_TIMEOUT' => '1800',
                        '# --- Giriş ve kayıt koruması (ayrıntı: .env.example) ---' => null,
                        'LOGIN_MAX_ATTEMPTS'    => '5',
                        'LOGIN_LOCKOUT'         => '900',
                        'LOGIN_IP_MAX_ATTEMPTS' => '30',
                        'REGISTER_MAX_PER_HOUR' => '5',
                        'REGISTER_MAX_ATTEMPTS_PER_HOUR' => '20',
                        '# Site Cloudflare/vekil arkasındaysa vekil adresleri (bkz. .env.example)' => null,
                        'TRUSTED_PROXIES'       => '',
                        '# --- REST API / mobil uygulama oturumu (gün) ---' => null,
                        'API_SESSION_DAYS'      => '30',
                        '# --- Yüklemeler ---' => null,
                        'UPLOAD_MAX_MB'     => '2',
                        'UPLOAD_MAX_PIXELS' => '25000000',
                        '# --- Günlük kayıtları ---' => null,
                        'LOG_ENABLED'      => 'true',
                        'LOG_LEVEL'        => $gelistirme ? 'debug' : 'info',
                        'LOG_DAYS'         => '30',
                        '# --- Önbellek ---' => null,
                        'CACHE_DRIVER'     => 'dosya',
                        'CACHE_TTL'        => '3600',
                        'CACHE_PREFIX'     => 'cy_' . substr(bin2hex(random_bytes(3)), 0, 6),
                    ]);
                    write_lock_file();
                    $yapilan[] = ['.env yazıldı, kurulum kilitlendi', true, $gelistirme ? 'geliştirme modu' : 'yayın modu'];

                    /* Artık .env var: ek tabloları ve 1.6 ayarlarını
                     * uygulamanın kendi Migrator'ı kurar. */
                    [$migrationSayisi, $migrationHatasi] = run_migrations();
                    $yapilan[] = ['Migration\'lar çalıştı', $migrationHatasi === null,
                        $migrationHatasi ?? $migrationSayisi . ' dosya'];

                    [$acilanModuller, $modulHatasi] = enable_modules($moduller);
                    if ($moduller !== []) {
                        $yapilan[] = ['Modüller açıldı', $modulHatasi === null,
                            $modulHatasi ?? implode(', ', $acilanModuller)];
                    }

                    if ($ornek_veri) {
                        $demoHatasi = $migrationHatasi === null ? run_demo_seed() : 'Migration hatası yüzünden atlandı.';

                        if ($demoHatasi === null && $acilanModuller !== [] && $modulHatasi === null) {
                            $demoHatasi = run_module_seeders();
                        }

                        $yapilan[] = ['Örnek veri yüklendi', $demoHatasi === null,
                            $demoHatasi ?? '6 demo hesap, sayfalar, mesajlar, vitrin'];
                    }

                    /* Formdaki değerler EN SON yazılır: örnek veri vitrin
                     * markasını kurar ama kullanıcının yazdığı site adı ve
                     * açıklaması her zaman kazanır. */
                    $ayar = $pdo->prepare('UPDATE ayarlar SET deger = :deger WHERE anahtar = :anahtar');
                    $son  = ['site_adi' => $site_adi, 'iletisim_eposta' => $admin_eposta, 'pwa_aktif' => $pwa_aktif ? '1' : '0'];
                    if ($site_aciklama !== '' || !$ornek_veri) {
                        $son['site_aciklama'] = $site_aciklama;
                    }
                    foreach ($son as $anahtar => $deger) {
                        $ayar->execute([':deger' => $deger, ':anahtar' => $anahtar]);
                    }

                    @unlink(KEY_PATH);

                    $_SESSION['kurulum_sonuc'] = [
                        'adimlar'    => $yapilan,
                        'site_adi'   => $site_adi,
                        'site_url'   => $site_url,
                        'kadi'       => $admin_kadi,
                        'ornek_veri' => $ornek_veri,
                        'demo_modu'  => $demo_modu,
                        'gelistirme' => $gelistirme,
                    ];
                    unset($_SESSION['kurulum']);

                    header('Location: index.php?adim=tamam');
                    exit;

                } catch (PDOException $e) {
                    $errors[]    = db_error_message($e);
                    $hataAyrinti = db_error_detail($e);
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

if (!$kilitli) {
    if ($adim === 'kur' && empty($kurulum['db'])) {
        $adim = 'veritabani';
    }
    if ($adim === 'tamam' && empty($_SESSION['kurulum_sonuc'])) {
        $adim = 'veritabani';
    }
}

$sonuc        = $_SESSION['kurulum_sonuc'] ?? null;
$adimSirasi   = array_keys(ADIMLAR);
$aktifIndeks  = (int) array_search($kilitli ? 'tamam' : $adim, $adimSirasi, true);
$form         = $kurulum['form'] ?? [];

/** Formdaki önceki değer: POST > oturum > varsayılan. */
$deger = static function (string $alan, string $varsayilan = '') use ($form, $kurulum): string {
    if (isset($_POST[$alan]) && is_string($_POST[$alan])) {
        return $_POST[$alan];
    }

    return (string) ($form[$alan] ?? $kurulum['db'][$alan] ?? $varsayilan);
};

/** Onay kutusu: form gönderildiyse gönderilen, değilse oturum, değilse varsayılan. */
$secili = static function (string $alan, bool $varsayilan) use ($form, $isPost, $adim): bool {
    if ($isPost && $adim === 'kur') {
        return isset($_POST[$alan]);
    }

    return (bool) ($form[$alan] ?? $varsayilan);
};

$hataMetni = static fn (string $alan): string => isset($fieldErrors[$alan])
    ? '<p class="kur-field__error" id="hata_' . e($alan) . '">' . e($fieldErrors[$alan]) . '</p>'
    : '';
$hataAria = static fn (string $alan): string => isset($fieldErrors[$alan])
    ? ' aria-invalid="true" aria-describedby="hata_' . e($alan) . '"'
    : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>Kurulum · CY PHP Starter</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/cilginyazilim.css">
    <link rel="stylesheet" href="kurulum.css">
</head>
<body class="kur">

<main class="kur-wrap">

    <header class="kur-head">
        <img src="../assets/images/logo-256.png" alt="" width="36" height="36">
        <div>
            <strong>CY PHP Starter</strong>
            <span>Kurulum</span>
        </div>
    </header>

    <ol class="kur-steps" aria-label="Kurulum adımları">
        <?php foreach (ADIMLAR as $anahtar => $etiket): ?>
            <?php $sira = array_search($anahtar, $adimSirasi, true); ?>
            <li class="<?= $sira === $aktifIndeks ? 'is-active' : ($sira < $aktifIndeks ? 'is-done' : '') ?>"
                <?= $sira === $aktifIndeks ? 'aria-current="step"' : '' ?>>
                <span class="kur-steps__dot"><?= $sira < $aktifIndeks ? '✓' : $sira + 1 ?></span>
                <span class="kur-steps__label"><?= e($etiket) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <section class="kur-card">

        <?php foreach ($errors as $hata): ?>
            <div class="cy-alert cy-alert--danger mb-3" role="alert">
                <div class="cy-alert__body">
                    <?= e($hata) ?>
                    <?php if ($hataAyrinti !== ''): ?>
                        <details class="kur-detail"><summary>Teknik ayrıntı (yalnızca bu bilgisayarda görünür)</summary><code><?= e($hataAyrinti) ?></code></details>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($kilitli): ?>
            <!-- ============ KİLİTLİ ============ -->
            <div class="kur-done__icon kur-done__icon--muted" aria-hidden="true">✓</div>
            <h1 class="kur-title">Kurulum zaten tamamlanmış</h1>
            <p class="kur-lead">Sihirbaz kendini kilitledi; kimse buradan veritabanınızı sıfırlayamaz.</p>

            <div class="kur-links">
                <a class="kur-link" href="../"><strong>Siteyi aç</strong><span>Ana sayfa</span></a>
                <a class="kur-link" href="../giris"><strong>Panele git</strong><span>Yönetici girişi</span></a>
            </div>

            <?php /* Silme düğmesi bu ekranda YOK: kurulumu yapan oturum dışında
                     herkes için kapalıdır. Yönetici panelden siler. */ ?>
            <p class="kur-note">
                Bu klasörü silmek için panele yönetici olarak girin: <strong>Sistem Bilgisi → Kurulum ve örnek veri → Klasörü sil</strong>.
                Sıfırdan yeniden kurmak için sunucudan <code>.env</code> ve <code>storage/installed.lock</code> dosyalarını silin;
                bu işlem bilerek yalnızca sunucuya erişimi olan kişiye bırakılmıştır.
            </p>

        <?php elseif ($adim === 'veritabani'): ?>
            <!-- ============ 1) VERİTABANI ============ -->
            <h1 class="kur-title">Veritabanı</h1>
            <p class="kur-lead">Boş bir MySQL/MariaDB veritabanı yeterli; yoksa kurulum oluşturur.</p>

            <details class="kur-req <?= !$gereksinimTamam ? 'is-error' : ($uyarilar !== [] ? 'is-warn' : '') ?>" <?= !$gereksinimTamam ? 'open' : '' ?>>
                <summary>
                    <span class="kur-req__icon" aria-hidden="true"><?= !$gereksinimTamam ? '✕' : ($uyarilar !== [] ? '!' : '✓') ?></span>
                    <?php if (!$gereksinimTamam): ?>
                        <?= count($eksikZorunlu) ?> gereksinim eksik — kurulum başlayamaz
                    <?php else: ?>
                        Sunucu hazır (PHP <?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?>, <?= count($checks) ?> kontrol<?= $uyarilar !== [] ? ', ' . count($uyarilar) . ' uyarı' : '' ?>)
                    <?php endif; ?>
                    <span class="kur-req__more">Ayrıntılar</span>
                </summary>
                <ul class="kur-req__list">
                    <?php foreach ($checks as $check): ?>
                        <li class="<?= $check['ok'] ? 'is-ok' : ($check['zorunlu'] ? 'is-error' : 'is-warn') ?>">
                            <span aria-hidden="true"><?= $check['ok'] ? '✓' : ($check['zorunlu'] ? '✕' : '!') ?></span>
                            <span><strong><?= e($check['label']) ?></strong><?php if (!$check['ok'] || str_starts_with($check['note'], 'Mevcut')): ?> <small><?= e($check['note']) ?></small><?php endif; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>

            <form method="post" action="index.php?adim=veritabani" novalidate id="db_form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <?php if (key_required()): ?>
                    <div class="kur-key">
                        <label class="form-label" for="kurulum_anahtari">Kurulum anahtarı</label>
                        <input type="text" class="form-control" id="kurulum_anahtari" name="kurulum_anahtari" autocomplete="off"
                               placeholder="XXXX-XXXX-XXXX" required<?= $hataAria('kurulum_anahtari') ?>>
                        <?= $hataMetni('kurulum_anahtari') ?>
                        <p class="kur-help">Sihirbaz yerel bir bilgisayardan açılmadı. Kurulumu yalnızca sunucunun sahibi yapabilsin diye bir anahtar oluşturduk: sunucudaki <code>storage/kurulum-anahtari.txt</code> dosyasını (FTP ya da dosya yöneticisiyle) açıp içindeki kodu girin.</p>
                    </div>
                <?php endif; ?>

                <div class="kur-grid">
                    <div class="kur-field">
                        <label class="form-label" for="db_host">Sunucu</label>
                        <input type="text" class="form-control" id="db_host" name="db_host" required
                               value="<?= e($deger('db_host', 'localhost')) ?>"<?= $hataAria('db_host') ?>>
                        <?= $hataMetni('db_host') ?>
                    </div>
                    <div class="kur-field">
                        <label class="form-label" for="db_name">Veritabanı adı</label>
                        <input type="text" class="form-control" id="db_name" name="db_name" required
                               value="<?= e($deger('db_name', 'cy_php_starter')) ?>"<?= $hataAria('db_name') ?>>
                        <?= $hataMetni('db_name') ?>
                    </div>
                    <div class="kur-field">
                        <label class="form-label" for="db_user">Kullanıcı</label>
                        <input type="text" class="form-control" id="db_user" name="db_user" required autocomplete="username"
                               value="<?= e($deger('db_user', $yerel ? 'root' : '')) ?>"<?= $hataAria('db_user') ?>>
                        <?= $hataMetni('db_user') ?>
                    </div>
                    <div class="kur-field">
                        <label class="form-label" for="db_pass">Parola</label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass" autocomplete="current-password"
                               value="<?= e($deger('db_pass')) ?>">
                    </div>
                </div>
                <p class="kur-help">Farklı bir kapı kullanıyorsanız sunucuya ekleyin: <code>localhost:3307</code></p>

                <?php if (!empty($dbDoluUyarisi) && is_array($dbDoluUyarisi)): ?>
                    <div class="cy-alert cy-alert--warning mb-3">
                        <div class="cy-alert__body">
                            <strong>Bu veritabanı boş değil.</strong>
                            <?php if ($dbDoluUyarisi['silinecek'] !== []): ?>
                                Şu tablolar silinip yeniden oluşturulacak (içindeki veriler kaybolur):
                                <code><?= e(implode(', ', $dbDoluUyarisi['silinecek'])) ?></code>.
                            <?php else: ?>
                                Kurulumun sileceği adla bir tablo yok; yalnızca yeni tablolar eklenecek.
                            <?php endif; ?>
                            <?php if ($dbDoluUyarisi['kalacak'] !== []): ?>
                                Dokunulmayacak: <code><?= e(implode(', ', $dbDoluUyarisi['kalacak'])) ?></code>.
                            <?php endif; ?>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="db_ustune_yaz" name="db_ustune_yaz" value="1">
                                <label class="form-check-label" for="db_ustune_yaz">Anladım, bu veritabanına kur</label>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="kur-test" id="db_test_sonuc" role="status" aria-live="polite"></p>

                <div class="kur-actions">
                    <button type="button" class="btn cy-btn cy-btn--ghost" id="db_test" hidden>Bağlantıyı dene</button>
                    <button type="submit" class="btn cy-btn cy-btn--primary" <?= $gereksinimTamam ? '' : 'disabled' ?>>Devam →</button>
                </div>
            </form>

        <?php elseif ($adim === 'kur'): ?>
            <!-- ============ 2) SİTE VE YÖNETİCİ ============ -->
            <h1 class="kur-title">Site ve yönetici</h1>
            <p class="kur-lead">Hepsini sonradan panelden değiştirebilirsiniz. <strong>Kur</strong> düğmesi kurulumu başlatır.</p>

            <form method="post" action="index.php?adim=kur" novalidate id="kur_form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <?php if (key_required()): ?>
                    <div class="kur-key">
                        <label class="form-label" for="kurulum_anahtari">Kurulum anahtarı</label>
                        <input type="text" class="form-control" id="kurulum_anahtari" name="kurulum_anahtari" autocomplete="off" required<?= $hataAria('kurulum_anahtari') ?>>
                        <?= $hataMetni('kurulum_anahtari') ?>
                        <p class="kur-help">Sunucudaki <code>storage/kurulum-anahtari.txt</code> dosyasındaki kod.</p>
                    </div>
                <?php endif; ?>

                <h2 class="kur-section">Site</h2>
                <div class="kur-field">
                    <label class="form-label" for="site_adi">Site adı</label>
                    <input type="text" class="form-control" id="site_adi" name="site_adi" required maxlength="150"
                           value="<?= e($deger('site_adi', $yerel ? 'CY PHP Starter' : '')) ?>" placeholder="Örn. Çılgın Projeler"<?= $hataAria('site_adi') ?>>
                    <?= $hataMetni('site_adi') ?>
                </div>
                <div class="kur-field">
                    <label class="form-label" for="site_url">Site adresi</label>
                    <input type="url" class="form-control" id="site_url" name="site_url" required inputmode="url"
                           value="<?= e($deger('site_url', guess_site_url())) ?>"<?= $hataAria('site_url') ?>>
                    <?= $hataMetni('site_url') ?>
                    <p class="kur-help">E-postalardaki bağlantılar ve site haritası bu adresle üretilir.</p>
                </div>
                <details class="kur-more" <?= $deger('site_aciklama') !== '' || isset($fieldErrors['site_aciklama']) ? 'open' : '' ?>>
                    <summary>Açıklama ekle (isteğe bağlı)</summary>
                    <div class="kur-field">
                        <label class="form-label" for="site_aciklama">Açıklama</label>
                        <textarea class="form-control" id="site_aciklama" name="site_aciklama" rows="2" maxlength="300"<?= $hataAria('site_aciklama') ?>><?= e($deger('site_aciklama')) ?></textarea>
                        <?= $hataMetni('site_aciklama') ?>
                        <p class="kur-help">Arama motorlarında görünen kısa tanıtım; 120–160 karakter idealdir.</p>
                    </div>
                </details>

                <h2 class="kur-section">Yönetici hesabı</h2>
                <div class="kur-field">
                    <label class="form-label" for="admin_ad_soyad">Ad soyad</label>
                    <input type="text" class="form-control" id="admin_ad_soyad" name="admin_ad_soyad" required autocomplete="name"
                           value="<?= e($deger('admin_ad_soyad')) ?>"<?= $hataAria('admin_ad_soyad') ?>>
                    <?= $hataMetni('admin_ad_soyad') ?>
                </div>
                <div class="kur-grid">
                    <div class="kur-field">
                        <label class="form-label" for="admin_kadi">Kullanıcı adı</label>
                        <input type="text" class="form-control" id="admin_kadi" name="admin_kadi" required autocomplete="username"
                               autocapitalize="none" spellcheck="false" maxlength="50"
                               value="<?= e($deger('admin_kadi', 'admin')) ?>"
                               aria-describedby="admin_kadi_ipucu<?= isset($fieldErrors['admin_kadi']) ? ' hata_admin_kadi' : '' ?>"<?= isset($fieldErrors['admin_kadi']) ? ' aria-invalid="true"' : '' ?>>
                        <?= $hataMetni('admin_kadi') ?>
                        <p class="kur-help" id="admin_kadi_ipucu">Harf, rakam, nokta ve alt çizgi. Tahmin edilmesi zor bir ad seçin ("admin" yerine).</p>
                    </div>
                    <div class="kur-field">
                        <label class="form-label" for="admin_eposta">E-posta</label>
                        <input type="email" class="form-control" id="admin_eposta" name="admin_eposta" required autocomplete="email"
                               inputmode="email" maxlength="190"
                               value="<?= e($deger('admin_eposta')) ?>"
                               aria-describedby="admin_eposta_ipucu<?= isset($fieldErrors['admin_eposta']) ? ' hata_admin_eposta' : '' ?>"<?= isset($fieldErrors['admin_eposta']) ? ' aria-invalid="true"' : '' ?>>
                        <?= $hataMetni('admin_eposta') ?>
                        <p class="kur-help" id="admin_eposta_ipucu">İletişim formu bildirimleri de bu adrese gelir.</p>
                    </div>
                </div>
                <div class="kur-grid">
                    <div class="kur-field">
                        <label class="form-label" for="admin_sifre">Parola</label>
                        <div class="cy-password">
                            <input type="password" class="form-control" id="admin_sifre" name="admin_sifre" required autocomplete="new-password"
                                   aria-describedby="sifre_guc<?= isset($fieldErrors['admin_sifre']) ? ' hata_admin_sifre' : '' ?>"<?= isset($fieldErrors['admin_sifre']) ? ' aria-invalid="true"' : '' ?>>
                            <button type="button" class="cy-password__toggle" data-sifre-goster aria-label="Parolayı göster"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </div>
                        <?= $hataMetni('admin_sifre') ?>
                        <div class="kur-meter" id="sifre_guc" data-guc="0" aria-live="polite">
                            <span class="kur-meter__bar"><span></span></span>
                            <span class="kur-meter__label">En az 8 karakter; harf ve rakam</span>
                        </div>
                    </div>
                    <div class="kur-field">
                        <label class="form-label" for="admin_sifre2">Parola (tekrar)</label>
                        <input type="password" class="form-control" id="admin_sifre2" name="admin_sifre2" required autocomplete="new-password"<?= $hataAria('admin_sifre2') ?>>
                        <?= $hataMetni('admin_sifre2') ?>
                    </div>
                </div>

                <h2 class="kur-section">Seçenekler</h2>
                <div class="kur-switches">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="ornek_veri" name="ornek_veri" value="1"
                               <?= $secili('ornek_veri', $yerel) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ornek_veri"><strong>Örnek veriyle kur</strong></label>
                        <p class="kur-help">6 demo hesap, sayfalar, mesajlar, örnek modül kayıtları ve CY PHP Starter vitrini. Daha sonra Panel → Sistem'den tek tıkla kaldırılabilir.</p>
                    </div>
                    <div class="form-check form-switch" id="demo_modu_satiri">
                        <input class="form-check-input" type="checkbox" role="switch" id="demo_modu" name="demo_modu" value="1"
                               <?= $secili('demo_modu', false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="demo_modu"><strong>Demo modu</strong> (herkese açık deneme sitesi)</label>
                        <p class="kur-help">Giriş ekranı demo hesapları tek tıkla girişle listeler; bu hesaplarla parola, kullanıcı, ayar ve e-posta işlemleri kilitlidir. Demo her 3 saatte bir sıfırlanır. Gerçek bir sitede açmayın.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="gelistirme" name="gelistirme" value="1"
                               <?= $secili('gelistirme', $yerel) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="gelistirme"><strong>Geliştirme modu</strong></label>
                        <p class="kur-help">Hata ayrıntıları ekranda görünür. Yayındaki bir sitede kapalı bırakın.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="pwa_aktif" name="pwa_aktif" value="1"
                               <?= $secili('pwa_aktif', true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pwa_aktif"><strong>Uygulama modu (PWA)</strong></label>
                        <p class="kur-help">Ziyaretçiler siteyi telefonlarına uygulama olarak kurabilir.</p>
                    </div>
                    <?php
                    $modulSecenekleri = discover_modules();
                    $seciliModuller   = $isPost && $adim === 'kur'
                        ? (is_array($_POST['moduller'] ?? null) ? $_POST['moduller'] : [])
                        : ($form['moduller'] ?? array_keys(array_filter($modulSecenekleri, static fn (array $m): bool => $m['varsayilan'])));
                    ?>
                    <?php foreach ($modulSecenekleri as $modulAdi => $modul): ?>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="modul_<?= e($modulAdi) ?>" name="moduller[]" value="<?= e($modulAdi) ?>"
                                   <?= in_array($modulAdi, $seciliModuller, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="modul_<?= e($modulAdi) ?>"><strong><?= e($modul['baslik']) ?></strong> açık kurulsun</label>
                            <?php if ($modul['aciklama'] !== ''): ?><p class="kur-help"><?= e($modul['aciklama']) ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="kur-actions">
                    <a class="btn cy-btn cy-btn--ghost" href="index.php?adim=veritabani">← Geri</a>
                    <button type="submit" class="btn cy-btn cy-btn--primary" id="kur_dugmesi">Kur</button>
                </div>
            </form>

        <?php elseif ($adim === 'tamam' && $sonuc !== null): ?>
            <!-- ============ BİTİŞ ============ -->
            <div class="kur-done__icon" aria-hidden="true">✓</div>
            <h1 class="kur-title text-center"><?= e($sonuc['site_adi']) ?> hazır</h1>

            <ol class="kur-log">
                <?php foreach ($sonuc['adimlar'] as $i => [$baslik, $tamam, $ayrinti]): ?>
                    <li class="<?= $tamam ? 'is-ok' : 'is-warn' ?>" style="--sira: <?= (int) $i ?>">
                        <span aria-hidden="true"><?= $tamam ? '✓' : '!' ?></span>
                        <span><strong><?= e($baslik) ?></strong> <small><?= e((string) $ayrinti) ?></small></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <div class="kur-links">
                <a class="kur-link" href="../"><strong>Siteyi aç</strong><span>Ana sayfa</span></a>
                <a class="kur-link" href="../giris"><strong>Panele git</strong><span><?= e($sonuc['kadi']) ?> ile giriş</span></a>
                <form method="post" action="index.php" class="kur-link kur-link--form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="islem" value="temizle">
                    <button type="submit"><strong>Kurulum klasörünü sil</strong><span>Önerilir · geri dönüşü yok</span></button>
                </form>
            </div>

            <?php if (!empty($sonuc['ornek_veri'])): ?>
                <h2 class="kur-section">Demo hesaplar</h2>
                <table class="kur-table">
                    <thead><tr><th>Rol</th><th>Kullanıcı adı</th></tr></thead>
                    <tbody>
                        <tr><td>Yönetici</td><td><code>ali.yonetici</code></td></tr>
                        <tr><td>Editör</td><td><code>elif.editor</code></td></tr>
                        <tr><td>Üye</td><td><code>mehmet.uye</code></td></tr>
                    </tbody>
                </table>
                <p class="kur-help">
                    Ortak parola: <code>Demo1234!</code>
                    <?= !empty($sonuc['demo_modu'])
                        ? '· Demo modu açık: giriş ekranı bu hesapları tek tıkla girişle listeler.'
                        : '· Bu hesaplar yalnızca geliştirme ortamında giriş ekranında listelenir. Yayına çıkmadan Panel → Sistem\'den kaldırın.' ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($sonuc['gelistirme'])): ?>
                <p class="kur-note">Site <strong>geliştirme modunda</strong> kuruldu. Yayına almadan önce <code>.env</code> içinde <code>APP_ENV=production</code> ve <code>APP_DEBUG=false</code> yapın.</p>
            <?php endif; ?>

            <p class="kur-next">
                Sonraki adımlar:
                <a href="../panel/ayarlar/eposta">SMTP ayarla</a> ·
                <a href="../panel/ayarlar/genel">Logonu yükle</a> ·
                <a href="https://github.com/CilginYazilim/cy-php-starter/blob/main/modules/Ornek/README.md" target="_blank" rel="noopener">İlk modülünü üret</a>
                (<code>php cy make:module Stok</code>)
            </p>
        <?php endif; ?>
    </section>

    <p class="kur-foot">ÇILGIN Yazılım · <a href="https://cilginyazilim.com/kutuphane/php-baslangic-sablonu" target="_blank" rel="noopener">Belgeler</a> · <a href="https://github.com/CilginYazilim/cy-php-starter" target="_blank" rel="noopener">GitHub</a></p>
</main>

<?php /* Kur düğmesine basınca: kurulum sunucuda TEK istekte çalışır; bu
         katman yapılacakları sırayla canlandırır ki ekran donmuş görünmesin. */ ?>
<div class="kur-overlay" id="kur_overlay" hidden>
    <div class="kur-overlay__card" role="status" aria-live="polite">
        <span class="kur-spinner" aria-hidden="true"></span>
        <strong>Kuruluyor…</strong>
        <ol class="kur-log kur-log--pending">
            <li><span aria-hidden="true">•</span><span>Veritabanı ve şema</span></li>
            <li><span aria-hidden="true">•</span><span>Yönetici hesabı ve .env</span></li>
            <li><span aria-hidden="true">•</span><span>Migration'lar ve modüller</span></li>
            <li><span aria-hidden="true">•</span><span>Örnek veri</span></li>
        </ol>
    </div>
</div>

<script src="kurulum.js"></script>
</body>
</html>
