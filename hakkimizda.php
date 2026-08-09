<?php
/**
 * =====================================================================
 *  HAKKIMIZDA (herkese açık)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Sayfanın metni koda gömülü DEĞİLDİR; yönetim panelindeki
 *  "Hakkımızda Metni" ayarından gelir. Böylece içerik değişince
 *  dosya düzenlemek gerekmez.
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik   = 'Hakkımızda';
$aktifSayfa    = 'hakkimizda';
$sayfaAciklama = 'Biz kimiz, ne yapıyoruz?';

require __DIR__ . '/_ust.php';

$hakkinda = trim((string) setting('site_hakkinda', ''));
?>

<!-- ================= SAYFA BAŞLIĞI ================= -->
<div class="cy-page-head">
    <div class="container">
        <nav class="cy-breadcrumb" aria-label="Konum">
            <a href="index.php">Ana Sayfa</a> <span aria-hidden="true">›</span> Hakkımızda
        </nav>
        <h1>Hakkımızda</h1>
        <p><?= e((string) setting('site_aciklama', APP_DESCRIPTION)) ?></p>
    </div>
</div>

<section class="cy-section container">
    <div class="row g-5">

        <!-- ---------- Metin ---------- -->
        <div class="col-lg-7">
            <?php if ($hakkinda !== ''): ?>
                <?php
                /* nl2br(e(...)) SIRASI ÖNEMLİDİR:
                 *   1) önce e()   → metindeki HTML etkisiz hale gelir
                 *   2) sonra nl2br → satır sonları <br> olur
                 * Ters sırada yazsaydık, e() bizim eklediğimiz <br>
                 * etiketlerini de kaçışlar ve ekranda "&lt;br&gt;" görünürdü. */
                ?>
                <div style="font-size:1.03rem; line-height:1.8;">
                    <?= nl2br(e($hakkinda)) ?>
                </div>
            <?php else: ?>
                <!-- Ayar boşsa örnek metin: sayfa hiçbir zaman boş görünmesin. -->
                <div style="font-size:1.03rem; line-height:1.8;">
                    <p>
                        Bu alan yönetim panelindeki <strong>Genel &rsaquo; Hakkımızda Metni</strong>
                        ayarından doldurulur. Şu anda örnek metin görüyorsunuz.
                    </p>
                    <p>
                        Çılgın Yazılım PHP Başlangıç Şablonu; kurulum sihirbazı,
                        oturum yönetimi, rol tabanlı yetkilendirme, veritabanı
                        destekli ayarlar ve hazır bir tasarım kalıbıyla gelir.
                        Amacı, her yeni projede aynı işleri baştan yazmayı
                        bırakıp doğrudan kendi işinize odaklanmanızdır.
                    </p>
                </div>

                <?php if (is_admin()): ?>
                    <div class="alert alert-primary mt-4" role="alert">
                        <strong>Yönetici notu:</strong> Bu metni değiştirmek için
                        <a href="yonetim/ayarlar.php">Ayarlar &rsaquo; Genel</a>
                        bölümünü kullanın.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- ---------- Yan bilgi kartı ---------- -->
        <div class="col-lg-5">
            <div class="cy-card">
                <div class="cy-card__header">
                    <h2 class="cy-brand__title mb-0">Künye</h2>
                </div>
                <div class="cy-card__body">
                    <dl class="cy-detail mb-0">
                        <dt>Site</dt>
                        <dd><?= e((string) setting('site_adi', APP_NAME)) ?></dd>

                        <?php $siteUrl = (string) setting('site_url', ''); ?>
                        <?php if ($siteUrl !== ''): ?>
                            <dt>Adres</dt>
                            <dd><a href="<?= e($siteUrl) ?>" rel="noopener"><?= e($siteUrl) ?></a></dd>
                        <?php endif; ?>

                        <?php $eposta = (string) setting('iletisim_eposta', ''); ?>
                        <?php if ($eposta !== ''): ?>
                            <dt>E-posta</dt>
                            <dd><a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a></dd>
                        <?php endif; ?>

                        <?php $telefon = (string) setting('iletisim_telefon', ''); ?>
                        <?php if ($telefon !== ''): ?>
                            <dt>Telefon</dt>
                            <dd><?= e($telefon) ?></dd>
                        <?php endif; ?>

                        <?php $saatler = (string) setting('iletisim_saatler', ''); ?>
                        <?php if ($saatler !== ''): ?>
                            <dt>Çalışma saatleri</dt>
                            <dd><?= e($saatler) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
                <div class="cy-card__footer">
                    <a href="iletisim.php" class="btn cy-btn cy-btn--primary btn-sm">İletişime Geç →</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/_alt.php'; ?>
