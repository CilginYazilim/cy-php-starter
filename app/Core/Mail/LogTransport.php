<?php
/**
 * =====================================================================
 *  LogTransport – Hiçbir yere göndermez, diske yazar
 * ---------------------------------------------------------------------
 *  GELİŞTİRME SÜRÜCÜSÜ. Mektup "storage/mail/" klasörüne .eml olarak
 *  kaydedilir; dosyayı çift tıklayıp Outlook/Thunderbird ile ya da
 *  bir metin düzenleyiciyle açabilirsiniz.
 *
 *  Yerelde çalışırken varsayılan budur: yanlışlıkla gerçek
 *  kullanıcılara test maili gitmesini imkânsız kılar.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

final class LogTransport implements Transport
{
    public function __construct(private readonly string $directory)
    {
    }

    public function name(): string
    {
        return 'Kayıt (diske yazar)';
    }

    public function send(Mailable $mail): void
    {
        $this->ensureDirectory();

        $from = $mail->sender() ?? ['posta@localhost', 'Yerel'];
        $raw  = Mime::raw($mail, $from);

        $recipient = $mail->recipients()[0][0] ?? 'alici-yok';
        $safe      = (string) preg_replace('/[^a-zA-Z0-9._@-]/', '_', $recipient);

        $file = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR
              . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '-' . $safe . '.eml';

        if (@file_put_contents($file, $raw) === false) {
            throw new MailException('Mektup diske yazılamadı: ' . $file);
        }
    }

    public function verify(): void
    {
        $this->ensureDirectory();
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new MailException('Posta klasörü oluşturulamadı: ' . $this->directory);
        }

        if (!is_writable($this->directory)) {
            throw new MailException('Posta klasörüne yazılamıyor: ' . $this->directory);
        }

        // Klasör web kökünün altında olduğu için dışarıdan okunmasını
        // engelleyen bir .htaccess bırakıyoruz.
        $htaccess = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';

        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }
}
