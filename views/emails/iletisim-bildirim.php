<?php
/**
 * =====================================================================
 *  E-POSTA: Yeni iletişim mesajı bildirimi (YÖNETİCİYE gider)
 * ---------------------------------------------------------------------
 *  @var string $ad
 *  @var string $eposta
 *  @var string $konu
 *  @var string $mesaj
 *  @var string $ip
 *  @var string $tarih
 *  @var string $panelUrl
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('Yeni bir mesajınız var') ?>
<?= MailUi::paragraf('İletişim formundan yeni bir mesaj geldi. Mektubu doğrudan yanıtlarsanız cevabınız gönderene ulaşır.') ?>

<?= MailUi::bilgiTablosu([
    'Gönderen' => $ad,
    'E-posta'  => $eposta,
    'Konu'     => $konu !== '' ? $konu : '(konu belirtilmemiş)',
    'Tarih'    => $tarih,
    'IP'       => $ip !== '' ? $ip : '—',
]) ?>

<?= MailUi::altBaslik('Mesaj') ?>
<?= MailUi::alinti($mesaj) ?>

<?= MailUi::dugme($panelUrl, 'Panelde Aç') ?>

<?= MailUi::kucukNot('Bu bildirim otomatik gönderildi. Bildirimleri kapatmak için: Panel → Site Ayarları → E-posta.') ?>
