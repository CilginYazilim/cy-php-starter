<?php
/**
 * =====================================================================
 *  E-POSTA: "Bu adresle zaten hesabınız var"
 * ---------------------------------------------------------------------
 *  Kayıt formuna KAYITLI bir adres yazıldığında ekranda "bu e-posta
 *  zaten kayıtlı" DENMEZ (hangi adreslerin üye olduğu dışarıdan
 *  öğrenilmesin); ekran her durumda aynı yanıtı verir. Adresin gerçek
 *  sahibi ise ne olduğunu bu mektuptan öğrenir.
 *
 *  Formdan gelen hiçbir metin buraya basılmaz.
 *
 *  @var string $siteAdi
 *  @var string $girisUrl
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('Bu adresle zaten bir hesabınız var') ?>
<?= MailUi::paragraf($siteAdi . ' sitesinde bu e-posta adresiyle yeni bir hesap açılmak istendi. Adresinize bağlı bir hesap zaten bulunduğu için yeni hesap AÇILMADI.') ?>

<?= MailUi::dugme($girisUrl, 'Giriş Yap') ?>

<?= MailUi::kucukNot('Bu isteği siz yapmadıysanız bu e-postayı görmezden gelin; hesabınızda hiçbir değişiklik yapılmadı.') ?>
