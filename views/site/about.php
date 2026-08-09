<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Hakkımızda
 * =====================================================================
 */

use App\Core\Setting;

$about = Setting::get('site_hakkinda');
?>

<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <h1 class="mb-4">Hakkımızda</h1>

            <?php if ($about !== ''): ?>
                <div class="cy-prose"><?= nl2br(e($about)) ?></div>
            <?php else: ?>
                <p class="cy-muted">
                    Bu, Çılgın Yazılım PHP Başlangıç Şablonu ile oluşturulmuş bir örnek sayfadır.
                    İçeriği yönetim panelindeki <strong>Site Ayarları → Genel → Hakkımızda Metni</strong>
                    alanından güncelleyebilirsiniz.
                </p>
            <?php endif; ?>

            <div class="cy-card mt-4">
                <div class="cy-card__header"><h2 class="cy-section-title mb-0">Künye</h2></div>
                <div class="cy-card__body">
                    <dl class="cy-detail">
                        <dt>Şablon</dt> <dd>Çılgın Yazılım PHP Başlangıç Şablonu</dd>
                        <dt>Geliştirici</dt> <dd><a class="cy-link" href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a></dd>
                        <dt>Lisans</dt> <dd>MIT</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</section>
