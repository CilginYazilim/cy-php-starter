<?php
/**
 * =====================================================================
 *  BİRİM TESTLERİ – Veritabanı ve web sunucusu GEREKTİRMEZ
 * ---------------------------------------------------------------------
 *      php tests/unit.php
 *
 *  Güvenlik düzeltmelerinin "saf" mantığını sınar: .env okuma/yazma,
 *  yönlendirme hedefi doğrulaması, görünüm adı beyaz listesi, sendmail
 *  argüman koruması, hata sayfası eşlemesi… Bağımlılık yoktur
 *  (PHPUnit gerekmez); GitHub Actions her itmede çalıştırır.
 *
 *  Çıkış kodu: 0 → hepsi geçti, 1 → en az bir test kaldı.
 * =====================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('CY_BASE', dirname(__DIR__));
define('CY_START', microtime(true));

/* bootstrap.php .env yoksa da çalışır; veritabanına hiç bağlanmayız. */
require CY_BASE . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Console\Commands\MakeControllerCommand;
use App\Core\Console\Input;
use App\Core\Demo;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Core\Ip;
use App\Core\Mail\NativeTransport;
use App\Core\Middleware;
use App\Core\NotificationPrefs;
use App\Core\Modules\Module;
use App\Core\Modules\Modules;
use App\Core\Request;
use App\Core\Signer;
use App\Core\Throttle;
use App\Core\Url;
use App\Core\View;
use App\Models\Role;

$gecti = 0;
$kaldi = 0;

function dogrula(string $ad, bool $kosul, string $ayrinti = ''): void
{
    global $gecti, $kaldi;

    if ($kosul) {
        $gecti++;
        echo "  ✔ $ad\n";

        return;
    }

    $kaldi++;
    echo "  ✖ $ad" . ($ayrinti !== '' ? "\n      → $ayrinti" : '') . "\n";
}

function firlatir(callable $fn, string $sinif = Throwable::class): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $sinif;
    }

    return false;
}

echo "\nCY PHP Starter birim testleri\n";

/* ---------------------------------------------------------------- */
echo "\n.env okuma/yazma\n";

$ornekler = ['', 'abc', 'Pa${x}ss', "a\tb", 'c:\\new\\path', 'with "quote"', "multi\nline",
             'p#ss # x', ' bosluk', '$', '\\', '\\$', "'tek'", 'ünicode şifre', 'son\\'];

foreach ($ornekler as $deger) {
    $yazilan = Env::quote($deger);
    dogrula('gidiş-dönüş: ' . json_encode($deger, JSON_UNESCAPED_UNICODE), Env::parseValue($yazilan) === $deger, $yazilan . ' → ' . json_encode(Env::parseValue($yazilan)));
}

dogrula('tırnaklı değerden sonra yorum', Env::parseValue('"deger"   # not') === 'deger');
dogrula('tek tırnak ham kalır', Env::parseValue("'a\\nb'") === 'a\\nb');
dogrula('tırnaksız değerde "#" önünde boşluk yoksa korunur', Env::parseValue('Parola#123') === 'Parola#123');

/* ---------------------------------------------------------------- */
echo "\nGirişten sonra dönüş adresi (açık yönlendirme)\n";

foreach (['panel', 'panel/kullanicilar', '/panel/hesabim/'] as $iyi) {
    dogrula("kabul: $iyi", Middleware::safeIntended($iyi) === trim($iyi, '/'));
}

foreach (['/\\evil.example', '//evil.example', 'https://evil.example', '\\\\evil', '../panel',
          'panel/../../x', 'javascript:alert(1)', 'a b', ['dizi'], '', str_repeat('a', 300)] as $kotu) {
    dogrula('red: ' . json_encode($kotu), Middleware::safeIntended($kotu) === '');
}

/* ---------------------------------------------------------------- */
echo "\nAra katman\n";

dogrula('bilinmeyen ad ("Auth") hata fırlatır, sessizce geçmez',
    firlatir(static fn () => Middleware::handle('Auth', new Request()), LogicException::class));
dogrula('yazım hatalı yetki ("cann:x") hata fırlatır',
    firlatir(static fn () => Middleware::handle('cann:users.delete', new Request()), LogicException::class));

/* ---------------------------------------------------------------- */
echo "\nGörünüm adı beyaz listesi\n";

foreach (['auth/login', 'errors/genel', 'Ornek::index', 'emails/iletisim-yanit'] as $iyi) {
    dogrula("kabul: $iyi", !firlatir(static fn () => View::resolve($iyi)));
}

foreach (['../.env', '.\\.', 'a/../b', 'auth/login.php', 'Ornek::../../x', 'Or nek::index'] as $kotu) {
    dogrula('red: ' . $kotu, firlatir(static fn () => View::resolve($kotu), RuntimeException::class));
}

dogrula('mutlak yol bile views/ klasöründe kalır',
    str_starts_with(View::resolve('/etc/passwd'), CY_BASE . '/views/'));

/* ---------------------------------------------------------------- */
echo "\nsendmail zarf göndereni (-f argüman enjeksiyonu)\n";

dogrula('düz adres kabul', NativeTransport::safeEnvelopeSender('bilgi@ornek.com') === 'bilgi@ornek.com');

foreach (['a@b.co -X/tmp/log', '-oQ/tmp@x.com', '"a b"@x.com', "a@b.com\n-X", 'a@b', ''] as $kotu) {
    dogrula('red: ' . json_encode($kotu), NativeTransport::safeEnvelopeSender($kotu) === '');
}

/* ---------------------------------------------------------------- */
echo "\nHata sayfaları\n";

$beklenen = [404 => 'errors/404', 403 => 'errors/403', 419 => 'errors/403', 405 => 'errors/genel',
             429 => 'errors/genel', 503 => 'errors/genel', 500 => 'errors/500'];

foreach ($beklenen as $kod => $gorunum) {
    $e = new HttpException($kod);
    dogrula("$kod → $gorunum", $e->view() === $gorunum, $e->view());
}

dogrula('429 Retry-After taşır', HttpException::tooManyRequests(42)->retryAfter() === 42);
dogrula('503 Retry-After taşır', HttpException::maintenance()->retryAfter() > 0);

/* ---------------------------------------------------------------- */
echo "\nİstek girdileri\n";

$_GET['r'] = ['x'];
Url::forgetCurrent();
dogrula('?r[]=x hata vermez, kök yol sayılır', Url::current() === '');

$_GET['r'] = 'panel/../../etc';
Url::forgetCurrent();
dogrula('".." içeren yol reddedilir', Url::current() === '');
unset($_GET['r']);
Url::forgetCurrent();

$_POST = ['sifre' => ['dizi'], 'ad' => 'Ali'];
$istek = new Request();
dogrula('Request::string dizi görünce varsayılanı döner', $istek->string('sifre', 'yok') === 'yok');
dogrula('Request::string metni kırpmadan döner', $istek->string('ad') === 'Ali');
$_POST = [];

/* ---------------------------------------------------------------- */
echo "\nRoller\n";

dogrula('üye bakım modunu atlayamaz', !Role::can(Role::MEMBER, 'maintenance.bypass'));
dogrula('editör bakım modunu atlayabilir', Role::can(Role::EDITOR, 'maintenance.bypass'));
dogrula('yönetici her şeyi yapabilir', Role::can(Role::ADMIN, 'maintenance.bypass'));

/* ---------------------------------------------------------------- */
echo "\nSatır sonu çapaları (\$ yerine \\z)\n";

dogrula('safeIntended sondaki satır sonunu reddeder', Middleware::safeIntended("panel\n") === '');
dogrula('Env::quote sondaki satır sonlu değeri tırnaklar ve korur', Env::parseValue(Env::quote("abc\n")) === "abc\n" && Env::quote("abc\n") !== "abc\n");
dogrula('View::resolve satır sonlu adı reddeder', firlatir(static fn () => View::resolve("home\n")));

/* ---------------------------------------------------------------- */
echo "\nIP adresleri (TRUSTED_PROXIES, IPv6 kovası)\n";

dogrula('IPv4 aralık içinde', Ip::inRange('10.1.2.3', '10.0.0.0/8'));
dogrula('IPv4 aralık dışında', !Ip::inRange('11.0.0.1', '10.0.0.0/8'));
dogrula('IPv6 aralık içinde', Ip::inRange('2001:db8::1', '2001:db8::/32'));
dogrula('IPv4 ile IPv6 karışmaz', !Ip::inRange('10.0.0.1', '::/0'));
dogrula('IPv6 /64 kovasına indirgenir', Ip::bucket('2001:db8:1:2:3:4:5:6') === '2001:db8:1:2::/64', Ip::bucket('2001:db8:1:2:3:4:5:6'));
dogrula('aynı /64 içindeki iki adres aynı kova', Ip::bucket('2001:db8:1:2::1') === Ip::bucket('2001:db8:1:2:ffff::9'));
dogrula('IPv4 kovası adresin kendisi', Ip::bucket('192.0.2.7') === '192.0.2.7');
dogrula('IPv4\'e eşlenmiş IPv6 düz IPv4 olur', Ip::normalize('::ffff:1.2.3.4') === '1.2.3.4');

$sunucu = $_SERVER;
Config::set('security.trusted_proxies', ['10.0.0.0/8']);
Config::set('security.proxy_header', 'X-Forwarded-For');

$_SERVER['REMOTE_ADDR']          = '10.0.0.5';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6, 1.2.3.4, 10.0.0.7';
Ip::forget();
dogrula('vekilden gelen istekte gerçek adres zincirin SAĞINDAN okunur', Ip::client() === '1.2.3.4', Ip::client());

