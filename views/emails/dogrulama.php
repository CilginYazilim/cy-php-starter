<?php
/**
 * =====================================================================
 *  E-POSTA: Hesap doğrulama (kayıt formuna yazılan ADRESE gider)
 * ---------------------------------------------------------------------
 *  Kayıt formu herkese açıktır; bu mektup formda yazılan HERHANGİ bir
 *  adrese gidebilir. Bu yüzden formdan gelen hiçbir metin (ad, soyad,
 *  kullanıcı adı) mektuba BASILMAZ — aksi hâlde form, sitenin adıyla
 *  başkalarına metin gönderen bir röle olurdu.
 *
 *  @var string $siteAdi
 *  @var string $dogrulamaUrl
 *  @var int    $saat         Bağlantının geçerlilik süresi
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('E-posta adresinizi doğrulayın') ?>
<?= MailUi::paragraf($siteAdi . ' sitesinde bu adresle bir hesap açıldı. Hesabı etkinleştirmek için aşağıdaki düğmeye tıklayın.') ?>

<?= MailUi::dugme($dogrulamaUrl, 'Hesabımı Etkinleştir') ?>

<?= MailUi::kucukNot('Bağlantı ' . $saat . ' saat geçerlidir. Bu hesabı siz açmadıysanız bu e-postayı görmezden gelin; bağlantıya tıklanmadıkça hesap kullanılamaz.') ?>
