<?php
/**
 * =====================================================================
 *  E-POSTA: Otomatik yanıt (ZİYARETÇİYE gider)
 * ---------------------------------------------------------------------
 *  "Mesajınızı aldık" mektubu. Kısa olmalı: uzun otomatik yanıtlar
 *  hem spam filtrelerini hem de okuyanı rahatsız eder.
 *
 *  ZİYARETÇİNİN YAZDIĞI METİN BURAYA BASILMAZ. Form herkese açık ve
 *  alıcı adresi formdan geldiği için, mesajı geri yansıtmak sitenin
 *  alan adından başkalarına içerik gönderen bir röle demekti
 *  (bkz. Notifier::mesajAlindi).
 *
 *  @var string $ad
 *  @var string $siteAdi
 * =====================================================================
 */

use App\Core\Mail\MailUi;
use App\Core\Setting;

$telefon = Setting::get('iletisim_telefon');
$saatler = Setting::get('iletisim_saatler');
?>
<?= MailUi::baslik('Mesajınızı aldık, teşekkürler') ?>
<?= MailUi::paragraf('Merhaba ' . $ad . ',') ?>
<?= MailUi::paragraf($siteAdi . ' iletişim formundan gönderdiğiniz mesaj bize ulaştı. En kısa sürede size dönüş yapacağız.') ?>
<?= MailUi::paragraf('Bu mesajı siz göndermediyseniz bu e-postayı dikkate almayın; adresiniz başka biri tarafından yazılmış olabilir.') ?>

<?php if ($telefon !== '' || $saatler !== ''): ?>
    <?= MailUi::ayrac() ?>
    <?= MailUi::bilgiTablosu(array_filter([
        'Telefon'          => $telefon,
        'Çalışma Saatleri' => $saatler,
    ])) ?>
<?php endif; ?>

<?= MailUi::kucukNot('Bu otomatik bir yanıttır; mesajınızın bize ulaştığını doğrulamak için gönderildi.') ?>
