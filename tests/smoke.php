<?php
/**
 * =====================================================================
 *  DUMAN TESTİ – Kurulu bir siteye HTTP üzerinden güvenlik denetimi
 * ---------------------------------------------------------------------
 *      php tests/smoke.php http://localhost/cy-php-starter
 *      php tests/smoke.php http://localhost/cy-php-starter --kullanici=admin --parola=Admin1234
 *      php tests/smoke.php http://localhost/cy-php-starter --kullanici=admin --parola=Admin1234 --api=cy_...
 *
 *  Sitenin VERİSİNİ DEĞİŞTİRMEZ; yalnızca istek atar ve yanıtlara
 *  bakar. Kaba kuvvet testi rastgele, var olmayan bir kimlikle yapılır
 *  (gerçek hesaplar kilitlenmez). Giriş bilgisi verilirse oturum
 *  gerektiren testler de çalışır.
 *
 *  Çıkış kodu: 0 → hepsi geçti, 1 → en az bir test kaldı.
 *  Bu dosya web'den erişilemez (kök .htaccess "tests/" klasörünü kapatır).
 * =====================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "Bu test için PHP curl eklentisi gerekli.\n");
    exit(2);
}

$base = rtrim($argv[1] ?? '', '/');

if ($base === '' || !preg_match('#^https?://#', $base)) {
    fwrite(STDERR, "Kullanım: php tests/smoke.php <site-adresi> [--kullanici=.. --parola=..] [--api=cy_...]\n");
    exit(2);
}

$opts = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    }
}

/* ---------------------------------------------------------------------
 *  Küçük HTTP istemcisi (çerez kavanozuyla)
 * ------------------------------------------------------------------ */
final class Istemci
{
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'cyjar') ?: '';
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** @return array{kod:int,basliklar:string,govde:string,sure:float,konum:string} */
    public function istek(string $yol, string $yontem = 'GET', array $veri = [], array $basliklar = []): array
    {
        $ch = curl_init(str_starts_with($yol, 'http') ? $yol : $this->base . '/' . ltrim($yol, '/'));

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_CUSTOMREQUEST  => $yontem,
            CURLOPT_HTTPHEADER     => $basliklar,
            CURLOPT_TIMEOUT        => 20,
        ]);

        if ($yontem !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($veri));
        }

        $basla = microtime(true);
        $yanit = (string) curl_exec($ch);
        $sure  = microtime(true) - $basla;
        $boyut = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $kod   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        // curl_close() PHP 8'den beri etkisiz ve 8.5'te kullanımdan kalktı;
        // tutamak kapsamdan çıkınca kendiliğinden kapanır.

        $bas  = substr($yanit, 0, $boyut);
        $konum = preg_match('/^Location:\s*(.+)$/mi', $bas, $m) ? trim($m[1]) : '';

        return ['kod' => $kod, 'basliklar' => $bas, 'govde' => substr($yanit, $boyut), 'sure' => $sure, 'konum' => $konum];
    }

    public function token(string $yol): string
    {
        $r = $this->istek($yol);

        return preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['govde'], $m) ? $m[1] : '';
    }

    public function giris(string $kullanici, string $parola, array $ek = []): array
    {
        $t = $this->token('giris');

        return $this->istek('giris', 'POST', ['csrf_token' => $t, 'identifier' => $kullanici, 'password' => $parola] + $ek);
    }

    /**
     * Giriş ekranı "çok fazla hatalı deneme" mi diyor?
     *
     * Bu betik her çalıştırmada ~15 hatalı deneme üretir. Kısa sürede
     * iki-üç kez çalıştırılırsa IP GENELİNDEKİ kilit (varsayılan 30
     * deneme / 15 dk) devreye girer — yani koruma çalışıyordur. Bu
     * durumda giriş gerektiren testler "kaldı" değil "atlandı" sayılır.
     */
    public function kilitli(): bool
    {
        return str_contains($this->istek('giris')['govde'], 'Çok fazla hatalı deneme');
    }
}

/** IP kilidi yüzünden atlanan testin nedeni. */
const IP_KILIDI = 'IP kilidi devrede (önceki çalıştırmalardan kalan denemeler). 15 dk bekleyin ya da yerelde: DELETE FROM login_attempts;';

/* ---------------------------------------------------------------------
 *  Test kaydı
 * ------------------------------------------------------------------ */
$sonuclar = ['gecti' => 0, 'kaldi' => 0, 'atlandi' => 0];

