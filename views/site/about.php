<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Hakkımızda
 * ---------------------------------------------------------------------
 *  İçerik "Site Ayarları → Genel → Hakkımızda Metni" alanından gelir.
 *  Boşsa kullanıcıya NEREDEN dolduracağını söyleyen bir yönlendirme
 *  gösterilir — boş bir sayfa bırakmak yerine.
 *
 *  @var array<int,array<string,string>> $iletisim, $ozellikler
 * =====================================================================
 */

use App\Core\Setting;

$siteAdi  = Setting::get('site_adi', $appName ?? 'Yeni Proje');
$about    = Setting::get('site_hakkinda');
$slogan   = Setting::get('site_slogan');

$iletisim   = $iletisim ?? [];
$ozellikler = array_slice($ozellikler ?? [], 0, 3);
?>

<section class="cy-pagehero">
    <div class="container">
        <nav class="cy-breadcrumb cy-breadcrumb--light" aria-label="Konum">
            <a href="<?= e(url('')) ?>">Ana Sayfa</a>
            <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
            <span>Hakkımızda</span>
        </nav>

        <h1 class="cy-pagehero__title">Hakkımızda</h1>

        <?php if ($slogan !== ''): ?>
            <p class="cy-pagehero__lead"><?= e($slogan) ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="cy-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="cy-card">
                    <div class="cy-card__body">
                        <?php if ($about !== ''): ?>
                            <div class="cy-prose"><?= nl2br(e($about)) ?></div>
                        <?php else: ?>
                            <div class="cy-empty">
                                <span class="cy-empty__icon"><?= icon('edit') ?></span>
                                <p class="cy-empty__text">
                                    Bu sayfanın içeriği henüz yazılmamış.
                                    <?php if (can('settings.manage')): ?>
                                        <br>
                                        <a class="cy-link" href="<?= e(url('panel/ayarlar/genel')) ?>">
                                            Site Ayarları → Genel → Hakkımızda Metni
                                        </a>
                                        alanından ekleyebilirsiniz.
                                    <?php else: ?>
                                        <br>Yakında burada olacak.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($ozellikler !== []): ?>
                    <div class="cy-feature-grid cy-feature-grid--compact mt-4">
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
                <?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <?php if ($iletisim !== []): ?>
                    <div class="cy-card mb-3">
                        <div class="cy-card__header">
                            <h2 class="cy-section-title mb-0"><?= icon('phone', 'cy-icon cy-icon--sm') ?> İletişim</h2>
                        </div>
                        <div class="cy-card__body">
                            <?php foreach ($iletisim as $kart): ?>
                                <div class="cy-contact-row">
                                    <span class="cy-contact-row__icon"><?= icon($kart['ikon'], 'cy-icon cy-icon--sm') ?></span>
                                    <div>
                                        <span class="cy-contact-row__label"><?= e($kart['etiket']) ?></span>
                                        <?php if ($kart['link'] !== ''): ?>
                                            <a class="cy-link" href="<?= e($kart['link']) ?>"><?= e($kart['deger']) ?></a>
                                        <?php else: ?>
                                            <span><?= e($kart['deger']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <a class="btn cy-btn cy-btn--primary cy-btn--block cy-btn--sm mt-3"
                               href="<?= e(url('iletisim')) ?>">
                                <?= icon('mail', 'cy-icon cy-icon--sm') ?> Mesaj Gönder
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="cy-card">
                    <div class="cy-card__header">
                        <h2 class="cy-section-title mb-0">Künye</h2>
                    </div>
                    <div class="cy-card__body">
                        <dl class="cy-detail cy-detail--compact mb-0">
                            <dt>Site</dt>
                            <dd><?= e($siteAdi) ?></dd>
                            <dt>Altyapı</dt>
                            <dd>Çılgın Yazılım PHP Başlangıç Şablonu</dd>
                            <dt>Geliştirici</dt>
                            <dd><a class="cy-link" href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a></dd>
                            <dt>Lisans</dt>
                            <dd>MIT</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