$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
Ip::forget();
dogrula('vekil olmayan adresin X-Forwarded-For başlığına güvenilmez', Ip::client() === '203.0.113.9');

Config::set('security.trusted_proxies', []);
$_SERVER = $sunucu;
Ip::forget();

/* ---------------------------------------------------------------- */
echo "\nİmza ve form zaman damgası\n";

Signer::useKey('birim-testi-anahtari');
$damga = Signer::stamp('kayit');
dogrula('taze damga geçerli (yaşı 0)', Signer::stampAge('kayit', $damga) === 0);
dogrula('başka form için üretilmiş damga geçersiz', Signer::stampAge('iletisim', $damga) === null);
dogrula('değiştirilmiş zaman geçersiz', Signer::stampAge('kayit', (string) (time() - 3600) . substr($damga, strpos($damga, '.'))) === null);
dogrula('eksik damga geçersiz', Signer::stampAge('kayit', '') === null);
dogrula('imza doğrulaması', Signer::check('x', Signer::sign('x')) && !Signer::check('y', Signer::sign('x')));
Signer::useKey(null);

/* ---------------------------------------------------------------- */
echo "\nÇerez yolu (boşluklu / Türkçe klasör)\n";

$yollar = ['' => '/', '/proje' => '/proje/', '/my app' => '/my%20app/', '/ürün' => '/%C3%BCr%C3%BCn/',
           '/a(b)' => '/a(b)/', '/a,b' => '/', '/a;b' => '/'];

foreach ($yollar as $taban => $beklenen) {
    Url::useBase($taban);
    dogrula('taban "' . $taban . '" → çerez yolu ' . $beklenen, Url::cookiePath() === $beklenen, Url::cookiePath());
}

Url::useBase(null);

/* ---------------------------------------------------------------- */
echo "\nHız sınırı (kilitli dosya sayacı)\n";

Throttle::useDirectory(sys_get_temp_dir() . '/cy-hiz-' . bin2hex(random_bytes(4)));
$sonuclar = [];

for ($i = 0; $i < 4; $i++) {
    $sonuclar[] = Throttle::attempt('test', 3, 60);
}

dogrula('ilk üç deneme geçer, dördüncü bekletilir', $sonuclar[0] === 0 && $sonuclar[1] === 0 && $sonuclar[2] === 0 && $sonuclar[3] > 0, json_encode($sonuclar));
dogrula('kalan hak 0', Throttle::remaining('test', 3, 60) === 0);
dogrula('başka anahtar etkilenmez', Throttle::attempt('baska', 3, 60) === 0);
Throttle::useDirectory(null);

/* ---------------------------------------------------------------- */
echo "\nHTTP 405 ve komut satırı seçenekleri\n";

dogrula('405 yanıtı Allow başlığını taşır (HEAD dahil)', HttpException::methodNotAllowed('DELETE', ['GET', 'POST'])->allow() === 'GET, HEAD, POST');
dogrula('405 dışında Allow yok', HttpException::notFound()->allow() === '');

$girdi = new Input(['cy', 'api:token', 'admin', '--iptal', '--gun=90g', '--sayi=12']);
dogrula('değersiz --iptal sayı sayılmaz', $girdi->positiveIntOption('iptal') === false);
dogrula('--gun=90g geçersiz', $girdi->positiveIntOption('gun', 3650) === false);
dogrula('--sayi=12 geçerli', $girdi->positiveIntOption('sayi') === 12);
dogrula('verilmeyen seçenek null', $girdi->positiveIntOption('yok') === null);
dogrula('--gun üst sınırı', (new Input(['cy', 'x', '--gun=99999']))->positiveIntOption('gun', 3650) === false);

$make = (new ReflectionClass(MakeControllerCommand::class))->newInstanceWithoutConstructor();
$cakisma = new ReflectionMethod(MakeControllerCommand::class, 'importCollision');
dogrula('make: içe aktarılan adla çakışan sınıf yakalanır', $cakisma->invoke($make, "<?php\nuse App\\Http\\Controller;\nfinal class Controller extends Controller {}") !== null);
dogrula('make: çakışmayan sınıf geçer', $cakisma->invoke($make, "<?php\nuse App\\Http\\Controller;\nfinal class UrunController extends Controller {}") === null);

/* ---------------------------------------------------------------- */
echo "\nEXIF yönleri (8 değerin hepsi)\n";

if (function_exists('imagecreatetruecolor')) {
    $w = 3;
    $h = 2;
    $kaynak = static function () use ($w, $h): GdImage {
        $img = imagecreatetruecolor($w, $h);

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                imagesetpixel($img, $x, $y, ($x + 1) * 40 + ($y + 1) * 1000);
            }
        }

        return $img;
    };
    $renk = static fn (int $x, int $y): int => ($x + 1) * 40 + ($y + 1) * 1000;

    // Beklenen: yeni(x,y) = eski(f(x,y)); döndürmelerde boyutlar yer değiştirir.
    $donusum = [
        2 => [$w, $h, static fn ($x, $y) => [$w - 1 - $x, $y]],
        3 => [$w, $h, static fn ($x, $y) => [$w - 1 - $x, $h - 1 - $y]],
        4 => [$w, $h, static fn ($x, $y) => [$x, $h - 1 - $y]],
        5 => [$h, $w, static fn ($x, $y) => [$y, $x]],
        6 => [$h, $w, static fn ($x, $y) => [$y, $h - 1 - $x]],
        7 => [$h, $w, static fn ($x, $y) => [$w - 1 - $y, $h - 1 - $x]],
        8 => [$h, $w, static fn ($x, $y) => [$w - 1 - $y, $x]],
    ];

    $orient = new ReflectionMethod(App\Core\Uploader::class, 'orient');

    foreach ($donusum as $deger => [$yw, $yh, $f]) {
        $sonuc = $orient->invoke(null, $kaynak(), $deger);
        $dogru = imagesx($sonuc) === $yw && imagesy($sonuc) === $yh;

        for ($x = 0; $dogru && $x < $yw; $x++) {
            for ($y = 0; $dogru && $y < $yh; $y++) {
                [$ex, $ey] = $f($x, $y);
                $dogru = (imagecolorat($sonuc, $x, $y) & 0xFFFFFF) === $renk($ex, $ey);
            }
        }

        dogrula('Orientation=' . $deger . ' doğru uygulanır', $dogru);
    }
} else {
    echo "  (GD yok; atlandı)\n";
}

/* ---------------------------------------------------------------- */
echo "\nDemo modu (örnek hesaplar ve kilit)\n";

dogrula('Okuma hiçbir zaman kilitlenmez', Demo::blockReason('GET', 'panel/ayarlar/genel') === null);
foreach (['panel/hesabim/parola', 'panel/hesabim/guncelle', 'panel/hesabim/api-anahtari', 'panel/ayarlar/genel',
          'panel/ayarlar/logo', 'panel/sistem/migrate', 'api/kullanicilar/save', 'api/kullanicilar/delete',
          'api/kullanicilar/status', 'api/eposta/gonder', 'api/eposta/sinama'] as $yol) {
    dogrula('Kilitli: POST ' . $yol, Demo::blockReason('POST', $yol) !== null);
}
foreach (['giris', 'cikis', 'api/tema', 'panel/sayfalar/yeni', 'panel/ornek/kaydet', 'api/mesajlar/okundu',
          'api/eposta/list', 'api/eposta/onizle', 'api/kullanicilar/list', 'panel/hesabimx'] as $yol) {
    dogrula('Açık: POST ' . $yol, Demo::blockReason('POST', $yol) === null);
}
dogrula('PUT/DELETE de kilitlenir', Demo::blockReason('PUT', '/panel/ayarlar/genel/') !== null
    && Demo::blockReason('DELETE', 'api/kullanicilar/delete') !== null);
dogrula('Kilitten sonra bölüm sayfasına dönülür', Demo::backPath('panel/ayarlar/genel') === 'panel/ayarlar'
    && Demo::backPath('panel/hesabim/parola') === 'panel/hesabim' && Demo::backPath('api/eposta/gonder') === 'panel');

$tumu = [];
foreach (Demo::HESAPLAR as $kadi => $hesap) {
    $tumu[$kadi] = $hesap['eposta'];
}
$demoListesi = array_column(Demo::visibleAccounts(true, $tumu), 'kullanici_adi');
dogrula('Demo modunda Yönetici, Editör, Üye listelenir', $demoListesi === ['ali.yonetici', 'elif.editor', 'mehmet.uye'],
    implode(', ', $demoListesi));
dogrula('Demo dışı görünümde pasif/askıda/onay bekleyenler de sayılır', count(Demo::visibleAccounts(false, $tumu)) === 6);

