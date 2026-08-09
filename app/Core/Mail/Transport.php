<?php
/**
 * =====================================================================
 *  Transport – "Mektubu gerçekten taşıyan" katmanın sözleşmesi
 * ---------------------------------------------------------------------
 *  Uygulama e-postayı NASIL gönderdiğini bilmek zorunda değildir.
 *  Mailer bir Mailable hazırlar, Transport onu taşır:
 *
 *      SmtpTransport   → gerçek bir SMTP sunucusuna bağlanır
 *      NativeTransport → PHP'nin mail() fonksiyonunu kullanır
 *      LogTransport    → hiçbir yere göndermez, diske .eml yazar
 *                        (geliştirme sırasında en güvenli seçenek)
 *
 *  Yeni bir sağlayıcı (ör. bir API servisi) eklemek isterseniz tek
 *  yapmanız gereken bu arayüzü uygulayan bir sınıf yazmaktır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

interface Transport
{
    /**
     * Mektubu gönderir.
     *
     * @throws MailException Gönderim başarısız olduğunda
     */
    public function send(Mailable $mail): void;

    /**
     * Yapılandırmayı sınar (SMTP'de bağlan + kimlik doğrula + kapat).
     * Ayarlar sayfasındaki "Bağlantıyı Sına" düğmesi bunu kullanır.
     *
     * @throws MailException Sınama başarısız olduğunda
     */
    public function verify(): void;

    /** Arayüzde gösterilecek kısa ad ("SMTP", "PHP mail()", "Kayıt"). */
    public function name(): string;
}
