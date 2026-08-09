<?php
/**
 * =====================================================================
 *  SİTE (ÖN YÜZ) – ORTAK ÜST ŞABLON
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Ziyaretçiye görünen HER sayfa bu dosyayla başlar. Üst menü, oturum
 *  durumu, tema, SEO etiketleri ve bakım modu TEK yerde yönetilir.
 *
 *  KULLANIMI (sayfanın en üstünde):
 *      <?php
 *      $sayfaBaslik   = 'İletişim';        // <title> ve menü vurgusu
 *      $aktifSayfa    = 'iletisim';        // menüde hangi öğe parlasın
 *      $sayfaAciklama = 'Bize ulaşın.';    // meta description (ops.)
 *      require __DIR__ . '/_ust.php';
 *      ?>
 *      ... sayfa içeriği ...
 *      <?php require __DIR__ . '/_alt.php'; ?>
 *
 *  NOT: Bu dosya config.php'yi kendisi yükler; sayfanızda ayrıca
 *  yüklemenize gerek yoktur. Yükleseniz de require_once olduğu için
 *  sorun çıkmaz.
 * =====================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/system/config.php';

/* ---------------------------------------------------------------------
 *  SAYFA DEĞİŞKENLERİ (sayfa tanımlamadıysa varsayılanlar)
 * ------------------------------------------------------------------ */
$siteAdi       = (string) setting('site_adi', APP_NAME);
$sayfaBaslik   = $sayfaBaslik   ?? '';
$aktifSayfa    = $aktifSayfa    ?? '';
$sayfaAciklama = $sayfaAciklama ?? (string) setting('site_aciklama', APP_DESCRIPTION);

$csrfToken     = csrf_token();
$aktifKullanici = auth_user($db);          // Giriş yoksa null
$kayitAcik     = setting_bool('sistem_kayit_acik');

// <title> içeriği: "İletişim | Site Adı" ya da sadece "Site Adı"
$tamBaslik = $sayfaBaslik !== ''
    ? $sayfaBaslik . ' | ' . $siteAdi
    : $siteAdi;


/* =====================================================================
 *  BAKIM MODU
 * ---------------------------------------------------------------------
 *  Ayarlardan açıldıysa ziyaretçilere kapalıyız. Editör ve yöneticiler
 *  siteyi normal görmeye devam eder ki düzenleme yapabilsinler.
 *
 *  HTTP 503 "Service Unavailable" göndermek önemlidir: arama motorları
 *  bunu "geçici" olarak yorumlar ve sayfayı dizinden düşürmez.
 *  200 dönseydiniz Google bakım sayfasını gerçek içerik sanardı.
 * ------------------------------------------------------------------ */
if (setting_bool('sistem_bakim_modu') && !auth_at_least('editor')) {
    http_response_code(503);
    header('Retry-After: 3600');   // "Bir saat sonra tekrar uğra"
    ?>
    <!DOCTYPE html>
    <html lang="<?= e((string) setting('site_dil', 'tr')) ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex">
        <title>Bakım çalışması | <?= e($siteAdi) ?></title>
        <link rel="icon" type="image/png" href="<?= e(site_logo_url()) ?>">
        <link rel="stylesheet" href="assets/css/bootstrap.min.css">
        <link rel="stylesheet" href="assets/css/cilginyazilim.css">
    </head>
    <body class="cy-app">
        <div class="cy-topbar"></div>
        <div class="container py-5" style="max-width:520px">
            <div class="cy-card">
                <div class="cy-card__body text-center py-5">
                    <img src="<?= e(site_logo_url()) ?>" alt="" style="width:64px;height:64px;object-fit:contain">
                    <h1 class="h5 mt-3">Kısa bir bakım çalışması yapıyoruz</h1>
                    <p class="cy-muted mb-4">Çok yakında geri döneceğiz.</p>
                    <a href="giris.php" class="btn cy-btn cy-btn--primary btn-sm">Yönetici Girişi</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}