/* Geliştirme ortamında (APP_DEBUG=true, APP_DEMO=false) giriş ekranı
 * eskiden altı hesabı "Demo1234!" ile listeliyordu; "geliştirme modu"
 * açık kurulup yayına alınan sitede de. Artık yalnızca demo modunda. */
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $demoDb = new PDO('sqlite::memory:');
    $demoDb->exec('CREATE TABLE kullanicilar (kullanici_adi TEXT, eposta TEXT)');
    $ekle = $demoDb->prepare('INSERT INTO kullanicilar VALUES (?, ?)');
    foreach ($tumu as $kadi => $eposta) {
        $ekle->execute([$kadi, $eposta]);
    }
    $onceki = ['app.demo' => Config::get('app.demo'), 'app.debug' => Config::get('app.debug'), 'app.env' => Config::get('app.env')];
    Config::set('app.demo', false);
    Config::set('app.debug', true);
    Config::set('app.env', 'local');
    dogrula('Geliştirmede (demo kapalı) giriş ekranı parola listelemez', Demo::loginAccounts($demoDb) === []);
    dogrula('Geliştirmede örnek veri için parolasız not çıkar', Demo::sampleDataNote($demoDb));
    $kalanDb = new PDO('sqlite::memory:');
    $kalanDb->exec('CREATE TABLE kullanicilar (kullanici_adi TEXT, eposta TEXT)');
    $kalanDb->exec("INSERT INTO kullanicilar VALUES ('mehmet.uye', 'mehmet@sirketim.com')");
    dogrula('Adresi değişmiş (gerçek) hesap örnek veri notunu açık tutmaz', !Demo::sampleDataNote($kalanDb));
    Config::set('app.debug', false);
    dogrula('Yayında (debug kapalı) not da çıkmaz', !Demo::sampleDataNote($demoDb));
    Config::set('app.demo', true);
    dogrula('Demo modunda üç hesap parolasıyla listelenir', count(Demo::loginAccounts($demoDb)) === 3 && !Demo::sampleDataNote($demoDb));
    foreach ($onceki as $anahtar => $deger) {
        Config::set($anahtar, $deger);
    }
}
dogrula('Veritabanında olmayan ya da e-postası farklı hesap listelenmez',
    array_column(Demo::visibleAccounts(true, ['ali.yonetici' => 'baska@ornek.com', 'elif.editor' => 'elif.demo@ornek.com']), 'kullanici_adi') === ['elif.editor']);
dogrula('Listelenen hesap parolasını taşır', (Demo::visibleAccounts(true, $tumu)[0]['parola'] ?? '') === Demo::PAROLA);

$ornekKullanici = new App\Models\User(id: 7, ad: 'Ali', soyad: 'Yılmaz', kullaniciAdi: 'ali.yonetici', eposta: 'ali.demo@ornek.com', rol: Role::ADMIN);
$gercekKullanici = new App\Models\User(id: 1, ad: 'Evren', soyad: 'Ç', kullaniciAdi: 'admin', eposta: 'x@y.z', rol: Role::ADMIN);
dogrula('Örnek hesap tanınır, kurulumdaki yönetici tanınmaz', Demo::isDemoUser($ornekKullanici) && !Demo::isDemoUser($gercekKullanici) && !Demo::isDemoUser(null));

/* DEMO VERİSİ TEK KAYNAKTAN (App\Core\DemoData): yazdığı her ayarın
 * şemada ya da 1.6 tanımlarında GERÇEKTEN bir satırı olmalı — yoksa
 * UPDATE sessizce hiçbir şey yapmaz ve vitrin eksik kurulur. */
dogrula('kurulum/demo.sql kaldırıldı (tek kaynak DemoData)', !is_file(CY_BASE . '/kurulum/demo.sql'));
$hesapRolleri = array_count_values(array_column(Demo::HESAPLAR, 'rol'));
dogrula('Demo hesaplarında her rol var', ($hesapRolleri['admin'] ?? 0) >= 1 && ($hesapRolleri['editor'] ?? 0) >= 1 && ($hesapRolleri['uye'] ?? 0) >= 1);
dogrula('Demo hesaplarında her durum var (aktif, pasif, askıda, onay bekliyor)',
    array_values(array_unique(array_column(Demo::HESAPLAR, 'durum'))) === ['aktif', 'pasif', 'askida', 'onay_bekliyor']);
dogrula('Bütün demo e-postaları temizlik sonekiyle biter', array_filter(Demo::HESAPLAR, static fn (array $h): bool => !str_ends_with($h['eposta'], App\Core\DemoData::EPOSTA_SONEKI)) === []);

$semaSql      = (string) @file_get_contents(CY_BASE . '/kurulum/database.sql');
$surum16Ayar  = array_column(App\Support\Surum16::AYARLAR, 0);
if ($semaSql !== '') {
    $eksikAyar = array_filter(array_keys(App\Core\DemoData::settings()), static fn (string $k): bool => !in_array($k, $surum16Ayar, true) && !str_contains($semaSql, "('" . $k . "'"));
    dogrula('Demo verisinin yazdığı her ayarın satırı var', $eksikAyar === [], implode(', ', $eksikAyar));
    $ciftTanim = array_filter($surum16Ayar, static fn (string $k): bool => str_contains($semaSql, "('" . $k . "'"));
    dogrula('1.6 ayarları database.sql\'de ikinci kez tanımlanmamış', $ciftTanim === [], implode(', ', $ciftTanim));
    dogrula('Şema nötr: marka yok', !str_contains($semaSql, 'Çılgın Yazılım örnek uygulaması'));
} else {
    echo "  (kurulum/ klasörü silinmiş; şema denetimi atlandı)\n";
}
dogrula('1.6 ayar anahtarları benzersiz', count($surum16Ayar) === count(array_unique($surum16Ayar)));
dogrula('Ana sayfa bölüm varsayılanları tanımlı bölümlerden', array_diff(json_decode(App\Support\Surum16::AYARLAR[10][1], true), array_keys(App\Support\Surum16::ANASAYFA_BOLUMLERI)) === []);
$ornekKunye = json_decode((string) @file_get_contents(CY_BASE . '/modules/Ornek/module.json'), true);
if (is_array($ornekKunye)) {
    dogrula('Ornek modülü kurulumda açık gelir (module.json)', ($ornekKunye['kurulumda_acik'] ?? null) === true);
} else {
    echo "  (modules/Ornek yok; atlandı)\n";
}

/* "Örnek veriyi kaldır" tek yöneticiyi silmez. SQL dosyasıyla kurulan
 * sitede gerçek yönetici yoktur; kaldırma ali.yonetici'yi de silse
 * panele kimse giremezdi. */
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $yonDb = new PDO('sqlite::memory:');
    $yonDb->exec('CREATE TABLE kullanicilar (kullanici_adi TEXT, eposta TEXT, rol TEXT, durum TEXT)');
    $yonEkle = $yonDb->prepare('INSERT INTO kullanicilar VALUES (?, ?, ?, ?)');
    foreach (Demo::HESAPLAR as $kadi => $h) {
        $yonEkle->execute([$kadi, $h['eposta'], $h['rol'], $h['durum']]);
    }
    dogrula('Yalnızca örnek yönetici varken örnek veri kaldırılamaz', !App\Core\DemoData::leavesAdmin($yonDb));
    $yonEkle->execute(['evren', 'evren@sirketim.com', 'admin', 'pasif']);
    dogrula('Pasif gerçek yönetici yetmez', !App\Core\DemoData::leavesAdmin($yonDb));
    $yonDb->exec("UPDATE kullanicilar SET eposta = 'ali@sirketim.com' WHERE kullanici_adi = 'ali.yonetici'");
    dogrula('Adresi değişmiş örnek yönetici gerçek sayılır (kaldırma onu silmez)', App\Core\DemoData::leavesAdmin($yonDb));
}

/* SQL İLE KURULUM: database/ornek-veritabani.sql, tests/ornek-sql.php ile
 * sihirbaz kurulumundan üretilir. Sürüm ya da migration eklenip dosya
 * yeniden üretilmezse SQL ile kuran kişi eski şemayı alır. */
$ornekSql = (string) @file_get_contents(CY_BASE . '/database/ornek-veritabani.sql');
dogrula('database/ornek-veritabani.sql var', $ornekSql !== '');
if ($ornekSql !== '') {
    dogrula('Örnek SQL bu sürümden üretilmiş', str_contains($ornekSql, 'CY PHP Starter ' . Config::get('app.version') . ' —'),
        'php tests/ornek-sql.php ile yeniden üretin');
    preg_match('/INSERT INTO `migrasyonlar` .*? VALUES\n(.*?);\n/s', $ornekSql, $sqlMig);
    preg_match_all("/'([^']+)'/", $sqlMig[1] ?? '', $sqlMigAd);
    $sqlMigEksik = array_diff(array_map(static fn (string $f): string => basename($f, '.php'), glob(CY_BASE . '/database/migrations/*.php') ?: []), $sqlMigAd[1]);
    dogrula('Örnek SQL bütün migration\'ları uygulanmış sayar', $sqlMigEksik === [], implode(', ', $sqlMigEksik));
    preg_match('/INSERT INTO `kullanicilar` .*? VALUES\n(.*?);\n/s', $ornekSql, $sqlHesap);
    preg_match_all("/'([a-z0-9._-]+@[a-z0-9.-]+)'/i", $sqlHesap[1] ?? '', $sqlEposta);
    dogrula('Örnek SQL\'de yalnızca örnek hesaplar var (kurulumdaki yönetici sızmaz)',
        count($sqlEposta[1]) === count(Demo::HESAPLAR)
        && array_filter($sqlEposta[1], static fn (string $e): bool => !str_ends_with($e, App\Core\DemoData::EPOSTA_SONEKI)) === []);
    dogrula('Örnek SQL dolu veritabanını ezmez (DROP TABLE yok), sayaç taşımaz',
        stripos($ornekSql, 'DROP TABLE') === false && !str_contains($ornekSql, 'AUTO_INCREMENT='));
}

/* ---------------------------------------------------------------- */
echo "\nModül yetkileri (module.json → yetkiler)\n";

