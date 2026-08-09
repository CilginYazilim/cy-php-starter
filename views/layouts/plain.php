<?php
/**
 * DÜZEN: Sade sayfa — hata sayfaları için. Menü ve veritabanı
 * gerektirmez; bağlantı koptuğunda bile çalışabilmelidir.
 */
$theme = ($_COOKIE['cy_theme'] ?? '') === 'dark' ? 'dark'
       : ((($_COOKIE['cy_theme'] ?? '') === 'light') ? 'light' : '');
?>
<!DOCTYPE html>
<html lang="tr"<?= $theme !== '' ? ' data-cy-theme="' . e($theme) . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Hata') ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/cilginyazilim.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="cy-app">
    <div class="container"><?= $content ?? '' ?></div>
</body>
</html>
