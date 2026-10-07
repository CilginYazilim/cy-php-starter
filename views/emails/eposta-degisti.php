<?php
/**
 * =====================================================================
 *  E-POSTA: E-posta adresiniz değiştirildi (ESKİ adrese)
 * ---------------------------------------------------------------------
 *  @var string $siteAdi
 *  @var string $ad
 *  @var string $tarih
 *  @var string $nasil         "hesap ayarlarınızdan" | "site yöneticisi tarafından"
 *  @var string $yeniMaskeli   "y***@ornek.com" — yeni adres açık yazılmaz
 *  @var string $iletisim      Boşsa "site yöneticisine haber verin" denir
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('E-posta adresiniz değiştirildi') ?>
<?= MailUi::paragraf('Merhaba ' . $ad . ', ' . $siteAdi . ' hesabınızın e-posta adresi ' . $tarih . ' tarihinde ' . $nasil . ' değiştirildi.') ?>

<?= MailUi::bilgiTablosu([
    'Yeni adres' => $yeniMaskeli,
    'Tarih'      => $tarih,
]) ?>

<?= MailUi::paragraf('Bu adrese artık bildirim ve parola sıfırlama bağlantısı gelmeyecek. Değişikliği siz yaptıysanız başka bir şey yapmanız gerekmez.') ?>

<?= MailUi::kucukNot($iletisim !== ''
    ? 'Siz yapmadıysanız hesabınız başkasının eline geçmiş olabilir; hemen ' . $iletisim . ' adresine yazın.'
    : 'Siz yapmadıysanız hesabınız başkasının eline geçmiş olabilir; hemen site yöneticisine haber verin.') ?>
