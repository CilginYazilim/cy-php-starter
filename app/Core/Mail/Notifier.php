<?php
/**
 * =====================================================================
 *  Notifier – Uygulamanın "hangi olayda hangi mektup gider" listesi
 * ---------------------------------------------------------------------
 *  Denetleyiciler mektup KURMAZ, yalnızca olayı bildirir:
 *
 *      Notifier::yeniMesaj($mesaj);        // yöneticiye bildirim
 *      Notifier::mesajAlindi($mesaj);      // ziyaretçiye otomatik yanıt
 *      Notifier::hosgeldin($user);         // yeni üyeye karşılama
 *
 *  Böylece şablon, alıcı ve ayar denetimi tek yerde toplanır. Yeni bir
 *  bildirim eklemek istediğinizde buraya bir metot yazarsınız.
 *
 *  HEPSİ SESSİZDİR: gönderim başarısız olsa bile istisna fırlatmaz,
 *  false döner. Ziyaretçinin iletişim formu, posta sunucusu çöktü
 *  diye hata vermemelidir — mesaj zaten veritabanına kaydedilmiştir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\Setting;
use App\Models\Message;
use App\Models\User;

final class Notifier
{
    /* =================================================================
     *  İLETİŞİM FORMU
     * ============================================================== */

    /** Yöneticiye "yeni mesaj var" bildirimi. */
    public static function yeniMesaj(Message $message): bool
    {
        if (!Setting::bool('mail_bildirim_yeni_mesaj', true)) {
            return false;
        }

        $admin = Mailer::adminAddress();

        if ($admin === '') {
            // Bildirimin gideceği adres tanımlı değil. Bu bir hata
            // değil, eksik ayardır: Panel → Site Ayarları → İletişim.
            return false;
        }

        $konu = $message->konu !== '' ? $message->konu : '(konusuz)';

        $mail = Mailable::make()
            ->to($admin, Setting::get('site_adi', 'Yönetici'))
            ->subject('Yeni iletişim mesajı: ' . $konu)
            ->type('iletisim')
            ->forUser($message->kullaniciId)
            // "Yanıtla" dendiğinde cevap doğrudan ziyaretçiye gitsin.
            ->replyTo($message->eposta, $message->ad)
            ->view('emails/iletisim-bildirim', [
                'konu'     => $message->konu,
                'ad'       => $message->ad,
                'eposta'   => $message->eposta,
                'mesaj'    => $message->mesaj,
                'ip'       => $message->ip,
                'tarih'    => Message::formatDate($message->createdAt),
                'panelUrl' => Mailer::absolute(url('panel/mesajlar')),
            ]);

        return Mailer::send($mail);
    }

    /** Ziyaretçiye "mesajınızı aldık" otomatik yanıtı. */
    public static function mesajAlindi(Message $message): bool
    {
        if (!Setting::bool('mail_otomatik_yanit', true)) {
            return false;
        }

        $siteAdi = Setting::get('site_adi', 'Site');
        $admin   = Mailer::adminAddress();

        $mail = Mailable::make()
            ->to($message->eposta, $message->ad)
            ->subject('Mesajınızı aldık – ' . $siteAdi)
            ->type('otomatik')
            ->forUser($message->kullaniciId)
            ->view('emails/iletisim-yanit', [
                'ad'      => $message->ad,
                'konu'    => $message->konu,
                'mesaj'   => $message->mesaj,
                'siteAdi' => $siteAdi,
            ]);

        // Ziyaretçi otomatik yanıtı yanıtlarsa yöneticiye ulaşsın.
        if ($admin !== '') {
            $mail->replyTo($admin, $siteAdi);
        }

        return Mailer::send($mail);
    }

    /* =================================================================
     *  ÜYELİK
     * ============================================================== */

    /** Yeni kayıt olan üyeye karşılama mektubu. */
    public static function hosgeldin(User $user): bool
    {
        if (!Setting::bool('mail_hosgeldin', true)) {
            return false;
        }

        $siteAdi = Setting::get('site_adi', 'Site');

        $mail = Mailable::make()
            ->to($user->eposta, $user->fullName())
            ->subject($siteAdi . ' – Hoş geldiniz')
            ->type('sistem')
            ->forUser($user->id)
            ->view('emails/hosgeldin', [
                'ad'           => $user->ad,
                'kullaniciAdi' => $user->kullaniciAdi,
                'siteAdi'      => $siteAdi,
                'girisUrl'     => Mailer::absolute(url('giris')),
            ]);

        return Mailer::send($mail);
    }

    /* =================================================================
     *  SINAMA
     * ============================================================== */

    /**
     * Ayarları sınamak için örnek mektup gönderir.
     *
     * Bu metot SESSİZ DEĞİLDİR: hata olursa MailException fırlatır,
     * çünkü kullanıcı düğmeye basıp sonucu görmeyi bekler.
     *
     * @throws MailException
     */
    public static function sinama(string $to, string $name = ''): void
    {
        $config  = Mailer::config();
        $siteAdi = Setting::get('site_adi', 'Site');

        $ozet = [
            'Yöntem' => match ($config['surucu']) {
                'smtp'  => 'SMTP',
                'php'   => 'PHP mail()',
                default => 'Kayıt (diske yazılır)',
            },
            'Gönderen' => $config['gonderenAd'] !== ''
                ? $config['gonderenAd'] . ' <' . $config['gonderen'] . '>'
                : $config['gonderen'],
        ];

        if ($config['surucu'] === 'smtp') {
            $ozet['Sunucu']    = $config['host'] . ':' . $config['port'];
            $ozet['Şifreleme'] = strtoupper($config['guvenlik']);
        }

        $ozet['Tarih'] = date('d.m.Y H:i');

        $mail = Mailable::make()
            ->to($to, $name)
            ->subject($siteAdi . ' – Sınama e-postası')
            ->type('test')
            ->view('emails/sinama', [
                'ayarlar' => $ozet,
                'siteAdi' => $siteAdi,
            ]);

        Mailer::send($mail, rethrow: true);
    }
}
