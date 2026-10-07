<?php
/**
 * =====================================================================
 *  E-POSTA: Hesabınız açıldı (yöneticinin oluşturduğu hesap)
 * ---------------------------------------------------------------------
 *  Parola mektupta YAZMAZ: kullanıcı bağlantıyla kendi parolasını
 *  belirler. Bağlantı tek kullanımlıktır, $saat saat geçerlidir. Panelin
 *  e-posta geçmişinde bu mektubun gövdesi editörden GİZLENİR
 *  (MailLog::GUVENLIK_SABLONLARI).
 *
 *  @var string $siteAdi
 *  @var string $ad
 *  @var string $kullaniciAdi
 *  @var string $baglanti
 *  @var int    $saat
 *  @var string $girisUrl
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('Hesabınız açıldı') ?>
<?= MailUi::paragraf('Merhaba ' . $ad . ', ' . $siteAdi . ' yöneticisi sizin için bir hesap açtı.') ?>

<?= MailUi::bilgiTablosu([
    'Kullanıcı adı' => $kullaniciAdi,
    'Giriş adresi'  => $girisUrl,
]) ?>

<?= MailUi::paragraf('Başlamak için aşağıdaki bağlantıyla kendi parolanızı belirleyin. Bağlantı ' . $saat . ' saat geçerlidir ve bir kez kullanılabilir.') ?>

<?= MailUi::dugme($baglanti, 'Parolanızı belirleyin') ?>

<?= MailUi::kucukNot('Bağlantının süresi dolarsa giriş ekranındaki "Parolamı unuttum" ile yenisini isteyebilirsiniz. Bu hesabı beklemiyorsanız mektubu görmezden gelin.') ?>
