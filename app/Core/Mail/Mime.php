<?php
/**
 * =====================================================================
 *  Mime – Mailable'ı "telde giden" ham metne çevirir
 * ---------------------------------------------------------------------
 *  Bir e-posta aslında düz metindir: önce başlıklar, sonra boş bir
 *  satır, sonra gövde. Türkçe karakterler ve dosya ekleri işin içine
 *  girince MIME kuralları devreye girer:
 *
 *      multipart/mixed                 ← ek varsa en dış katman
 *        multipart/alternative         ← düz metin + HTML birlikte
 *          text/plain                  ← HTML göremeyen istemciler
 *          text/html                   ← herkesin gördüğü sürüm
 *        application/pdf (ek)          ← base64 kodlanmış dosya
 *
 *  ÖNEMLİ: E-posta protokolünde satır sonu HER ZAMAN CRLF'tir (\r\n).
 *  Tek başına \n gönderirseniz bazı sunucular mektubu bozuk sayar.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

final class Mime
{
    public const CRLF = "\r\n";

    /**
     * Mektubun başlıklarını ve gövdesini ayrı ayrı üretir.
     *
     * @param array{0:string,1:string} $from Varsayılan gönderen [adres, ad]
     * @return array{headers:array<string,string>,body:string}
     */
    public static function build(Mailable $mail, array $from): array
    {
        $sender = $mail->sender() ?? $from;

        $headers = [
            'From'         => self::address($sender[0], $sender[1]),
            'To'           => self::addressList($mail->recipients()),
            'Subject'      => self::encodeHeader($mail->getSubject()),
            'Date'         => date('r'),
            'Message-ID'   => self::messageId($sender[0]),
            'MIME-Version' => '1.0',
        ];

        if ($mail->ccList() !== []) {
            $headers['Cc'] = self::addressList($mail->ccList());
        }

        // Bcc BAŞLIĞA YAZILMAZ: "gizli karbon kopya" adresleri
        // zarfta (RCPT TO) taşınır, mektubun içinde değil.

        $reply = $mail->replyAddress();

        if ($reply !== null) {
            $headers['Reply-To'] = self::address($reply[0], $reply[1]);
        }

        foreach ($mail->extraHeaders() as $name => $value) {
            $headers[$name] = self::encodeHeader($value);
        }

        [$contentType, $body] = self::buildBody($mail);

        $headers['Content-Type'] = $contentType;

        return ['headers' => $headers, 'body' => $body];
    }

    /** Başlıkları ve gövdeyi tek bir ham mektuba birleştirir (SMTP DATA için). */
    public static function raw(Mailable $mail, array $from): string
    {
        $built = self::build($mail, $from);

        $lines = [];

        foreach ($built['headers'] as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode(self::CRLF, $lines) . self::CRLF . self::CRLF . $built['body'];
    }

    /**
     * Gövdeyi kurar.
     *
     * @return array{0:string,1:string} [Content-Type başlığı, gövde]
     */
    private static function buildBody(Mailable $mail): array
    {
        $altBoundary = self::boundary('alt');

        // 1) Düz metin + HTML ikilisi
        $alternative =
              '--' . $altBoundary . self::CRLF
            . 'Content-Type: text/plain; charset=UTF-8' . self::CRLF
            . 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF
            . self::encodeBody($mail->getText()) . self::CRLF
            . '--' . $altBoundary . self::CRLF
            . 'Content-Type: text/html; charset=UTF-8' . self::CRLF
            . 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF
            . self::encodeBody($mail->getHtml()) . self::CRLF
            . '--' . $altBoundary . '--' . self::CRLF;

        $attachments = $mail->getAttachments();

        if ($attachments === []) {
            return ['multipart/alternative; boundary="' . $altBoundary . '"', $alternative];
        }

        // 2) Ek varsa hepsini "mixed" katmanına sar
        $mixBoundary = self::boundary('mix');

        $body =
              '--' . $mixBoundary . self::CRLF
            . 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . self::CRLF . self::CRLF
            . $alternative;

        foreach ($attachments as $file) {
            $body .=
                  '--' . $mixBoundary . self::CRLF
                . 'Content-Type: ' . $file['tur'] . '; name="' . self::encodeHeader($file['ad']) . '"' . self::CRLF
                . 'Content-Transfer-Encoding: base64' . self::CRLF
                . 'Content-Disposition: attachment; filename="' . self::encodeHeader($file['ad']) . '"' . self::CRLF . self::CRLF
                . self::encodeBody($file['icerik']) . self::CRLF;
        }

        $body .= '--' . $mixBoundary . '--' . self::CRLF;

        return ['multipart/mixed; boundary="' . $mixBoundary . '"', $body];
    }

    /** Gövdeyi base64'e çevirir ve 76 karakterlik satırlara böler. */
    private static function encodeBody(string $content): string
    {
        return rtrim(chunk_split(base64_encode($content), 76, self::CRLF), self::CRLF);
    }

    /**
     * Başlıklarda Türkçe karakter kullanmak için "encoded-word"
     * biçimi (RFC 2047) gerekir: =?UTF-8?B?...?=
     * ASCII dışı karakter yoksa boşuna kodlamayız.
     */
    public static function encodeHeader(string $value): string
    {
        $value = self::stripNewlines($value);

        if ($value === '' || preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return $value;
        }

        return mb_encode_mimeheader($value, 'UTF-8', 'B', self::CRLF);
    }

    /** "Ad Soyad <adres@ornek.com>" biçiminde tek adres üretir. */
    public static function address(string $email, string $name = ''): string
    {
        $email = self::stripNewlines($email);

        if ($name === '') {
            return $email;
        }

        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    /** @param array<int,array{0:string,1:string}> $addresses */
    public static function addressList(array $addresses): string
    {
        $parts = [];

        foreach ($addresses as $address) {
            $parts[] = self::address($address[0], $address[1] ?? '');
        }

        return implode(', ', $parts);
    }

    /**
     * BAŞLIK ENJEKSİYONU KORUMASI.
     *
     * Bir saldırgan iletişim formundaki "ad" alanına satır sonu
     * karakteri koyup arkasına "Bcc: kurban@..." yazarsa, sunucunuz
     * spam makinesine döner. Başlığa giren HER değerden satır sonu
     * karakterlerini söküyoruz.
     */
    public static function stripNewlines(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }

    private static function boundary(string $prefix): string
    {
        return '=_cy_' . $prefix . '_' . bin2hex(random_bytes(12));
    }

    private static function messageId(string $senderEmail): string
    {
        $host = substr((string) strrchr($senderEmail, '@'), 1);

        if ($host === '' || $host === false) {
            $host = 'localhost';
        }

        return '<' . bin2hex(random_bytes(16)) . '@' . $host . '>';
    }
}