dogrula('Geçerli yetki listesi okunur', Module::parseAbilities(['editor' => ['stok.view', 'stok.update.own']]) === ['editor' => ['stok.view', 'stok.update.own']]);
dogrula('Bozuk girdiler atlanır', Module::parseAbilities(['editor' => ['stok.view', 'GECERSIZ', 'nokta_yok', 7, 'a.b'], 'uye' => 'stok.view', 3 => ['x.y']]) === ['editor' => ['stok.view', 'a.b']]);
dogrula('Boş liste ve dizi olmayan girdi yok sayılır', Module::parseAbilities(['editor' => []]) === [] && Module::parseAbilities('metin') === []);

$modulA = new Module('A', '/yok', 'A', '', '1.0.0', true, ['editor' => ['a.view', 'ortak.view']]);
$modulB = new Module('B', '/yok', 'B', '', '1.0.0', true, ['editor' => ['ortak.view'], 'uye' => ['b.view']]);
dogrula('Açık modüllerin yetkileri birleşir (tekrarsız)', Modules::collectAbilities([$modulA, $modulB], 'editor') === ['a.view', 'ortak.view']);
dogrula('Rol başka modülün yetkisini almaz', Modules::collectAbilities([$modulA, $modulB], 'uye') === ['b.view']);

$ornekModul = Module::fromDirectory(CY_BASE . '/modules/Ornek', true);
if (is_dir($ornekModul->yol)) {
    dogrula('Ornek: editör görür, ekler, kendi kaydını düzenler, yayınlar', Modules::collectAbilities([$ornekModul], 'editor') === ['ornek.view', 'ornek.create', 'ornek.update.own', 'ornek.publish']);
    dogrula('Ornek: üye görür, ekler, kendi kaydını düzenler ama yayınlayamaz', Modules::collectAbilities([$ornekModul], 'uye') === ['ornek.view', 'ornek.create', 'ornek.update.own']);
    dogrula('Ornek: menü tanımı module.json\'dan okunur', ($ornekModul->menu['route'] ?? '') === 'panel/ornek' && ($ornekModul->menu['can'] ?? '') === 'ornek.view');
    dogrula('Ornek: herkesi yönetme yetkisi hiçbir role dağıtılmaz (yalnız yönetici)', !in_array('ornek.manage', array_merge(...array_values($ornekModul->yetkiler)), true));
    dogrula('Ornek modülünün örnek verisi var', is_file(CY_BASE . '/modules/Ornek/seeders/OrnekIcerik.php'));
}
dogrula('Yönetici her yetkiye sahip, bilinmeyen rol hiçbirine', Role::can(Role::ADMIN, 'herhangi.bir') && !Role::can('hayalet', 'ornek.view'));
$geciciModul = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cy-modul-' . bin2hex(random_bytes(4)) . DIRECTORY_SEPARATOR . 'StokTakip';
mkdir($geciciModul, 0777, true);
file_put_contents($geciciModul . '/module.json', '{"baslik": "Stok Takibi"}');
dogrula('Menü tanımsızsa addan türetilir (StokTakip → panel/stok-takip)', Module::fromDirectory($geciciModul, true)->menu === ['route' => 'panel/stok-takip', 'icon' => 'server', 'label' => 'Stok Takibi', 'can' => 'stok_takip.view']);
file_put_contents($geciciModul . '/module.json', '{"menu": false}');
dogrula('"menu": false yazan modülün menüsü yok', Module::fromDirectory($geciciModul, true)->menu === null);
unlink($geciciModul . '/module.json');
rmdir($geciciModul);
rmdir(dirname($geciciModul));

/* ---------------------------------------------------------------- */
echo "\nÖrnek modül: kayıt düzeyi kurallar (OrnekPolicy)\n";

/* Gerçek veritabanına dokunmadan "Ornek açık" durumu: ayarlar bellek
 * içi SQLite'tan okunur, test bitince temizlenir. */
if (is_file(CY_BASE . '/modules/Ornek/src/OrnekPolicy.php') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $bellek = new PDO('sqlite::memory:');
    $bellek->exec('CREATE TABLE ayarlar (anahtar TEXT, deger TEXT)');
    $bellek->exec("INSERT INTO ayarlar VALUES ('aktif_moduller', '[\"Ornek\"]')");
    App\Core\Setting::load($bellek);
    Modules::forget();
    Modules::boot();

    $yonetici = new App\Models\User(id: 1, ad: 'Y', soyad: 'Y', kullaniciAdi: 'y', eposta: 'y@ornek.com', rol: Role::ADMIN);
    $editor   = new App\Models\User(id: 2, ad: 'E', soyad: 'E', kullaniciAdi: 'e', eposta: 'e@ornek.com', rol: 'editor');
    $uye      = new App\Models\User(id: 3, ad: 'U', soyad: 'U', kullaniciAdi: 'u', eposta: 'u@ornek.com', rol: 'uye');

    $pY = new \Modules\Ornek\OrnekPolicy($yonetici);
    $pE = new \Modules\Ornek\OrnekPolicy($editor);
    $pU = new \Modules\Ornek\OrnekPolicy($uye);

    $uyeTaslak    = ['kullanici_id' => 3, 'durum' => 'taslak'];
    $uyeOnay      = ['kullanici_id' => 3, 'durum' => 'onay'];
    $uyeYayinda   = ['kullanici_id' => 3, 'durum' => 'yayinda'];
    $editorTaslak = ['kullanici_id' => 2, 'durum' => 'taslak'];
    $baskaYayinda = ['kullanici_id' => 1, 'durum' => 'yayinda'];

    dogrula('Yönetici her kaydı görür ve düzenler', $pY->canView($uyeTaslak) && $pY->canEdit($uyeTaslak) && $pY->canEdit($editorTaslak));
    dogrula('Editör onay bekleyen üye kaydını görür ve onaylayabilir', $pE->canView($uyeOnay) && $pE->transitions($uyeOnay) === ['yayinda', 'taslak']);
    dogrula('Editör başkasının taslağını görmez', !$pE->canView($uyeTaslak));
    dogrula('Editör başkasının kaydını düzenleyemez, silemez', !$pE->canEdit($uyeOnay) && !$pE->canEdit($baskaYayinda));
    dogrula('Editör kendi kaydını düzenler', $pE->canEdit($editorTaslak));
    dogrula('Üye kendi kaydını düzenler, başkasınınkini düzenleyemez', $pU->canEdit($uyeTaslak) && !$pU->canEdit($baskaYayinda));
    dogrula('Üye yalnızca onaya gönderebilir, yayına alamaz', $pU->transitions($uyeTaslak) === ['onay'] && !$pU->canMoveTo($uyeTaslak, 'yayinda'));
    dogrula('Üye onaydaki kaydını geri çeker, yayındakini kaldırır', $pU->transitions($uyeOnay) === ['taslak'] && $pU->transitions($uyeYayinda) === ['taslak']);
    dogrula('Üye başkasının kaydının durumunu değiştiremez', $pU->transitions($baskaYayinda) === []);
    dogrula('Üye formunda "Yayında" seçeneği yok', !in_array('yayinda', $pU->formStatuses(), true) && in_array('yayinda', $pE->formStatuses(), true));
    dogrula('Üyenin kaydı onaya düşer (elle "yayinda" gönderse bile)', $pU->statusAfterSave('yayinda') === 'onay' && $pE->statusAfterSave('yayinda') === 'yayinda');
    dogrula('Kapsamlar: yönetici hepsi, editör onay dahil, üye kendi', $pY->scope() === 'hepsi' && $pE->scope() === 'editor' && $pU->scope() === 'kendi');
    dogrula('Giriş yapmamış kullanıcı hiçbir şey yapamaz', (new \Modules\Ornek\OrnekPolicy(null))->transitions($baskaYayinda) === [] && !(new \Modules\Ornek\OrnekPolicy(null))->canEdit($baskaYayinda));

    $editorYayinda = ['kullanici_id' => 2, 'durum' => 'yayinda'];
    dogrula('Editör başkasının yayındaki kaydını onaya geri gönderir', $pE->transitions($uyeYayinda) === ['onay'] && $pE->transitionLabel($uyeYayinda, 'onay') === 'Onaya geri gönder');
    dogrula('Editör kendi yayındaki kaydını taslağa alır', $pE->transitions($editorYayinda) === ['taslak'] && $pE->transitionLabel($editorYayinda, 'taslak') === 'Taslağa al');
    dogrula('Sahibi yayındakini "Taslağa al", onaydakini "Geri çek" görür', $pU->transitionLabel($uyeYayinda, 'taslak') === 'Taslağa al' && $pU->transitionLabel($uyeOnay, 'taslak') === 'Geri çek');
    dogrula('Editör onaydaki kaydı "Onayla" ya da "Sahibine geri gönder"', $pE->transitionLabel($uyeOnay, 'yayinda') === 'Onayla' && $pE->transitionLabel($uyeOnay, 'taslak') === 'Sahibine geri gönder');

    App\Core\Setting::flush();
    Modules::forget();
} else {
    echo "  (modules/Ornek ya da pdo_sqlite yok; atlandı)\n";
}

/* ---------------------------------------------------------------- */
echo "\nHTML → düz metin (özet ve meta açıklama)\n";

dogrula('Blok sınırları boşluğa döner', App\Core\Html::toText('<h2>Biz kimiz?</h2><p>Bu metni<br>değiştirin.</p><ul><li>Bir</li><li>İki</li></ul>') === 'Biz kimiz? Bu metni değiştirin. Bir İki');
dogrula('Satır içi etiketler kelimeyi bölmez', App\Core\Html::toText('<p>ka<strong>lın</strong> &amp; <em>eğik</em></p>') === 'kalın & eğik');
dogrula('Boş HTML boş metin', App\Core\Html::toText('<p> </p><br>') === '');

