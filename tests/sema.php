<?php
/**
 * =====================================================================
 *  ŞEMA KAYMASI TESTİ – Yeni kurulum ile yükseltilmiş kurulum aynı mı?
 * ---------------------------------------------------------------------
 *      php tests/sema.php --eski=eski.sql [--host=127.0.0.1] [--port=3306]
 *                         [--kullanici=root] [--parola=] [--birak]
 *
 *  İki geçici veritabanı kurar:
 *    YENİ  → bugünkü kurulum/database.sql + bütün migration'lar
 *            (sihirbazın yaptığı)
 *    ESKİ  → --eski ile verilen ÖNCEKİ sürümün database.sql'i + bugünkü
 *            migration'lar ("php cy migrate" ile yükselten kullanıcı)
 *
 *  Sonra INFORMATION_SCHEMA üzerinden sütunları (tür, NULL, varsayılan),
 *  indeksleri, yabancı anahtarları ve ayar anahtarlarını karşılaştırır.
 *  Fark varsa listeler ve 1 ile çıkar. CI eski dosyayı son sürüm
 *  etiketinden alır:  git show v1.5.1:kurulum/database.sql > eski.sql
 *
 *  Sütun SIRASI karşılaştırılmaz (ALTER ... ADD sona ekler; anlamı yok).
 * =====================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('CY_BASE', dirname(__DIR__));
define('CY_START', microtime(true));

require CY_BASE . '/app/bootstrap.php';

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? '1';
    }
}

$eskiDosya = (string) ($opts['eski'] ?? '');

if ($eskiDosya === '' || !is_file($eskiDosya)) {
    fwrite(STDERR, "Kullanım: php tests/sema.php --eski=eski.sql [--host=..] [--kullanici=..] [--parola=..]\n");
    exit(2);
}

$host  = (string) ($opts['host'] ?? '127.0.0.1');
$port  = (int) ($opts['port'] ?? 3306);
$user  = (string) ($opts['kullanici'] ?? 'root');
$pass  = (string) ($opts['parola'] ?? '');
$sunucu = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

/** database.sql'i ifadelere böler (kurulum/index.php ile aynı kural). */
function sql_ifadeleri(string $sql): array
{
    $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
    $ifadeler = [];
    $simdiki = '';
    $tirnak = '';
    $uzunluk = strlen($sql);

    for ($i = 0; $i < $uzunluk; $i++) {
        $c = $sql[$i];

        if ($tirnak !== '') {
            $simdiki .= $c;
            if ($c === $tirnak && $sql[$i - 1] !== '\\') {
                $tirnak = '';
            }
            continue;
        }

        if ($c === "'" || $c === '"' || $c === '`') {
            $tirnak = $c;
            $simdiki .= $c;
            continue;
        }

        if ($c === ';') {
            if (trim($simdiki) !== '') {
                $ifadeler[] = trim($simdiki);
            }
            $simdiki = '';
            continue;
        }

        $simdiki .= $c;
    }

    if (trim($simdiki) !== '') {
        $ifadeler[] = trim($simdiki);
    }

    return $ifadeler;
}

function kur(PDO $sunucu, string $ad, string $sqlDosyasi, array $baglanti): PDO
{
    $sunucu->exec("DROP DATABASE IF EXISTS `$ad`");
    $sunucu->exec("CREATE DATABASE `$ad` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");

    [$host, $port, $user, $pass] = $baglanti;
    $db = new PDO("mysql:host=$host;port=$port;dbname=$ad;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    foreach (sql_ifadeleri((string) file_get_contents($sqlDosyasi)) as $ifade) {
        /* GÜVENLİK: Eski sürümlerin database.sql'i "CREATE DATABASE /
         * USE yeni_proje" içeriyordu (1.3'te kaldırıldı). Çalıştırılsaydı
         * test, sunucudaki BAŞKA bir veritabanına tablo silip yazardı.
         * Bu iki ifade atlanır; her şey yalnızca geçici veritabanında olur. */
        if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s|DROP\s+DATABASE)/i', $ifade) === 1) {
            continue;
        }

        $db->exec($ifade);
    }

    // Sihirbaz gibi: uygulama + açık modüllerin (Örnek) migration'ları, temel partiye.
    $moduller = [];
    foreach (glob(CY_BASE . '/modules/*/migrations', GLOB_ONLYDIR) ?: [] as $klasor) {
        $moduller[basename(dirname($klasor))] = $klasor;
    }

    (new App\Core\Database\Migrator($db, CY_BASE . '/database/migrations', $moduller, $moduller))->run(null, true);

    return $db;
}