function test(string $ad, callable $fn): void
{
    global $sonuclar;

    try {
        $sonuc = $fn();
    } catch (Throwable $e) {
        $sonuc = 'HATA: ' . $e->getMessage();
    }

    if ($sonuc === true) {
        $sonuclar['gecti']++;
        echo "  \033[32m✔\033[0m $ad\n";
    } elseif ($sonuc === null) {
        $sonuclar['atlandi']++;
        echo "  \033[33m–\033[0m $ad (atlandı)\n";
    } elseif (is_string($sonuc) && str_starts_with($sonuc, 'KİLİT')) {
        $sonuclar['atlandi']++;
        echo "  \033[33m–\033[0m $ad (atlandı: " . IP_KILIDI . ")\n";
    } else {
        $sonuclar['kaldi']++;
        echo "  \033[31m✖\033[0m $ad\n      → " . (is_string($sonuc) ? $sonuc : 'beklenen sonuç alınamadı') . "\n";
    }
}

$yolTabani = (string) parse_url($base, PHP_URL_PATH);

echo "\nCY PHP Starter duman testi → $base\n\n";

/* =====================================================================
 *  1) KURULUM SİHİRBAZI
 * ================================================================== */
echo "Kurulum sihirbazı\n";

test('Kurulum sihirbazı kilitli ya da kaldırılmış (?yeniden=1 dahil)', function () use ($base): bool|string {
    $c = new Istemci($base);
    $r = $c->istek('kurulum/index.php?adim=veritabani&yeniden=1');

    if (in_array($r['kod'], [403, 404], true)) {
        return true;
    }

    return str_contains($r['govde'], 'Kurulum zaten tamamlanmış') ? true : 'Sihirbaz form gösteriyor (kod ' . $r['kod'] . ')';
});

test('Kurulum adımları POST ile çalıştırılamıyor', function () use ($base): bool|string {
    $c = new Istemci($base);
    $t = $c->token('kurulum/index.php?adim=veritabani');
    $r = $c->istek('kurulum/index.php?adim=veritabani&yeniden=1', 'POST', [
        'csrf_token' => $t, 'db_host' => '127.0.0.1', 'db_name' => 'saldirgan_db', 'db_user' => 'root', 'db_pass' => '',
    ]);

    return str_contains($r['konum'], 'adim=site') ? 'Veritabanı adımı kabul edildi!' : true;
});

/* =====================================================================
 *  2) GİZLİ DOSYALAR
 * ================================================================== */
echo "\nGizli dosyalar\n";

foreach (['.env', '.git/config', '.git/HEAD', 'cy', 'kurulum/database.sql', 'storage/installed.lock',
          'storage/logs/', 'config/db.php', 'app/bootstrap.php', 'tests/smoke.php', 'composer.json'] as $yol) {
    test("/$yol dışarıya kapalı", function () use ($base, $yol): bool|string {
        $r = (new Istemci($base))->istek($yol);

        return $r['kod'] === 200 && trim($r['govde']) !== '' ? 'HTTP 200 döndü' : true;
    });
}

/* =====================================================================
 *  3) İSTEK SAĞLAMLIĞI VE HATA SAYFALARI
 * ================================================================== */
echo "\nİstek sağlamlığı\n";

test('?r[]=x 500 üretmiyor', function () use ($base): bool|string {
    $r = (new Istemci($base))->istek('index.php?r[]=x');

    return $r['kod'] < 500 ? true : 'HTTP ' . $r['kod'];
});

test('Yanlış yöntem 405 ve kendi sayfası (500 şablonu değil)', function () use ($base): bool|string {
    /* Tek parçalı yollar ("cikis") "{slug}" içerik sayfası rotasına
     * düşer ve dürüstçe 404 verir; 405'i çok parçalı bir POST
     * rotasında sınıyoruz. */
    $r = (new Istemci($base))->istek('panel/hesabim/guncelle');

    if ($r['kod'] !== 405) {
        return 'HTTP ' . $r['kod'];
    }

    return str_contains($r['govde'], 'Bir şeyler ters gitti') ? '500 şablonu gösterildi' : true;
});

test('Güvenlik başlıkları gönderiliyor', function () use ($base): bool|string {
    $b = (new Istemci($base))->istek('')['basliklar'];

    foreach (['X-Content-Type-Options', 'X-Frame-Options', 'Content-Security-Policy', 'Referrer-Policy'] as $h) {
        if (stripos($b, $h . ':') === false) {
            return $h . ' eksik';
        }
    }

    return true;
});

test('Giriş sayfası demo kapalıyken parola önermiyor (geliştirme modunda da)', function () use ($base): bool|string|null {
    $g = (new Istemci($base))->istek('giris')['govde'];

    // Demo modunda (APP_DEMO=true) hesapları göstermek bilinçli bir seçimdir.
    if (str_contains($g, 'data-demo-modu="1"')) {
        return null;
    }

    return str_contains($g, 'Demo1234!') ? 'Demo parolası sayfada görünüyor (APP_DEMO kapalıyken liste çıkmamalı)' : true;
});

