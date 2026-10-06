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
 *  KURULUM SONUNDA TEK BİR EKSİK KALMAZ. Son adım sırasıyla:
 *    · veritabanını oluşturur ve database.sql'i içeri aktarır
 *    · istenmişse kurulum/demo.sql örnek verisini yükler
 *    · site ayarlarını formdaki değerlerle günceller
 *    · yönetici hesabını açar
 *    · .env dosyasını yazar
 *    · database/migrations altındaki EK tabloları kurar
 *      (onbellek, isler, api_anahtarlari) — komut satırı GEREKMEZ
 *
 *  NEDEN AYRI BİR KLASÖR VE BAĞIMSIZ KOD?
 *  Bu dosya, .env yazılana KADAR app/ klasöründeki hiçbir sınıfı
 *  yüklemez. Sebebi: Autoloader ve Config, projenin geri kalanı gibi
 *  ".env" dosyasının ve dolayısıyla VERİTABANININ var olduğunu
 *  varsayar — ki kurulumun amacı tam olarak bunları oluşturmaktır.
 *  Bu yüzden gereken birkaç yardımcı fonksiyon (doğrulama, parola
 *  özetleme) bu dosyanın içinde ayrıca tanımlanmıştır.
 *
 *  .env YAZILDIKTAN SONRA bu varsayım artık geçerlidir; migration'ları
 *  çalıştırmak için uygulamanın kendi önyüklemesi güvenle kullanılır
 *  (bkz. run_migrations).
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
 *
 *  ► KİLİT KOŞULSUZDUR VE "KAPALI" YÖNDE BOZULUR. Proje kökünde .env
 *    ya da storage/installed.lock varsa sihirbaz HİÇBİR form işlemez.
 *    Eskiden kilit veritabanına bağlanıp yönetici sayısına bakıyordu;
 *    veritabanı bir an düştüğünde kilit AÇILIYORDU. Ayrıca "?yeniden=1"
 *    parametresi kimlik sormadan kilidi kaldırıyor ve herhangi bir
 *    ziyaretçinin .env'i kendi sunucusuna yönlendirip kendine yönetici
 *    hesabı açmasına izin veriyordu. Yeniden kurmak isteyen kişi artık
 *    sunucuya erişip .env ve storage/installed.lock dosyalarını kendisi
 *    siler — yani bunu yalnızca sunucunun sahibi yapabilir.
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
const SCHEMA_PATH   = __DIR__ . '/database.sql';
const DEMO_PATH     = __DIR__ . '/demo.sql';
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
    if (preg_match('/^(.+):(\d{1,5})\z/', $host, $m) === 1 && !str_contains($m[1], ':')) {
        return [$m[1], (int) $m[2]];
    }

    return [$host, 3306];
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
 * --enable=Ad" + "php cy migrate"in sihirbazdaki karşılığı. Panelde
 * modül aç/kapa ekranı olmadığı için SSH'siz bir hostingde modülü
 * açmanın tek yolu budur.
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