/* ---------------------------------------------------------------- */
echo "\nMenü, e-posta gizliliği, sayfa adresleri (1.6.0)\n";

$_GET['r'] = 'panel/kullanicilar';
Url::forgetCurrent();
dogrula('Kontrol Paneli (tam eşleşme) alt sayfada aktif DEĞİL', !Url::isCurrent('panel', true));
dogrula('Önek eşleşmesi alt sayfada yine çalışır', Url::isCurrent('panel'));
dogrula('Tam eşleşme kendi sayfasında aktif', Url::isCurrent('panel/kullanicilar', true));
$_GET['r'] = 'panel';
Url::forgetCurrent();
dogrula('Kontrol Paneli kendi sayfasında aktif', Url::isCurrent('panel', true));
unset($_GET['r']);
Url::forgetCurrent();

dogrula('E-posta maskelenir', App\Models\MailLog::maskEmail('elif.demir@ornek.com') === 'e***@ornek.com');
dogrula('Bozuk adres de maskelenir', App\Models\MailLog::maskEmail('adsiz') === 'a***' && App\Models\MailLog::maskEmail('') === '');
$guvenlikMektubu = static fn (string $sablon): App\Models\MailLog => App\Models\MailLog::fromRow(['id' => 1, 'alici_eposta' => 'a@b.c', 'konu' => 'x', 'sablon' => $sablon]);
dogrula('Doğrulama ve parola sıfırlama mektupları hassas', $guvenlikMektubu('dogrulama')->isSensitive() && $guvenlikMektubu('parola-sifirlama')->isSensitive());
dogrula('Duyuru mektubu hassas değil', !$guvenlikMektubu('duyuru')->isSensitive());

foreach (['app', 'config', 'database', 'docs', 'routes', 'tests', 'views', 'Panel'] as $ayrilmis) {
    dogrula('"' . $ayrilmis . '" sayfa adresi olamaz', App\Repositories\PageRepository::reserved($ayrilmis));
}
dogrula('"hakkimizda" sayfa adresi olabilir', !App\Repositories\PageRepository::reserved('hakkimizda'));

/* Demo kilidi yetki denetiminden SONRA çalışır: Router her rotada
 * ara katmanlardan sonra Middleware::afterAll'u çağırır, auth() artık
 * Demo::guard'ı çağırmaz. (Davranışın HTTP sınaması tests/smoke.php'de.) */
$routerKaynak     = (string) file_get_contents(CY_BASE . '/app/Core/Router.php');
$middlewareKaynak = (string) file_get_contents(CY_BASE . '/app/Core/Middleware.php');
dogrula('Demo kilidi ara katmanlardan sonra çalışır', str_contains($routerKaynak, 'Middleware::afterAll($middleware, $request)'));
preg_match('/private static function auth\(Request \$request\): void\s*\{(.*?)\n    \}/s', $middlewareKaynak, $authGovde);
dogrula('auth() demo kilidini kendisi çağırmaz', isset($authGovde[1]) && !str_contains($authGovde[1], 'Demo::guard('));

/* ---------------------------------------------------------------- */
echo "\nVeritabanı: indeksler ve sorgu biçimi\n";

$sema = (string) @file_get_contents(CY_BASE . '/kurulum/database.sql');
if ($sema !== '') {
    foreach (['idx_mail_gonderim', 'idx_mail_alici', 'idx_attempts_tarih', 'idx_kullanicilar_kayit'] as $indeks) {
        dogrula('Taze kurulum ' . $indeks . ' indeksiyle gelir', str_contains($sema, '`' . $indeks . '`'));
    }
    dogrula('database.sql indeks migration\'ını kurulmuş sayar', str_contains($sema, "'2026_10_07_010000_sorgu_indeksleri'"));
} else {
    echo "  (kurulum/ klasörü silinmiş; database.sql denetimi atlandı)\n";
}
dogrula('Var olan kurulumlar için indeks migration\'ı var', is_file(CY_BASE . '/database/migrations/2026_10_07_010000_sorgu_indeksleri.php'));

/* "DATE(created_at) = CURDATE()" gibi, sütunu fonksiyona saran bir
 * karşılaştırma indeksi devre dışı bırakır ve tabloyu baştan sona
 * tarar. Bugün gönderilen e-posta sayacı böyle yazıldığı için 300 bin
 * kayıtta sorgu başına ~1 sn sürüyordu. Aralık yazılmalı:
 * "created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY". */
$fonksiyonluKarsilastirma = [];
foreach ([CY_BASE . '/app', CY_BASE . '/modules'] as $kok) {
    if (!is_dir($kok)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kok, FilesystemIterator::SKIP_DOTS)) as $dosya) {
        if ($dosya->getExtension() === 'php'
            && preg_match('/\b(DATE|YEAR|MONTH|DAY|LOWER|UPPER)\s*\(\s*`?\w+`?(\.`?\w+`?)?\s*\)\s*(=|<|>|BETWEEN\b|IN\s*\()/i', (string) file_get_contents($dosya->getPathname())) === 1) {
            $fonksiyonluKarsilastirma[] = substr($dosya->getPathname(), strlen(CY_BASE) + 1);
        }
    }
}
dogrula('Hiçbir sorgu sütunu fonksiyona sarıp karşılaştırmıyor (indeks kullanılabilir)', $fonksiyonluKarsilastirma === []);
if ($fonksiyonluKarsilastirma !== []) {
    echo '    → ' . implode(', ', $fonksiyonluKarsilastirma) . "\n";
}

/* ---------------------------------------------------------------- */
echo "\nHTML süzgeci (XSS) ve içerik görselleri\n";

Config::set('app.url', 'https://site.ornek.com/proje');
Config::set('security.csp_extra.img-src', ['https://cdn.ornek.net', '*.resim.org']);

$xss = [
    'script etiketi'                 => '<p>a</p><script>alert(1)</script>',
    'img onerror'                    => '<img src="/upload/a.png" onerror="alert(1)">',
    'javascript: bağlantı'           => '<a href="javascript:alert(1)">x</a>',
    'büyük/küçük harf ve boşluk'     => '<a href=" JaVaScRiPt:alert(1)">x</a>',
    'sekme gizlenmiş şema'           => "<a href=\"java\tscript:alert(1)\">x</a>",
    'HTML varlığıyla gizlenmiş şema' => '<a href="&#106;avascript:alert(1)">x</a>',
    'data: görsel'                   => '<img src="data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+">',
    'svg onload'                     => '<svg onload="alert(1)"><circle/></svg>',
    'iframe'                         => '<iframe src="https://kotu.com"></iframe>',
    'style özniteliği'               => '<p style="background:url(javascript:alert(1))">x</p>',
    'form/input'                     => '<form action="https://kotu.com"><input name="p"></form>',
    'meta yenileme'                  => '<meta http-equiv="refresh" content="0;url=https://kotu.com">',
    'koşullu yorum'                  => '<!--[if IE]><script>alert(1)</script><![endif]-->',
    'vbscript şeması'                => '<a href="vbscript:msgbox(1)">x</a>',
    'olay öznitelikli div'           => '<div onclick="alert(1)" onmouseover="alert(1)">x</div>',
    'style etiketi'                  => '<style>body{background:url(javascript:alert(1))}</style><p>x</p>',
    'svg içinde xlink'               => '<svg><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>',
    'object/embed'                   => '<object data="x.swf"></object><embed src="x.swf">',
];
foreach ($xss as $ad => $girdi) {
    $cikti = App\Core\Html::sanitize($girdi);
    dogrula('XSS süzülür: ' . $ad, preg_match('/<script|on[a-z]+\s*=|javascript:|vbscript:|data:|<iframe|<svg|<form|<input|<meta|style=/i', $cikti) !== 1, $cikti);
}
dogrula('Güvenli biçimlendirme korunur', App\Core\Html::sanitize('<h2>Başlık</h2><p><strong>kalın</strong> <a href="/iletisim">bağlantı</a></p>') === '<h2>Başlık</h2><p><strong>kalın</strong> <a href="/iletisim">bağlantı</a></p>');
dogrula('Yeni sekme bağlantısına rel="noopener" eklenir', str_contains(App\Core\Html::sanitize('<a href="https://ornek.com" target="_blank">x</a>'), 'rel="noopener noreferrer"'));

dogrula('Göreli görsel adresi yereldir', App\Core\Html::localImage('/proje/upload/img/sayfa/a.webp') && App\Core\Html::localImage('upload/a.png'));
dogrula('Kendi alan adındaki tam adres yereldir', App\Core\Html::localImage('https://site.ornek.com/proje/upload/a.png'));
dogrula('Başka alan adı yerel değildir', !App\Core\Html::localImage('https://kotu.com/a.png') && !App\Core\Html::localImage('//kotu.com/a.png'));
dogrula('Alt alan adı taklidi yerel değildir', !App\Core\Html::localImage('https://site.ornek.com.kotu.com/a.png'));
dogrula('CSP_IMG_SRC listesindeki adres (ve *. deseni) kabul edilir', App\Core\Html::localImage('https://cdn.ornek.net/a.png') && App\Core\Html::localImage('https://img.resim.org/a.png') && !App\Core\Html::localImage('https://resim.org.kotu.com/a.png'));
dogrula('Dış görsel içerikten tamamen çıkarılır', App\Core\Html::sanitize('<p>x<img src="https://kotu.com/izle.png" alt="i"></p>') === '<p>x</p>');
dogrula('Yerel görsel içerikte kalır', str_contains(App\Core\Html::sanitize('<p><img src="/proje/upload/img/sayfa/a.webp" alt="a"></p>'), '<img src="/proje/upload/img/sayfa/a.webp" alt="a">'));

