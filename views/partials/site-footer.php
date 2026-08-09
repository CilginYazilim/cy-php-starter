<?php
/**
 * =====================================================================
 *  PARÇA: Ön yüz alt bilgi
 * =====================================================================
 */

use App\Core\Setting;

$socials = [
    'sosyal_facebook'  => ['label' => 'Facebook',  'icon' => 'globe'],
    'sosyal_x'         => ['label' => 'X',         'icon' => 'globe'],
    'sosyal_instagram' => ['label' => 'Instagram', 'icon' => 'globe'],
    'sosyal_linkedin'  => ['label' => 'LinkedIn',  'icon' => 'globe'],
    'sosyal_youtube'   => ['label' => 'YouTube',   'icon' => 'globe'],
    'sosyal_github'    => ['label' => 'GitHub',    'icon' => 'github'],
];
?>
<footer class="cy-site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-md-4">
                <div class="cy-site-nav__brand mb-2">
                    <img src="<?= e(Setting::logoUrl()) ?>" alt="">
                    <span><?= e(Setting::get('site_adi', $appName ?? '')) ?></span>
                </div>
                <p class="cy-muted small mb-0"><?= e(Setting::get('site_slogan', Setting::get('site_aciklama'))) ?></p>
            </div>

            <div class="col-6 col-md-4">
                <span class="cy-eyebrow d-block mb-2">İletişim</span>
                <ul class="list-unstyled small cy-muted d-flex flex-column gap-1">
                    <?php if (Setting::get('iletisim_eposta') !== ''): ?>
                        <li><?= icon('mail', 'cy-icon cy-icon--sm') ?> <?= e(Setting::get('iletisim_eposta')) ?></li>
                    <?php endif; ?>
                    <?php if (Setting::get('iletisim_telefon') !== ''): ?>
                        <li><?= icon('phone', 'cy-icon cy-icon--sm') ?> <?= e(Setting::get('iletisim_telefon')) ?></li>
                    <?php endif; ?>
                    <?php if (Setting::get('iletisim_adres') !== ''): ?>
                        <li><?= nl2br(e(Setting::get('iletisim_adres'))) ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-6 col-md-4">
                <span class="cy-eyebrow d-block mb-2">Sosyal Medya</span>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($socials as $key => $meta): ?>
                        <?php $url = Setting::get($key); ?>
                        <?php if ($url !== ''): ?>
                            <a class="cy-btn-icon" href="<?= e($url) ?>" target="_blank" rel="noopener" title="<?= e($meta['label']) ?>">
                                <?= icon($meta['icon'], 'cy-icon cy-icon--sm') ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <hr class="cy-divider">

        <div class="d-flex flex-wrap justify-content-between gap-2 small cy-muted">
            <span><?= date('Y') ?> © <?= e(Setting::get('site_adi', $appName ?? '')) ?>. Tüm hakları saklıdır.</span>
            <span>
                <a class="cy-link" href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a>
                ile geliştirildi
            </span>
        </div>
    </div>
</footer>
