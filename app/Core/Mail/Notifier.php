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
 *      Notifier::epostaDegisti($user, …);  // eski adrese güvenlik bildirimi
 *      Notifier::hesapSilinecek($user, …); // silme isteği alındı
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
        if (!Setting::bool('mail_otomatik_yanit', false)) {
            return false;
        }

        /* SPAM RÖLESİ KORUMASI.
         *
         * İletişim formu herkese açıktır ve otomatik yanıt, formda
         * yazılan HERHANGİ bir adrese SİZİN alan adınızdan gider.
         * Eskiden yanıt, ziyaretçinin yazdığı konu ve mesajı da
         * içeriyordu: bir spamcı kurbanın adresini yazıp mesaj
         * kutusuna reklamını koyarak sitenizi güvenilir bir posta
         * rölesi gibi kullanabiliyordu. Artık:
         *   · mektup ziyaretçinin yazdığı metni İÇERMEZ,
         *   · aynı adrese 24 saatte en fazla BİR otomatik yanıt gider. */
        try {
            $log = new \App\Repositories\MailRepository(\App\Core\Database::connection());

            if ($log->sentRecentlyTo($message->eposta, 'otomatik', 24)) {
                return false;
            }
        } catch (\Throwable) {
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

    /**
     * Hesap doğrulama bağlantısı (kayıt formuna yazılan adrese).
     *
     * Aynı adrese 10 dakikada en fazla BİR doğrulama mektubu gider:
     * "yeniden gönder" ya da tekrar tekrar giriş denemesi, bir adresi
     * mektup yağmuruna tutmanın yolu olmasın. Alıcı adı bile formdan
     * gelen metin olduğu için yazılmaz.
     */
    public static function dogrulama(User $user, string $link, int $saat): bool
    {
        if (self::sentRecently($user->eposta, 'dogrulama', 10)) {
            return false;
        }

        $siteAdi = Setting::get('site_adi', 'Site');

        $mail = Mailable::make()
            ->to($user->eposta)
            ->subject($siteAdi . ' – E-posta adresinizi doğrulayın')
            ->type('sistem')
            ->forUser($user->id)
            ->view('emails/dogrulama', [
                'siteAdi'      => $siteAdi,
                'dogrulamaUrl' => $link,
                'saat'         => $saat,
            ]);

        return Mailer::send($mail);
    }

    /**
     * Kayıt formuna KAYITLI bir adres yazıldığında adresin sahibine
     * bilgi. Aynı adrese günde en fazla bir kez.
     */
    public static function zatenKayitli(User $user): bool
    {
        if (self::sentRecently($user->eposta, 'zaten-kayitli', 24 * 60)) {
            return false;
        }

        $siteAdi = Setting::get('site_adi', 'Site');

        $mail = Mailable::make()
            ->to($user->eposta)
            ->subject($siteAdi . ' – Bu adresle zaten bir hesabınız var')
            ->type('sistem')
            ->forUser($user->id)
            ->view('emails/zaten-kayitli', [
                'siteAdi'  => $siteAdi,
                'girisUrl' => Mailer::absolute(url('giris')),
            ]);

        return Mailer::send($mail);
    }

    /**
     * "Parolamı unuttum" bağlantısı. Aynı adrese 2 dakikada en fazla
     * BİR mektup: formu art arda göndermek gelen kutusunu doldurmasın.
     */
    public static function parolaSifirlama(User $user, string $link, int $dakika): bool
    {
        if (self::sentRecently($user->eposta, 'parola-sifirlama', 2)) {
            return false;
        }

        $siteAdi = Setting::get('site_adi', 'Site');

        $mail = Mailable::make()
            ->to($user->eposta, $user->fullName())
            ->subject($siteAdi . ' – Parola sıfırlama')
            ->type('sistem')
            ->forUser($user->id)
            ->view('emails/parola-sifirlama', [
                'siteAdi'      => $siteAdi,
                'sifirlamaUrl' => $link,
                'dakika'       => $dakika,
            ]);

        return Mailer::send($mail);
    }

    /** "Parolanız değiştirildi" bilgilendirmesi (bkz. Listeners\ParolaDegistiBildir). */
    public static function parolaDegisti(User $user, string $kaynak = 'profil'): bool
    {
        $siteAdi = Setting::get('site_adi', 'Site');
        $nasil   = match ($kaynak) {
            'sifirlama' => 'e-posta ile gönderilen sıfırlama bağlantısıyla',
            'yonetici'  => 'site yöneticisi tarafından',
            default     => 'hesap ayarlarınızdan',
        };

        $mail = Mailable::make()
            ->to($user->eposta, $user->fullName())
            ->subject($siteAdi . ' – Parolanız değiştirildi')
            ->type('sistem')
            ->forUser($user->id)
            ->view('emails/parola-degisti', [
                'siteAdi'      => $siteAdi,
                'ad'           => $user->ad,
                'nasil'        => $nasil,
                'tarih'        => date('d.m.Y H:i'),
                'sifirlamaUrl' => \App\Core\PasswordReset::enabled() ? Mailer::absolute(url('parolami-unuttum')) : '',
                'girisUrl'     => Mailer::absolute(url('giris')),
            ]);

        return Mailer::send($mail);
    }

    /**
     * "E-posta adresiniz değiştirildi" — ESKİ adrese (bkz. Listeners\EpostaDegistiBildir).
     *
     * Yeni adres mektupta maskelenir: eski adresi okuyan herkes (ör.
     * devredilmiş bir iş adresi) yeni adresi öğrenmesin. Not satırındaki
     * iletişim adresi, değişen eski adresin kendisiyse yazılmaz — kişiyi
     * kendine yazmaya yönlendirmek anlamsız olurdu.
     */
    public static function epostaDegisti(User $user, string $eskiEposta, string $yeniEposta, string $kaynak = 'profil'): bool
    {
        if ($eskiEposta === '' || mb_strtolower($eskiEposta) === mb_strtolower($yeniEposta)) {
            return false;
        }

        $siteAdi  = Setting::get('site_adi', 'Site');
        $iletisim = trim(Setting::get('iletisim_eposta'));

        if (mb_strtolower($iletisim) === mb_strtolower($eskiEposta)) {
            $iletisim = '';
        }

        $mail = Mailable::make()
            ->to($eskiEposta, $user->fullName())
            ->subject($siteAdi . ' – E-posta adresiniz değiştirildi')
            ->type('sistem')
            ->forUser($user->id)
            ->view('emails/eposta-degisti', [
                'siteAdi'     => $siteAdi,
                'ad'          => $user->ad,
                'tarih'       => date('d.m.Y H:i'),
                'nasil'       => $kaynak === 'yonetici' ? 'site yöneticisi tarafından' : 'hesap ayarlarınızdan',
                'yeniMaskeli' => \App\Models\MailLog::maskEmail($yeniEposta),
                'iletisim'    => $iletisim,
            ]);

        return Mailer::send($mail);
    }

    /** "Hesabınız silinecek" — üyenin silme isteğinden sonra (bkz. Listeners\HesapSilmeBildir). */
    public static function hesapSilinecek(User $user, string $tarih): bool
    {
        $siteAdi = Setting::get('site_adi', 'Site');

        $mail = Mailable::make()
            ->to($user->eposta, $user->fullName())
            ->subject($siteAdi . ' – Hesabınız silinecek')
            ->type('sistem')
            ->forUser($user->id)
            ->view('emails/hesap-silinecek', [
                'siteAdi'  => $siteAdi,
                'ad'       => $user->ad,
                'tarih'    => $tarih,
                'girisUrl' => Mailer::absolute(url('giris')),
            ]);

        return Mailer::send($mail);
    }

    /** Bu adrese, bu şablonla son $dakika içinde mektup gitti mi? (veritabanı yoksa "evet") */
    private static function sentRecently(string $email, string $template, int $dakika): bool
    {
        try {
            return (new \App\Repositories\MailRepository(\App\Core\Database::connection()))
                ->sentTemplateRecently($email, $template, $dakika);
        } catch (\Throwable) {
            return true;
        }
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
