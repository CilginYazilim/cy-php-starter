<?php
/**
 * =====================================================================
 *  GİRİŞ SAYFASI
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Kullanıcı e-posta VEYA kullanıcı adı ile giriş yapabilir.
 *  Asıl doğrulama mantığı system/auth.php içindeki auth_login()
 *  fonksiyonundadır; bu dosya sadece formu gösterir.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/system/config.php';

// Zaten giriş yapmışsa formu göstermenin anlamı yok.
if (auth_check()) {
    header('Location: yonetim/index.php');
    exit;
}

$csrfToken = csrf_token();
$error     = null;
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Oturum doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password   = (string) ($_POST['password'] ?? '');

        [$ok, $message] = auth_login($db, $identifier, $password);

        if ($ok) {
            /* Kullanıcı korumalı bir sayfaya girmeye çalışıp buraya
             * yönlendirildiyse, giriş sonrası oraya geri götür.
             * Açık yönlendirme (open redirect) açığı oluşmasın diye
             * SADECE kendi sitemizdeki göreli adresleri kabul ediyoruz. */
            $redirect = (string) ($_SESSION['auth_redirect'] ?? '');
            unset($_SESSION['auth_redirect']);

            $isSafeRedirect = $redirect !== ''
                && !str_starts_with($redirect, '//')
                && !preg_match('~^[a-z][a-z0-9+.-]*://~i', $redirect);

            header('Location: ' . ($isSafeRedirect ? $redirect : 'yonetim/index.php'));
            exit;
        }

        $error = $message;
    }
}

$siteName = (string) setting('site_adi', APP_NAME);
?>
<!DOCTYPE html>
<html lang="<?= e((string) setting('site_dil', 'tr')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Giriş Yap | <?= e($siteName) ?></title>
    <link rel="icon" type="image/png" href="<?= e(site_logo_url()) ?>">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/cilginyazilim.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="cy-app">
    <div class="cy-topbar"></div>

    <div class="container py-5" style="max-width: 440px;">

        <div class="text-center mb-4">
            <img src="<?= e(site_logo_url()) ?>" alt="<?= e($siteName) ?>"
                 style="width:64px;height:64px;object-fit:contain;background:#fff;border-radius:50%;padding:8px;box-shadow:0 4px 12px rgba(6,19,33,.15)">
            <h1 class="h5 mt-3 mb-1"><?= e($siteName) ?></h1>
            <p class="cy-muted small mb-0">Devam etmek için giriş yapın</p>
        </div>

        <div class="cy-card">
            <div class="cy-card__body">

                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="identifier" class="form-label">E-posta veya Kullanıcı Adı</label>
                        <input type="text" name="identifier" id="identifier" class="form-control"
                               autocomplete="username" autofocus
                               value="<?= e($identifier) ?>">
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Parola</label>
                        <input type="password" name="password" id="password" class="form-control"
                               autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn cy-btn cy-btn--primary w-100">Giriş Yap</button>
                </form>

            </div>
        </div>

        <p class="cy-footer-note mt-4 mb-0">
            <a href="index.php">← Siteye dön</a>
        </p>
    </div>
</body>
</html>