/* =====================================================================
 *  4) AÇIK YÖNLENDİRME
 * ================================================================== */
echo "\nAçık yönlendirme\n";

test('Girişten sonra site dışına yönlendirilemiyor', function () use ($base, $opts, $yolTabani): bool|string|null {
    if (!isset($opts['kullanici'], $opts['parola'])) {
        return null;
    }

    $c = new Istemci($base);
    // Saldırgan sitesi POST ile "r" yerleştirmeye çalışır (auth, csrf'ten önce çalışır).
    $c->istek('panel/hesabim/guncelle', 'POST', ['r' => '/\\evil.example']);
    $c->istek('index.php?r=panel/hesabim/guncelle', 'POST', ['r' => '//evil.example']);
    $r = $c->giris($opts['kullanici'], $opts['parola']);

    if ($r['kod'] !== 302) {
        return 'Giriş başarısız (HTTP ' . $r['kod'] . ')';
    }

    if (str_ends_with((string) parse_url($r['konum'], PHP_URL_PATH), '/giris')) {
        return $c->kilitli() ? null : 'Giriş başarısız (kullanıcı adı/parola?)';
    }

    $konum = $r['konum'];
    $yol   = (string) (parse_url($konum, PHP_URL_PATH) ?? '');

    if (str_contains($konum, 'evil') || str_contains($konum, '\\') || str_starts_with($yol, '//')) {
        return 'Location: ' . $konum;
    }

    return str_starts_with($yol, $yolTabani . '/') ? true : 'Beklenmeyen hedef: ' . $konum;
});

test('Korunan sayfaya dönüş (intended) çalışıyor', function () use ($base, $opts): bool|string|null {
    if (!isset($opts['kullanici'], $opts['parola'])) {
        return null;
    }

    $c = new Istemci($base);
    $c->istek('panel/hesabim');
    $r = $c->giris($opts['kullanici'], $opts['parola']);

    if (str_ends_with((string) parse_url($r['konum'], PHP_URL_PATH), '/giris') && $c->kilitli()) {
        return null;
    }

    return str_ends_with((string) parse_url($r['konum'], PHP_URL_PATH), '/panel/hesabim') ? true : 'Location: ' . $r['konum'];
});

/* =====================================================================
 *  5) KABA KUVVET VE KULLANICI TESPİTİ
 * ================================================================== */
echo "\nKaba kuvvet ve kullanıcı tespiti\n";

test('Hatalı girişler kilide takılıyor (saat diliminden bağımsız)', function () use ($base): bool|string {
    $c     = new Istemci($base);
    $kimlik = 'yok_' . bin2hex(random_bytes(4));

    $iz = [];

    for ($i = 1; $i <= 8; $i++) {
        $p     = $c->giris($kimlik, 'yanlis-parola-' . $i);
        $sayfa = $c->istek('giris')['govde'];

        // Teşhis: kilit geç gelirse hangi denemenin kimlik doğrulamaya ulaşmadığı görünsün.
        $iz[] = $i . ':' . $p['kod'] . (str_contains($sayfa, 'Kalan deneme') ? '/kalan' : '')
              . (str_contains($sayfa, 'Güvenlik doğrulaması') || $p['kod'] === 419 ? '/csrf' : '')
              . (str_contains($sayfa, 'hatalı') ? '/hatali' : '');

        if (str_contains($sayfa, 'Çok fazla hatalı deneme')) {
            return $i <= 6 ? true : $i . '. denemede kilitlendi [' . implode(' ', $iz) . ']';
        }
    }

    return '8 denemede kilit devreye girmedi [' . implode(' ', $iz) . ']';
});

test('"Kalan deneme hakkı" var olmayan hesapta da gösteriliyor', function () use ($base): bool|string|null {
    $c      = new Istemci($base);
    $kimlik = 'yok_' . bin2hex(random_bytes(4));
    $goruldu = false;

    for ($i = 1; $i <= 4; $i++) {
        $c->giris($kimlik, 'x' . $i);
        $sayfa = $c->istek('giris')['govde'];

        if (str_contains($sayfa, 'Çok fazla hatalı deneme')) {
            return null; // IP kilidi: test anlamsız
        }

        if (str_contains($sayfa, 'Kalan deneme hakkı')) {
            $goruldu = true;
            break;
        }
    }

    return $goruldu ? true : 'Mesaj yalnızca var olan hesapta çıkıyor olabilir (kullanıcı tespiti)';
});

