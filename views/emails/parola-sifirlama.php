<?php
/**
 * =====================================================================
 *  E-POSTA: Parola sıfırlama bağlantısı
 * ---------------------------------------------------------------------
 *  Formdan gelen hiçbir metin mektuba basılmaz; yalnızca site adı ve
 *  bağlantı. Bağlantı tek kullanımlıktır (bkz. App\Core\PasswordReset).
 *  Panelin e-posta geçmişinde bu mektubun gövdesi GİZLENİR (MailLog::
 *  GUVENLIK_SABLONLARI): bağlantı, mektubu okuyabilen bir yöneticinin
 *  eline geçmesin.
 *
 *  @var string $siteAdi
 *  @var string $sifirlamaUrl
 *  @var int    $dakika
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('Parola sıfırlama isteği') ?>
<?= MailUi::paragraf($siteAdi . ' hesabınız için parola sıfırlama istendi. Yeni bir parola belirlemek için aşağıdaki düğmeye tıklayın.') ?>

<?= MailUi::dugme($sifirlamaUrl, 'Yeni Parola Belirle') ?>

<?= MailUi::kucukNot('Bağlantı ' . $dakika . ' dakika geçerlidir ve yalnızca bir kez kullanılabilir. Bu isteği siz yapmadıysanız bu e-postayı görmezden gelin; parolanız değişmez.') ?>