/** Formda önceki değeri göstermek için: POST > oturum > varsayılan. */
function eski(string $field, array $store, string $default = ''): string
{
    if (isset($_POST[$field])) {
        return post_str($field);
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

/* Kurulum zaten tamamlandı mı? .env ya da kilit dosyası varsa evet.
 * Bu kontrol veritabanına HİÇ gitmez (bkz. is_installed). */
$alreadyInstalled = is_installed();

$adim = is_string($_GET['adim'] ?? null) ? $_GET['adim'] : 'gereksinimler';
if (!array_key_exists($adim, ADIMLAR)) {
    $adim = 'gereksinimler';
}

/* Kurulumu AZ ÖNCE bu oturumda bitiren kişi özet ekranını bir kez
 * görebilir. Özet yalnızca o oturumun $_SESSION'ında durur; başka bir
 * ziyaretçinin bu bayrağı üretmesinin yolu yoktur. */
$yeniBitti = ($adim === 'tamam' && !empty($_SESSION['kurulum_sonuc']));
$kilitli   = $alreadyInstalled && !$yeniBitti;


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

        /* Silinemedi: en azından klasörü web'e kapatalım. Bu istekten
         * sonra sihirbaz tarayıcıdan hiç açılmaz. */
        deny_web_access();

        $errors[] = 'Klasör silinemedi; web erişimine kapatıldı. Yine de "kurulum" klasörünü FTP/dosya yöneticisi ile elle silin.';
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
    ['label' => 'storage/ klasörü yazılabilir', 'ok' => !is_dir(ROOT_PATH . '/storage') || is_writable(ROOT_PATH . '/storage'), 'note' => 'Günlük kayıtları, önbellek ve kuyruk dosyaları buraya yazılır.'],
    ['label' => 'kurulum/ klasörü silinebilir', 'ok' => is_writable(ROOT_PATH) && is_writable(__DIR__), 'note' => 'Kurulum bitince klasörü tek tıkla silebilmek için gerekli.'],
];
$allChecksOk = !in_array(false, array_column($checks, 'ok'), true);


/* =====================================================================
 *  FORM İŞLEME
 * ================================================================== */
/* İKİ KİLİT BİRDEN: $kilitli özet ekranı için gevşer, ama hiçbir
 * kurulum adımı .env varken çalışmamalıdır — bu yüzden dosya kontrolü
 * burada ayrıca yapılır. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$kilitli && !$alreadyInstalled && ($_POST['islem'] ?? '') !== 'temizle') {

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Oturum doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {

        /* ---------- ADIM 2: VERİTABANI ---------- */
        if ($adim === 'veritabani') {
            $db_host = trim(post_str('db_host'));
            $db_name = trim(post_str('db_name'));
            $db_user = trim(post_str('db_user'));
            $db_pass = post_str('db_pass');
            $db_ustune_yaz = isset($_POST['db_ustune_yaz']);

            if ($db_host === '') {
                $errors[] = 'Veritabanı sunucusu boş bırakılamaz.';
            } elseif (preg_match('/^[A-Za-z0-9._\-\[\]:]+\z/', $db_host) !== 1) {
                $errors[] = 'Veritabanı sunucusu geçersiz karakter içeriyor. Örn: 127.0.0.1 ya da localhost:3307';
            }
            if ($db_name === '') {
                $errors[] = 'Veritabanı adı boş bırakılamaz.';
            } elseif (!preg_match(IDENTIFIER_RE, $db_name)) {
                $errors[] = 'Veritabanı adı yalnızca harf, rakam ve alt çizgi (_) içerebilir, rakamla başlayamaz.';
            }
            if ($db_user === '') {
                $errors[] = 'Veritabanı kullanıcı adı boş bırakılamaz.';
            }

            $mevcutTablolar = [];
            $engelleyenler  = [];
            $silinecekler   = schema_drop_tables(__DIR__ . '/database.sql');

            if ($errors === []) {
                try {
                    $baglanti       = connect_without_database($db_host, $db_user, $db_pass);
                    $mevcutTablolar = existing_tables($baglanti, $db_name);
                    $engelleyenler  = blocking_foreign_keys($baglanti, $db_name, $silinecekler);
                } catch (PDOException $e) {
                    $errors[] = 'Veritabanı sunucusuna bağlanılamadı: ' . $e->getMessage();
                }
            }

            /* YABANCI ANAHTAR ENGELİ: onay kutusu işaretli olsa bile
             * kurulum başlamaz; aksi hâlde şema yarıda kalırdı. */
            if ($errors === [] && $engelleyenler !== []) {
                $errors[] = 'Bu veritabanındaki bazı tablolar, kurulumun silip yeniden oluşturacağı tablolara '
                    . 'yabancı anahtarla bağlı. Kurulum yarıda kalmasın diye hiçbir şeye dokunulmadı. '
                    . 'Boş bir veritabanı kullanın ya da önce şu kısıtları kaldırın: ' . implode('; ', $engelleyenler);
            }

            /* DOLU VERİTABANI KORUMASI: şema DROP TABLE içerir. Onay
             * ekranında SİLİNECEK tablolar tek tek listelenir; "N tablo
             * var" demek neyin kaybolacağını söylemiyordu. */
            if ($errors === [] && $mevcutTablolar !== [] && !$db_ustune_yaz) {
                $errors[] = sprintf(
                    '"%s" veritabanı boş değil (%d tablo var). Boş bir veritabanı adı girin ya da aşağıdaki listeyi inceleyip onay kutusunu işaretleyin.',
                    $db_name,
                    count($mevcutTablolar)
                );
                $dbDoluUyarisi = [
                    'silinecek' => array_values(array_intersect($mevcutTablolar, $silinecekler)),
                    'kalacak'   => array_values(array_diff($mevcutTablolar, $silinecekler)),
                ];
            }

            if ($errors === []) {
                $kurulum['db'] = compact('db_host', 'db_name', 'db_user', 'db_pass');
                header('Location: index.php?adim=site');
                exit;
            }
        }

        /* ---------- ADIM 3: SİTE AYARLARI ---------- */
        if ($adim === 'site') {
            [$site_adi, $siteHata] = validate_text(post_str('site_adi'), 'Site adı', 2, 150);
            $site_aciklama = trim(post_str('site_aciklama'));
            $site_url      = trim(post_str('site_url'));

            /* Uygulama modu VARSAYILAN AÇIK gelir; kutuyu boşaltmak
             * yalnızca "pwa_aktif" ayarını 0 yapar, başka hiçbir şeye
             * dokunmaz ve panelden istendiği an geri açılır. */
            $pwa_aktif = isset($_POST['pwa_aktif']);

            /* GELİŞTİRME MODU VARSAYILAN KAPALI gelir. Eskiden kurulum
             * her siteyi APP_ENV=local + APP_DEBUG=true ile kuruyordu;
             * canlıya alınan sitede hata ayrıntıları (dosya yolları,
             * SQL metinleri) ziyaretçiye görünüyor, giriş ekranı demo
             * parolalarını öneriyordu. Yerel makinede çalışan geliştirici
             * kutuyu bilerek işaretler. */
            $gelistirme = isset($_POST['gelistirme']);

            /* Yalnızca diskte GERÇEKTEN bulunan modül adları kabul
             * edilir; formdan gelen ad doğrudan kullanılmaz. */
            $gonderilen = is_array($_POST['moduller'] ?? null) ? array_filter($_POST['moduller'], 'is_string') : [];
            $moduller   = array_values(array_intersect(array_keys(discover_modules()), $gonderilen));

            if ($siteHata !== null) {
                $errors[] = $siteHata;
            }

            if ($site_url !== '' && filter_var($site_url, FILTER_VALIDATE_URL) === false) {
                $errors[] = 'Site adresi geçerli bir adres olmalıdır (örn. https://ornek.com).';
            } elseif ($site_url !== '' && !preg_match('#^https?://#i', $site_url)) {
                $errors[] = 'Site adresi http:// ya da https:// ile başlamalıdır.';
            }

            if ($errors === []) {
                $site_url = rtrim($site_url, '/');
                $kurulum['site'] = compact('site_adi', 'site_aciklama', 'site_url', 'pwa_aktif', 'gelistirme', 'moduller');
                header('Location: index.php?adim=yonetici');
                exit;
            }
        }

        /* ---------- ADIM 4: YÖNETİCİ + KURULUMU ÇALIŞTIR ---------- */
        if ($adim === 'yonetici') {
            [$admin_ad, $adHata]         = validate_name(post_str('admin_ad'), 'Ad');
            [$admin_soyad, $soyadHata]   = validate_name(post_str('admin_soyad'), 'Soyad');
            [$admin_kadi, $kadiHata]     = validate_username(post_str('admin_kadi'));
            [$admin_eposta, $epostaHata] = validate_email(post_str('admin_eposta'));
            $admin_sifre = post_str('admin_sifre');
            $demo_yukle  = isset($_POST['demo_yukle']);

            /* DEMO MODU örnek hesaplar olmadan anlamsızdır: giriş
             * ekranı listelenecek hesap bulamazdı. Seçildiyse örnek
             * veri de yüklenir. */
            $demo_modu = isset($_POST['demo_modu']);
            if ($demo_modu) {
                $demo_yukle = true;
            }

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

                    /* Örnek veri temel şemadan SONRA gelir: demo
                     * kullanıcılar "kullanicilar" tablosuna, demo
                     * mesajlar ona bağlı "mesajlar" tablosuna yazar. */
                    if ($demo_yukle && is_file(DEMO_PATH)) {
                        $statements += import_schema($pdo, DEMO_PATH);
                    }

                    $settingsStmt = $pdo->prepare('UPDATE ayarlar SET deger = :deger WHERE anahtar = :anahtar');
                    /* "site_url" AYAR SATIRI YOKTUR: adres artık yalnızca
                     * .env → APP_URL içinde tutulur. İki yerde durduğu
                     * sürece hangisinin geçerli olduğu belirsizdi ve
                     * panelden değiştirilen değer .env'i güncellemediği
                     * için hiçbir işe yaramıyordu. */
                    /* PWA'nın YALNIZCA açma anahtarı yazılır. Uygulama adı,
                     * açıklaması ve simgesi bilerek BOŞ bırakılır: künyeyi
                     * üreten PwaController boş alanlarda site ayarlarına
                     * düşer, yani uygulama yukarıda girilen site adıyla
                     * kurulur ve site adı sonradan değişirse uygulama adı
                     * da onunla birlikte değişir. Buraya kopyalasaydık iki
                     * ayrı doğruluk kaynağı olur, biri sessizce bayatlardı. */
                    foreach ([
                        'site_adi'        => $site['site_adi'],
                        'site_aciklama'   => $site['site_aciklama'],
                        'iletisim_eposta' => $admin_eposta,
                        'pwa_aktif'       => ($site['pwa_aktif'] ?? true) ? '1' : '0',
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

                    [$dbSunucu, $dbKapi] = split_host($db['db_host']);
                    $gelistirme          = !empty($site['gelistirme']);

                    /* .env, .env.example ile AYNI anahtarları taşır.
                     * Eksik bırakılan her anahtar için kod varsayılana
                     * düşerdi; dosyada görünmediği için de kullanıcı
                     * öyle bir ayarın var olduğunu fark etmezdi.
                     *
                     * SESSION_NAME ve APP_KEY HER KURULUMA ÖZELDİR. Aynı
                     * alan adında iki kurulum (örn. /demo1 ve /demo2) eski
                     * sürümde aynı çerez adını ve oturum klasörünü
                     * paylaşıyordu; birinde yönetici olan, diğerinde de
                     * yönetici sayılabiliyordu (bkz. App\Core\Session). */
                    write_env_file(ENV_PATH, [
                        'APP_NAME'         => $site['site_adi'],
                        'APP_DESCRIPTION'  => $site['site_aciklama'],
                        'APP_URL'          => $site['site_url'],
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

                    /* Artık .env var: ek tabloları uygulamanın kendi
                     * Migrator'ı kurabilir. Komut satırı gerekmez. */
                    [$migrationSayisi, $migrationHatasi] = run_migrations();
                    [$acilanModuller, $modulHatasi]      = enable_modules($site['moduller'] ?? []);

                    $_SESSION['kurulum_sonuc'] = [
                        'db_name'     => $db['db_name'],
                        'statements'  => $statements,
                        'migrations'  => $migrationSayisi,
                        'migr_hata'   => $migrationHatasi,
                        'moduller'    => $acilanModuller,
                        'modul_hata'  => $modulHatasi,
                        'demo'        => $demo_yukle,
                        'demo_modu'   => $demo_modu,
                        'kadi'        => $admin_kadi,
                        'eposta'      => $admin_eposta,
                        'site_adi'    => $site['site_adi'],
                        'pwa'         => (bool) ($site['pwa_aktif'] ?? true),
                        'gelistirme'  => $gelistirme,
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
                <h2 class="cy-title mb-1">Kurulum zaten tamamlanmış</h2>
                <p class="cy-subtitle mb-3">Sihirbaz kendini kilitledi; kimse buradan veritabanınızı sıfırlayamaz.</p>

                <?php /* Bu ekranda da silme düğmesi VARDIR. Kullanıcı kurulumu
                         bitirip tarayıcıyı kapatmış, sonra "klasörü silmeyi
                         unutmuştum" diye geri dönmüş olabilir. Düğme yalnızca
                         son ekranda olsaydı, tam da en çok gereken durumda
                         ortada olmazdı. */ ?>
                <div class="cy-alert cy-alert--warning mb-3">
                    <strong>Bu klasör hâlâ sunucuda.</strong> Güvenlik için silin.
                    Kilit açık olsa bile, bu dosyanın var olması sunucunuz hakkında
                    gereksiz bilgi verir.
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <form method="post" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="islem" value="temizle">
                        <button type="submit" class="btn cy-btn cy-btn--danger">Kurulum Klasörünü Sil</button>
                    </form>
                    <a class="btn cy-btn cy-btn--primary" href="../index.php">Siteye Git</a>
                </div>

                <?php /* Yeniden kurulum TARAYICIDAN BAŞLATILAMAZ. Eskiden
                         "?yeniden=1" kilidi kaldırıyordu ve herhangi bir
                         ziyaretçi siteyi kendi veritabanına bağlayıp kendine
                         yönetici hesabı açabiliyordu. */ ?>
                <p class="cy-subtitle mb-0">
                    Sıfırdan yeniden kurmak isterseniz sunucudan <code>.env</code> ve
                    <code>storage/installed.lock</code> dosyalarını silin. Bu işlem bilerek yalnızca
                    sunucuya erişimi olan kişiye bırakılmıştır.
                </p>

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
                        <div class="col-12">
                            <div class="form-text">
                                Varsayılan dışında bir kapı kullanıyorsanız sunucuya ekleyin: <code>localhost:3307</code>
                            </div>
                        </div>

                        <?php if (!empty($dbDoluUyarisi) && is_array($dbDoluUyarisi)): ?>
                            <div class="col-12">
                                <?php if ($dbDoluUyarisi['silinecek'] !== []): ?>
                                    <p class="mb-1"><strong>SİLİNİP yeniden oluşturulacak tablolar (içindeki veriler kaybolur):</strong></p>
                                    <p class="small mb-2"><code><?= e(implode(', ', $dbDoluUyarisi['silinecek'])) ?></code></p>
                                <?php else: ?>
                                    <p class="small mb-2">Kurulumun sileceği adla bir tablo yok; yalnızca yeni tablolar eklenecek.</p>
                                <?php endif; ?>
                                <?php if ($dbDoluUyarisi['kalacak'] !== []): ?>
                                    <p class="mb-1"><strong>Dokunulmayacak tablolar:</strong></p>
                                    <p class="small mb-2"><code><?= e(implode(', ', $dbDoluUyarisi['kalacak'])) ?></code></p>
                                <?php endif; ?>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="db_ustune_yaz" name="db_ustune_yaz" value="1">
                                    <label class="form-check-label" for="db_ustune_yaz">
                                        <strong>Yukarıdaki tabloların silinip yeniden oluşturulmasını onaylıyorum.</strong>
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
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

                        <?php
                        /* ONAY KUTUSU "eski()" İLE GERİ GETİRİLEMEZ: işaretlenmemiş
                         * bir kutu hiç gönderilmez, yani "kullanıcı kapattı" ile
                         * "form hiç gönderilmedi" aynı görünür. Bu yüzden isteğin
                         * POST olup olmadığına bakıyoruz; ilk açılışta ve geri
                         * dönüldüğünde varsayılan AÇIK'tır. */
                        $pwaSecili = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
                            ? isset($_POST['pwa_aktif'])
                            : (bool) ($kurulum['site']['pwa_aktif'] ?? true);
                        ?>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="pwa_aktif" name="pwa_aktif" value="1" <?= $pwaSecili ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pwa_aktif">
                                    Uygulama modu (PWA) açık kurulsun
                                </label>
                            </div>
                            <div class="form-text">
                                Ziyaretçiler siteyi telefonlarına uygulama olarak kurabilir ve ağ
                                yokken açabilir. Uygulamanın adı, açıklaması ve simgesi yukarıdaki
                                site bilgilerinden gelir; hepsi daha sonra
                                <strong>Panel → Site Ayarları → Uygulama (PWA)</strong>
                                bölümünden değiştirilebilir.
                            </div>
                        </div>

                        <?php
                        /* MODÜLLER: module.json'da "kurulumda_acik": true olan modül
                         * işaretli gelir. Açılan modülün tabloları kurulumla birlikte
                         * kurulur. Panelde modül aç/kapa ekranı yoktur; sonradan
                         * değiştirmek için "php cy module --enable=Ad / --disable=Ad". */
                        $modulSecenekleri = discover_modules();
                        $seciliModuller   = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
                            ? (is_array($_POST['moduller'] ?? null) ? $_POST['moduller'] : [])
                            : ($kurulum['site']['moduller'] ?? array_keys(array_filter($modulSecenekleri, static fn (array $m): bool => $m['varsayilan'])));
                        ?>
                        <?php if ($modulSecenekleri !== []): ?>
                            <div class="col-12">
                                <span class="form-label d-block">Modüller</span>
                                <?php foreach ($modulSecenekleri as $modulAdi => $modul): ?>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="modul_<?= e($modulAdi) ?>" name="moduller[]" value="<?= e($modulAdi) ?>"
                                               <?= in_array($modulAdi, $seciliModuller, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="modul_<?= e($modulAdi) ?>">
                                            <?= e($modul['baslik']) ?> açık kurulsun
                                        </label>
                                        <?php if ($modul['aciklama'] !== ''): ?>
                                            <div class="form-text mt-0"><?= e($modul['aciklama']) ?> (<code>modules/<?= e($modulAdi) ?></code>)</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <div class="form-text">
                                    Açılan modülün tabloları kurulumla birlikte kurulur ve panel menüsünde görünür.
                                    Sonradan değiştirmek için: <code>php cy module --enable=Ad</code> /
                                    <code>--disable=Ad</code>.
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        $gelistirmeSecili = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
                            ? isset($_POST['gelistirme'])
                            : (bool) ($kurulum['site']['gelistirme'] ?? false);
                        ?>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="gelistirme" name="gelistirme" value="1" <?= $gelistirmeSecili ? 'checked' : '' ?>>
                                <label class="form-check-label" for="gelistirme">
                                    Geliştirme ortamı (hata ayrıntıları ekranda görünsün)
                                </label>
                            </div>
                            <div class="form-text">
                                Yalnızca kendi bilgisayarınızda deneme yapıyorsanız işaretleyin.
                                <strong>Yayındaki bir sitede kapalı bırakın:</strong> açıkken hata
                                ekranları dosya yollarını ve SQL ayrıntılarını ziyaretçiye gösterir.
                                Daha sonra <code>.env</code> dosyasındaki <code>APP_ENV</code> ve
                                <code>APP_DEBUG</code> satırlarından değiştirilebilir.
                            </div>
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
                            <input type="text" class="form-control" id="admin_ad" name="admin_ad" value="<?= e(post_str('admin_ad')) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="admin_soyad">Soyad</label>
                            <input type="text" class="form-control" id="admin_soyad" name="admin_soyad" value="<?= e(post_str('admin_soyad')) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="admin_kadi">Kullanıcı Adı</label>
                            <input type="text" class="form-control" id="admin_kadi" name="admin_kadi" value="<?= e($_SERVER['REQUEST_METHOD'] === 'POST' ? post_str('admin_kadi') : 'admin') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="admin_eposta">E-posta</label>
                            <input type="email" class="form-control" id="admin_eposta" name="admin_eposta" value="<?= e(post_str('admin_eposta')) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="admin_sifre">Parola</label>
                            <input type="password" class="form-control" id="admin_sifre" name="admin_sifre" required>
                            <div class="form-text">En az 8 karakter; harf ve rakam içermelidir.</div>
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="demo_yukle" name="demo_yukle" value="1"
                                       <?= isset($_POST['demo_yukle']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="demo_yukle">
                                    <strong>Örnek verileri de yükle</strong>
                                </label>
                                <div class="form-text">
                                    Şablonu ilk kez deniyorsanız işaretleyin: 5 örnek kullanıcı (yönetici, editör,
                                    üye, pasif, askıda; parolaları <code>Demo1234!</code>) ve 2 iletişim mesajı
                                    eklenir, böylece listeleri ve filtreleri dolu görürsünüz.
                                    Gerçek bir projeye başlıyorsanız <strong>boş bırakın</strong>.
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="demo_modu" name="demo_modu" value="1"
                                       <?= isset($_POST['demo_modu']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="demo_modu">
                                    <strong>Demo modu</strong> (herkese açık deneme sitesi)
                                </label>
                                <div class="form-text">
                                    Giriş ekranı Yönetici, Editör ve Üye örnek hesaplarını parolasıyla listeler;
                                    ziyaretçi tek tıkla giriş yapar. Örnek hesaplarla hesap bilgileri, kullanıcılar,
                                    site ayarları ve e-posta gönderimi kilitlidir; yukarıda açtığınız yönetici hesabı
                                    kısıtlanmaz. Örnek verileri de yükler. <strong>Gerçek bir sitede işaretlemeyin</strong>
                                    — sonradan <code>.env</code> içindeki <code>APP_DEMO</code> satırından kapatılır.
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn cy-btn cy-btn--primary mt-3">Kurulumu Tamamla</button>
                </form>

            <?php elseif ($adim === 'tamam' && $sonuc !== null): ?>
                <!-- ============ ADIM 5 ============ -->
                <div class="cy-alert cy-alert--success mb-3">Kurulum başarıyla tamamlandı!</div>

                <div class="kurulum-summary mb-3">
                    Veritabanı&nbsp;: <?= e($sonuc['db_name']) ?> (<?= (int) $sonuc['statements'] ?> ifade)<br>
                    Ek tablolar : <?= (int) ($sonuc['migrations'] ?? 0) ?> migration çalıştı<br>
                    Örnek veri&nbsp;: <?= !empty($sonuc['demo']) ? 'yüklendi' : 'yüklenmedi (temiz kurulum)' ?><br>
                    Site&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?= e($sonuc['site_adi']) ?><br>
                    Uygulama&nbsp;&nbsp;: <?= !empty($sonuc['pwa']) ? 'PWA açık (telefona kurulabilir)' : 'PWA kapalı' ?><br>
                    Ortam&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?= !empty($sonuc['gelistirme']) ? 'geliştirme (APP_DEBUG=true)' : 'yayın (APP_DEBUG=false)' ?><br>
                    Modüller&nbsp;&nbsp;: <?= !empty($sonuc['moduller']) ? e(implode(', ', $sonuc['moduller'])) . ' açık' : 'hiçbiri açılmadı' ?><br>
                    Demo modu&nbsp;: <?= !empty($sonuc['demo_modu']) ? 'açık (APP_DEMO=true)' : 'kapalı' ?><br>
                    Yönetici&nbsp;&nbsp;: <?= e($sonuc['kadi']) ?> · <?= e($sonuc['eposta']) ?>
                </div>

                <?php if (!empty($sonuc['modul_hata'])): ?>
                    <div class="cy-alert cy-alert--warning mb-3">
                        <strong>Modül tabloları kurulamadı.</strong> Site çalışır durumda; tabloları kurmak için
                        <code>php cy migrate</code> çalıştırın ya da <strong>Panel → Sistem Bilgisi</strong>
                        sayfasındaki düğmeyi kullanın.<br>
                        <small><?= e($sonuc['modul_hata']) ?></small>
                    </div>
                <?php endif; ?>

                <?php if (!empty($sonuc['demo_modu'])): ?>
                    <div class="cy-alert cy-alert--info mb-3">
                        <strong>Demo modu açık.</strong> Giriş ekranı Yönetici, Editör ve Üye örnek hesaplarını
                        tek tıkla giriş için listeler. Demoyu kendi hesabınızla
                        (<strong><?= e($sonuc['kadi']) ?></strong>) yönetin; örnek hesaplar için hesap, kullanıcı,
                        ayar ve e-posta işlemleri kilitlidir.
                    </div>
                <?php endif; ?>

                <?php if (!empty($sonuc['gelistirme'])): ?>
                    <div class="cy-alert cy-alert--warning mb-3">
                        Site <strong>geliştirme modunda</strong> kuruldu. Yayına almadan önce
                        <code>.env</code> içinde <code>APP_ENV=production</code> ve
                        <code>APP_DEBUG=false</code> yapın.
                    </div>
                <?php endif; ?>

                <?php if (!empty($sonuc['migr_hata'])): ?>
                    <div class="cy-alert cy-alert--warning mb-3">
                        <strong>Ek tablolar kurulamadı.</strong> Site çalışır durumda, ancak önbelleğin
                        veritabanı sürücüsü, iş kuyruğu ve API anahtarları için şu komutu çalıştırın:
                        <code>php cy migrate</code><br>
                        <small><?= e($sonuc['migr_hata']) ?></small>
                    </div>
                <?php endif; ?>

                <?php if (!empty($sonuc['demo']) && empty($sonuc['demo_modu'])): ?>
                    <div class="cy-alert cy-alert--info mb-3">
                        Örnek kullanıcıların tümünün parolası <code>Demo1234!</code>.
                        Canlıya çıkmadan önce silin:
                        <code>DELETE FROM kullanicilar WHERE eposta LIKE '%.demo@ornek.com';</code>
                    </div>
                <?php endif; ?>

                <div class="cy-alert cy-alert--warning mb-3">
                    <strong>Son adım:</strong> "kurulum/" klasörünü şimdi silin. Sihirbaz kendini
                    kilitledi (<code>.env</code> ve <code>storage/installed.lock</code> varken hiçbir
                    adım çalışmaz), ama kullanılmayan kodu sunucuda bırakmamak en iyisidir.
                    Aşağıdaki düğme klasörü siler ve sizi sitenin ana sayfasına götürür.
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="islem" value="temizle">
                        <button type="submit" class="btn cy-btn cy-btn--danger">Kurulum Klasörünü Sil ve Bitir</button>
                    </form>
                    <a class="btn cy-btn cy-btn--ghost" href="../index.php?r=giris">Klasörü elle sileceğim, girişe git</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