test('Var olan / olmayan hesap yanıt süreleri benzer', function () use ($base, $opts): bool|string|null {
    if (!isset($opts['kullanici'])) {
        return null;
    }

    $olc = function (string $kimlik) use ($base): float {
        $sureler = [];

        for ($i = 0; $i < 3; $i++) {
            $c = new Istemci($base);
            $t = $c->token('giris');
            $sureler[] = $c->istek('giris', 'POST', ['csrf_token' => $t, 'identifier' => $kimlik, 'password' => 'x' . random_int(1, 9999)])['sure'];
        }

        sort($sureler);

        return $sureler[1]; // ortanca
    };

    $var = $olc($opts['kullanici']);
    $yok = $olc('yok_' . bin2hex(random_bytes(4)));
    $oran = max($var, $yok) / max(0.001, min($var, $yok));

    return $oran < 1.8 ? true : sprintf('var: %.0f ms, yok: %.0f ms (oran %.1f)', $var * 1000, $yok * 1000, $oran);
});

/* =====================================================================
 *  6) OTURUM
 * ================================================================== */
echo "\nOturum\n";

test('Oturum çerezi kuruluma özel (CYSTARTERSESS değil, yol uygulamaya bağlı)', function () use ($base, $yolTabani): bool|string {
    $b = (new Istemci($base))->istek('giris')['basliklar'];

    if (!preg_match('/^Set-Cookie:\s*([^=]+)=[^;]*;(.*)$/mi', $b, $m)) {
        return 'Set-Cookie yok';
    }

    if ($m[1] === 'CYSTARTERSESS') {
        return 'Ortak çerez adı kullanılıyor';
    }

    if ($yolTabani !== '' && stripos($m[2], 'path=' . $yolTabani) === false) {
        return 'Çerez yolu uygulama klasörüne bağlı değil: ' . trim($m[2]);
    }

    return true;
});

test('Panel sayfası servis çalışanına "önbelleğe alma" diyor', function () use ($base, $opts): bool|string|null {
    if (!isset($opts['kullanici'], $opts['parola'])) {
        return null;
    }

    $c = new Istemci($base);
    $c->giris($opts['kullanici'], $opts['parola']);
    $p = $c->istek('panel');

    if ($p['kod'] === 302 && $c->kilitli()) {
        return null;
    }

    $b = $p['basliklar'];

    return stripos($b, 'X-CY-Onbellek: hayir') !== false ? true : 'X-CY-Onbellek başlığı yok';
});

test('sw.js panel sayfalarını önbelleğe almıyor', function () use ($base): bool|string {
    $g = (new Istemci($base))->istek('sw.js')['govde'];

    return str_contains($g, "'panel'") && str_contains($g, 'X-CY-Onbellek') ? true : 'sw.js eski sürüm olabilir';
});

/* =====================================================================
 *  7) API
 * ================================================================== */
echo "\nAPI\n";

test('api/v1/ben anahtarsız 401 döner', function () use ($base): bool|string {
    $r = (new Istemci($base))->istek('api/v1/ben', 'GET', [], ['Accept: application/json']);

    return $r['kod'] === 401 ? true : 'HTTP ' . $r['kod'];
});

test('Geçersiz Bearer anahtarı 401 döner ve oturum çerezi üretmez', function () use ($base): bool|string {
    $r = (new Istemci($base))->istek('api/v1/ben', 'GET', [], ['Authorization: Bearer cy_gecersiz000000000000']);

    if ($r['kod'] !== 401) {
        return 'HTTP ' . $r['kod'];
    }

    return stripos($r['basliklar'], 'Set-Cookie:') === false ? true : 'Set-Cookie gönderildi';
});

test('api/v1/sayfalar (anahtarsız uç) JSON döner', function () use ($base): bool|string {
    $r = (new Istemci($base))->istek('api/v1/sayfalar');
    $j = json_decode($r['govde'], true);

    return $r['kod'] === 200 && is_array($j) && ($j['success'] ?? false) === true ? true : 'HTTP ' . $r['kod'];
});

test('Geçerli Bearer anahtarı Apache\'den PHP\'ye ulaşıyor, çerez üretmiyor', function () use ($base, $opts): bool|string|null {
    if (!isset($opts['api'])) {
        return null;
    }

    $r = (new Istemci($base))->istek('api/v1/ben', 'GET', [], ['Authorization: Bearer ' . $opts['api']]);

    if ($r['kod'] !== 200) {
        return 'HTTP ' . $r['kod'] . ' ' . substr($r['govde'], 0, 120);
    }

    return stripos($r['basliklar'], 'Set-Cookie:') === false ? true : 'Bearer isteği oturum çerezi aldı';
});

/* =====================================================================
 *  8) MOBİL API (oturum aç → kullan → kapat)
 * ---------------------------------------------------------------------
 *  --kullanici/--parola verilmişse ya da örnek veri kuruluysa çalışır.
 *  Açtığı oturumu sonunda kapatır; veriye dokunmaz.
 * ================================================================== */
