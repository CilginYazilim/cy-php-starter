<?php
/**
 * =====================================================================
 *  Mailable – Gönderilecek TEK bir mektup
 * ---------------------------------------------------------------------
 *  Zincirleme (fluent) kurulur, sonra Mailer'a verilir:
 *
 *      $mail = Mailable::make()
 *          ->to('ali@ornek.com', 'Ali Veli')
 *          ->subject('Hoş geldiniz')
 *          ->view('emails/welcome', ['user' => $user]);
 *
 *      Mailer::send($mail);
 *
 *  NEDEN AYRI BİR SINIF? Gönderim mantığı (SMTP konuşması) ile
 *  mektubun İÇERİĞİ birbirinden bağımsızdır. Ayrı tutunca mektubu
 *  göndermeden önce ekranda önizleyebilir, kuyruğa yazabilir ya da
 *  testte gerçekten göndermeden içeriğini doğrulayabilirsiniz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\View;

final class Mailable
{
    /** @var array<int,array{0:string,1:string}> */
    private array $to = [];

    /** @var array<int,array{0:string,1:string}> */
    private array $cc = [];

    /** @var array<int,array{0:string,1:string}> */
    private array $bcc = [];

    /** @var array{0:string,1:string}|null */
    private ?array $from = null;

    /** @var array{0:string,1:string}|null */
    private ?array $replyTo = null;

    private string $subject = '';
    private string $html    = '';
    private string $text    = '';

    /** @var array<int,array{ad:string,tur:string,icerik:string}> */
    private array $attachments = [];

    /** @var array<string,string> Ek başlıklar (X-... gibi) */
    private array $headers = [];

    /** Kayıt/kuyruk tablosunda hangi şablonla üretildiğini gösterir. */
    private string $template = 'genel';

    /** Kayıt tablosundaki "tur" sütunu: bildirim|iletisim|otomatik|toplu|test|sistem */
    private string $type = 'bildirim';

    private ?int $userId = null;

    public static function make(): self
    {
        return new self();
    }

    /* =================================================================
     *  ALICILAR
     * ============================================================== */

    public function to(string $email, string $name = ''): self
    {
        $this->to[] = [$email, $name];

        return $this;
    }

    public function cc(string $email, string $name = ''): self
    {
        $this->cc[] = [$email, $name];

        return $this;
    }

    public function bcc(string $email, string $name = ''): self
    {
        $this->bcc[] = [$email, $name];

        return $this;
    }

    public function from(string $email, string $name = ''): self
    {
        $this->from = [$email, $name];

        return $this;
    }

    /**
     * Yanıt adresi. İletişim formunda ÇOK ÖNEMLİDİR: mektup sizin
     * sunucunuzdan çıkar (From: siteniz), ama "Yanıtla" dediğinizde
     * doğrudan ziyaretçiye gitmesini isteriz.
     */
    public function replyTo(string $email, string $name = ''): self
    {
        $this->replyTo = [$email, $name];

        return $this;
    }

    /* =================================================================
     *  İÇERİK
     * ============================================================== */

    public function subject(string $subject): self
    {
        $this->subject = trim($subject);

        return $this;
    }

    public function html(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Düz metin sürümü. Vermezseniz HTML'den otomatik üretilir —
     * ama e-posta istemcilerinin bir kısmı (ve spam filtreleri) düz
     * metin alternatifini görmeyi sever, o yüzden HER ZAMAN gönderiyoruz.
     */
    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Gövdeyi bir görünüm dosyasından üretir (views/emails/...).
     *
     * İçerik varsayılan olarak "emails/layout" şablonuna sarılır:
     * başlıkta logonuz, altta site adı ve yasal not. Sarmalamak
     * istemiyorsanız üçüncü parametreye null verin.
     *
     * Düzene mektubun konusu "mailKonu" adıyla geçilir — "konu" DEĞİL.
     * Çünkü iç şablonların çoğu kendi "konu" değişkenini kullanır
     * (ör. iletişim bildiriminde ziyaretçinin yazdığı konu); ikisi
     * aynı adı paylaşsaydı biri diğerini ezerdi.
     *
     * @param array<string,mixed> $data
     */
    public function view(string $view, array $data = [], ?string $layout = 'emails/layout'): self
    {
        $this->template = str_starts_with($view, 'emails/') ? substr($view, 7) : $view;

        $content = View::capture($view, $data);

        $this->html = $layout === null
            ? $content
            : View::capture($layout, [
                'content'  => $content,
                'mailKonu' => $this->subject,
                'onizleme' => $data['onizleme'] ?? $this->subject,
            ]);

        return $this;
    }

    /** Ham veriden dosya ekler (diskte olması gerekmez). */
    public function attachData(string $filename, string $contents, string $mime = 'application/octet-stream'): self
    {
        $this->attachments[] = [
            'ad'      => $filename,
            'tur'     => $mime,
            'icerik'  => $contents,
        ];

        return $this;
    }

    /** Diskteki bir dosyayı ekler. */
    public function attach(string $path, string $filename = '', string $mime = 'application/octet-stream'): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new MailException('Eklenecek dosya okunamadı: ' . $path);
        }

        return $this->attachData(
            $filename !== '' ? $filename : basename($path),
            (string) file_get_contents($path),
            $mime
        );
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /* =================================================================
     *  ÜST VERİ (kayıt tablosu için)
     * ============================================================== */

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function forUser(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function template(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    /* =================================================================
     *  OKUYUCULAR
     * ============================================================== */

    /** @return array<int,array{0:string,1:string}> */
    public function recipients(): array
    {
        return $this->to;
    }

    /** @return array<int,array{0:string,1:string}> */
    public function ccList(): array
    {
        return $this->cc;
    }

    /** @return array<int,array{0:string,1:string}> */
    public function bccList(): array
    {
        return $this->bcc;
    }

    /** @return array{0:string,1:string}|null */
    public function sender(): ?array
    {
        return $this->from;
    }

    /** @return array{0:string,1:string}|null */
    public function replyAddress(): ?array
    {
        return $this->replyTo;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function getText(): string
    {
        return $this->text !== '' ? $this->text : self::htmlToText($this->html);
    }

    /** @return array<int,array{ad:string,tur:string,icerik:string}> */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /** @return array<string,string> */
    public function extraHeaders(): array
    {
        return $this->headers;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Zarfın gideceği bütün adresler (To + Cc + Bcc). SMTP'de "RCPT TO"
     * komutu her biri için ayrı ayrı verilir.
     *
     * @return array<int,string>
     */
    public function envelopeRecipients(): array
    {
        $all = [];

        foreach ([...$this->to, ...$this->cc, ...$this->bcc] as $address) {
            $all[] = $address[0];
        }

        return array_values(array_unique($all));
    }

    /* =================================================================
     *  YARDIMCI
     * ============================================================== */

    /**
     * HTML gövdeden okunabilir düz metin üretir.
     *
     * NEDEN GEREKLİ? Her mektup iki sürüm taşır: HTML ve düz metin.
     * Düz metin sürümünü hem HTML gösteremeyen istemciler hem de spam
     * filtreleri okur — düz metin alternatifi olmayan mektupların
     * gereksiz klasörüne düşme ihtimali belirgin biçimde yüksektir.
     *
     * Kusursuz bir dönüştürücü değildir; amacı mektubun HTML'siz de
     * anlaşılır kalmasıdır. Tabloları "Etiket: Değer" satırlarına
     * indirger, bağlantıları "metin (adres)" biçiminde korur.
     */
    public static function htmlToText(string $html): string
    {
        // Ön izleme bloğu (gelen kutusu için gizli metin) ve
        // <style>/<script> içerikleri düz metinde işimize yaramaz.
        // <head> bloğu (title, meta) düz metinde görünmemeli — yoksa
        // mektup konuyu iki kez yazarak başlar.
        $text = (string) preg_replace('#<head\b[^>]*>.*?</head>#is', '', $html);
        $text = (string) preg_replace('#<div\b[^>]*\bdata-preheader\b[^>]*>.*?</div>#is', '', $text);
        $text = (string) preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', '', $text);

        // Bağlantıları "metin (adres)" biçiminde koru.
        $text = (string) preg_replace('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '$2 ($1)', $text);

        // Tablo hücreleri sekmeye, satırlar satır sonuna dönüşür;
        // aşağıda sekmeler "Etiket: Değer" biçimine çevrilecek.
        $text = (string) preg_replace('#</(td|th)>#i', "\t", $text);

        // Satır kıran etiketleri gerçek satır sonuna çevir.
        // Paragraf ve başlık sonlarına ÇİFT satır koyuyoruz: aşağıdaki
        // temizlik ardışık boşları teke indirdiği için sonuçta
        // paragraflar arasında tam olarak bir boş satır kalır.
        $text = (string) preg_replace('#<(br|hr)\s*/?>#i', "\n", $text);
        $text = (string) preg_replace('#</(p|h[1-6])>#i', "\n\n", $text);
        $text = (string) preg_replace('#</(div|tr|li|table)>#i', "\n", $text);

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        /* Satır satır temizlik: düzen tabloları çok sayıda boş hücre
         * üretir, hepsini olduğu gibi bırakırsak metin boş satır
         * çölüne döner. */
        $lines = [];

        foreach (explode("\n", $text) as $line) {
            // Aynı satırdaki hücreleri "Etiket: Değer" yap.
            $cells = array_values(array_filter(
                array_map(static fn (string $cell): string => trim($cell), explode("\t", $line)),
                static fn (string $cell): bool => $cell !== ''
            ));

            $line = trim((string) preg_replace('/[^\S\n]+/u', ' ', implode(': ', $cells)));

            // Ardışık boş satırları tek boş satıra indir.
            if ($line === '' && ($lines === [] || end($lines) === '')) {
                continue;
            }

            $lines[] = $line;
        }

        return trim(implode("\n", $lines));
    }
}
