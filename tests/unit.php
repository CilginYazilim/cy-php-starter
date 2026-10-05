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

use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Core\Mail\NativeTransport;
use App\Core\Middleware;
use App\Core\Request;
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
printf("\n%d geçti · %d kaldı\n\n", $gecti, $kaldi);

exit($kaldi > 0 ? 1 : 0);
