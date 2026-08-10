<?php
/**
 * =====================================================================
 *  DÜZEN: Ön yüz (ziyaretçi sitesi)
 * ---------------------------------------------------------------------
 *  Ana sayfa, Hakkımızda, İletişim, Giriş ve Kayıt sayfaları bu düzeni
 *  paylaşır: ortak üst menü + alt bilgi.
 * =====================================================================
 */

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Setting;
use App\Core\View;

$theme   = resolve_theme();
$flashes = Flash::pull();

$siteAdi = Setting::get('site_adi', $appName ?? 'Yeni Proje');

/* BAŞLIK ŞABLONU (Ayarlar → SEO). "%sayfa% · %site%" gibi bir kalıp
 * beklenir. Ana sayfada ($title boş) yalnızca site adı basılır:
 * "· Site Adı" diye başlayan bir sekme başlığı bozuk görünürdü. */
$baslikSablonu = Setting::get('seo_baslik_sablonu', '%sayfa% · %site%');

$pageTitle = ($title ?? '') !== ''
    ? str_replace(['%sayfa%', '%site%'], [$title, $siteAdi], $baslikSablonu)
    : $siteAdi;

/* Dil, panelden değiştirilebilir (Ayarlar → Genel). Beklenmedik bir
 * değer HTML'e girmesin diye iki harfe indirgiyoruz. */
$siteDil = preg_replace('/[^a-z]/', '', strtolower(Setting::get('site_dil', 'tr'))) ?: 'tr';
$siteDil = substr($siteDil, 0, 5);
?>
<!DOCTYPE html>
<html lang="<?= e($siteDil) ?>"<?= $theme !== '' ? ' data-cy-theme="' . e($theme) . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php
    /* SEO / paylaşım etiketleri.
       Sayfa başlığı ve açıklaması ayarlardan gelir; bir sayfa kendi
       $ogAciklama değerini vererek bunu geçersiz kılabilir. */
    $ogBaslik   = $pageTitle;
    $ogAciklama = $ogAciklama ?? Setting::get('site_aciklama', '');
    /* shareImage() köke göreli bir adres döner ve TABAN YOLUNU ZATEN
     * içerir ("/proje/assets/images/logo.png"). Url::absolute() ise
     * kendisi taban yolu ekler; ikisini birleştirmek yolu iki kez
     * yazıp paylaşım görselini kırıyordu. Yalnızca şema + alan adı
     * eklemek yeterli — tam adres verilmişse ona hiç dokunmuyoruz. */
    $paylasim   = Setting::shareImage();
    $ogGorsel   = str_starts_with($paylasim, 'http')
        ? $paylasim
        : App\Core\Url::origin() . '/' . ltrim($paylasim, '/');
    $kanonik    = App\Core\Url::absolute(App\Core\Url::current());
    ?>
    <meta name="description" content="<?= e($ogAciklama) ?>">
    <?php if (($anahtarKelimeler = Setting::get('seo_anahtar_kelimeler')) !== ''): ?>
        <meta name="keywords" content="<?= e($anahtarKelimeler) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($kanonik) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteAdi) ?>">
    <meta property="og:title" content="<?= e($ogBaslik) ?>">
    <meta property="og:description" content="<?= e($ogAciklama) ?>">
    <meta property="og:url" content="<?= e($kanonik) ?>">
    <meta property="og:image" content="<?= e($ogGorsel) ?>">
    <meta property="og:locale" content="tr_TR">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($ogBaslik) ?>">
    <meta name="twitter:description" content="<?= e($ogAciklama) ?>">
    <meta name="twitter:image" content="<?= e($ogGorsel) ?>">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="cy-base" content="<?= e(url('__PATH__')) ?>">
    <?php if (!Setting::bool('seo_indeksleme', true)): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>

    <?php if (($googleDogrulama = Setting::get('seo_google_dogrulama')) !== ''): ?>
        <meta name="google-site-verification" content="<?= e($googleDogrulama) ?>">
    <?php endif; ?>

    <title><?= e($pageTitle) ?></title>

    <?php View::partial('partials/pwa-head'); ?>

    <?php /* Favicon panelden değiştirilebilir (Ayarlar → Genel → Site Favicon). */ ?>
    <link rel="icon" type="image/png" href="<?= e(Setting::faviconUrl()) ?>">
    <link rel="apple-touch-icon" href="<?= e(Setting::faviconUrl()) ?>">

    <link rel="stylesheet" href="<?= e(asset('css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/cilginyazilim.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site-sections.css')) ?>">

    <?php /* Ayarlar → Sistem → Tema Rengi. Varsayılan renkte hiçbir şey basılmaz. */ ?>
    <?= App\Core\Theme::styleTag() ?>

    <?php
    /* ANALYTICS KODU (Ayarlar → SEO)
     *
     * Yöneticinin yapıştırdığı HTML olduğu gibi basılır — kaçışlamak
     * <script> etiketini metne çevirir ve kod hiç çalışmaz. Bu ayara
     * yalnızca "settings.manage" yetkisi olan kişi dokunabilir; yani
     * zaten siteye kod ekleyebilecek biridir.
     *
     * İçerik Güvenliği Politikası bu kodu ENGELLEMEZ: gereken
     * adresler ve satır içi betik özetleri otomatik tanıtılır
     * (bkz. app/Core/Response.php → analyticsSources).
     *
     * Yalnızca ön yüzde basılır; panelde izleme kodu çalıştırmanın
     * anlamı yok (noindex, nofollow zaten). */
    $analytics = trim(Setting::get('seo_analytics'));
    ?>
    <?php if ($analytics !== ''): ?>
        <?= $analytics ?>
    <?php endif; ?>
