<?php
/**
 * =====================================================================
 *  SmtpTransport – Elle yazılmış, bağımlılıksız SMTP istemcisi
 * ---------------------------------------------------------------------
 *  Bu şablon Composer kullanmaz; bu yüzden PHPMailer yerine SMTP
 *  konuşmasını kendimiz yapıyoruz. Konuşma şöyle ilerler:
 *
 *      S: 220 mail.ornek.com ESMTP        ← sunucu kendini tanıtır
 *      C: EHLO siteniz.com                 ← biz selam veririz
 *      S: 250-STARTTLS ...                 ← sunucu yeteneklerini sayar
 *      C: STARTTLS                         ← şifreli hatta geçiş
 *      C: AUTH LOGIN                       ← kullanıcı adı/parola
 *      C: MAIL FROM:<...>  /  RCPT TO:<...>← zarf
 *      C: DATA ... .                       ← mektubun kendisi
 *      C: QUIT
 *
 *  ŞİFRELEME SEÇENEKLERİ
 *    ssl  → 465 numaralı kapı. Bağlantı en baştan şifrelidir.
 *    tls  → 587 numaralı kapı. Düz başlar, STARTTLS ile şifrelenir.
 *    yok  → şifresiz. YALNIZCA yerel test sunucusu için.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

final class SmtpTransport implements Transport
{
    /** @var resource|null */
    private $socket = null;

    /** @var array<int,string> Konuşma dökümü — hata ayıklamada altın değerinde. */
    private array $log = [];

    public function __construct(
        private readonly string $host,
        private readonly int    $port = 587,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly string $encryption = 'tls',
        private readonly int    $timeout = 15,
        private readonly string $heloName = 'localhost',
    ) {
    }

    public function name(): string
    {
        return 'SMTP';
    }

    public function send(Mailable $mail): void
    {
        $from = $mail->sender() ?? ['', ''];

        if ($from[0] === '') {
            throw new MailException('Gönderen adresi tanımlı değil.');
        }

        $recipients = $mail->envelopeRecipients();

        if ($recipients === []) {
            throw new MailException('Alıcı adresi yok.');
        }

        $raw = Mime::raw($mail, $from);

        try {
            $this->connect();
            $this->authenticate();

            $this->command('MAIL FROM:<' . Mime::stripNewlines($from[0]) . '>', [250]);

            foreach ($recipients as $recipient) {
                $this->command('RCPT TO:<' . Mime::stripNewlines($recipient) . '>', [250, 251]);
            }

            $this->command('DATA', [354]);

            // Nokta ile başlayan satırlar kaçırılmalıdır (RFC 5321):
            // yalnız başına bir "." mektubun bittiği anlamına gelir.
            $this->write(preg_replace('/^\./m', '..', $raw) . Mime::CRLF . '.' . Mime::CRLF);
            $this->expect([250]);

            $this->quit();
        } catch (MailException $e) {
            $this->close();

            throw $e;
        }
    }

    public function verify(): void
    {
        try {
            $this->connect();
            $this->authenticate();
            $this->quit();
        } catch (MailException $e) {
            $this->close();

            throw $e;
        }
    }

    /** @return array<int,string> Son konuşmanın dökümü. */
    public function conversation(): array
    {
        return $this->log;
    }

    /* =================================================================
     *  BAĞLANTI
     * ============================================================== */

    private function connect(): void
    {
        $this->log = [];

        $prefix = $this->encryption === 'ssl' ? 'ssl://' : '';

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client(
            $prefix . $this->host . ':' . $this->port,
            $errorCode,
            $errorMessage,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new MailException(sprintf(
                'SMTP sunucusuna bağlanılamadı (%s:%d): %s',
                $this->host,
                $this->port,
                $errorMessage !== '' ? $errorMessage : 'bilinmeyen hata #' . $errorCode
            ));
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, $this->timeout);

        $this->expect([220]);
        $this->command('EHLO ' . $this->heloName, [250]);

        if ($this->encryption === 'tls') {
            $this->command('STARTTLS', [220]);

            $crypto = @stream_socket_enable_crypto(
                $this->socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($crypto !== true) {
                throw new MailException('STARTTLS ile şifreli bağlantıya geçilemedi. Kapı numarasını (587) ve sunucu sertifikasını kontrol edin.');
            }

            // Şifreli hatta geçtikten sonra EHLO tekrarlanır: sunucu
            // yeteneklerini yeniden bildirir (ör. AUTH artık açıktır).
            $this->command('EHLO ' . $this->heloName, [250]);
        }
    }

    private function authenticate(): void
    {
        if ($this->username === '') {
            return;
        }

        // AUTH LOGIN: kullanıcı adı ve parola base64 ile ayrı ayrı
        // gönderilir. (Kodlama şifreleme DEĞİLDİR — güvenlik
        // TLS katmanından gelir, bu yüzden şifresiz bağlantıda
        // kimlik doğrulamaktan kaçının.)
        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($this->username), [334], '***kullanıcı***');
        $this->command(base64_encode($this->password), [235], '***parola***');
    }

    private function quit(): void
    {
        try {
            $this->command('QUIT', [221]);
        } catch (MailException) {
            // Sunucu vedalaşmadan kapatmış olabilir; mektup gitti,
            // bu aşamadaki hata kullanıcıyı ilgilendirmez.
        }

        $this->close();
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }

        $this->socket = null;
    }

    /* =================================================================
     *  DÜŞÜK SEVİYE KONUŞMA
     * ============================================================== */

    /** @param array<int,int> $expected */
    private function command(string $command, array $expected, ?string $logAs = null): string
    {
        $this->log[] = '> ' . ($logAs ?? $command);
        $this->write($command . Mime::CRLF);

        return $this->expect($expected);
    }

    private function write(string $data): void
    {
        if (!is_resource($this->socket)) {
            throw new MailException('SMTP bağlantısı kapalı.');
        }

        if (@fwrite($this->socket, $data) === false) {
            throw new MailException('SMTP sunucusuna veri yazılamadı.');
        }
    }

    /**
     * Sunucunun yanıtını okur ve beklenen durum kodlarından biri
     * değilse istisna fırlatır.
     *
     * @param array<int,int> $expected
     */
    private function expect(array $expected): string
    {
        $response = $this->read();
        $code     = (int) substr($response, 0, 3);

        if (!in_array($code, $expected, true)) {
            throw new MailException('SMTP sunucusu beklenmeyen yanıt verdi: ' . trim($response));
        }

        return $response;
    }

    /**
     * Çok satırlı yanıtları da okur. SMTP'de devam eden satırların
     * dördüncü karakteri "-", son satırınki boşluktur:
     *      250-STARTTLS
     *      250 AUTH LOGIN PLAIN     ← son satır
     */
    private function read(): string
    {
        if (!is_resource($this->socket)) {
            throw new MailException('SMTP bağlantısı kapalı.');
        }

        $response = '';

        while (true) {
            $line = @fgets($this->socket, 8192);

            if ($line === false) {
                $meta = stream_get_meta_data($this->socket);

                throw new MailException(
                    ($meta['timed_out'] ?? false)
                        ? 'SMTP sunucusu zaman aşımına uğradı.'
                        : 'SMTP sunucusundan yanıt okunamadı.'
                );
            }

            $response .= $line;
            $this->log[] = '< ' . rtrim($line);

            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        return $response;
    }
}
