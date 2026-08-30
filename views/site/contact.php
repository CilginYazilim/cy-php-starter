<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: İletişim
 * ---------------------------------------------------------------------
 *  Sayfanın giriş metni panelden gelir (Sayfalar → İletişim); form ve
 *  iletişim kartları çevresini tamamlar.
 *
 *  @var App\Models\Page|null $sayfa
 *  @var array<int,array<string,string>> $iletisim
 * =====================================================================
 */

use App\Core\Setting;
use App\Http\Controllers\Site\HomeController;

$formOpen = Setting::bool('sistem_iletisim_formu', true);
$sayfa    = $sayfa ?? null;
$iletisim = $iletisim ?? [];
$whatsapp = HomeController::whatsappLink();
$harita   = Setting::get('iletisim_harita');
$baslik   = $sayfa?->baslik ?? 'İletişim';
?>

<section class="cy-pagehero">
    <div class="container">
        <nav class="cy-breadcrumb" aria-label="Konum">
            <a href="<?= e(url('')) ?>">Ana Sayfa</a>
            <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
            <span><?= e($baslik) ?></span>
        </nav>

        <h1 class="cy-pagehero__title"><?= e($baslik) ?></h1>

        <p class="cy-pagehero__lead">
            <?= e($sayfa?->ozet !== '' && $sayfa !== null ? $sayfa->ozet : 'Sorularınız için bize ulaşabilirsiniz.') ?>
        </p>
    </div>
</section>

