<?php
/**
 * =====================================================================
 *  Html – Zengin metin süzgeci ve slug üretici
 * ---------------------------------------------------------------------
 *  ZENGİN METİN EDİTÖRÜ BİR GÜVENLİK SORUNUDUR. Editör tarayıcıda
 *  HTML üretir ve o HTML sunucuya gönderilir; araya girip
 *  <script>, <iframe> ya da onerror="…" eklemek yalnızca istek
 *  gövdesini değiştirmek kadar kolaydır. Editörün kendisine
 *  ("nasılsa sadece kalın/italik üretiyor") GÜVENİLMEZ.
 *
 *  BU YÜZDEN: içerik KAYDEDİLİRKEN burada süzülür ve yalnızca
 *  izin verilen etiket/öznitelikler hayatta kalır. Ekrana basarken
 *  ikinci bir kaçışlama YAPILMAZ — zaten yapılsaydı sayfa HTML
 *  kaynak kodu olarak görünürdü.
 *
 *  Süzgeç "izin verilenler listesi" (allowlist) mantığıyla çalışır:
 *  tanımadığı her etiketi ve her özniteliği atar. Yeni bir etikete
 *  ihtiyaç duyulduğunda listeye eklenir — tersi (yasaklılar listesi)
 *  her zaman eksik kalır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

final class Html
{
    /**
     * İzin verilen etiketler → o etikette izin verilen öznitelikler.
     *
     * Liste bilerek kısa: bir içerik sayfasının ihtiyacı kadar.
     * <script>, <style>, <iframe>, <form>, <object> BİLEREK yok.
     *
     * @var array<string,array<int,string>>
     */
    private const ALLOWED = [
        'p'          => [],
        'br'         => [],
        'hr'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'u'          => [],
        's'          => [],
        'mark'       => [],
        'small'      => [],
        'sub'        => [],
        'sup'        => [],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
        'blockquote' => [],
        'pre'        => [],
        'code'       => [],
        'ul'         => [],
        'ol'         => [],
        'li'         => [],
        'dl'         => [],
        'dt'         => [],
        'dd'         => [],
        'a'          => ['href', 'title', 'target', 'rel'],
        'img'        => ['src', 'alt', 'title', 'width', 'height'],
        'figure'     => [],
        'figcaption' => [],
        'table'      => [],
        'thead'      => [],
        'tbody'      => [],
        'tr'         => [],
        'th'         => ['colspan', 'rowspan'],
        'td'         => ['colspan', 'rowspan'],
        'div'        => [],
        'span'       => [],
    ];

    /** Adres taşıyan özniteliklerde kabul edilen şemalar. */
    private const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Zengin metni güvenli HTML'e indirger.
     *
     * Boş ya da yalnızca boşluk içeren girdi için boş dize döner —
     * "<p><br></p>" gibi editör artıkları içerik SAYILMAZ, aksi
     * halde "içerik boş mu?" kontrolleri hep yanlış cevap verirdi.
     */
    public static function sanitize(string $html): string
    {
        $html = trim($html);

        /* strip_tags'e <img> tanıtıyoruz: yalnızca görsel içeren bir
         * içerik de doludur, metni olmadığı için boş sayılmamalı. */
        if (trim(strip_tags($html, '<img>')) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        /* libxml uyarılarını bastırıyoruz: girdi kullanıcıdan gelir,
         * "geçersiz etiket" uyarısı hata günlüğünü doldurmamalı.
         * HTML_PARSE_* bayrakları <html>/<body> sarmalayıcılarının
         * eklenmesini engeller; biz yalnızca parçayı işliyoruz. */
        $onceki = libxml_use_internal_errors(true);

        $yuklendi = $document->loadHTML(
            '<?xml encoding="UTF-8"?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($onceki);

        /* NOIMPLIED sayesinde <html>/<body> eklenmez: sardığımız div
         * doğrudan belge kökü olur. getElementById kullanmıyoruz —
         * DTD olmadan "id" özniteliği gerçek bir ID sayılmaz ve o
         * metot güvenilmez biçimde null döner. */
        $kok = $document->documentElement;

        if (!$yuklendi || $kok === null) {
            return '';
        }

        self::clean($kok);

        $cikti = '';

        foreach (iterator_to_array($kok->childNodes) as $child) {
            $cikti .= $document->saveHTML($child);
        }

        return trim($cikti);
    }

    /**
     * Düğümü ve tüm alt ağacını yerinde temizler.
     *
     * İzin verilmeyen bir ETİKET silinirken İÇERİĞİ korunur
     * (<font> gibi zararsız ama gereksiz sarmalayıcılar metni
     * yutmasın) — ancak <script>/<style> gibi tehlikeli olanlarda
     * içerik de gider; onların "metni" zaten koddur.
     */
    private static function clean(DOMNode $node): void
    {
        $tehlikeli = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta'];

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $etiket = strtolower($child->nodeName);

                if (in_array($etiket, $tehlikeli, true)) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                if (!array_key_exists($etiket, self::ALLOWED)) {
                    self::clean($child);
                    self::unwrap($child);

                    continue;
                }

                self::cleanAttributes($child, $etiket);

                // Adresi süzgece takılan görsel boş bir kutu olarak kalmasın.
                if ($etiket === 'img' && !$child->hasAttribute('src')) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                self::clean($child);

                continue;
            }

            /* Yorum düğümleri: içerik değildir ve koşullu yorumlar
             * (eski IE) HTML enjeksiyonu için kullanılabilir. */
            if ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $element, string $etiket): void
    {
        $izinli = self::ALLOWED[$etiket];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $ad = strtolower($attribute->nodeName);

            if (!in_array($ad, $izinli, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (($ad === 'href' || $ad === 'src') && !self::safeUrl((string) $attribute->nodeValue)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            /* Görsel YALNIZCA kendi alan adımızdan (ya da CSP_IMG_SRC ile
             * izin verilen adreslerden) gelir. Başka bir sitedeki görsel
             * zaten CSP tarafından engellenip kırık görünürdü; ayrıca her
             * ziyaretçinin IP'sini o siteye sızdırırdı. */
            if ($ad === 'src' && $etiket === 'img' && !self::localImage((string) $attribute->nodeValue)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        /* Yeni sekmede açılan bağlantıya rel="noopener" ŞARTTIR:
         * onsuz açılan sayfa window.opener üzerinden bizim sayfamızı
         * başka bir adrese yönlendirebilir (tabnabbing). */
        if ($etiket === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /** Etiketi kaldırır, çocuklarını bulunduğu yere taşır. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /**
     * Adres güvenli mi?
     *
     * "javascript:alert(1)" en bilinen XSS taşıyıcısıdır; "data:"
     * ise SVG içine betik gizlemeye yarar. Göreli adresler
     * (/hakkimizda, iletisim, #bolum) serbesttir — şemaları yoktur.
     */
    private static function safeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        /* Şema arıyoruz: ilk ":" karakterinden önce "/" ya da "?"
         * varsa bu bir şema değil, göreli yoldur. */
        $iki = strpos($url, ':');

        if ($iki === false) {
            return true;
        }

        $onEk = substr($url, 0, $iki);

        if (preg_match('/[\/?#]/', $onEk) === 1) {
            return true;
        }

        return in_array(strtolower(trim($onEk)), self::SCHEMES, true);
    }

    /**
     * Görsel adresi bu siteye mi ait?
     *
     * Göreli adres ("/upload/img/sayfa/a.webp", "upload/…") her zaman
     * yereldir. Tam adreste alan adı ya APP_URL'inki ya da .env'deki
     * CSP_IMG_SRC listesinde olmalıdır ("*.ornek.com" deseni dahil).
     * "//baska.com/x.png" gibi şemasız tam adresler de tam adres sayılır.
     */
    public static function localImage(string $src): bool
    {
        $src = trim($src);

        if (str_starts_with($src, '//')) {
            $src = 'https:' . $src;
        }

        $host = parse_url($src, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return preg_match('~^[a-z][a-z0-9+.\-]*:~i', $src) !== 1;   // şemalı ama alan adsız: hayır
        }

        $host   = strtolower($host);
        $izinli = [strtolower((string) parse_url(Url::origin(), PHP_URL_HOST))];

        foreach ((array) Config::get('security.csp_extra.img-src', []) as $kaynak) {
            $kaynak = strtolower(trim((string) $kaynak));
            $izinli[] = (string) (parse_url(str_contains($kaynak, '//') ? $kaynak : 'https://' . $kaynak, PHP_URL_HOST) ?: '');
        }

        foreach (array_filter($izinli) as $alan) {
            if ($host === $alan || (str_starts_with($alan, '*.') && str_ends_with($host, substr($alan, 1)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * HTML'den tek satırlık düz metin (özet, meta açıklama, kelime sayımı).
     *
     * strip_tags tek başına blok sınırlarını SİLER ama yerine boşluk
     * koymaz: "<h2>Biz kimiz?</h2><p>Bu metni…" → "Biz kimiz?Bu metni…".
     * Ana sayfadaki Hakkımızda özeti ve sayfaların meta açıklaması
     * böyle bitişik cümlelerle çıkıyordu. Önce her blok ve satır sonu
     * etiketinin yerine boşluk konur, sonra etiketler silinir.
     */
    public static function toText(string $html): string
    {
        $html = preg_replace('#<(br|hr)\b[^>]*>|</?(p|div|h[1-6]|li|ul|ol|blockquote|pre|tr|td|th|table|section|article|figure|figcaption)\b[^>]*>#i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Başlıktan adres parçası (slug) üretir.
     *
     * Türkçe harfler ASCII karşılıklarına çevrilir: "Sıkça Sorulan
     * Sorular" → "sikca-sorulan-sorular". Adreste yüzde kodlu
     * karakterler (%C3%A7) hem çirkin hem de paylaşırken bozulur.
     */
    public static function slug(string $value): string
    {
        $tr = ['ş' => 's', 'Ş' => 's', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g',
               'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c'];

        $value = strtr(trim($value), $tr);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';

        return trim($value, '-');
    }
}