</head>
<body class="cy-app cy-site"<?= ($currentUser ?? null) !== null ? ' data-cy-auth="1"' : '' ?>>

    <?php View::partial('partials/site-nav'); ?>

    <?php
    /* GİRİŞ SAYFASI BAKIM ÖRTÜSÜNDEN MUAFTIR.
     *
     * Örtü "panele girebilenler hariç herkes" için çiziliyordu; ama
     * bakım modunu kapatabilecek kişi de siteye tam olarak buradan
     * giriyor. Sonuç: bakım modunu açan yönetici kendini dışarıda
     * bırakıp yalnızca veritabanından geri dönebiliyordu. */
    $bakimOrtusu = Setting::bool('sistem_bakim_modu', false)
        && !can('dashboard.view')
        && !App\Core\Url::isCurrent('giris');
    ?>
    <?php if ($bakimOrtusu): ?>
        <?php
        /* Görünüm tamponlanarak üretildiği için başlık göndermek hâlâ
         * mümkün. 200 ile "bakımdayız" basmak arama motorlarına bunun
         * sayfanın YENİ İÇERİĞİ olduğunu söyler ve gerçek sayfalar
         * dizinden düşer; 503 + Retry-After "geçici, sonra uğra" der. */
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3600');
        }
        ?>
        <div class="cy-maintenance">
            <div class="cy-maintenance__box">
                <?= icon('settings', 'cy-icon cy-icon--lg') ?>
                <h1>Bakım Çalışması</h1>
                <p>Site şu anda bakımda. Kısa süre içinde geri döneceğiz.</p>
            </div>
        </div>
    <?php else: ?>
        <main>
            <?= $content ?? '' ?>
        </main>
    <?php endif; ?>

    <?php View::partial('partials/site-footer'); ?>

    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="cy_toasts"></div>

    <script type="application/json" id="cy_flash"><?= json_encode($flashes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <script src="<?= e(asset('js/jquery-3.7.0.js')) ?>"></script>
    <script src="<?= e(asset('js/bootstrap.bundle.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>

    <?php foreach (($scripts ?? []) as $script): ?>
        <script src="<?= e(asset('js/' . $script)) ?>"></script>
    <?php endforeach; ?>

    <?php /* PWA KAPALIYKEN DE YÜKLENİR — bkz. layouts/admin.php: betiğin
             ikinci işi, kapatıldığında geride kalan servis çalışanını
             ve önbelleğini temizlemektir. */ ?>
    <script src="<?= e(asset('js/pwa.js')) ?>"></script>
</body>
</html>