/* ---------------------------------------------------------------------
 *  MENÜ TANIMI
 * ---------------------------------------------------------------------
 *  Yeni sayfa eklemek için buraya bir satır eklemeniz yeterli.
 *  'oturum' anahtarı görünürlüğü belirler:
 *      null    → herkese görünür
 *      true    → sadece giriş yapmışlara
 *      false   → sadece giriş yapmamışlara
 * ------------------------------------------------------------------ */
$siteMenu = [
    ['anahtar' => 'anasayfa',  'baslik' => 'Ana Sayfa',   'url' => 'index.php',      'oturum' => null],
    ['anahtar' => 'hakkimizda','baslik' => 'Hakkımızda',  'url' => 'hakkimizda.php', 'oturum' => null],
    ['anahtar' => 'iletisim',  'baslik' => 'İletişim',    'url' => 'iletisim.php',   'oturum' => null],
    ['anahtar' => 'hesabim',   'baslik' => 'Hesabım',     'url' => 'hesabim.php',    'oturum' => true],
];
?>
<!DOCTYPE html>
<html lang="<?= e((string) setting('site_dil', 'tr')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Çılgın Yazılım - cilginyazilim.com">
    <meta name="description" content="<?= e($sayfaAciklama) ?>">
    <meta name="keywords" content="<?= e((string) setting('seo_anahtar_kelimeler', '')) ?>">
    <meta name="theme-color" content="<?= e((string) setting('sistem_tema_rengi', '#0b5cb5')) ?>">

    <?php if (!setting_bool('seo_indeksleme', true)): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>

    <!-- CSRF anahtarı: JavaScript buradan okuyup AJAX isteklerine ekler. -->
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">

    <title><?= e($tamBaslik) ?></title>

    <!-- Sosyal medyada paylaşıldığında görünecek kart (Open Graph) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteAdi) ?>">
    <meta property="og:title" content="<?= e($sayfaBaslik !== '' ? $sayfaBaslik : $siteAdi) ?>">
    <meta property="og:description" content="<?= e($sayfaAciklama) ?>">

    <link rel="icon" type="image/png" href="<?= e(site_logo_url()) ?>">

    <!--
        CSS YÜKLEME SIRASI ÖNEMLİDİR:
        bootstrap → dataTables → cilginyazilim → style
        Sonra yüklenen dosya öncekini geçersiz kılabilir.
    -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/cilginyazilim.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Tema rengini ayarlardan uygula (CSS değişkenini ezerek) -->
    <style>
        :root { --cy-brand-600: <?= e((string) setting('sistem_tema_rengi', '#0b5cb5')) ?>; }
    </style>

    <script>
    /* KOYU TEMA – "yanıp sönme" (flash) önleyici
     * ------------------------------------------------------------------
     * Bu betik BİLEREK <head> içinde ve satır içidir. Sayfa çizilmeden
     * ÖNCE çalışması gerekir; aşağıda olsaydı kullanıcı önce açık temayı
     * görür, sonra ekran bir anda kararırdı.
     *
     * try/catch: gizli sekmede localStorage erişimi hata verebilir;
     * hata sayfanın geri kalanını durdurmasın diye yutuyoruz. */
    (function () {
        try {
            var tema = localStorage.getItem('cy-tema');
            if (tema === 'dark' || tema === 'light') {
                document.documentElement.setAttribute('data-cy-theme', tema);
            }
        } catch (e) {}
    })();
    </script>
</head>

