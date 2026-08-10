<?php
/**
 * =====================================================================
 *  E-POSTA: Otomatik yanıt (ZİYARETÇİYE gider)
 * ---------------------------------------------------------------------
 *  "Mesajınızı aldık" mektubu. Kısa olmalı: uzun otomatik yanıtlar
 *  hem spam filtrelerini hem de okuyanı rahatsız eder.
 *
 *  @var string $ad
 *  @var string $konu
 *  @var string $mesaj
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

<?= MailUi::altBaslik('Gönderdiğiniz mesaj') ?>
<?php if ($konu !== ''): ?>
    <?= MailUi::bilgiTablosu(['Konu' => $konu]) ?>
<?php endif; ?>
<?= MailUi::alinti($mesaj) ?>

<?php if ($telefon !== '' || $saatler !== ''): ?>
    <?= MailUi::ayrac() ?>
    <?= MailUi::bilgiTablosu(array_filter([
        'Telefon'          => $telefon,
        'Çalışma Saatleri' => $saatler,
    ])) ?>
<?php endif; ?>

<?= MailUi::kucukNot('Bu otomatik bir yanıttır; mesajınızın bize ulaştığını doğrulamak için gönderildi.') ?>