<section class="cy-section">
    <div class="container">
        <div class="row g-4">
            <!-- ============ SOL: BİLGİLER ============ -->
            <?php /* MOBİLDE FORM ÖNCE GELİR. Bu sayfaya gelen kişinin
                     amacı yazmaktır; telefonda önce iki ekran boyu
                     iletişim kartı okutmak, formu görünmez kılıyordu.
                     Geniş ekranda sıra korunur (order-lg-*). */ ?>
            <div class="col-12 col-lg-5 order-2 order-lg-1">
                <?php if ($iletisim !== []): ?>
                    <div class="cy-card mb-3">
                        <div class="cy-card__header">
                            <h2 class="cy-section-title mb-0"><?= icon('phone', 'cy-icon cy-icon--sm') ?> İletişim Bilgileri</h2>
                        </div>
                        <div class="cy-card__body">
                            <?php foreach ($iletisim as $kart): ?>
                                <div class="cy-contact-row">
                                    <span class="cy-contact-row__icon"><?= icon($kart['ikon'], 'cy-icon cy-icon--sm') ?></span>
                                    <div>
                                        <span class="cy-contact-row__label"><?= e($kart['etiket']) ?></span>
                                        <?php if ($kart['link'] !== ''): ?>
                                            <a class="cy-link" href="<?= e($kart['link']) ?>"
                                               <?= str_starts_with($kart['link'], 'http') ? 'target="_blank" rel="noopener"' : '' ?>>
                                                <?= nl2br(e($kart['deger'])) ?>
                                            </a>
                                        <?php else: ?>
                                            <span><?= nl2br(e($kart['deger'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($whatsapp !== ''): ?>
                                <a class="btn cy-btn cy-btn--whatsapp cy-btn--block mt-3"
                                   href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">
                                    <?= icon('whatsapp', 'cy-icon cy-icon--sm') ?> WhatsApp'tan Yazın
                                </a>
                            <?php endif; ?>

                            <?php if ($harita !== ''): ?>
                                <a class="btn cy-btn cy-btn--ghost cy-btn--block cy-btn--sm mt-2"
                                   href="<?= e($harita) ?>" target="_blank" rel="noopener">
                                    <?= icon('map', 'cy-icon cy-icon--sm') ?> Haritada Aç
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($sayfa !== null && trim($sayfa->icerik) !== ''): ?>
                    <div class="cy-card">
                        <div class="cy-card__body">
                            <div class="cy-prose"><?= $sayfa->icerik ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ============ SAĞ: FORM ============ -->
            <div class="col-12 col-lg-7 order-1 order-lg-2">
                <?php if ($formOpen): ?>
                    <div class="cy-card">
                        <div class="cy-card__header">
                            <h2 class="cy-section-title mb-0"><?= icon('send', 'cy-icon cy-icon--sm') ?> Mesaj Gönderin</h2>
                            <span class="cy-muted small">* ile işaretli alanlar zorunludur</span>
                        </div>
                        <div class="cy-card__body">
                            <div class="cy-alert cy-alert--danger d-none mb-3" id="contact_alert" role="alert"></div>
                            <div class="cy-alert cy-alert--success d-none mb-3" id="contact_success" role="alert"></div>

                            <?php
                            /* YÖNETİCİYE ÖZEL UYARI.
                             *
                             * Mesajlar her koşulda veritabanına yazılır, ama e-posta
                             * yöntemi hâlâ "kayıt" modundaysa BİLDİRİM MEKTUBU
                             * KİMSEYE GİTMEZ; mektup storage/mail/ altına .eml olarak
                             * düşer. Ziyaretçi "ulaştı" yazısını görür, yönetici ise
                             * panele girmedikçe mesajdan haberdar olmaz.
                             *
                             * Bu uyarı YALNIZCA ayarları değiştirebilecek kişiye
                             * gösterilir — ziyaretçinin sunucu yapılandırmasını
                             * bilmesi ne gerekir ne de doğrudur. */
                            $smtpEksik = Setting::get('mail_surucu', 'kayit') === 'kayit';
                            ?>
                            <?php if ($smtpEksik && can('settings.manage')): ?>
                                <div class="cy-alert cy-alert--warning mb-3">
                                    <strong><?= icon('alert', 'cy-icon cy-icon--sm') ?> E-posta gönderimi kapalı.</strong>
                                    Buradan gelen mesajlar veritabanına kaydedilir ama
                                    <u>bildirim e-postası kimseye ulaşmaz</u>.
                                    <a class="cy-link" href="<?= e(url('panel/ayarlar/eposta')) ?>">SMTP ayarlarını yapın</a>
                                    ya da mesajları <a class="cy-link" href="<?= e(url('panel/mesajlar')) ?>">panelden</a> takip edin.
                                    <span class="d-block small mt-1">Bu uyarıyı yalnızca yöneticiler görür.</span>
                                </div>
                            <?php endif; ?>

                            <form id="contact_form" novalidate>
                                <?= csrf_field() ?>

                                <?php /*
                                    BAL KÜPÜ (honeypot) – ekranda görünmez, gerçek kullanıcı doldurmaz.

                                    ALAN ADI ÖNEMLİDİR: eskiden "website" idi ve tarayıcıların
                                    OTOMATİK DOLDURMA özelliği (Chrome/parola yöneticileri
                                    "website" alanlarını doldurur, autocomplete="off" çoğu zaman
                                    yok sayılır) bu alanı doldurduğu için gerçek ziyaretçilerin
                                    mesajları bot sanılıp sessizce çöpe atılıyordu.

                                    Artık hiçbir otomatik doldurma sezgisine uymayan anlamsız bir
                                    ad kullanıyoruz; ayrıca parola yöneticilerine "dokunma"
                                    diyen öznitelikleri ekliyoruz.
                                */ ?>
                                <div class="cy-hp" aria-hidden="true">
                                    <label for="cy_kontrol">Bu alanı boş bırakın</label>
                                    <input type="text" name="cy_kontrol" id="cy_kontrol" value=""
                                           tabindex="-1" autocomplete="off"
                                           data-lpignore="true" data-1p-ignore data-form-type="other">
                                </div>

                                <?php /* Form ne zaman üretildi? Botlar formu anında gönderir. */ ?>
                                <input type="hidden" name="cy_zaman" value="<?= (int) time() ?>">

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
                                        <input type="text" name="konu" id="konu" class="form-control" maxlength="190"
                                               placeholder="Örn: Teklif talebi">
                                        <div class="invalid-feedback" data-error-for="konu"></div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="mesaj">Mesajınız <span class="text-danger">*</span></label>
                                        <textarea name="mesaj" id="mesaj" class="form-control" rows="7" maxlength="4000"
                                                  placeholder="Size nasıl yardımcı olabiliriz?"></textarea>
                                        <div class="invalid-feedback" data-error-for="mesaj"></div>
                                    </div>
                                </div>

                                <div class="cy-contact-actions d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                                    <button type="submit" class="btn cy-btn cy-btn--primary" id="contact_submit">
                                        <span class="spinner-border spinner-border-sm me-1 d-none" id="contact_spinner"></span>
                                        <?= icon('send', 'cy-icon cy-icon--sm') ?> <span id="contact_submit_label">Gönder</span>
                                    </button>

                                    <span class="cy-muted small">
                                        <?= icon('lock', 'cy-icon cy-icon--sm') ?>
                                        Bilgileriniz yalnızca size dönüş yapmak için kullanılır.
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="cy-alert cy-alert--info">
                        İletişim formu şu anda kapalı. Lütfen soldaki bilgilerden bize ulaşın.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
