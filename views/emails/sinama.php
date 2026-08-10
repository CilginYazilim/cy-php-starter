<?php
/**
 * =====================================================================
 *  E-POSTA: Sınama mektubu
 * ---------------------------------------------------------------------
 *  Ayarlar sayfasındaki "Sınama E-postası Gönder" düğmesi bunu yollar.
 *  İçinde yapılandırmanın özeti vardır: mektup elinize ulaştıysa
 *  hangi ayarlarla geldiğini oradan görürsünüz.
 *
 *  @var array<string,string> $ayarlar
 *  @var string $siteAdi
 * =====================================================================
 */

use App\Core\Mail\MailUi;
?>
<?= MailUi::baslik('E-posta ayarlarınız çalışıyor 🎉') ?>
<?= MailUi::paragraf('Bu mektubu okuyabiliyorsanız ' . $siteAdi . ' sitesinin e-posta gönderimi doğru yapılandırılmış demektir. Artık iletişim formu bildirimleri, karşılama mektupları ve toplu duyurular sorunsuz gidecek.') ?>

<?= MailUi::altBaslik('Kullanılan ayarlar') ?>
<?= MailUi::bilgiTablosu($ayarlar) ?>

<?= MailUi::kucukNot('Mektup spam/gereksiz klasörüne düştüyse alan adınız için SPF ve DKIM kayıtlarını tanımlamayı düşünün — teslim oranını en çok bu iki kayıt etkiler.') ?>