echo "\nMobil API\n";

/** Mobil giriş için kullanılacak hesap: önce komut satırı, yoksa demo üye. */
function mobil_hesap(array $opts): ?array
{
    if (isset($opts['kullanici'], $opts['parola'])) {
        return [$opts['kullanici'], $opts['parola']];
    }

    return DEMO_VAR ? ['mehmet.uye', DEMO_PAROLA] : null;
}

const DEMO_PAROLA = 'Demo1234!';
define('DEMO_VAR', (static function () use ($base): bool {
    $r = (new Istemci($base))->istek('api/v1/oturum', 'POST', ['kullanici' => 'mehmet.uye', 'parola' => DEMO_PAROLA, 'cihaz' => 'smoke-algilama']);
    $j = json_decode($r['govde'], true);

    if ($r['kod'] !== 201 || !isset($j['data']['token'])) {
        return false;
    }

    (new Istemci($base))->istek('api/v1/oturum', 'DELETE', [], ['Authorization: Bearer ' . $j['data']['token']]);

    return true;
})());

test('POST api/v1/oturum yanlış parolada 401 döner', function () use ($base): bool|string {
    $r = (new Istemci($base))->istek('api/v1/oturum', 'POST', ['kullanici' => 'yok_' . bin2hex(random_bytes(3)), 'parola' => 'yanlis123']);

    return $r['kod'] === 401 ? true : 'HTTP ' . $r['kod'];
});

test('Mobil oturum: giriş → ben → dosyalar → çıkış → 401', function () use ($base, $opts): bool|string|null {
    $hesap = mobil_hesap($opts);

    if ($hesap === null) {
        return null;
    }

    $r = (new Istemci($base))->istek('api/v1/oturum', 'POST', ['kullanici' => $hesap[0], 'parola' => $hesap[1], 'cihaz' => 'Duman testi']);
    $j = json_decode($r['govde'], true);
    $t = (string) ($j['data']['token'] ?? '');

    if ($r['kod'] !== 201 || $t === '') {
        return 'Giriş: HTTP ' . $r['kod'] . ' ' . substr($r['govde'], 0, 120);
    }

    $bearer = ['Authorization: Bearer ' . $t];
    $ben    = (new Istemci($base))->istek('api/v1/ben', 'GET', [], $bearer);

    if ($ben['kod'] !== 200 || stripos($ben['basliklar'], 'Set-Cookie:') !== false) {
        return 'ben: HTTP ' . $ben['kod'] . ' (Bearer isteği çerez almamalı)';
    }

    $dosya = (new Istemci($base))->istek('api/v1/dosyalar', 'GET', [], $bearer);

    if ($dosya['kod'] !== 200) {
        return 'dosyalar: HTTP ' . $dosya['kod'];
    }

    $oturumlar = json_decode((new Istemci($base))->istek('api/v1/oturumlar', 'GET', [], $bearer)['govde'], true);
    $buCihaz   = array_filter((array) ($oturumlar['data'] ?? []), static fn ($o) => ($o['bu_cihaz'] ?? false) === true);

    if (count($buCihaz) !== 1) {
        return 'oturumlar: bu cihaz işaretli değil';
    }

    $cikis = (new Istemci($base))->istek('api/v1/oturum', 'DELETE', [], $bearer);
    $sonra = (new Istemci($base))->istek('api/v1/ben', 'GET', [], $bearer);

    return $cikis['kod'] === 200 && $sonra['kod'] === 401 ? true : 'Çıkış: ' . $cikis['kod'] . ', sonrası: ' . $sonra['kod'];
});

test('Örnek dosyalar anahtarsız verilmiyor, yol dışına çıkılamıyor', function () use ($base): bool|string {
    $a = (new Istemci($base))->istek('api/v1/dosyalar/hosgeldiniz.txt');
    $b = (new Istemci($base))->istek('api/v1/dosyalar/..%2F..%2F.env');

    return $a['kod'] === 401 && in_array($b['kod'], [400, 401, 403, 404], true) ? true : 'HTTP ' . $a['kod'] . ' / ' . $b['kod'];
});

/* =====================================================================
 *  9) ROL MATRİSİ (örnek veriyle kurulmuş sitede, demo hesaplarla)
 * ---------------------------------------------------------------------
 *  Demo modu AÇIK ya da KAPALI çalışır; CI ikisini de dener. Veri
 *  değiştiren uçlar yalnızca geçersiz kayıtla çağrılır, yani hiçbir
 *  şey değişmez: amaç "kapı kimi geçiriyor" sorusudur.
 * ================================================================== */
echo "\nRol matrisi (demo hesaplar)\n";

