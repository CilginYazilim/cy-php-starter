<?php
/**
 * =====================================================================
 *  İLETİŞİM (herkese açık)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Form AJAX ile system/ajax.php?action=mesaj_gonder ucuna gönderilir;
 *  mesaj "mesajlar" tablosuna kaydedilir. E-posta sunucusu (SMTP)
 *  ayarlamadan çalışan en güvenilir yöntem budur — mesajlar kaybolmaz,
 *  spam klasörüne düşmez, panelden okunur.
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik   = 'İletişim';
$aktifSayfa    = 'iletisim';
$sayfaAciklama = 'Bize ulaşın; en kısa sürede dönüş yapalım.';

require __DIR__ . '/_ust.php';

$formAcik = setting_bool('sistem_iletisim_formu', true);

$eposta  = (string) setting('iletisim_eposta', '');
$telefon = (string) setting('iletisim_telefon', '');
$adres   = (string) setting('iletisim_adres', '');
$saatler = (string) setting('iletisim_saatler', '');

/* Giriş yapmış kullanıcı için ad ve e-posta alanlarını önceden dolduruyoruz —
 * ziyaretçi aynı bilgiyi ikinci kez yazmak zorunda kalmasın. */
$formAd     = $aktifKullanici !== null
    ? trim($aktifKullanici['ad'] . ' ' . $aktifKullanici['soyad'])
    : '';
$formEposta = $aktifKullanici !== null ? (string) $aktifKullanici['eposta'] : '';
?>

<div class="cy-page-head">
    <div class="container">
        <nav class="cy-breadcrumb" aria-label="Konum">
            <a href="index.php">Ana Sayfa</a> <span aria-hidden="true">›</span> İletişim
        </nav>
        <h1>İletişim</h1>
        <p><?= e($sayfaAciklama) ?></p>
    </div>
</div>

