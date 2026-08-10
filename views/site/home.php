<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Ana sayfa
 * ---------------------------------------------------------------------
 *  Her bölüm panelden beslenir ve İLGİLİ VERİ BOŞSA HİÇ BASILMAZ.
 *  Yarım dolu bir sayfa, boş bir sayfadan daha kötü görünür.
 *
 *  @var array<int,array<string,string>> $ozellikler, $iletisim, $istatistik
 *  @var App\Models\Page|null $hakkinda
 * =====================================================================
 */

use App\Core\Setting;
use App\Http\Controllers\Site\HomeController;

$siteAdi  = Setting::get('site_adi', $appName ?? 'Yeni Proje');
$slogan   = Setting::get('site_slogan');
$aciklama = Setting::get('site_aciklama');

// Slogan tanımlıysa onu, değilse açıklamayı kullan.
$girisMetni = $slogan !== '' ? $slogan : $aciklama;

$ozellikler = $ozellikler ?? [];
$iletisim   = $iletisim ?? [];
$istatistik = $istatistik ?? [];
$hakkinda   = $hakkinda ?? null;
$whatsapp   = HomeController::whatsappLink();
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

                    <?php if ($whatsapp !== ''): ?>
                        <a class="btn cy-btn cy-btn--whatsapp" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">
                            <?= icon('whatsapp', 'cy-icon cy-icon--sm') ?> WhatsApp
                        </a>
                    <?php endif; ?>

                    <?php if ($hakkinda !== null): ?>
                        <a class="btn cy-btn cy-btn--ghost" href="<?= e(url($hakkinda->slug)) ?>">
                            <?= e($hakkinda->baslik) ?> <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
                        </a>
                    <?php endif; ?>
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
                    <div class="cy-hero__card">
                        <img src="<?= e(Setting::logoUrl()) ?>" alt="<?= e($siteAdi) ?>" class="cy-hero__logo">
                        <span class="cy-hero__card-name"><?= e($siteAdi) ?></span>
                        <?php if ($slogan !== '' && $aciklama !== ''): ?>
                            <span class="cy-hero__card-note"><?= e(mb_strimwidth($aciklama, 0, 90, '…', 'UTF-8')) ?></span>
                        <?php endif; ?>
                    </div>
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
                <span class="cy-eyebrow">Neler var?</span>
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
<?php if ($hakkinda !== null): ?>
    <section class="cy-section cy-section--alt">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-lg-7">
                    <span class="cy-eyebrow"><?= e($hakkinda->baslik) ?></span>
                    <h2 class="cy-section__title text-start"><?= e($siteAdi) ?> hakkında</h2>

                    <div class="cy-prose">
                        <?php /* Sayfanın DÜZ METİN özeti: ana sayfada başlık ve
                                 liste basmak bölümü dağıtırdı. Tam metin
                                 sayfanın kendisinde. */ ?>
                        <p><?= e(mb_strimwidth(trim(preg_replace('/\s+/u', ' ', strip_tags($hakkinda->icerik)) ?? ''), 0, 420, '…', 'UTF-8')) ?></p>
                    </div>

                    <a class="btn cy-btn cy-btn--ghost mt-3" href="<?= e(url($hakkinda->slug)) ?>">
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

            <div class="d-flex flex-wrap gap-2">
                <a class="btn cy-btn cy-btn--primary" href="<?= e(url('iletisim')) ?>">
                    <?= icon('mail', 'cy-icon cy-icon--sm') ?> İletişim Formu
                </a>

                <?php if ($whatsapp !== ''): ?>
                    <a class="btn cy-btn cy-btn--whatsapp" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">
                        <?= icon('whatsapp', 'cy-icon cy-icon--sm') ?> Hemen Yazın
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($iletisim !== []): ?>
            <div class="cy-contact-grid mt-3">
                <?php foreach ($iletisim as $kart): ?>
                    <?php if ($kart['link'] !== ''): ?>
                        <a class="cy-contact-card" href="<?= e($kart['link']) ?>"
                           <?= str_starts_with($kart['link'], 'http') ? 'target="_blank" rel="noopener"' : '' ?>>
                    <?php else: ?>
                        <div class="cy-contact-card">
                    <?php endif; ?>

                        <span class="cy-contact-card__icon"><?= icon($kart['ikon']) ?></span>
                        <span class="cy-contact-card__body">
                            <span class="cy-contact-card__label"><?= e($kart['etiket']) ?></span>
                            <span class="cy-contact-card__value"><?= nl2br(e($kart['deger'])) ?></span>
                        </span>

                    <?= $kart['link'] !== '' ? '</a>' : '</div>' ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
