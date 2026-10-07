<?php
/**
 * =====================================================================
 *  E-POSTA: Hesabınız silinecek (üyenin silme isteği)
 * ---------------------------------------------------------------------
 *  @var string $siteAdi
 *  @var string $ad
 *  @var string $tarih      Silinme tarihi ("14.10.2026 09:30")
 *  @var string $girisUrl
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('Hesabınız silinecek') ?>
<?= MailUi::paragraf('Merhaba ' . $ad . ', ' . $siteAdi . ' hesabınızı silme isteğinizi aldık. Hesabınız ' . $tarih . ' tarihinde kalıcı olarak silinecek; oturumlar ve API anahtarları kapatıldı.') ?>

<?= MailUi::paragraf('Vazgeçtiyseniz bu tarihe kadar giriş yapmanız yeterli; silme kendiliğinden iptal edilir.') ?>

<?= MailUi::dugme($girisUrl, 'Giriş yap ve silmeyi iptal et') ?>

<?= MailUi::kucukNot('Bu isteği siz yapmadıysanız hemen giriş yapın ve parolanızı değiştirin.') ?>
