<?php
/**
 * =====================================================================
 *  ÖRNEK VERİTABANI DÖKÜMÜ – database/ornek-veritabani.sql'i üretir
 * ---------------------------------------------------------------------
 *      php tests/ornek-sql.php --db-adi=cy_ornek [--host=127.0.0.1]
 *          [--port=3306] [--kullanici=root] [--parola=] [--cikti=dosya.sql]
 *
 *  KAYNAK, sihirbazla "Örnek veriyle kur" seçilerek YENİ kurulmuş bir
 *  veritabanıdır; dosya elle yazılmaz. Böylece SQL, sihirbazın kurduğu
 *  şemanın ve DemoData'nın yazdığı verinin birebir aynısı olur:
 *
 *      php cy serve --port=8470                       (temiz bir kopyada)
 *      php tests/kurulum.php http://127.0.0.1:8470 --db-adi=cy_ornek --ornek-veri
 *      php tests/ornek-sql.php --db-adi=cy_ornek
 *
 *  Veritabanına YAZMAZ; okurken ayıklar:
 *    · Sihirbazın açtığı yönetici ve ona ait kayıtlar alınmaz — dosyada
 *      yalnızca parolası herkesçe bilinen örnek hesaplar (Demo1234!) olur.
 *    · Sihirbaz formundan gelen ayarlar (site adı, açıklama, iletişim
 *      adresi, PWA) formun varsayılanına döner.
 *    · Giriş denemeleri, önbellek, iş kuyruğu, parola bağlantıları ve
 *      API anahtarları yalnızca tablo olarak gelir, satırları gelmez.
 *    · Sayaçlar (AUTO_INCREMENT=) yazılmaz; MariaDB'nin JSON yazımı
 *      "json" olarak yazılır ki dosya MySQL 5.7/8'de de aynı kurulsun.
 *
 *  tests/unit.php dosyanın sürümünün ve migration listesinin güncel
 *  olduğunu denetler: sürüm çıkarırken bu betiği yeniden çalıştırın.
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

use App\Core\Config;
use App\Core\Demo;
use App\Core\DemoData;

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? '1';
    }
}

$dbAdi = (string) ($opts['db-adi'] ?? '');

if ($dbAdi === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbAdi)) {
    fwrite(STDERR, "Kullanım: php tests/ornek-sql.php --db-adi=cy_ornek [--host=..] [--kullanici=..] [--parola=..] [--cikti=..]\n");
    exit(2);
}

$cikti = (string) ($opts['cikti'] ?? CY_BASE . '/database/ornek-veritabani.sql');
$host  = (string) ($opts['host'] ?? '127.0.0.1');
$port  = (int) ($opts['port'] ?? 3306);

$db = new PDO("mysql:host=$host;port=$port;dbname=$dbAdi;charset=utf8mb4", (string) ($opts['kullanici'] ?? 'root'), (string) ($opts['parola'] ?? ''), [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* TIMESTAMP sütunları sunucunun saat dilimine göre okunur; dosya UTC
 * yazılır ve UTC okunur (mysqldump gibi), kurulduğu sunucuda kaymaz. */
$db->exec("SET time_zone = '+00:00'");

if (!DemoData::present($db)) {
    fwrite(STDERR, "$dbAdi içinde örnek veri yok. Sihirbazla \"Örnek veriyle kur\" seçilerek kurulmuş bir veritabanı verin.\n");
    exit(1);
}

/** Satırları alınmayan tablolar (kurulumdan kuruluma değişen, kişiye ait kayıtlar). */
const YALNIZ_YAPI = ['login_attempts', 'onbellek', 'isler', 'parola_sifirlama', 'api_anahtarlari'];

/** Sihirbaz formunun yazdığı ayarlar → formun varsayılanı (kurulum/index.php "Formdaki değerler EN SON"). */
$formAyarlari = [
    'site_adi'        => 'CY PHP Starter',
    'site_aciklama'   => DemoData::settings()['site_aciklama'] ?? '',
    'iletisim_eposta' => '',
    'pwa_aktif'       => '1',
];

$sonek = '%' . DemoData::EPOSTA_SONEKI;

/* Örnek hesaplar: DemoData::remove()'un sildiğiyle aynı ölçüt. */
$adlar   = array_keys(Demo::HESAPLAR);
$hesap   = $db->prepare('SELECT id FROM kullanicilar WHERE kullanici_adi IN (' . implode(',', array_fill(0, count($adlar), '?')) . ') AND eposta LIKE ?');
$hesap->execute([...$adlar, $sonek]);
$ornekHesaplar = array_map('intval', $hesap->fetchAll(PDO::FETCH_COLUMN));

/** Satır filtresi: tablo => SQL koşulu. */
$kosullar = [
    'kullanicilar'   => 'id IN (' . implode(',', $ornekHesaplar ?: [0]) . ')',
    'mesajlar'       => 'eposta LIKE ' . $db->quote($sonek),
    'mail_kayitlari' => 'alici_eposta LIKE ' . $db->quote($sonek),
];

/* kullanicilar'a bağlı sütunlar: alınmayan hesabı gösteriyorsa NULL olur
 * (yabancı anahtarların hepsi ON DELETE SET NULL ya da CASCADE'dir). */
$baglar = [];
foreach ($db->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = " . $db->quote($dbAdi) . " AND REFERENCED_TABLE_NAME = 'kullanicilar'") as $b) {
    $baglar[$b['t']][] = $b['c'];
}

