<?php
/**
 * =====================================================================
 *  ANA SAYFA (herkese açık)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Bu sayfa iki iş yapar:
 *    1. Kurulumun doğru çalıştığını gösterir (ayarlar okunuyor mu?)
 *    2. Tasarım kalıbındaki hazır bileşenleri örnekler
 *
 *  ► Yeni projeye başlarken: "BİLEŞEN GALERİSİ" bölümünü silip
 *    yerine kendi içeriğinizi yazın. İskelet (başlık, ayarlar,
 *    oturum durumu, toast) olduğu gibi kalsın.
 * =====================================================================
 */

declare(strict_types=1);

// config.php; function.php, settings.php ve auth.php'yi de yükler
// ve ayarları veritabanından okuyup önbelleğe alır.
require __DIR__ . '/system/config.php';

$csrfToken = csrf_token();
$siteAdi   = (string) setting('site_adi', APP_NAME);
$aktif     = auth_user($db);   // Giriş yoksa null

/* BAKIM MODU
 * Ayarlardan açıldıysa, giriş yapmamış ziyaretçilere kapalıyız.
 * Yöneticiler siteyi normal görmeye devam eder ki düzenleme
 * yapabilsinler. */
if (setting_bool('sistem_bakim_modu') && !auth_at_least('editor')) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="<?= e((string) setting('site_dil', 'tr')) ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bakım çalışması | <?= e($siteAdi) ?></title>
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
?>
<!DOCTYPE html>
<html lang="<?= e((string) setting('site_dil', 'tr')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Çılgın Yazılım - cilginyazilim.com">
    <meta name="description" content="<?= e((string) setting('site_aciklama', APP_DESCRIPTION)) ?>">
    <meta name="keywords" content="<?= e((string) setting('seo_anahtar_kelimeler', '')) ?>">
    <?php if (!setting_bool('seo_indeksleme', true)): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">

    <title><?= e($siteAdi) ?></title>

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
</head>

