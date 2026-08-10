<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: İçerik sayfası (ön yüz)
 * ---------------------------------------------------------------------
 *  Panelden yazılan her sayfa bu görünümle basılır. İçerik
 *  KAYDEDİLİRKEN süzüldüğü için burada kaçışlanmaz — kaçışlansaydı
 *  ziyaretçi HTML etiketlerini metin olarak görürdü.
 *
 *  @var App\Models\Page $sayfa
 *  @var array<int,array<string,string>> $iletisim
 * =====================================================================
 */

use App\Core\Setting;
use App\Http\Controllers\Site\HomeController;
use App\Models\Page;

$iletisim = $iletisim ?? [];
$whatsapp = HomeController::whatsappLink();
?>

<section class="cy-pagehero">
    <div class="container">
        <nav class="cy-breadcrumb cy-breadcrumb--light" aria-label="Konum">
            <a href="<?= e(url('')) ?>">Ana Sayfa</a>
            <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
            <span><?= e($sayfa->baslik) ?></span>
        </nav>

        <h1 class="cy-pagehero__title"><?= e($sayfa->baslik) ?></h1>

        <?php if ($sayfa->ozet !== ''): ?>
            <p class="cy-pagehero__lead"><?= e($sayfa->ozet) ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="cy-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <article class="cy-card">
                    <div class="cy-card__body cy-card__body--roomy">
                        <div class="cy-prose"><?= $sayfa->icerik ?></div>
                    </div>
                </article>

                <p class="cy-muted small mt-3 mb-0">
                    <?= icon('clock', 'cy-icon cy-icon--sm') ?>
                    Son güncelleme: <?= e(Page::formatDate($sayfa->updatedAt)) ?>
                </p>
            </div>

            <aside class="col-12 col-lg-4">
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
                                            <a class="cy-link" href="<?= e($kart['link']) ?>"><?= nl2br(e($kart['deger'])) ?></a>
                                        <?php else: ?>
                                            <span><?= nl2br(e($kart['deger'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="d-grid gap-2 mt-3">
                                <a class="btn cy-btn cy-btn--primary cy-btn--sm" href="<?= e(url('iletisim')) ?>">
                                    <?= icon('mail', 'cy-icon cy-icon--sm') ?> Mesaj Gönder
                                </a>

                                <?php if ($whatsapp !== ''): ?>
                                    <a class="btn cy-btn cy-btn--whatsapp cy-btn--sm" href="<?= e($whatsapp) ?>"
                                       target="_blank" rel="noopener">
                                        <?= icon('whatsapp', 'cy-icon cy-icon--sm') ?> WhatsApp'tan Yazın
                                    </a>
                                <?php endif; ?>
                            </div>
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
                            <dd><?= e(Setting::get('site_adi', $appName ?? '')) ?></dd>
                            <?php if (Setting::get('site_slogan') !== ''): ?>
                                <dt>Slogan</dt>
                                <dd><?= e(Setting::get('site_slogan')) ?></dd>
                            <?php endif; ?>
                            <dt>Geliştirici</dt>
                            <dd><a class="cy-link" href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a></dd>
                        </dl>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>
