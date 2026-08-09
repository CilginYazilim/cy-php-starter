<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: İletişim
 * =====================================================================
 */

use App\Core\Setting;

$formOpen = Setting::bool('sistem_iletisim_formu', true);
?>

<section class="container py-5">
    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <h1 class="mb-3">İletişim</h1>
            <p class="cy-muted">Sorularınız için bize ulaşabilirsiniz.</p>

            <div class="cy-card mt-4">
                <div class="cy-card__body">
                    <dl class="cy-detail">
                        <?php if (Setting::get('iletisim_eposta') !== ''): ?>
                            <dt>E-posta</dt> <dd><?= e(Setting::get('iletisim_eposta')) ?></dd>
                        <?php endif; ?>
                        <?php if (Setting::get('iletisim_telefon') !== ''): ?>
                            <dt>Telefon</dt> <dd><?= e(Setting::get('iletisim_telefon')) ?></dd>
                        <?php endif; ?>
                        <?php if (Setting::get('iletisim_adres') !== ''): ?>
                            <dt>Adres</dt> <dd><?= nl2br(e(Setting::get('iletisim_adres'))) ?></dd>
                        <?php endif; ?>
                        <?php if (Setting::get('iletisim_saatler') !== ''): ?>
                            <dt>Çalışma Saatleri</dt> <dd><?= e(Setting::get('iletisim_saatler')) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <?php if ($formOpen): ?>
                <div class="cy-card">
                    <div class="cy-card__header"><h2 class="cy-section-title mb-0">Mesaj Gönderin</h2></div>
                    <div class="cy-card__body">
                        <div class="cy-alert cy-alert--danger d-none mb-3" id="contact_alert" role="alert"></div>
                        <div class="cy-alert cy-alert--success d-none mb-3" id="contact_success" role="alert"></div>

                        <form id="contact_form" novalidate>
                            <?= csrf_field() ?>

                            <!-- Bal küpü: ekranda GÖRÜNMEZ, gerçek kullanıcı asla doldurmaz. -->
                            <div class="cy-sr-only" aria-hidden="true">
                                <label for="website">Web siteniz</label>
                                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="ad">Ad Soyad <span class="text-danger">*</span></label>
                                    <input type="text" name="ad" id="ad" class="form-control" maxlength="150" autocomplete="name">
                                    <div class="invalid-feedback" data-error-for="ad"></div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="eposta">E-posta <span class="text-danger">*</span></label>
                                    <input type="email" name="eposta" id="eposta" class="form-control" maxlength="190" autocomplete="email">
                                    <div class="invalid-feedback" data-error-for="eposta"></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="konu">Konu</label>
                                    <input type="text" name="konu" id="konu" class="form-control" maxlength="190">
                                    <div class="invalid-feedback" data-error-for="konu"></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="mesaj">Mesajınız <span class="text-danger">*</span></label>
                                    <textarea name="mesaj" id="mesaj" class="form-control" rows="6" maxlength="4000"></textarea>
                                    <div class="invalid-feedback" data-error-for="mesaj"></div>
                                </div>
                            </div>

                            <button type="submit" class="btn cy-btn cy-btn--primary mt-3" id="contact_submit">
                                <span class="spinner-border spinner-border-sm me-1 d-none" id="contact_spinner"></span>
                                <?= icon('mail', 'cy-icon cy-icon--sm') ?> <span id="contact_submit_label">Gönder</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="cy-alert cy-alert--info">İletişim formu şu anda kapalı. Lütfen soldaki bilgilerden bize ulaşın.</div>
            <?php endif; ?>
        </div>
    </div>
</section>
