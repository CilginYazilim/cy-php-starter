<?php
/**
 * =====================================================================
 *  E-POSTA: Parolanız değiştirildi (bilgilendirme)
 * ---------------------------------------------------------------------
 *  @var string $siteAdi
 *  @var string $ad
 *  @var string $nasil         "hesap ayarlarınızdan", "e-posta bağlantısıyla"…
 *  @var string $tarih
 *  @var string $sifirlamaUrl  Boşsa (sıfırlama kapalı) giriş düğmesi gösterilir
 *  @var string $girisUrl
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('Parolanız değiştirildi') ?>
<?= MailUi::paragraf('Merhaba ' . $ad . ', ' . $siteAdi . ' hesabınızın parolası ' . $tarih . ' tarihinde ' . $nasil . ' değiştirildi. Güvenliğiniz için diğer cihazlardaki oturumlar ve API anahtarları kapatıldı.') ?>

<?= MailUi::paragraf('Bu değişikliği siz yaptıysanız başka bir şey yapmanız gerekmez.') ?>

<?php if ($sifirlamaUrl !== ''): ?>
<?= MailUi::dugme($sifirlamaUrl, 'Ben yapmadım, parolamı sıfırla') ?>
<?php else: ?>
<?= MailUi::dugme($girisUrl, 'Giriş Yap') ?>
<?php endif; ?>

<?= MailUi::kucukNot('Bu değişikliği siz yapmadıysanız hemen yeni bir parola belirleyin ve site yöneticisine haber verin.') ?>