Config::set('app.url', '');
Config::set('security.csp_extra.img-src', []);

/* ---------------------------------------------------------------- */
echo "\nSayfa önizleme bağlantısı (imzalı, 30 dk)\n";

$simdi  = 1_800_000_000;
$adres  = App\Http\Controllers\PageController::previewUrl(7, $simdi);
parse_str((string) parse_url($adres, PHP_URL_QUERY), $sorgu);
$Onizle = App\Http\Controllers\PageController::class;
dogrula('Üretilen bağlantı geçerlidir', $Onizle::previewValid(7, (int) $sorgu['son'], (string) $sorgu['imza'], $simdi));
dogrula('Başka sayfa kimliğiyle kullanılamaz', !$Onizle::previewValid(8, (int) $sorgu['son'], (string) $sorgu['imza'], $simdi));
dogrula('Süresi dolunca geçersizdir', !$Onizle::previewValid(7, (int) $sorgu['son'], (string) $sorgu['imza'], $simdi + 1801));
dogrula('Uzatılmış bitiş zamanı imzayı bozar', !$Onizle::previewValid(7, (int) $sorgu['son'] + 3600, (string) $sorgu['imza'], $simdi));
dogrula('30 dakikadan uzun ömürlü bağlantı kabul edilmez', !$Onizle::previewValid(7, $simdi + 7200, Signer::sign('sayfa-onizleme|7|' . ($simdi + 7200)), $simdi));

/* ---------------------------------------------------------------- */
echo "\nParola sıfırlama, KVKK, API kapsamı, hesap silme (1.6.0)\n";

dogrula('Sıfırlama jetonu biçimi denetlenir (veritabanına gitmeden)',
    App\Core\PasswordReset::find('') === null
    && App\Core\PasswordReset::find('abc') === null
    && App\Core\PasswordReset::find(str_repeat('g', 64)) === null
    && App\Core\PasswordReset::find(str_repeat('a', 64) . "\n") === null);
dogrula('Sıfırlama bağlantısı 60 dakika geçerli', App\Core\PasswordReset::DAKIKA === 60);

$sifirlamaKaynak = (string) file_get_contents(CY_BASE . '/app/Core/PasswordReset.php');
dogrula('Jetonun yalnızca SHA-256 özeti saklanır', str_contains($sifirlamaKaynak, "hash('sha256', \$jeton)") && !str_contains($sifirlamaKaynak, "':ozet'      => \$jeton"));
dogrula('Jeton tek kullanımlık (koşullu UPDATE + rowCount)', str_contains($sifirlamaKaynak, 'kullanildi_at IS NULL AND son_gecerlilik > NOW()') && str_contains($sifirlamaKaynak, 'rowCount() !== 1'));
dogrula('Parola sıfırlama gövdesi panelde gizli (güvenlik şablonu)', in_array('parola-sifirlama', App\Models\MailLog::GUVENLIK_SABLONLARI, true));
dogrula('PasswordChanged olayı kaynağı taşır', (new App\Events\PasswordChanged(1, kaynak: App\Events\PasswordChanged::SIFIRLAMA))->toArray()['kaynak'] === 'sifirlama');

$olaylar = (string) file_get_contents(CY_BASE . '/routes/events.php');
dogrula('"Parolanız değişti" dinleyicisi bağlı', str_contains($olaylar, 'Events::listen(PasswordChanged::class, ParolaDegistiBildir::class)'));

$web = (string) file_get_contents(CY_BASE . '/routes/web.php');
dogrula('Parola sıfırlama rotaları misafire açık, POST\'lar CSRF korumalı',
    str_contains($web, "post('parolami-unuttum', PasswordResetController::class, 'sendLink',    ['installed', 'guest', 'csrf'])")
    && str_contains($web, "post('parola-sifirla',   PasswordResetController::class, 'reset',       ['installed', 'guest', 'csrf'])"));
dogrula('Mobil API rotaları: giriş anahtarsız, diğerleri Bearer ister',
    str_contains($web, "post('oturum',            MobileController::class, 'login',         ['api.guest'])")
    && preg_match_all("/MobileController::class, '(logout|sessions|revokeSession|files|file)',\s+\['api'\]/", $web) === 5);

dogrula('Saklama süresi 0 ise anonimleştirme çalışmaz', App\Core\Privacy::anonymizeMessages(null, 0) === 0);
dogrula('Hesap silme bekleme süresi 7 gün', App\Core\AccountDeletion::GUN === 7);
dogrula('API kapsamları: okuma / yazma', App\Core\Api\ApiToken::OKUMA === 'okuma' && App\Core\Api\ApiToken::YAZMA === 'yazma');

$koruma = (string) file_get_contents(CY_BASE . '/app/Core/Api/ApiGuard.php');
dogrula('Okuma anahtarı yalnızca GET/HEAD/OPTIONS yapar (çıkış hariç)', str_contains($koruma, "\$kayit['kapsam'] === ApiToken::OKUMA") && str_contains($koruma, "'kapsam_yetersiz'"));

$auth = (string) file_get_contents(CY_BASE . '/app/Core/Auth.php');
dogrula('Mobil giriş tarayıcı girişiyle aynı kapıdan geçer', str_contains($auth, 'public static function verifyCredentials(')
    && str_contains((string) file_get_contents(CY_BASE . '/app/Http/Controllers/Api/MobileController.php'), 'Auth::verifyCredentials('));
dogrula('Giriş, bekleyen hesap silmeyi iptal eder', str_contains($auth, 'AccountDeletion::cancel($user->id)'));

$_SERVER['REQUEST_URI'] = '/api/v1/dosyalar/rapor.pdf';
unset($_GET['r']);
Url::forgetCurrent();
dogrula('Rota parametresi nokta içerebilir, ".." içeremez',
    Url::current() === 'api/v1/dosyalar/rapor.pdf'
    && (function (): bool { $_SERVER['REQUEST_URI'] = '/api/v1/dosyalar/../.env'; Url::forgetCurrent(); return Url::current() === ''; })());
Url::forgetCurrent();

/* ---------------------------------------------------------------- */
echo "\nRol matrisi (her rota → kim erişebilir, tests/rol-matrisi.php)\n";

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    require_once CY_BASE . '/tests/rol-matrisi-hesap.php';

    $bellek = new PDO('sqlite::memory:');
    $bellek->exec('CREATE TABLE ayarlar (anahtar TEXT, deger TEXT)');
    $bellek->exec("INSERT INTO ayarlar VALUES ('aktif_moduller', '[\"Ornek\"]')");
    App\Core\Setting::load($bellek);
    Modules::forget();
    Modules::boot();

    $rotaTablosu = (static function (): App\Core\Router {
        $router = require CY_BASE . '/routes/web.php';
        Modules::loadRoutes($router);

        return $router;
    })()->table();

    $beklenen = require CY_BASE . '/tests/rol-matrisi.php';
    $gercek   = [];

    foreach ($rotaTablosu as $rota) {
        $gercek[$rota['verb'] . ' ' . $rota['path']] = cy_rota_erisimi($rota['middleware']);
    }

    $eksik  = array_keys(array_diff_key($gercek, $beklenen));
    $fazla  = array_keys(array_diff_key($beklenen, $gercek));
    $farkli = [];

    foreach (array_intersect_key($gercek, $beklenen) as $anahtar => $erisim) {
        if ($erisim !== $beklenen[$anahtar]) {
            $farkli[] = $anahtar . ' (beklenen ' . $beklenen[$anahtar] . ', gerçek ' . $erisim . ')';
        }
    }

    dogrula('Her rota matriste yazılı (' . count($gercek) . ' rota)', $eksik === [], 'Matrise ekleyin: ' . implode(', ', $eksik));
    dogrula('Matriste silinmiş rota kalmadı', $fazla === [], 'Matristen çıkarın: ' . implode(', ', $fazla));
    dogrula('Her rotanın erişimi matrisle aynı', $farkli === [], implode('; ', $farkli));

    $panelGirissiz = $postCsrfsiz = $apiGirissiz = [];

    foreach ($rotaTablosu as $rota) {
        $mw = $rota['middleware'];

        if (str_starts_with($rota['path'], 'panel') && !in_array('auth', $mw, true)) {
            $panelGirissiz[] = $rota['path'];
        }

        // Oturum çereziyle çalışan her veri değiştiren uç CSRF ister (API anahtarıyla gelenler hariç).
        if ($rota['verb'] === 'POST' && !str_starts_with($rota['path'], 'api/v1') && !in_array('csrf', $mw, true)) {
            $postCsrfsiz[] = $rota['path'];
        }

        if (str_starts_with($rota['path'], 'api/') && !str_starts_with($rota['path'], 'api/v1')
            && !in_array('auth', $mw, true) && $rota['path'] !== 'api/iletisim/gonder') {
            $apiGirissiz[] = $rota['path'];
        }
    }

    dogrula('Bütün panel rotaları giriş ister', $panelGirissiz === [], implode(', ', $panelGirissiz));
    dogrula('Oturumla çalışan bütün POST rotaları CSRF korumalı', $postCsrfsiz === [], implode(', ', $postCsrfsiz));
    dogrula('Panelin iç API uçları giriş ister (iletişim formu hariç)', $apiGirissiz === [], implode(', ', $apiGirissiz));
    dogrula('Üye yönetim ekranlarına erişemez', !str_contains($gercek['GET panel/kullanicilar'] ?? 'U', 'U') && !str_contains($gercek['GET panel/ayarlar'] ?? 'U', 'U') && !str_contains($gercek['GET panel/sistem'] ?? 'U', 'U'));
    dogrula('Editör kullanıcıları, ayarları ve sistemi yönetemez', !str_contains($gercek['POST api/kullanicilar/save'] ?? 'E', 'E') && !str_contains($gercek['POST panel/ayarlar/{grup}'] ?? 'E', 'E') && !str_contains($gercek['POST panel/sistem/migrate'] ?? 'E', 'E'));

    App\Core\Setting::flush();
    Modules::forget();
} else {
    echo "  (pdo_sqlite yok; rol matrisi atlandı)\n";
}