/** @return array<string,array<int,string>> bölüm → satırlar */
function sema(PDO $sunucu, string $ad): array
{
    $sorgu = static function (string $sql) use ($sunucu, $ad): array {
        $stmt = $sunucu->prepare($sql);
        $stmt->execute([':db' => $ad]);

        return array_map(static fn (array $s): string => implode(' | ', array_map('strval', $s)), $stmt->fetchAll(PDO::FETCH_ASSOC));
    };

    $sonuc = [
        'tablolar'  => $sorgu("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db ORDER BY 1"),
        'sutunlar'  => $sorgu("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, IFNULL(COLUMN_DEFAULT, '∅'), EXTRA
                                 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db ORDER BY 1, 2"),
        'indeksler' => $sorgu("SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
                                 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = :db GROUP BY 1, 2, 3 ORDER BY 1, 2"),
        'yabanci'   => $sorgu("SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
                                 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
                                 JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
                                   ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                                WHERE k.TABLE_SCHEMA = :db AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY 1, 2"),
    ];

    $sonuc['ayarlar'] = array_map(
        static fn (array $s): string => implode(' | ', $s),
        $sunucu->query("SELECT anahtar, grup, tip FROM `$ad`.ayarlar ORDER BY anahtar")->fetchAll(PDO::FETCH_ASSOC)
    );

    return $sonuc;
}

$baglanti = [$host, $port, $user, $pass];
$yeniAd   = 'cy_sema_yeni';
$eskiAd   = 'cy_sema_eski';

echo "\nŞema kayması testi\n";

try {
    kur($sunucu, $yeniAd, CY_BASE . '/kurulum/database.sql', $baglanti);
    echo "  ✔ YENİ: bugünkü database.sql + migration'lar kuruldu\n";

    kur($sunucu, $eskiAd, $eskiDosya, $baglanti);
    echo "  ✔ ESKİ: " . basename($eskiDosya) . " + migration'lar kuruldu (yükseltme)\n";

    $yeni = sema($sunucu, $yeniAd);
    $eski = sema($sunucu, $eskiAd);
    $fark = 0;

    foreach ($yeni as $bolum => $satirlar) {
        $yalnizYeni = array_diff($satirlar, $eski[$bolum]);
        $yalnizEski = array_diff($eski[$bolum], $satirlar);

        if ($yalnizYeni === [] && $yalnizEski === []) {
            echo "  ✔ $bolum aynı (" . count($satirlar) . ")\n";
            continue;
        }

        $fark++;
        echo "  ✖ $bolum farklı\n";
        foreach ($yalnizYeni as $s) {
            echo "      + yalnız yeni kurulumda: $s\n";
        }
        foreach ($yalnizEski as $s) {
            echo "      - yalnız yükseltilende:  $s\n";
        }
    }
} finally {
    if (!isset($opts['birak'])) {
        $sunucu->exec("DROP DATABASE IF EXISTS `$yeniAd`");
        $sunucu->exec("DROP DATABASE IF EXISTS `$eskiAd`");
    }
}

echo $fark === 0 ? "\nŞemalar aynı.\n\n" : "\n$fark bölümde fark var.\n\n";

exit($fark === 0 ? 0 : 1);