/** @return array<string,Istemci>|null Rol → oturum açmış istemci */
function rol_istemcileri(string $base): ?array
{
    static $hazir = null;

    if ($hazir !== null || !DEMO_VAR) {
        return $hazir;
    }

    $hazir = [];

    foreach (['yonetici' => 'ali.yonetici', 'editor' => 'elif.editor', 'uye' => 'mehmet.uye'] as $rol => $ad) {
        $c = new Istemci($base);
        $r = $c->giris($ad, DEMO_PAROLA);

        if (!str_contains($r['konum'], 'panel')) {
            $hazir = null;

            return null;
        }

        $hazir[$rol] = $c;
    }

    return $hazir;
}

function meta_jeton(Istemci $c): string
{
    return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $c->istek('panel')['govde'], $m) ? $m[1] : '';
}

$demoModu = str_contains((new Istemci($base))->istek('giris')['govde'], 'data-demo-modu="1"');
echo '  (site demo modunda: ' . ($demoModu ? 'EVET' : 'HAYIR') . ")\n";

$sayfaMatrisi = [
    'panel'               => [200, 200, 200],
    'panel/kullanicilar'  => [200, 403, 403],
    'panel/mesajlar'      => [200, 200, 403],
    'panel/eposta'        => [200, 200, 403],
    'panel/sayfalar'      => [200, 200, 403],
    'panel/sayfalar/yeni' => [200, 200, 403],
    'panel/ayarlar/genel' => [200, 403, 403],
    'panel/sistem'        => [200, 403, 403],
    'panel/hesabim'       => [200, 200, 200],
    'panel/ornek'         => [200, 200, 200],
];

foreach ($sayfaMatrisi as $yol => $beklenen) {
    test("GET /$yol → yönetici " . $beklenen[0] . ', editör ' . $beklenen[1] . ', üye ' . $beklenen[2] . ', misafir girişe', function () use ($base, $yol, $beklenen): bool|string|null {
        $roller = rol_istemcileri($base);

        if ($roller === null) {
            return null;
        }

        $gercek = [];

        foreach (['yonetici', 'editor', 'uye'] as $i => $rol) {
            $kod = $roller[$rol]->istek($yol)['kod'];

            // Modül kapalıysa /panel/ornek herkese 404'tür; o satır atlanır.
            if ($yol === 'panel/ornek' && $kod === 404) {
                return null;
            }

            $gercek[] = $kod;
        }

        $misafir = (new Istemci($base))->istek($yol);

        if ($gercek !== $beklenen) {
            return 'Alınan: ' . implode(' / ', $gercek);
        }

        return $misafir['kod'] === 302 && str_contains($misafir['konum'], 'giris') ? true : 'Misafir: HTTP ' . $misafir['kod'];
    });
}

$islemMatrisi = [
    'api/kullanicilar/list' => [200, 403, 403],
    'api/mesajlar/list'     => [200, 200, 403],
    'api/eposta/list'       => [200, 200, 403],
];

foreach ($islemMatrisi as $yol => $beklenen) {
    test("POST $yol (CSRF ile) → " . implode(' / ', $beklenen), function () use ($base, $yol, $beklenen): bool|string|null {
        $roller = rol_istemcileri($base);

        if ($roller === null) {
            return null;
        }

        $gercek = [];

        foreach (['yonetici', 'editor', 'uye'] as $rol) {
            $c        = $roller[$rol];
            $gercek[] = $c->istek($yol, 'POST', ['draw' => 1, 'start' => 0, 'length' => 5], [
                'X-CSRF-Token: ' . meta_jeton($c),
                'X-Requested-With: XMLHttpRequest',
            ])['kod'];
        }

        return $gercek === $beklenen ? true : 'Alınan: ' . implode(' / ', $gercek);
    });
}

test('Jetonsuz POST reddedilir (419/403)', function () use ($base): bool|string|null {
    $roller = rol_istemcileri($base);

    if ($roller === null) {
        return null;
    }

    $kod = $roller['yonetici']->istek('api/kullanicilar/list', 'POST', ['draw' => 1], ['X-Requested-With: XMLHttpRequest'])['kod'];

    return in_array($kod, [403, 419], true) ? true : 'HTTP ' . $kod;
});

test('Editörün yetkisiz işlemi "yetki" ile reddedilir, demo mesajıyla değil', function () use ($base): bool|string|null {
    $roller = rol_istemcileri($base);

    if ($roller === null) {
        return null;
    }

    $c = $roller['editor'];
    $r = $c->istek('api/kullanicilar/status', 'POST', ['id' => 0, 'durum' => 'pasif'], [
        'X-CSRF-Token: ' . meta_jeton($c), 'X-Requested-With: XMLHttpRequest',
    ]);

    if ($r['kod'] !== 403) {
        return 'HTTP ' . $r['kod'];
    }

    return str_contains($r['govde'], 'Demo') ? 'Demo kilidi yetkiden önce çalışıyor' : true;
});

