<?php
/**
 * =====================================================================
 *  E-POSTA: Hoş geldiniz (yeni kayıt olan ÜYEYE gider)
 * ---------------------------------------------------------------------
 *  @var string $ad
 *  @var string $kullaniciAdi
 *  @var string $siteAdi
 *  @var string $girisUrl
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('Aramıza hoş geldiniz, ' . $ad . '!') ?>
<?= MailUi::paragraf($siteAdi . ' hesabınız oluşturuldu. Aşağıdaki bilgilerle giriş yapabilirsiniz.') ?>

<?= MailUi::bilgiTablosu([
    'Kullanıcı adı' => $kullaniciAdi,
    'Parola'        => 'Kayıt sırasında belirlediğiniz parola',
]) ?>

<?= MailUi::dugme($girisUrl, 'Giriş Yap') ?>

<?= MailUi::kucukNot('Bu hesabı siz oluşturmadıysanız bu e-postayı görmezden gelin; hesap parolanızı bilmeyen kimse giriş yapamaz.') ?>
