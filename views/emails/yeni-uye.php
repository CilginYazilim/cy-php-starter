<?php
/**
 * =====================================================================
 *  E-POSTA: Yeni üye (yöneticiye bildirim)
 * ---------------------------------------------------------------------
 *  @var string $siteAdi
 *  @var string $adSoyad
 *  @var string $kullaniciAdi
 *  @var string $eposta
 *  @var string $tarih
 *  @var string $panelUrl
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('Yeni üye kaydı') ?>
<?= MailUi::paragraf($siteAdi . ' sitesine yeni bir üye katıldı.') ?>

<?= MailUi::bilgiTablosu([
    'Ad Soyad'     => $adSoyad,
    'Kullanıcı adı' => '@' . $kullaniciAdi,
    'E-posta'      => $eposta,
    'Kayıt tarihi' => $tarih,
]) ?>

<?= MailUi::dugme($panelUrl, 'Kullanıcıyı aç') ?>

<?= MailUi::kucukNot('Bu bildirimi Panel → Ayarlar → E-posta → Yeni Üye Bildirimi ile kapatabilirsiniz.') ?>