<body class="cy-app">
    <div class="cy-topbar"></div>

    <div class="container py-2 py-lg-2">
        <div class="cy-card">

            <!-- ---------- Başlık ---------- -->
            <div class="cy-card__header">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                    <div class="cy-brand">
                        <span class="cy-brand__mark">
                            <img src="<?= e(site_logo_url()) ?>" alt="<?= e($siteAdi) ?> logosu">
                        </span>
                        <div>
                            <h1 class="cy-brand__title"><?= e($siteAdi) ?></h1>
                            <p class="cy-brand__subtitle">
                                <?= e((string) setting('site_slogan', (string) setting('site_aciklama', ''))) ?>
                            </p>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($aktif !== null): ?>
                            <span class="cy-badge cy-badge--glass">
                                <?= e($aktif['ad'] . ' ' . $aktif['soyad']) ?>
                            </span>
                            <a href="yonetim/index.php" class="btn cy-btn cy-btn--onbrand">Yönetim Paneli</a>
                        <?php else: ?>
                            <a href="giris.php" class="btn cy-btn cy-btn--onbrand">Giriş Yap</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ---------- Gövde ---------- -->
            <div class="cy-card__body">

                <!-- =======================================================
                     BİLEŞEN GALERİSİ
                     Yeni projeye başlarken bu bölümü silin.
                     ======================================================= -->

                <div class="alert alert-primary" role="alert">
                    <strong>Şablon hazır ve kurulu.</strong>
                    Bu sayfadaki site adı, açıklama ve tema rengi doğrudan
                    <code>ayarlar</code> tablosundan geliyor. Değiştirmek için
                    <a href="yonetim/ayarlar.php">yönetim panelindeki ayarlar</a>
                    sayfasını kullanın — kod düzenlemenize gerek yok.
                </div>

                <h2 class="h6 text-uppercase cy-muted mt-4 mb-3">Ayarlardan Gelen Değerler</h2>
                <dl class="cy-detail mb-4">
                    <dt>Site adı</dt>
                    <dd><?= e((string) setting('site_adi', '—')) ?></dd>
                    <dt>Açıklama</dt>
                    <dd><?= e((string) setting('site_aciklama', '—')) ?></dd>
                    <dt>İletişim</dt>
                    <dd><?= e((string) setting('iletisim_eposta', 'Tanımlanmamış')) ?></dd>
                    <dt>Bakım modu</dt>
                    <dd><?= setting_bool('sistem_bakim_modu') ? 'Açık' : 'Kapalı' ?></dd>
                </dl>

                <h2 class="h6 text-uppercase cy-muted mb-3">Butonlar</h2>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button class="btn cy-btn cy-btn--primary">Ana Eylem</button>
                    <button class="btn btn-outline-secondary cy-btn">İkincil</button>
                    <button class="btn btn-danger cy-btn">Tehlikeli</button>
                    <span class="cy-actions ms-2">
                        <button class="cy-btn-icon cy-btn-icon--view" title="Görüntüle">&#128065;</button>
                        <button class="cy-btn-icon cy-btn-icon--edit" title="Düzenle">&#9998;</button>
                        <button class="cy-btn-icon cy-btn-icon--delete" title="Sil">&#128465;</button>
                    </span>
                </div>

                <h2 class="h6 text-uppercase cy-muted mb-3">Rozetler ve Avatarlar</h2>
                <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                    <span class="cy-badge cy-badge--soft">Yumuşak Rozet</span>
                    <span class="cy-avatar cy-avatar--initial">Ç</span>
                    <img src="<?= e(site_logo_url()) ?>" class="cy-avatar" alt="Örnek avatar">
                </div>

                <h2 class="h6 text-uppercase cy-muted mb-3">Bildirim</h2>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-secondary cy-btn js-toast" data-type="success">Başarı</button>
                    <button class="btn btn-outline-secondary cy-btn js-toast" data-type="danger">Hata</button>
                </div>

                <!-- ================= GALERİ SONU ================= -->
            </div>

            <div class="cy-card__footer d-flex flex-wrap justify-content-between gap-2">
                <span><?= e($siteAdi) ?></span>
                <span>Çılgın Yazılım PHP Başlangıç Şablonu</span>
            </div>
        </div>

        <!-- Sosyal medya bağlantıları (sadece doldurulmuş olanlar görünür) -->
        <?php
        $sosyal = [
            'Facebook'  => (string) setting('sosyal_facebook', ''),
            'X'         => (string) setting('sosyal_x', ''),
            'Instagram' => (string) setting('sosyal_instagram', ''),
            'LinkedIn'  => (string) setting('sosyal_linkedin', ''),
            'YouTube'   => (string) setting('sosyal_youtube', ''),
            'GitHub'    => (string) setting('sosyal_github', ''),
        ];
        $sosyal = array_filter($sosyal);
        ?>
        <?php if ($sosyal !== []): ?>
            <p class="cy-footer-note mt-4 mb-0">
                <?php foreach ($sosyal as $ad => $url): ?>
                    <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="me-2"><?= e($ad) ?></a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <p class="cy-footer-note mt-3 mb-0">
            <a href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a>
        </p>
    </div>

    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="toast_container"></div>

    <script src="assets/js/jquery-3.7.0.js"></script>
    <script src="assets/js/bootstrap.bundle.js"></script>

    <script>
    $(function () {
        'use strict';

        /** Sağ üstte geçici bildirim gösterir. */
        function notify(message, type) {
            type = type || 'success';

            var $toast = $(
                '<div class="toast cy-toast cy-toast--' + type + '" role="alert" aria-live="assertive" aria-atomic="true">' +
                    '<div class="d-flex">' +
                        '<div class="toast-body"></div>' +
                        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Kapat"></button>' +
                    '</div>' +
                '</div>'
            );

            // .html() değil .text() — mesajdaki HTML çalıştırılmasın (XSS).
            $toast.find('.toast-body').text(message);
            $('#toast_container').append($toast);

            var toast = new bootstrap.Toast($toast[0], { delay: 4000 });
            $toast.on('hidden.bs.toast', function () { $toast.remove(); });
            toast.show();
        }

        // Galeri: bildirim örneği (kendi projenizde silin)
        $('.js-toast').on('click', function () {
            var type = $(this).data('type');
            notify(type === 'danger' ? 'Bir şeyler ters gitti.' : 'İşlem başarıyla tamamlandı.', type);
        });
    });
    </script>

    <?php
    // SEO: Analytics kodu ayarlardan gelir.
    // Bu alan yöneticiye ait olduğu için bilerek kaçışlanmadan basılır;
    // panele sadece güvendiğiniz kişilere admin yetkisi verin.
    $analytics = (string) setting('seo_analytics', '');
    if ($analytics !== '') {
        echo $analytics;
    }
    ?>
</body>
</html>
