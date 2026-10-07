<?php
/**
 * =====================================================================
 *  E-POSTA: Duyuru / serbest mesaj (panelden yazılır)
 * ---------------------------------------------------------------------
 *  Tekil ve toplu gönderimlerde kullanılan şablon. Gövde metni
 *  yöneticinin panele yazdığı düz metindir; MailUi::metinBloklari()
 *  onu güvenle HTML'e çevirir.
 *
 *  @var string $baslik
 *  @var string $govde
 *  @var string $ad        Alıcının adı ("Merhaba Ali," için)
 *  @var string $dugmeMetni
 *  @var string $dugmeUrl
 *  @var string $iptalUrl  Kitle duyurusunda imzalı "almak istemiyorum" bağlantısı
 * =====================================================================
 */

use App\Core\Mail\MailUi;

$baslik     = $baslik ?? '';
$ad         = trim($ad ?? '');
$dugmeMetni = trim($dugmeMetni ?? '');
$dugmeUrl   = trim($dugmeUrl ?? '');
?>
<?php if ($baslik !== ''): ?>
    <?= MailUi::baslik($baslik) ?>
<?php endif; ?>

<?php if ($ad !== ''): ?>
    <?= MailUi::paragraf('Merhaba ' . $ad . ',') ?>
<?php endif; ?>

<?= MailUi::metinBloklari($govde) ?>

<?php if ($dugmeMetni !== '' && $dugmeUrl !== ''): ?>
    <?= MailUi::dugme($dugmeUrl, $dugmeMetni) ?>
<?php endif; ?>

<?php if (($iptalUrl ?? '') !== ''): ?>
    <?php /* Yalnızca kitleye (tüm üyeler / bir rol) giden duyurularda.
             İmzalı, giriş gerektirmez (bkz. App\Core\NotificationPrefs). */ ?>
    <?= MailUi::ayrac() ?>
    <?= MailUi::kucukBaglanti('Bu duyuruyu sitemize üye olduğunuz için aldınız.', $iptalUrl, 'Duyuruları almak istemiyorum') ?>
<?php endif; ?>
