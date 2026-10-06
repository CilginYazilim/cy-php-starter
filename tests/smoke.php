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

test('Giriş sayfası demo parolası önermiyor (yayın ortamı)', function () use ($base): bool|string|null {
    $g = (new Istemci($base))->istek('giris')['govde'];

    // Demo modunda (APP_DEMO=true) hesapları göstermek bilinçli bir seçimdir.
    if (str_contains($g, 'data-demo-modu="1"')) {
        return null;
    }

    return str_contains($g, 'Demo1234!') ? 'Demo parolası sayfada görünüyor (APP_DEBUG açık olabilir)' : true;
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

    for ($i = 1; $i <= 8; $i++) {
        $c->giris($kimlik, 'yanlis-parola-' . $i);
        $sayfa = $c->istek('giris')['govde'];

        if (str_contains($sayfa, 'Çok fazla hatalı deneme')) {
            return $i <= 6 ? true : $i . '. denemede kilitlendi';
        }
    }

    return '8 denemede kilit devreye girmedi';
});

test('"Kalan deneme hakkı" var olmayan hesapta da gösteriliyor', function () use ($base): bool|string {
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
 *  SONUÇ
 * ================================================================== */
printf(
    "\n%d geçti · %d kaldı · %d atlandı\n\n",
    $sonuclar['gecti'],
    $sonuclar['kaldi'],
    $sonuclar['atlandi']
);

exit($sonuclar['kaldi'] > 0 ? 1 : 0);