/* ---------------------------------------------------------------- */
echo "\nForm yardım metinleri ve ayar açıklamaları (1.6.1)\n";

/* Formlar kuralı elle yazınca bayatlıyordu: en kısa parola 10 yapılsa
 * ekran hâlâ "en az 8" derdi. Metin config'ten üretilmeli. */
$parolaMin = (int) Config::get('security.password_min', 8);
dogrula('password_hint() config\'teki en kısa uzunluğu söylüyor', str_contains(password_hint(), 'En az ' . $parolaMin . ' karakter'));
Config::set('security.password_min', 12);
dogrula('password_hint() config değişince değişiyor', str_contains(password_hint(), 'En az 12 karakter'));
Config::set('security.password_min', $parolaMin);
dogrula('upload_max_mb() en az 1', upload_max_mb() >= 1);

$bosRol = array_filter([Role::ADMIN, Role::EDITOR, Role::MEMBER], static fn (string $r): bool => trim(Role::description($r)) === '');
dogrula('Her çekirdek rolün açıklaması dolu', $bosRol === [], implode(', ', $bosRol));

$aciklamaMigration = require CY_BASE . '/database/migrations/2026_10_08_010000_ayar_aciklamalari.php';
$migrationMetinleri = $aciklamaMigration->aciklamalar();

$surum16Farki = [];
$surum16Bos   = [];
foreach (App\Support\Surum16::AYARLAR as [$anahtar, , $grup, , , $aciklama]) {
    if ($grup === 'dahili') {
        continue;
    }
    if (trim((string) $aciklama) === '') {
        $surum16Bos[] = $anahtar;
    }
    if (($migrationMetinleri[$anahtar] ?? null) !== $aciklama) {
        $surum16Farki[] = $anahtar;
    }
}
dogrula('Surum16\'da ("dahili" dışında) her ayarın açıklaması dolu', $surum16Bos === [], implode(', ', $surum16Bos));
dogrula('Migration açıklamaları Surum16 ile birebir aynı', $surum16Farki === [], implode(', ', $surum16Farki));

if ($sema !== '') {
    /* database.sql'deki satırın 6. değeri açıklamadır:
     * ('anahtar', 'deger', 'grup', 'tip', 'Etiket', 'Açıklama', … */
    $sqlFarki = [];
    foreach ($aciklamaMigration::ACIKLAMALAR as $anahtar => $metin) {
        $sqlMetni = null;
        if (preg_match('/^\(\'' . preg_quote($anahtar, '/') . '\',.*$/mu', $sema, $satir)
            && preg_match_all("/'((?:[^']|'')*)'|NULL|-?\\d+/u", $satir[0], $degerler)
            && isset($degerler[0][5])) {
            $sqlMetni = str_replace("''", "'", $degerler[1][5]);
        }
        if ($sqlMetni !== $metin) {
            $sqlFarki[] = $anahtar;
        }
    }
    dogrula('Migration açıklamaları kurulum/database.sql ile birebir aynı', $sqlFarki === [], implode(', ', $sqlFarki));
    dogrula('database.sql açıklama migration\'ını kurulmuş sayar', str_contains($sema, "'2026_10_08_010000_ayar_aciklamalari'"));
}

/* ---------------------------------------------------------------- */
echo "\nGüvenlik bildirimleri ve panel uyarıları (1.6.1)\n";

$epostaOlayi = new App\Events\EmailChanged(7, 'eski.adres@ornek.com', 'yeni.adres@ornek.com', App\Events\EmailChanged::YONETICI);
$olayGunlugu = json_encode($epostaOlayi->toArray(), JSON_UNESCAPED_UNICODE);
dogrula('EmailChanged günlüğe tam adres yazmaz (maskeli)', !str_contains($olayGunlugu, 'eski.adres@') && !str_contains($olayGunlugu, 'yeni.adres@') && str_contains($olayGunlugu, 'e***@ornek.com'));

$olayTablosu = (string) file_get_contents(CY_BASE . '/routes/events.php');
dogrula('E-posta değişikliği ve hesap silme dinleyicileri kayıtlı',
    str_contains($olayTablosu, 'Events::listen(EmailChanged::class, EpostaDegistiBildir::class)')
    && str_contains($olayTablosu, 'Events::listen(AccountDeletionScheduled::class, HesapSilmeBildir::class)'));
dogrula('Yeni mektup şablonları var', is_file(CY_BASE . '/views/emails/eposta-degisti.php') && is_file(CY_BASE . '/views/emails/hesap-silinecek.php'));

/* Bildirim bağlantısı sorgu ve çapa taşıyabilir; "güzel adres" kapalıyken
 * ikisi de r= parametresinin içine kodlanıp bozuluyordu. */
$bildirim = ['yol' => 'panel/eposta?sekme=gecmis&durum=basarisiz'];
$guzelAdres = Config::get('app.pretty_urls', true);
Config::set('app.pretty_urls', true);
$acik = App\Core\PanelNotices::href($bildirim) . ' ' . App\Core\PanelNotices::href(['yol' => 'panel/sistem#kurulum']);
Config::set('app.pretty_urls', false);
$kapali = App\Core\PanelNotices::href($bildirim) . ' ' . App\Core\PanelNotices::href(['yol' => 'panel/sistem#kurulum']);
Config::set('app.pretty_urls', $guzelAdres);
dogrula('Bildirim bağlantısı: güzel adreste sorgu ve çapa korunur', str_contains($acik, '/panel/eposta?sekme=gecmis&durum=basarisiz') && str_contains($acik, '/panel/sistem#kurulum'));
dogrula('Bildirim bağlantısı: index.php?r= kipinde de bozulmaz', str_contains($kapali, 'index.php?r=panel/eposta&sekme=gecmis&durum=basarisiz') && str_contains($kapali, 'index.php?r=panel/sistem#kurulum'), $kapali);

/* ---------------------------------------------------------------- */
echo "\nÖrnek veriyi kaldırma: marka ayarları (1.6.1)\n";

/* Kaldırılan örnek verinin markası müşteri sitesinde kalıyordu (sosyal
 * hesaplar, slogan). MARKA_AYARLARI'ndaki her ayar örnek verinin
 * yazdığı bir ayar olmalı; nötr değeri kurulumdakiyle aynı olmalı. */
$ornekAyarlar = App\Core\DemoData::settings();
$markaDisi    = array_diff(array_keys(App\Core\DemoData::MARKA_AYARLARI), array_keys($ornekAyarlar));
dogrula('Marka listesi yalnızca örnek verinin yazdığı ayarları içerir', $markaDisi === [], implode(', ', $markaDisi));
dogrula('Site adı marka listesinde değil (kaldırınca site adsız kalmaz)', !array_key_exists('site_adi', App\Core\DemoData::MARKA_AYARLARI));

if ($sema !== '') {
    $surum16Varsayilan = array_column(App\Support\Surum16::AYARLAR, 1, 0);
    $notrFarki = [];
    foreach (App\Core\DemoData::MARKA_AYARLARI as $anahtar => $notrDeger) {
        $kurulumDegeri = $surum16Varsayilan[$anahtar] ?? null;
        if ($kurulumDegeri === null
            && preg_match('/^\(\'' . preg_quote($anahtar, '/') . '\',.*$/mu', $sema, $satir)
            && preg_match_all("/'((?:[^']|'')*)'|NULL|-?\\d+/u", $satir[0], $degerler)) {
            $kurulumDegeri = str_replace("''", "'", $degerler[1][1]);
        }
        if ($kurulumDegeri !== $notrDeger) {
            $notrFarki[] = $anahtar;
        }
    }
    dogrula('Marka ayarlarının nötr değeri taze kurulumdakiyle aynı', $notrFarki === [], implode(', ', $notrFarki));
}

$demoKaynak = (string) file_get_contents(CY_BASE . '/app/Core/DemoData.php');
dogrula('Kaldırma yöneticinin değiştirdiği marka ayarına dokunmaz (AND deger = örnek)', str_contains($demoKaynak, 'WHERE anahtar = :anahtar AND deger = :ornek'));

/* ---------------------------------------------------------------- */
echo "\nBildirim tercihleri, yeni üye ve hesap açılışı (1.6.1)\n";

dogrula('Tercih yoksa (NULL) duyurular açık', NotificationPrefs::decode(null) === [NotificationPrefs::DUYURU => true]);
dogrula('Bozuk JSON varsayılana döner', NotificationPrefs::decode('{bozuk') === NotificationPrefs::VARSAYILAN);
dogrula('Kapalı duyuru okunur, bilinmeyen anahtar atılır',
    NotificationPrefs::decode('{"duyuru":false,"reklam":true}') === [NotificationPrefs::DUYURU => false]);