test('Yöneticinin işlemi: demo açıkken demo kilidi, kapalıyken kapıdan geçer', function () use ($base, $demoModu): bool|string|null {
    $roller = rol_istemcileri($base);

    if ($roller === null) {
        return null;
    }

    // Geçersiz kayıt (id=0): demo kapalıyken bile hiçbir şey değişmez.
    $c = $roller['yonetici'];
    $r = $c->istek('api/kullanicilar/status', 'POST', ['id' => 0, 'durum' => 'pasif'], [
        'X-CSRF-Token: ' . meta_jeton($c), 'X-Requested-With: XMLHttpRequest',
    ]);

    if ($demoModu) {
        return $r['kod'] === 403 && str_contains($r['govde'], 'Demo') ? true : 'Demo kilidi bekleniyordu, HTTP ' . $r['kod'];
    }

    return $r['kod'] !== 403 && $r['kod'] < 500 ? true : 'Yönetici reddedildi ya da hata: HTTP ' . $r['kod'];
});

test('Pasif, askıdaki ve onay bekleyen hesaplar giriş yapamıyor', function () use ($base): bool|string|null {
    if (!DEMO_VAR) {
        return null;
    }

    foreach (['ayse.pasif', 'can.askida', 'zeynep.onay'] as $ad) {
        $r = (new Istemci($base))->giris($ad, DEMO_PAROLA);

        if (str_contains($r['konum'], 'panel')) {
            return "$ad panele girdi";
        }
    }

    return true;
});

test('Örnek Modül: üye başkasının kaydını düzenleyemez ve silemez', function () use ($base): bool|string|null {
    $roller = rol_istemcileri($base);

    if ($roller === null) {
        return null;
    }

    $hepsi = preg_match_all('#panel/ornek/duzenle/(\d+)#', $roller['yonetici']->istek('panel/ornek')['govde'], $m) ? array_unique($m[1]) : [];
    $kendi = preg_match_all('#panel/ornek/duzenle/(\d+)#', $roller['uye']->istek('panel/ornek')['govde'], $k) ? array_unique($k[1]) : [];
    $baska = array_values(array_diff($hepsi, $kendi));

    if ($baska === []) {
        return null;   // modül kapalı ya da kayıt yok
    }

    $uye = $roller['uye'];
    $duz = $uye->istek('panel/ornek/duzenle/' . $baska[0])['kod'];
    $sil = $uye->istek('panel/ornek/sil/' . $baska[0], 'POST', ['csrf_token' => meta_jeton($uye)])['kod'];

    return in_array($duz, [403, 404], true) && in_array($sil, [403, 404], true) ? true : "düzenle $duz, sil $sil";
});

test('Kayıt formu e-posta gönderilemiyorsa kapalı (geliştirme önizlemesinde açık)', function () use ($base): bool|string|null {
    $roller = rol_istemcileri($base);

    if ($roller === null) {
        return null;
    }

    $panel = $roller['yonetici']->istek('panel')['govde'];
    $r     = (new Istemci($base))->istek('kayit');

    // Yayın ortamı, e-posta yok → form kapalı, girişe yönlenir.
    if (str_contains($panel, 'Üye kaydı kapalı')) {
        return $r['kod'] === 302 && str_contains($r['konum'], 'giris') ? true : 'Form açık kaldı: HTTP ' . $r['kod'];
    }

    // Geliştirme modu, e-posta yok → form bilerek açık; doğrulama bağlantısı
    // Panel → E-posta'da gösterilir (Registration::developmentPreview).
    if (str_contains($panel, 'Doğrulama mektupları gönderilmiyor')) {
        return $r['kod'] === 200 ? true : 'Geliştirme önizlemesinde form açılmadı: HTTP ' . $r['kod'];
    }

    return null;   // site e-posta gönderebiliyor: form ayarına göre açık
});

/* =====================================================================
 *  10) BİLDİRİMLER (1.6.1): duyuru tercihi ve hesap açılışı
 * ---------------------------------------------------------------------
 *  Kurulum yöneticisiyle (--kullanici/--parola) çalışır; değiştirdiği
 *  her şeyi geri alır (tercih eski hâline döner, açılan hesap silinir).
 * ================================================================== */
echo "\nBildirimler\n";

/** Yönetici istemcisi ya da null (bilgi verilmediyse / giriş olmadıysa). */
function yonetici_istemcisi(string $base, array $opts): ?Istemci
{
    static $hazir = false;

    if ($hazir !== false) {
        return $hazir;
    }

    if (!isset($opts['kullanici'], $opts['parola'])) {
        return $hazir = null;
    }

    $c = new Istemci($base);

    return $hazir = str_contains($c->giris($opts['kullanici'], $opts['parola'])['konum'], 'panel') ? $c : null;
}

