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
dogrula('Geliştirmede pasif/askıda/onay bekleyenler de listelenir', count(Demo::visibleAccounts(false, $tumu)) === 6);
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
printf("\n%d geçti · %d kaldı\n\n", $gecti, $kaldi);

exit($kaldi > 0 ? 1 : 0);
