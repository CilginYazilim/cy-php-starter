<?php
/**
 * =====================================================================
 *  KURULUM TESTİ – Sihirbazı HTTP üzerinden uçtan uca çalıştırır
 * ---------------------------------------------------------------------
 *      php tests/kurulum.php http://127.0.0.1:8000 --db-adi=cy_ci \
 *          [--db-host=127.0.0.1] [--db-kullanici=root] [--db-parola=] \
 *          [--ornek-veri] [--demo] [--yonetici=yonetici] [--parola=Yonetici1234]
 *
 *  Bir kullanıcının tarayıcıda yaptığını yapar: veritabanı ekranı →
 *  site ve yönetici ekranı → bitiş. Her adımın yönlendirmesini ve bitiş
 *  ekranındaki ✓ satırlarını denetler; sonra yeni yöneticiyle panele
 *  giriş yapar. CI'da tests/smoke.php'den önce çalışır.
 *
 *  Veritabanı BOŞ olmalıdır (dolu veritabanında sihirbaz onay ister;
 *  bu betik onu vermez — yanlışlıkla gerçek bir veritabanını ezmesin).
 * =====================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = rtrim($argv[1] ?? '', '/');

if ($base === '' || !preg_match('#^https?://#', $base)) {
    fwrite(STDERR, "Kullanım: php tests/kurulum.php <site-adresi> --db-adi=.. [--db-host=..] [--db-kullanici=..] [--db-parola=..] [--ornek-veri] [--demo]\n");
    exit(2);
}

$o = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $o[$m[1]] = $m[2] ?? '1';
    }
}

$kavanoz = tempnam(sys_get_temp_dir(), 'cykur') ?: '';

/** @return array{kod:int,konum:string,govde:string} */
function iste(string $adres, string $kavanoz, array $veri = null): array
{
    $ch = curl_init($adres);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $kavanoz,
        CURLOPT_COOKIEFILE     => $kavanoz,
        CURLOPT_TIMEOUT        => 120,
    ]);

    if ($veri !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($veri));
    }

    $yanit = (string) curl_exec($ch);
    $boyut = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bas   = substr($yanit, 0, $boyut);

    return [
        'kod'   => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'konum' => preg_match('/^Location:\s*(.+)$/mi', $bas, $m) ? trim($m[1]) : '',
        'govde' => substr($yanit, $boyut),
    ];
}

function jeton(string $html): string
{
    return preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

function dur(string $mesaj, string $govde = ''): never
{
    fwrite(STDERR, "  ✖ $mesaj\n");

    if ($govde !== '' && preg_match_all('/class="[^"]*(?:invalid-feedback|cy-alert)[^"]*"[^>]*>(.*?)<\/div>/s', $govde, $m)) {
        foreach ($m[1] as $hata) {
            $hata = trim((string) preg_replace('/\s+/', ' ', strip_tags($hata)));
            if ($hata !== '') {
                fwrite(STDERR, "      → $hata\n");
            }
        }
    }

    exit(1);
}

$yonetici = (string) ($o['yonetici'] ?? 'yonetici');
$parola   = (string) ($o['parola'] ?? 'Yonetici1234');
$sihirbaz = $base . '/kurulum/index.php';

echo "\nKurulum sihirbazı → $base\n";

// 1) Veritabanı ekranı
$r = iste($sihirbaz . '?adim=veritabani', $kavanoz);
if ($r['kod'] !== 200 || jeton($r['govde']) === '') {
    dur('Sihirbaz açılmadı (HTTP ' . $r['kod'] . ')', $r['govde']);
}

$r = iste($sihirbaz . '?adim=veritabani', $kavanoz, [
    'csrf_token' => jeton($r['govde']),
    'db_host'    => (string) ($o['db-host'] ?? '127.0.0.1'),
    'db_name'    => (string) ($o['db-adi'] ?? 'cy_ci'),
    'db_user'    => (string) ($o['db-kullanici'] ?? 'root'),
    'db_pass'    => (string) ($o['db-parola'] ?? ''),
]);
if (!str_contains($r['konum'], 'adim=kur')) {
    dur('Veritabanı adımı geçilemedi (HTTP ' . $r['kod'] . ')', $r['govde']);
}
echo "  ✔ Veritabanı bağlantısı kabul edildi\n";

// 2) Site + yönetici ekranı
$r = iste($sihirbaz . '?adim=kur', $kavanoz);
preg_match_all('/name="moduller\[\]" value="([^"]+)"\s+checked/', $r['govde'], $mod);

$form = [
    'csrf_token'     => jeton($r['govde']),
    'site_adi'       => 'CY PHP Starter',
    'site_url'       => $base,
    'site_aciklama'  => 'Sürekli entegrasyon kurulumu',
    'admin_ad_soyad' => 'Ci Yönetici',
    'admin_kadi'     => $yonetici,
    'admin_eposta'   => $yonetici . '@ornek.com',
    'admin_sifre'    => $parola,
    'admin_sifre2'   => $parola,
    'pwa_aktif'      => '1',
    'moduller'       => $mod[1] ?? [],
];
if (isset($o['ornek-veri'])) {
    $form['ornek_veri'] = '1';
}
if (isset($o['demo'])) {
    $form['demo_modu'] = '1';
}

$basla = microtime(true);
$r     = iste($sihirbaz . '?adim=kur', $kavanoz, $form);
if (!str_contains($r['konum'], 'adim=tamam')) {
    dur('Kurulum adımı tamamlanmadı (HTTP ' . $r['kod'] . ')', $r['govde']);
}
printf("  ✔ Kurulum çalıştı (%.1f sn)\n", microtime(true) - $basla);

// 3) Bitiş ekranı
$r = iste($sihirbaz . '?adim=tamam', $kavanoz);
if ($r['kod'] !== 200 || !str_contains($r['govde'], 'hazır')) {
    dur('Bitiş ekranı açılmadı', $r['govde']);
}
if (str_contains($r['govde'], 'class="is-warn"')) {
    dur('Bitiş ekranında tamamlanmayan adım var (!)', $r['govde']);
}
echo "  ✔ Bitiş ekranı: kurulum hazır\n";

// 4) Sihirbaz artık kilitli
$r = iste($sihirbaz . '?adim=veritabani', tempnam(sys_get_temp_dir(), 'cykur2') ?: '');
if ($r['kod'] === 200 && jeton($r['govde']) !== '' && str_contains($r['govde'], 'db_host')) {
    dur('Kurulumdan sonra sihirbaz hâlâ veritabanı formu gösteriyor');
}
echo "  ✔ Sihirbaz kilitlendi\n";

// 5) Yeni yöneticiyle giriş
$kavanoz2 = tempnam(sys_get_temp_dir(), 'cykur3') ?: '';
$g = iste($base . '/giris', $kavanoz2);
$r = iste($base . '/giris', $kavanoz2, ['csrf_token' => jeton($g['govde']), 'identifier' => $yonetici, 'password' => $parola]);
if (!str_contains($r['konum'], 'panel')) {
    dur('Yönetici panele giremedi (HTTP ' . $r['kod'] . ')');
}
$p = iste($base . '/panel', $kavanoz2);
if ($p['kod'] !== 200) {
    dur('Panel açılmadı (HTTP ' . $p['kod'] . ')');
}
echo "  ✔ Yönetici ($yonetici) panele girdi\n";

@unlink($kavanoz);
@unlink($kavanoz2);

echo "\nKurulum testi geçti.\n\n";
exit(0);
