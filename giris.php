<?php
/**
 * =====================================================================
 *  GİRİŞ SAYFASI
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Kullanıcı e-posta VEYA kullanıcı adı ile giriş yapabilir.
 *  Asıl doğrulama mantığı system/auth.php içindeki auth_login()
 *  fonksiyonundadır; bu dosya sadece formu gösterir.
 *
 *  NEDEN AJAX DEĞİL?
 *  Giriş formu bilerek klasik POST ile çalışır. Tarayıcının parola
 *  yöneticisi ancak gerçek bir form gönderimini görünce "parolayı
 *  kaydedeyim mi?" diye sorar; AJAX'ta bu davranış kaybolur.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/system/config.php';

/* ---------------------------------------------------------------------
 *  FORM İŞLEME — ÇIKTIDAN ÖNCE
 * ---------------------------------------------------------------------
 *  header('Location: ...') çağrısı, tarayıcıya tek bir bayt bile
 *  gönderilmeden ÖNCE yapılmalıdır. Bu yüzden tüm işleme mantığı
 *  _ust.php'yi (yani HTML çıktısını) yüklemeden önce burada durur.
 * ------------------------------------------------------------------ */
if (auth_check()) {
    // Zaten giriş yapmışsa formu göstermenin anlamı yok.
    header('Location: index.php');
    exit;
}

$hata       = null;
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $hata = 'Oturum doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password   = (string) ($_POST['password'] ?? '');

        [$ok, $mesaj] = auth_login($db, $identifier, $password);

        if ($ok) {
            /* Kullanıcı korumalı bir sayfaya girmeye çalışıp buraya
             * yönlendirildiyse, giriş sonrası oraya geri götür.
             * Açık yönlendirme (open redirect) açığı oluşmasın diye
             * SADECE kendi sitemizdeki göreli adresleri kabul ediyoruz:
             *   "//kotusite.com"  → reddedilir (protokole göreli adres)
             *   "https://..."     → reddedilir (mutlak adres)
             *   "yonetim/index.php" → kabul */
            $redirect = (string) ($_SESSION['auth_redirect'] ?? '');
            unset($_SESSION['auth_redirect']);

            $guvenli = $redirect !== ''
                && !str_starts_with($redirect, '//')
                && !preg_match('~^[a-z][a-z0-9+.-]*://~i', $redirect);

            // Yetkisi varsa panele, yoksa hesabına gitsin.
            $varsayilan = auth_at_least('editor') ? 'yonetim/index.php' : 'hesabim.php';

            header('Location: ' . ($guvenli ? $redirect : $varsayilan));
            exit;
        }

        $hata = $mesaj;
    }
}

$sayfaBaslik   = 'Giriş Yap';
$aktifSayfa    = 'giris';
$sayfaAciklama = 'Hesabınıza giriş yapın.';

require __DIR__ . '/_ust.php';
?>

<div class="container cy-auth">

    <div class="text-center mb-4">
        <img src="<?= e(site_logo_url()) ?>" alt="<?= e($siteAdi) ?>"
             style="width:64px;height:64px;object-fit:contain;background:#fff;border-radius:50%;padding:8px;box-shadow:var(--cy-shadow)">
        <h1 class="h4 mt-3 mb-1">Tekrar hoş geldiniz</h1>
        <p class="cy-muted small mb-0">Devam etmek için giriş yapın</p>
    </div>

    <div class="cy-card">
        <div class="cy-card__body">

            <?php if ($hata !== null): ?>
                <div class="alert alert-danger" role="alert"><?= e($hata) ?></div>
            <?php endif; ?>

            <?php if (($_GET['kayit'] ?? '') === 'tamam'): ?>
                <div class="alert alert-success" role="alert">
                    Hesabınız oluşturuldu. Şimdi giriş yapabilirsiniz.
                </div>
            <?php endif; ?>

            <?php if (($_GET['cikis'] ?? '') === 'tamam'): ?>
                <div class="alert alert-primary" role="alert">
                    Oturumunuz güvenle kapatıldı.
                </div>
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

            <?php if ($kayitAcik): ?>
                <div class="cy-divider">hesabınız yok mu?</div>
                <a href="kayit.php" class="btn btn-outline-secondary cy-btn w-100">Ücretsiz Kayıt Ol</a>
            <?php endif; ?>

        </div>
    </div>

    <p class="cy-footer-note mt-4 mb-0 text-center">
        <a href="index.php">← Siteye dön</a>
    </p>
</div>

<?php require __DIR__ . '/_alt.php'; ?>
