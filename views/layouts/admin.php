<?php
/**
 * =====================================================================
 *  DÜZEN: Yönetim Paneli
 * =====================================================================
 */

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;

$theme     = ($_COOKIE['cy_theme'] ?? '') === 'dark' ? 'dark'
           : ((($_COOKIE['cy_theme'] ?? '') === 'light') ? 'light' : '');
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
    <meta name="author" content="Çılgın Yazılım - cilginyazilim.com">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="cy-base" content="<?= e(url('__PATH__')) ?>">

    <title><?= e($pageTitle) ?> · <?= e($appName ?? 'Panel') ?></title>

    <link rel="icon" type="image/png" href="<?= e(asset('images/logo.png')) ?>">

    <link rel="stylesheet" href="<?= e(asset('css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/dataTables.bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/cilginyazilim.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>

<body class="cy-app<?= $collapsed ? ' is-collapsed' : '' ?>">

    <a href="#cy-content" class="cy-sr-only">İçeriğe geç</a>

    <div class="cy-shell">
        <?php View::partial('partials/sidebar'); ?>

        <div class="cy-backdrop" id="cy_backdrop" hidden></div>

        <div class="cy-main">
            <?php View::partial('partials/topbar', [
                'pageTitle'    => $pageTitle,
                'pageSubtitle' => $pageSubtitle,
            ]); ?>

            <main class="cy-content" id="cy-content">
                <?= $content ?? '' ?>
            </main>

            <footer class="cy-footer">
                <?= date('Y') ?> · <?= e($appBrand ?? 'Çılgın Yazılım') ?> ·
                <a href="<?= e(url('')) ?>" target="_blank" rel="noopener">Siteyi görüntüle</a>
            </footer>
        </div>
    </div>

    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="cy_toasts"></div>

    <script type="application/json" id="cy_flash"><?= json_encode($flashes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <script src="<?= e(asset('js/jquery-3.7.0.js')) ?>"></script>
    <script src="<?= e(asset('js/bootstrap.bundle.js')) ?>"></script>
    <script src="<?= e(asset('js/jquery.dataTables.min.js')) ?>"></script>
    <script src="<?= e(asset('js/dataTables.bootstrap5.min.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>

    <?php foreach (($scripts ?? []) as $script): ?>
        <script src="<?= e(asset('js/' . $script)) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
