<?php
/**
 * =====================================================================
 *  Page – Panelden yazılan içerik sayfası
 * ---------------------------------------------------------------------
 *  "Hakkımızda", "Gizlilik", "KVKK" gibi durağan sayfalar. İçerik
 *  zengin metin editöründen gelir ve KAYDEDİLİRKEN süzülür
 *  (bkz. App\Core\Html::sanitize) — yani buradaki $icerik ekrana
 *  kaçışlanmadan basılabilir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class Page
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $baslik,
        public readonly string  $slug,
        public readonly string  $ozet,
        public readonly string  $icerik,
        public readonly string  $kapak,
        public readonly string  $durum,
        public readonly bool    $menude,
        public readonly bool    $korumali,
        public readonly int     $sira,
        public readonly string  $seoBaslik,
        public readonly string  $seoAciklama,
        public readonly ?int    $yazarId,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $yazarAdi = null,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:          (int) ($row['id'] ?? 0),
            baslik:      (string) ($row['baslik'] ?? ''),
            slug:        (string) ($row['slug'] ?? ''),
            ozet:        (string) ($row['ozet'] ?? ''),
            icerik:      (string) ($row['icerik'] ?? ''),
            kapak:       (string) ($row['kapak'] ?? ''),
            durum:       (string) ($row['durum'] ?? 'taslak'),
            menude:      ((int) ($row['menude'] ?? 0)) === 1,
            korumali:    ((int) ($row['korumali'] ?? 0)) === 1,
            sira:        (int) ($row['sira'] ?? 0),
            seoBaslik:   (string) ($row['seo_baslik'] ?? ''),
            seoAciklama: (string) ($row['seo_aciklama'] ?? ''),
            yazarId:     isset($row['yazar_id']) ? (int) $row['yazar_id'] : null,
            createdAt:   isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt:   isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            yazarAdi:    isset($row['yazar_adi']) ? (string) $row['yazar_adi'] : null,
        );
    }

    public function yayinda(): bool
    {
        return $this->durum === 'yayin';
    }

    public function durumEtiketi(): string
    {
        return $this->yayinda() ? 'Yayında' : 'Taslak';
    }

    /** Sekmede görünecek başlık — SEO başlığı doldurulmuşsa o kazanır. */
    public function baslikSeo(): string
    {
        return $this->seoBaslik !== '' ? $this->seoBaslik : $this->baslik;
    }

    /**
     * Meta açıklaması.
     *
     * Sırayla: SEO açıklaması → özet → içeriğin ilk cümleleri.
     * Hiçbiri yoksa boş döner ve etiket hiç basılmaz; boş bir
     * description etiketi olmamasından daha kötüdür.
     */
    public function aciklamaSeo(): string
    {
        if ($this->seoAciklama !== '') {
            return $this->seoAciklama;
        }

        if ($this->ozet !== '') {
            return $this->ozet;
        }

        $duz = trim(preg_replace('/\s+/u', ' ', strip_tags($this->icerik)) ?? '');

        return $duz === '' ? '' : mb_strimwidth($duz, 0, 160, '…', 'UTF-8');
    }

    /** Listede gösterilecek kısa özet. */
    public function onizleme(int $length = 90): string
    {
        $kaynak = $this->ozet !== '' ? $this->ozet : strip_tags($this->icerik);
        $kaynak = trim(preg_replace('/\s+/u', ' ', $kaynak) ?? '');

        return mb_strimwidth($kaynak, 0, $length, '…', 'UTF-8');
    }

    /** İçerikteki kelime sayısı — panelde "ne kadar yazılmış" göstergesi. */
    public function kelimeSayisi(): int
    {
        $duz = trim(strip_tags($this->icerik));

        return $duz === '' ? 0 : count(preg_split('/\s+/u', $duz) ?: []);
    }

    public static function formatDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format('d.m.Y H:i');
        } catch (\Exception) {
            return $value;
        }
    }
}