<section class="cy-section container">
    <div class="row g-4">

        <!-- ================= İLETİŞİM BİLGİLERİ ================= -->
        <div class="col-lg-5">
            <div class="cy-grid" style="grid-template-columns:1fr;">

                <?php if ($eposta !== ''): ?>
                    <div class="cy-feature">
                        <div class="cy-feature__icon" aria-hidden="true">✉️</div>
                        <h2 class="cy-feature__title">E-posta</h2>
                        <p class="cy-feature__text">
                            <a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($telefon !== ''): ?>
                    <div class="cy-feature">
                        <div class="cy-feature__icon" aria-hidden="true">📞</div>
                        <h2 class="cy-feature__title">Telefon</h2>
                        <p class="cy-feature__text">
                            <!-- tel: bağlantısında sadece rakam ve + kalmalı -->
                            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $telefon) ?? '') ?>"><?= e($telefon) ?></a>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($adres !== ''): ?>
                    <div class="cy-feature">
                        <div class="cy-feature__icon" aria-hidden="true">📍</div>
                        <h2 class="cy-feature__title">Adres</h2>
                        <p class="cy-feature__text"><?= nl2br(e($adres)) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($saatler !== ''): ?>
                    <div class="cy-feature">
                        <div class="cy-feature__icon" aria-hidden="true">🕘</div>
                        <h2 class="cy-feature__title">Çalışma Saatleri</h2>
                        <p class="cy-feature__text"><?= e($saatler) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($eposta === '' && $telefon === '' && $adres === '' && $saatler === ''): ?>
                    <div class="cy-empty">
                        İletişim bilgileri henüz girilmemiş.
                        <?php if (is_admin()): ?>
                            <br><a href="yonetim/ayarlar.php">Ayarlar &rsaquo; İletişim</a> bölümünden ekleyebilirsiniz.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================= MESAJ FORMU ================= -->
        <div class="col-lg-7">
            <div class="cy-card">
                <div class="cy-card__header">
                    <h2 class="cy-brand__title mb-0">Bize Yazın</h2>
                    <p class="cy-brand__subtitle mb-0">Mesajınız doğrudan yönetim paneline düşer.</p>
                </div>

                <div class="cy-card__body">
                <?php if (!$formAcik): ?>
                    <div class="cy-empty">
                        Mesaj formu şu anda kapalı.
                        <?php if ($eposta !== ''): ?>
                            Bize <a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a>
                            adresinden ulaşabilirsiniz.
                        <?php endif; ?>
                    </div>
                <?php else: ?>

                    <form id="iletisim_form" novalidate>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="mesaj_ad" class="form-label">Adınız <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="mesaj_ad" name="ad" maxlength="150"
                                       value="<?= e($formAd) ?>">
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="col-sm-6">
                                <label for="mesaj_eposta" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="mesaj_eposta" name="eposta" maxlength="190"
                                       value="<?= e($formEposta) ?>">
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="col-12">
                                <label for="mesaj_konu" class="form-label">Konu</label>
                                <input type="text" class="form-control" id="mesaj_konu" name="konu" maxlength="190">
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="col-12">
                                <label for="mesaj_metin" class="form-label">Mesajınız <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="mesaj_metin" name="mesaj" rows="6" maxlength="4000"></textarea>
                                <div class="invalid-feedback"></div>
                                <div class="form-text">En az 10, en fazla 4000 karakter.</div>
                            </div>

                            <!--
                                BAL KÜPÜ (honeypot) — basit ama etkili spam engeli
                                ---------------------------------------------------
                                Bu alan ekranda görünmez; gerçek ziyaretçi doldurmaz.
                                Formları otomatik dolduran botlar ise "her alanı
                                doldur" mantığıyla çalıştığı için buraya bir şey
                                yazar. Sunucu dolu geldiğini görünce mesajı sessizce
                                yok sayar. CAPTCHA gerektirmez, kullanıcıyı yormaz.

                                tabindex="-1" ve autocomplete="off": klavyeyle
                                sekmelenerek yanlışlıkla doldurulmasın diye.
                            -->
                            <div aria-hidden="true" style="position:absolute; left:-9999px;">
                                <label for="mesaj_website">Web siteniz (boş bırakın)</label>
                                <input type="text" id="mesaj_website" name="website" tabindex="-1" autocomplete="off">
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3 mt-4">
                            <button type="submit" class="btn cy-btn cy-btn--primary" id="mesaj_gonder">
                                Mesajı Gönder
                            </button>
                            <span class="cy-muted small" id="mesaj_durum" role="status" aria-live="polite"></span>
                        </div>
                    </form>

                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php ob_start(); ?>
<script>
$(function () {
    'use strict';

    $('#iletisim_form').on('submit', function (event) {
        // Sayfanın yeniden yüklenmesini engelle; isteği biz göndereceğiz.
        event.preventDefault();

        var $form   = $(this);
        var $buton  = $('#mesaj_gonder');
        var $durum  = $('#mesaj_durum');

        CY.clearErrors($form);

        // Çift tıklamada iki mesaj gitmesin diye butonu kilitliyoruz.
        $buton.prop('disabled', true).text('Gönderiliyor…');
        $durum.text('');

        CY.post('mesaj_gonder', {
            ad:      $('#mesaj_ad').val(),
            eposta:  $('#mesaj_eposta').val(),
            konu:    $('#mesaj_konu').val(),
            mesaj:   $('#mesaj_metin').val(),
            website: $('#mesaj_website').val()
        })
        .done(function (cevap) {
            CY.notify(cevap.description, 'success');
            $form[0].reset();
            $durum.text('Teşekkürler, mesajınız bize ulaştı.');
        })
        .fail(function (xhr) {
            var cevap = xhr.responseJSON || {};
            CY.notify(cevap.description || 'Mesaj gönderilemedi.', 'danger');

            if (cevap.errors) {
                CY.showErrors($form, cevap.errors);
            }
        })
        .always(function () {
            $buton.prop('disabled', false).text('Mesajı Gönder');
        });
    });
});
</script>
<?php $sayfaScript = ob_get_clean(); ?>

<?php require __DIR__ . '/_alt.php'; ?>
