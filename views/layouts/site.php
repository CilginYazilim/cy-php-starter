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
$pageTitle = ($title ?? '') !== '' ? ($title . ' · ' . $siteAdi) : $siteAdi;
?>
<!DOCTYPE html>
<html lang="tr"<?= $theme !== '' ? ' data-cy-theme="' . e($theme) . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?= e(Setting::get('site_aciklama', $appName ?? '')) ?>">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="cy-base" content="<?= e(url('__PATH__')) ?>">
    <?php if (!Setting::bool('seo_indeksleme', true)): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>

    <title><?= e($pageTitle) ?></title>

    <link rel="icon" type="image/png" href="<?= e(asset('images/logo.png')) ?>">

    <link rel="stylesheet" href="<?= e(asset('css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/cilginyazilim.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="cy-app cy-site"<?= ($currentUser ?? null) !== null ? ' data-cy-auth="1"' : '' ?>>

    <?php View::partial('partials/site-nav'); ?>

    <?php if (Setting::bool('sistem_bakim_modu', false) && !can('dashboard.view')): ?>
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
</body>
</html>