dogrula('Varsayılan tercih NULL yazılır (yeni tercih eskilere de varsayılanla gelsin)', NotificationPrefs::encode([NotificationPrefs::DUYURU => true]) === null);
dogrula('Kapalı tercih JSON yazılır', NotificationPrefs::encode([NotificationPrefs::DUYURU => false]) === '{"duyuru":false}');

$iptalAdresi = NotificationPrefs::unsubscribeUrl(42);
parse_str((string) parse_url($iptalAdresi, PHP_URL_QUERY), $iptalSorgu);
dogrula('İptal bağlantısı imzalı ve kendi kullanıcısı için geçerli', ($iptalSorgu['k'] ?? '') === '42' && NotificationPrefs::verify(42, (string) ($iptalSorgu['i'] ?? '')));
dogrula('Aynı imza başka kullanıcıda geçmez', !NotificationPrefs::verify(43, (string) ($iptalSorgu['i'] ?? '')));
dogrula('Bozulmuş imza geçmez', !NotificationPrefs::verify(42, substr((string) ($iptalSorgu['i'] ?? ''), 0, -1) . 'x') && !NotificationPrefs::verify(42, ''));

$yeniUyeAyari = array_values(array_filter(App\Support\Surum161::AYARLAR, static fn (array $a): bool => $a[0] === 'mail_bildirim_yeni_uye'))[0] ?? null;
dogrula('Yeni üye bildirimi ayarı: E-posta grubu, onay, varsayılan kapalı, açıklaması dolu',
    $yeniUyeAyari !== null && $yeniUyeAyari[1] === '0' && $yeniUyeAyari[2] === 'eposta' && $yeniUyeAyari[3] === 'onay' && trim((string) $yeniUyeAyari[5]) !== '');
dogrula('Yeni üye dinleyicisi kayıtlı, saatlik tavan 10',
    str_contains($olayTablosu, 'Events::listen(UserRegistered::class, YeniUyeyiBildir::class)') && App\Core\Mail\Notifier::YENI_UYE_SAATLIK === 10);

dogrula('Hesap açılış mektubu editörden gizlenir (güvenlik şablonu)', in_array('hesap-acildi', App\Models\MailLog::GUVENLIK_SABLONLARI, true));
$acilisSablonu = (string) @file_get_contents(CY_BASE . '/views/emails/hesap-acildi.php');
dogrula('Hesap açılış mektubu parola taşımaz, bağlantı taşır', $acilisSablonu !== '' && !preg_match('/\$(sifre|parola)\b/', $acilisSablonu) && str_contains($acilisSablonu, '$baglanti'));
dogrula('Hesap açılış bağlantısı 48 saat geçerli', App\Core\PasswordReset::HESAP_ACILIS_SAAT === 48);
$sifirlamaKaynak = (string) file_get_contents(CY_BASE . '/app/Core/PasswordReset.php');
dogrula('Temizlik süresi DOLMUŞ kayıtları siler (48 saatlik bağlantı ertesi gün silinmez)',
    str_contains($sifirlamaKaynak, "WHERE son_gecerlilik < NOW() - INTERVAL 1 DAY") && !str_contains($sifirlamaKaynak, 'WHERE created_at < NOW() - INTERVAL 1 DAY'));

if ($sema !== '') {
    dogrula('Taze kurulum bildirim_tercihleri sütunuyla gelir', str_contains($sema, '`bildirim_tercihleri` JSON NULL DEFAULT NULL'));
    dogrula('1.6.1 migration\'ı database.sql\'de kurulmuş sayılmaz (ayar satırını o yazar)', !str_contains($sema, "'2026_10_08_020000_surum_1_6_1'"));
}

/* ---------------------------------------------------------------- */
echo "\nKaba kuvvet sayacı\n";

/* MySQL'de lastInsertId() SON sorguya bakar: denemeyi yazdıktan sonra
 * araya giren budama (DELETE) numarayı 0 yapıyordu; kilit sorgusu
 * "id < 0" hiçbir denemeyi saymıyor, her 20 denemeden biri kilidi
 * atlıyordu. SQLite bu davranışı taklit etmediği için kaynak denetlenir. */
$sayacKaynak = (string) file_get_contents(CY_BASE . '/app/Core/RateLimiter.php');
$beginGovde  = (string) substr($sayacKaynak, (int) strpos($sayacKaynak, 'public function begin('), 1200);
dogrula('Deneme numarası budamadan ÖNCE okunur (kilit atlanamaz)',
    strpos($beginGovde, 'lastInsertId()') !== false
    && strpos($beginGovde, 'lastInsertId()') < (int) strpos($beginGovde, '$this->prune()'));

/* ---------------------------------------------------------------- */
echo "\nKurulum: sunucu, kapı ve bağlantı hataları (1.6.2)\n";

/* kurulum/index.php app/'e dayanmadan çalışır; içindeki küçük işlevler
 * kaynaktan çıkarılıp "kurulum_" önekiyle ayrıca tanımlanır. */
$kurulumIslevi = static function (string $ad): ?string {
    $yeni = 'kurulum_' . $ad;
    if (function_exists($yeni)) {
        return $yeni;
    }
    $kaynak = (string) @file_get_contents(CY_BASE . '/kurulum/index.php');
    if (preg_match('/^function ' . $ad . '\(.*?^\}\n/ms', $kaynak, $m) !== 1) {
        return null;
    }
    eval((string) preg_replace('/^function ' . $ad . '\(/', 'function ' . $yeni . '(', $m[0]));

    return $yeni;
};

$splitHost = $kurulumIslevi('split_host');
$dbHata    = $kurulumIslevi('db_error_message');
if ($splitHost !== null && $dbHata !== null) {
    $kapiOrnekleri = [
        'localhost:3399'   => ['127.0.0.1', 3399],
        'LOCALHOST:3307'   => ['127.0.0.1', 3307],
        'localhost'        => ['localhost', 3306],
        'localhost:3306'   => ['localhost', 3306],
        '127.0.0.1:3306'   => ['127.0.0.1', 3306],
        'db.ornek.com:3307' => ['db.ornek.com', 3307],
    ];
    $kapiFarki = array_filter($kapiOrnekleri, static fn (array $beklenen, string $girdi): bool => $splitHost($girdi) !== $beklenen, ARRAY_FILTER_USE_BOTH);
    dogrula('Sihirbaz: localhost + başka kapı → 127.0.0.1 (soket kapıyı yok sayardı)', $kapiFarki === [], implode(', ', array_keys($kapiFarki)));
    $uyumsuz = array_filter($kapiOrnekleri, static function (array $beklenen, string $girdi): bool {
        [$sunucu, $kapi] = array_pad(explode(':', $girdi), 2, '3306');

        return App\Core\Database::host($sunucu, (int) $kapi) !== $beklenen[0];
    }, ARRAY_FILTER_USE_BOTH);
    dogrula('Uygulama (.env) aynı kuralı uygular (Database::host)', $uyumsuz === [], implode(', ', array_keys($uyumsuz)));

    $soket = new PDOException('SQLSTATE[HY000] [1698] Access denied for user root@localhost');
    $soket->errorInfo = ['HY000', 1698, 'Access denied'];
    dogrula('MySQL 1698 (auth_socket) anlaşılır mesaj verir', str_contains($dbHata($soket), 'auth_socket'));
    $kurulumJs = (string) @file_get_contents(CY_BASE . '/kurulum/kurulum.js');
    dogrula('"Bağlantıyı dene" teknik ayrıntıyı sayfadaki gibi ayrı ve etiketli gösterir', str_contains($kurulumJs, "'kur-detail'") && str_contains($kurulumJs, 'Teknik ayrıntı'));
} else {
    echo "  (kurulum/ klasörü yok; atlandı)\n";
}

$paketKurallari = (string) @file_get_contents(CY_BASE . '/.gitattributes');
dogrula('ZIP paketinde .gitignore var (ZIP\'ten git\'e konan proje .env\'i commit\'lemez)', preg_match('/^\.gitignore\s+export-ignore/m', $paketKurallari) !== 1);
dogrula('ZIP paketinde docs/MOBIL-API.md var, yalnızca ekran görüntüleri çıkar',
    preg_match('/^docs\/\s+export-ignore/m', $paketKurallari) !== 1 && preg_match('/^docs\/screenshots\/\s+export-ignore/m', $paketKurallari) === 1);

/* ---------------------------------------------------------------- */
echo "
Test sayısı
";

/* Ana sayfa "{test} birim testi" der (config/app.php → test_sayisi). Test
 * eklenip sayı güncellenmezse bu test kırılır; sayı hep doğru kalır. */
$toplamTest = $gecti + $kaldi + 1;

// Kurulum klasörü silinmiş ya da pdo_sqlite yoksa bazı bölümler atlanır; sayı ancak tam koşuda anlamlıdır.
if (is_file(CY_BASE . '/kurulum/database.sql') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    dogrula('config/app.php test_sayisi gerçek sayıyla aynı (' . $toplamTest . ')', (int) Config::get('app.test_sayisi') === $toplamTest, 'config/app.php içinde test_sayisi => ' . $toplamTest . ' yazın');
} else {
    echo "  (kurulum/ klasörü ya da pdo_sqlite yok; bazı bölümler atlandı, sayı denetlenmedi)\n";
}

/* ---------------------------------------------------------------- */
printf("\n%d geçti · %d kaldı\n\n", $gecti, $kaldi);

exit($kaldi > 0 ? 1 : 0);
