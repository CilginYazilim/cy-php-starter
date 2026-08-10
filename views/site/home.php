<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Ana sayfa
 * ---------------------------------------------------------------------
 *  Her bölüm ayarlardan beslenir ve İLGİLİ AYAR BOŞSA HİÇ BASILMAZ.
 *  Yarım dolu bir sayfa, boş bir sayfadan daha kötü görünür.
 *
 *  @var array<int,array<string,string>> $ozellikler, $iletisim, $istatistik
 * =====================================================================
 */

use App\Core\Setting;

$siteAdi  = Setting::get('site_adi', $appName ?? 'Yeni Proje');
$slogan   = Setting::get('site_slogan');
$aciklama = Setting::get('site_aciklama');

// Slogan tanımlıysa onu, değilse açıklamayı kullan.
$girisMetni = $slogan !== '' ? $slogan : $aciklama;

$ozellikler = $ozellikler ?? [];
$iletisim   = $iletisim ?? [];
$istatistik = $istatistik ?? [];
?>

<!-- ================= HERO ================= -->
<section class="cy-hero">
    <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-12 col-lg-7">
                <span class="cy-badge cy-badge--brand mb-3">
                    <?= icon('shield', 'cy-icon cy-icon--sm') ?> Güvenli · Modüler · Sıfır bağımlılık
                </span>

                <h1 class="cy-hero__title"><?= e($siteAdi) ?></h1>

                <?php if ($girisMetni !== ''): ?>
                    <p class="cy-hero__lead"><?= e($girisMetni) ?></p>
                <?php endif; ?>

                <div class="cy-hero__actions">
                    <a class="btn cy-btn cy-btn--primary" href="<?= e(url('iletisim')) ?>">
                        <?= icon('mail', 'cy-icon cy-icon--sm') ?> Bize Ulaşın
                    </a>
                    <a class="btn cy-btn cy-btn--ghost" href="<?= e(url('hakkimizda')) ?>">
                        Hakkımızda <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
                    </a>
                </div>

                <?php if ($istatistik !== []): ?>
                    <div class="cy-hero__stats">
                        <?php foreach ($istatistik as $stat): ?>
                            <div class="cy-hero__stat">
                                <strong><?= e($stat['deger']) ?></strong>
                                <span><?= e($stat['etiket']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-lg-5">
                <div class="cy-hero__visual">
                    <img src="<?= e(Setting::logoUrl()) ?>" alt="<?= e($siteAdi) ?>" class="cy-hero__logo">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================= ÖZELLİKLER ================= -->
<?php if ($ozellikler !== []): ?>
    <section class="cy-section">
        <div class="container">
            <div class="cy-section__head">
                <h2 class="cy-section__title">Sıfırdan yazmanıza gerek kalmayan altyapı</h2>
                <p class="cy-section__lead">
                    Her yeni projede tekrar tekrar kurduğunuz temel ne varsa hazır geliyor.
                    Siz doğrudan işin özüne odaklanın.
                </p>
            </div>

            <div class="cy-feature-grid">
                <?php foreach ($ozellikler as $ozellik): ?>
                    <article class="cy-feature">
                        <span class="cy-feature__icon cy-feature__icon--<?= e($ozellik['renk']) ?>">
                            <?= icon($ozellik['ikon']) ?>
                        </span>
                        <h3 class="cy-feature__title"><?= e($ozellik['baslik']) ?></h3>
                        <p class="cy-feature__text"><?= e($ozellik['metin']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ================= HAKKIMIZDA ÖZETİ ================= -->
<?php $hakkinda = Setting::get('site_hakkinda'); ?>
<?php if ($hakkinda !== ''): ?>
    <section class="cy-section cy-section--alt">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-lg-7">
                    <h2 class="cy-section__title"><?= e($siteAdi) ?> hakkında</h2>
                    <div class="cy-prose">
                        <?php /* Yönetici düz metin yazar; satır sonları korunur,
                                 HTML metin olarak görünür (XSS'e kapalı). */ ?>
                        <?= nl2br(e(mb_strimwidth($hakkinda, 0, 420, '…', 'UTF-8'))) ?>
                    </div>
                    <a class="btn cy-btn cy-btn--ghost mt-3" href="<?= e(url('hakkimizda')) ?>">
                        Devamını oku <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
                    </a>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="cy-quote">
                        <?= icon('activity', 'cy-icon cy-icon--lg') ?>
                        <p>
                            “Yeni bir projeye başladığımda temel teknik altyapıyı
                            tekrar yazmak zorunda kalmayayım.”
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ================= İLETİŞİM ================= -->
<section class="cy-section">
    <div class="container">
        <div class="cy-cta">
            <div class="cy-cta__text">
                <h2>Bir sorunuz mu var?</h2>
                <p>Formu doldurun, en kısa sürede dönüş yapalım.</p>
            </div>

            <a class="btn cy-btn cy-btn--primary" href="<?= e(url('iletisim')) ?>">
                <?= icon('mail', 'cy-icon cy-icon--sm') ?> İletişim Formu
            </a>
        </div>

        <?php if ($iletisim !== []): ?>
            <div class="cy-contact-grid mt-3">
                <?php foreach ($iletisim as $kart): ?>
                    <?php if ($kart['link'] !== ''): ?>
                        <a class="cy-contact-card" href="<?= e($kart['link']) ?>">
                    <?php else: ?>
                        <div class="cy-contact-card">
                    <?php endif; ?>

                        <span class="cy-contact-card__icon"><?= icon($kart['ikon']) ?></span>
                        <span class="cy-contact-card__body">
                            <span class="cy-contact-card__label"><?= e($kart['etiket']) ?></span>
                            <span class="cy-contact-card__value"><?= e($kart['deger']) ?></span>
                        </span>

                    <?= $kart['link'] !== '' ? '</a>' : '</div>' ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
