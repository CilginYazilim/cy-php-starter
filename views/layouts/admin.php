<?php
/**
 * =====================================================================
 *  DÜZEN: Yönetim Paneli
 * =====================================================================
 */

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\PanelNotices;
use App\Core\Setting;
use App\Core\Url;
use App\Core\View;

$theme     = resolve_theme();
$collapsed = ($_COOKIE['cy_sidebar'] ?? '') === 'collapsed';

$pageTitle    = $title ?? 'Panel';
$pageSubtitle = $subtitle ?? null;
$flashes      = Flash::pull();
?>
<!DOCTYPE html>
<html lang="tr"<?= $theme !== '' ? ' data-cy-theme="' . e($theme) . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="author" content="<?= e(site_brand()) ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="cy-base" content="<?= e(url('__PATH__')) ?>">

    <?php /* Liste tablolarının varsayılan sayfa boyutu (Ayarlar → Sistem). */ ?>
    <meta name="cy-page-length" content="<?= (int) Setting::get('sistem_sayfa_basina', '10') ?>">

    <title><?= e($pageTitle) ?> · <?= e($appName ?? 'Panel') ?></title>

    <?php View::partial('partials/pwa-head'); ?>

    <?php /* Panelde de sitenin faviconu görünür: iki sekme arasında
             gidip gelirken aynı simgeyi görmek yön duygusunu korur. */ ?>
    <link rel="icon" type="image/png" href="<?= e(Setting::faviconUrl()) ?>">
    <link rel="apple-touch-icon" href="<?= e(Setting::faviconUrl()) ?>">

    <link rel="stylesheet" href="<?= e(asset('css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/dataTables.bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/cilginyazilim.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">

    <?php /* Ayarlar → Sistem → Tema Rengi. Varsayılan renkte hiçbir şey basılmaz. */ ?>
    <?= App\Core\Theme::styleTag() ?>
</head>

<body class="cy-app<?= $collapsed ? ' is-collapsed' : '' ?>" data-cy-auth="1">

    <a href="#cy-content" class="cy-skip">İçeriğe geç</a>

    <div class="cy-shell">
        <?php View::partial('partials/sidebar'); ?>

        <div class="cy-backdrop" id="cy_backdrop" hidden></div>

        <div class="cy-main">
            <?php View::partial('partials/topbar', [
                'pageTitle'    => $pageTitle,
                'pageSubtitle' => $pageSubtitle,
            ]); ?>

            <main class="cy-content" id="cy-content">
                <?php /* Yönetici uyarıları (debug açık + yerel değil, bekleyen
                         migration, APP_KEY boş…). Bkz. App\Core\PanelNotices.
                         Hepsi TEK bir kutuda alt alta: her biri ayrı renkli
                         şerit olunca sayfanın üstü bir uyarı yığınına dönüyordu. */ ?>
                <?php
                $uyarilar = array_values(array_filter(
                    PanelNotices::forCurrentUser(),
                    static fn (array $u): bool => !PanelNotices::dismissed($u)
                ));
                $uyariIkon = ['danger' => 'alert', 'warning' => 'alert', 'info' => 'info', 'success' => 'check'];
                ?>
                <?php if ($uyarilar !== []): ?>
                    <div class="cy-notices" role="region" aria-label="Bildirimler">
                        <?php foreach ($uyarilar as $uyari): ?>
                            <div class="cy-notice cy-notice--<?= e($uyari['tur']) ?>" data-notice="<?= e($uyari['id']) ?>"<?= $uyari['tur'] === 'danger' ? ' role="alert"' : '' ?>>
                                <span class="cy-notice__icon"><?= icon($uyariIkon[$uyari['tur']] ?? 'info', 'cy-icon cy-icon--sm') ?></span>
                                <p class="cy-notice__text">
                                    <strong><?= e($uyari['baslik']) ?></strong>
                                    <span><?= e($uyari['metin']) ?></span>
                                </p>
                                <?php if ($uyari['yol'] !== ''): ?>
                                    <a href="<?= e(url($uyari['yol'])) ?>" class="cy-notice__action"><?= e($uyari['baglanti']) ?> →</a>
                                <?php endif; ?>
                                <?php if ($uyari['kapatilabilir']): ?>
                                    <button type="button" class="cy-notice__close js-notice-close" aria-label="Bildirimi kapat" title="Bu oturumda gösterme">
                                        <?= icon('close', 'cy-icon cy-icon--sm') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?= $content ?? '' ?>
            </main>

            <footer class="cy-footer">
                <?= date('Y') ?> · <?= e(site_brand()) ?> ·
                <a href="<?= e(url('')) ?>" target="_blank" rel="noopener">Siteyi görüntüle</a>
            </footer>
        </div>
    </div>

    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="cy_toasts"></div>

    <script type="application/json" id="cy_flash"><?= json_encode($flashes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <script src="<?= e(asset('js/jquery-3.7.0.min.js')) ?>"></script>
    <script src="<?= e(asset('js/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('js/jquery.dataTables.min.js')) ?>"></script>
    <script src="<?= e(asset('js/dataTables.bootstrap5.min.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>

    <?php foreach (($scripts ?? []) as $script): ?>
        <script src="<?= e(asset('js/' . $script)) ?>"></script>
    <?php endforeach; ?>

    <?php /* PWA KAPALIYKEN DE YÜKLENİR — bilerek. Betik <head> içindeki
             "cy-sw" etiketine bakar: etiket yoksa daha önce kaydedilmiş
             servis çalışanını SİLER. Koşula bağlasaydık ayarı kapatmak
             siteyi daha önce ziyaret etmiş tarayıcılarda hiçbir şeyi
             değiştirmez, sayfalar eski önbellekten gelmeye devam ederdi. */ ?>
    <script src="<?= e(asset('js/pwa.js')) ?>"></script>
</body>
</html>