<body class="cy-app cy-site">
    <div class="cy-topbar"></div>

    <!-- Klavye kullanıcıları menüyü atlayıp içeriğe geçebilsin -->
    <a href="#icerik" class="visually-hidden-focusable btn cy-btn cy-btn--primary m-2">
        İçeriğe geç
    </a>

    <!-- ================= ÜST MENÜ ================= -->
    <header class="cy-nav">
        <div class="container">
            <div class="cy-nav__inner">

                <a class="cy-brand text-decoration-none" href="index.php">
                    <span class="cy-brand__mark">
                        <img src="<?= e(site_logo_url()) ?>" alt="<?= e($siteAdi) ?> logosu">
                    </span>
                    <div>
                        <span class="cy-brand__title d-block"><?= e($siteAdi) ?></span>
                        <?php $slogan = (string) setting('site_slogan', ''); ?>
                        <?php if ($slogan !== ''): ?>
                            <span class="cy-brand__subtitle d-none d-sm-block"><?= e($slogan) ?></span>
                        <?php endif; ?>
                    </div>
                </a>

                <!-- Mobil menü düğmesi -->
                <button class="cy-nav__toggle" type="button"
                        id="menu_toggle"
                        aria-expanded="false" aria-controls="site_menu" aria-label="Menüyü aç/kapat">
                    &#9776;
                </button>

                <ul class="cy-nav__links" id="site_menu">
                    <?php foreach ($siteMenu as $item): ?>
                        <?php
                        // Görünürlük kuralı: null → herkes, true → üyeler, false → misafirler
                        if ($item['oturum'] === true  && $aktifKullanici === null) { continue; }
                        if ($item['oturum'] === false && $aktifKullanici !== null) { continue; }
                        ?>
                        <li>
                            <a href="<?= e($item['url']) ?>"
                               class="cy-nav__link <?= $aktifSayfa === $item['anahtar'] ? 'cy-nav__link--aktif' : '' ?>"
                               <?= $aktifSayfa === $item['anahtar'] ? 'aria-current="page"' : '' ?>>
                                <?= e($item['baslik']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>

                    <li class="cy-nav__actions">
                        <!-- Tema düğmesi -->
                        <button type="button" class="cy-theme-toggle" id="tema_dugmesi"
                                title="Açık / koyu tema" aria-label="Açık veya koyu temaya geç">
                            <span aria-hidden="true">◐</span>
                        </button>

                        <?php if ($aktifKullanici !== null): ?>
                            <!-- ---------- OTURUM AÇIK ---------- -->
                            <div class="dropdown">
                                <button class="cy-usermenu" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php $avatar = avatar_url($aktifKullanici); ?>
                                    <?php if ($avatar !== ''): ?>
                                        <img src="<?= e($avatar) ?>" class="cy-avatar cy-avatar--circle" alt="">
                                    <?php else: ?>
                                        <span class="cy-avatar cy-avatar--initial cy-avatar--circle"><?= e(user_initials($aktifKullanici)) ?></span>
                                    <?php endif; ?>
                                    <span class="d-none d-sm-inline"><?= e($aktifKullanici['ad']) ?></span>
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li class="dropdown-header">
                                        <?= e($aktifKullanici['ad'] . ' ' . $aktifKullanici['soyad']) ?><br>
                                        <?= e(auth_role_labels()[$aktifKullanici['rol']] ?? $aktifKullanici['rol']) ?>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="hesabim.php">👤 Hesabım</a></li>

                                    <?php if (auth_at_least('editor')): ?>
                                        <li><a class="dropdown-item" href="yonetim/index.php">⚙️ Yönetim Paneli</a></li>
                                    <?php endif; ?>

                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <!-- Çıkış POST ile yapılır: bir <img src="cikis.php"> etiketi
                                             bile GET isteği tetikleyebilir ve sizi habersiz çıkarabilirdi. -->
                                        <form method="post" action="cikis.php" class="px-1">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <button type="submit" class="dropdown-item">🚪 Çıkış Yap</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>

                        <?php else: ?>
                            <!-- ---------- OTURUM KAPALI ---------- -->
                            <a href="giris.php" class="btn cy-btn btn-outline-secondary btn-sm">Giriş Yap</a>
                            <?php if ($kayitAcik): ?>
                                <a href="kayit.php" class="btn cy-btn cy-btn--primary btn-sm">Kayıt Ol</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main id="icerik">
