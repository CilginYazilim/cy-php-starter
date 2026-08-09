<?php
/**
 * =====================================================================
 *  NativeTransport – PHP'nin kendi mail() fonksiyonu
 * ---------------------------------------------------------------------
 *  Paylaşımlı hostinglerin çoğunda çalışır ve hiçbir ayar istemez.
 *  Buna karşılık:
 *    • gönderim başarısız olursa SEBEBİNİ öğrenemezsiniz,
 *    • mektuplar sık sık spam klasörüne düşer (SPF/DKIM imzası yok).
 *  Ciddi bir kurulumda SMTP kullanın.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

final class NativeTransport implements Transport
{
    public function name(): string
    {
        return 'PHP mail()';
    }

    public function send(Mailable $mail): void
    {
        if (!function_exists('mail')) {
            throw new MailException('PHP mail() fonksiyonu bu sunucuda kapalı.');
        }

        $from = $mail->sender() ?? ['', ''];

        if ($from[0] === '') {
            throw new MailException('Gönderen adresi tanımlı değil.');
        }

        $built = Mime::build($mail, $from);

        // mail() "To" ve "Subject" değerlerini ayrı parametre olarak
        // ister; aynı başlıkları bir de başlık listesinde göndermek
        // mektubu bozar, o yüzden listeden çıkarıyoruz.
        $to      = $built['headers']['To'];
        $subject = $built['headers']['Subject'];

        unset($built['headers']['To'], $built['headers']['Subject']);

        // Bcc mail() için başlıkta verilir (SMTP zarfını kendisi kurar).
        if ($mail->bccList() !== []) {
            $built['headers']['Bcc'] = Mime::addressList($mail->bccList());
        }

        $headerLines = [];

        foreach ($built['headers'] as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        // -f parametresi zarf gönderenini belirler; "Return-Path"
        // doğru olsun diye veriyoruz. safe_mode kapalı olmalıdır.
        $ok = @mail(
            $to,
            $subject,
            $built['body'],
            implode(Mime::CRLF, $headerLines),
            '-f' . Mime::stripNewlines($from[0])
        );

        if ($ok !== true) {
            throw new MailException('PHP mail() mektubu teslim edemedi. Sunucunun posta ayarlarını (php.ini → sendmail_path / SMTP) kontrol edin.');
        }
    }

    public function verify(): void
    {
        if (!function_exists('mail')) {
            throw new MailException('PHP mail() fonksiyonu bu sunucuda kapalı.');
        }
    }
}