$tablolar = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = " . $db->quote($dbAdi) . " AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")
               ->fetchAll(PDO::FETCH_COLUMN);

$surum = (string) Config::get('app.version');
$sql   = <<<SQL
-- =====================================================================
--  CY PHP Starter $surum — örnek veritabanı (şema + örnek veri)
-- ---------------------------------------------------------------------
--  Kurulum sihirbazında "Örnek veriyle kur" seçildiğinde kurulan
--  veritabanının aynısıdır: bütün tablolar, ayarlar, sayfalar, 6 örnek
--  hesap, mesajlar, e-posta geçmişi ve Örnek Modül kayıtları.
--
--  ÖNERİLEN KURULUM SİHİRBAZDIR (kurulum/index.php): .env'i yazar,
--  güvenli anahtarları üretir ve size ait bir yönetici hesabı açar.
--  Bu dosya sihirbazı çalıştıramadığınız durumlar ve veriyi incelemek
--  içindir.
--
--  NASIL KURULUR?
--    1. BOŞ bir veritabanı açın (utf8mb4). Dolu bir veritabanına
--       aktarmayın: aynı adlı tablo varsa aktarma durur.
--    2. Bu dosyayı içe aktarın: phpMyAdmin → İçe Aktar, ya da
--         mysql -u KULLANICI -p VERITABANI < database/ornek-veritabani.sql
--    3. .env.example'ı .env adıyla kopyalayın; DB_HOST, DB_NAME,
--       DB_USER, DB_PASS ve APP_URL'i doldurun. APP_KEY için:
--         php -r "echo bin2hex(random_bytes(32));"
--    4. kurulum/ klasörünü silin.
--    5. Giriş: ali.yonetici / Demo1234!  (yönetici)
--
--  YAYINA ALMADAN ÖNCE: örnek hesapların parolası herkesçe bilinir.
--  Kendinize bir yönetici hesabı açın, ardından Panel → Sistem →
--  "Örnek veriyi kaldır" ile örnek hesapları ve içeriği silin.
--
--  Görseller SQL'de değildir; isterseniz kopyalayın:
--    database/seeders/demo/og-cy-php-starter.png → upload/img/
--    database/seeders/demo/dosyalar/*            → storage/files/ornek/
--
--  Bu dosya elle düzenlenmez; tests/ornek-sql.php üretir.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

SQL;

$sayilar = [];

foreach ($tablolar as $tablo) {
    $olustur = (string) $db->query("SHOW CREATE TABLE `$tablo`")->fetch(PDO::FETCH_NUM)[1];
    $olustur = (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', $olustur);
    $olustur = (string) preg_replace('/longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin (DEFAULT NULL|NOT NULL) CHECK \(json_valid\(`\w+`\)\)/', 'json $1', $olustur);
    $olustur = str_ireplace('current_timestamp()', 'CURRENT_TIMESTAMP', $olustur);

    $sql .= "\n-- --------------------------------------------------------\n-- $tablo\n-- --------------------------------------------------------\n\n$olustur;\n";

    if (in_array($tablo, YALNIZ_YAPI, true)) {
        continue;
    }

    $sayisal = [];
    foreach ($db->query("SELECT COLUMN_NAME c, DATA_TYPE t FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = " . $db->quote($dbAdi) . " AND TABLE_NAME = " . $db->quote($tablo)) as $s) {
        $sayisal[$s['c']] = in_array($s['t'], ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double'], true);
    }

    $kosul    = $kosullar[$tablo] ?? '1 = 1';
    $sirala   = isset($sayisal['id']) ? 'id' : '1';
    $satirlar = $db->query("SELECT * FROM `$tablo` WHERE $kosul ORDER BY $sirala")->fetchAll();

    if ($satirlar === []) {
        continue;
    }

    $degerler = [];
    foreach ($satirlar as $satir) {
        if ($tablo === 'ayarlar' && array_key_exists($satir['anahtar'], $formAyarlari)) {
            $satir['deger'] = $formAyarlari[$satir['anahtar']];
        }

        foreach ($baglar[$tablo] ?? [] as $sutun) {
            if ($satir[$sutun] !== null && !in_array((int) $satir[$sutun], $ornekHesaplar, true)) {
                $satir[$sutun] = null;
            }
        }

        $degerler[] = '(' . implode(', ', array_map(
            static fn (string $sutun, $deger): string => match (true) {
                $deger === null    => 'NULL',
                $sayisal[$sutun]   => (string) $deger,
                default            => $db->quote((string) $deger),
            },
            array_keys($satir),
            $satir
        )) . ')';
    }

    $sutunlar = implode(', ', array_map(static fn (string $s): string => "`$s`", array_keys($satirlar[0])));
    $sql     .= "\nINSERT INTO `$tablo` ($sutunlar) VALUES\n" . implode(",\n", $degerler) . ";\n";
    $sayilar[$tablo] = count($satirlar);
}

$sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

if (file_put_contents($cikti, $sql) === false) {
    fwrite(STDERR, "$cikti yazılamadı.\n");
    exit(1);
}

printf("✔ %s yazıldı (%s KB, %d tablo)\n", $cikti, number_format(strlen($sql) / 1024, 1), count($tablolar));
foreach ($sayilar as $tablo => $adet) {
    printf("    %-16s %d satır\n", $tablo, $adet);
}