test('Duyuru tercihi: kapatınca kitle özeti atlar, açınca geri gelir', function () use ($base, $opts): bool|string|null {
    $c = yonetici_istemcisi($base, $opts);

    if ($c === null) {
        return null;
    }

    $hesabim = $c->istek('panel/hesabim')['govde'];

    if (!str_contains($hesabim, 'id="bildirim_duyuru"')) {
        return 'Hesabım\'da E-posta Bildirimleri kartı yok';
    }

    // Site geneli yönetici bildirimleri olduğu gibi korunur.
    $site = [];
    foreach (['yeni_mesaj', 'yeni_uye'] as $ad) {
        if (preg_match('/id="bildirim_' . $ad . '"[^>]*\bchecked\b/', $hesabim)) {
            $site[$ad] = '1';
        }
    }

    $kaydet = static function (bool $duyuru) use ($c, $site): array {
        $t = preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $c->istek('panel/hesabim')['govde'], $m) ? $m[1] : '';

        return $c->istek('panel/hesabim/bildirimler', 'POST', ['csrf_token' => $t] + $site + ($duyuru ? ['duyuru' => '1'] : []));
    };

    $kaydet(false);
    $kapali = !preg_match('/id="bildirim_duyuru"[^>]*\bchecked\b/', $c->istek('panel/hesabim')['govde']);
    $ozet   = json_decode($c->istek('api/eposta/alicilar', 'POST', ['hedef' => 'rol:admin'], [
        'X-CSRF-Token: ' . meta_jeton($c), 'X-Requested-With: XMLHttpRequest',
    ])['govde'], true);

    $kaydet(true);
    $acik = (bool) preg_match('/id="bildirim_duyuru"[^>]*\bchecked\b/', $c->istek('panel/hesabim')['govde']);

    if (!$kapali) {
        return 'Tercih kapanmadı';
    }
    if ((int) ($ozet['atlanan'] ?? 0) < 1) {
        return 'Kitle özeti duyuruyu kapatan yöneticiyi atlamadı: ' . json_encode($ozet, JSON_UNESCAPED_UNICODE);
    }

    return $acik ? true : 'Tercih yeniden açılmadı';
});

test('Duyuru iptal bağlantısı imzasız açılmaz', function () use ($base): bool|string {
    $r = (new Istemci($base))->istek('duyurular/iptal?k=1&i=' . str_repeat('0', 64));

    return $r['kod'] === 403 && str_contains($r['govde'], 'Bağlantı geçersiz') ? true : 'HTTP ' . $r['kod'];
});

test('Hesap açılışı: parolasız kullanıcı + "parolanızı belirleyin" mektubu', function () use ($base, $opts): bool|string|null {
    $c = yonetici_istemcisi($base, $opts);

    if ($c === null) {
        return null;
    }

    $ad = 'duman_' . bin2hex(random_bytes(3));
    $r  = $c->istek('api/kullanicilar/save', 'POST', [
        'action' => 'add', 'ad' => 'Duman', 'soyad' => 'Testi', 'kullanici_adi' => $ad, 'eposta' => $ad . '@ornek.test',
        'rol' => 'uye', 'durum' => 'aktif', 'sifre' => '', 'hesap_bilgisi' => '1',
    ], ['X-CSRF-Token: ' . meta_jeton($c), 'X-Requested-With: XMLHttpRequest']);
    $j = json_decode($r['govde'], true) ?: [];

    // E-posta gönderilemiyorsa seçenek yok sayılır ve parola zorunludur.
    if ($r['kod'] === 422 && isset($j['errors']['sifre'])) {
        return null;
    }

    $id = (int) ($j['id'] ?? 0);

    if ($id > 0) {
        $c->istek('api/kullanicilar/delete', 'POST', ['id' => $id], ['X-CSRF-Token: ' . meta_jeton($c), 'X-Requested-With: XMLHttpRequest']);
    }

    return $r['kod'] === 200 && str_contains((string) ($j['description'] ?? ''), 'hesap bilgisi e-postayla gönderildi')
        ? true
        : 'HTTP ' . $r['kod'] . ' ' . substr($r['govde'], 0, 160);
});

/* =====================================================================
 *  SONUÇ
 * ================================================================== */
printf(
    "\n%d geçti · %d kaldı · %d atlandı\n\n",
    $sonuclar['gecti'],
    $sonuclar['kaldi'],
    $sonuclar['atlandi']
);

exit($sonuclar['kaldi'] > 0 ? 1 : 0);
