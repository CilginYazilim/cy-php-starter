<?php
/**
 * =====================================================================
 *  MailLog – Gönderilen (ya da kuyrukta bekleyen) tek bir mektup kaydı
 * ---------------------------------------------------------------------
 *  "mail_kayitlari" tablosunun bir satırının nesne karşılığıdır.
 *  Panelde e-posta geçmişini listelerken ve kuyruğu işlerken kullanılır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class MailLog
{
    public const DURUM_KUYRUKTA   = 'kuyrukta';
    /** Bir süreç tarafından ayrılmış, şu an gönderiliyor (bkz. MailRepository::claim). */
    public const DURUM_GONDERILIYOR = 'gonderiliyor';
    public const DURUM_GONDERILDI = 'gonderildi';
    public const DURUM_BASARISIZ  = 'basarisiz';

    /**
     * GÜVENLİK BAĞLANTISI taşıyan şablonlar. Gövdeleri yalnızca
     * kullanıcıları yönetebilen rollere gösterilir: e-posta geçmişini
     * okuyabilen bir editör, başkasının doğrulama ya da parola sıfırlama
     * bağlantısını açıp o hesabı ele geçirebilirdi.
     */
    public const GUVENLIK_SABLONLARI = ['dogrulama', 'parola-sifirlama'];

    public function isSensitive(): bool
    {
        return in_array($this->sablon, self::GUVENLIK_SABLONLARI, true);
    }

    /** "elif.demir@ornek.com" → "e***@ornek.com" */
    public static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return $email === '' ? '' : mb_substr($email, 0, 1) . '***';
        }

        return mb_substr($email, 0, 1) . '***' . substr($email, $at);
    }

    public function __construct(
        public readonly int     $id,
        public readonly string  $aliciEposta,
        public readonly string  $aliciAd,
        public readonly string  $konu,
        public readonly string  $sablon,
        public readonly string  $tur,
        public readonly string  $durum,
        public readonly string  $hata,
        public readonly int     $deneme,
        public readonly ?int    $kullaniciId,
        public readonly ?int    $gonderenId,
        public readonly string  $topluId,
        public readonly ?string $gonderildiAt,
        public readonly ?string $createdAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int) ($row['id'] ?? 0),
            aliciEposta:  (string) ($row['alici_eposta'] ?? ''),
            aliciAd:      (string) ($row['alici_ad'] ?? ''),
            konu:         (string) ($row['konu'] ?? ''),
            sablon:       (string) ($row['sablon'] ?? 'genel'),
            tur:          (string) ($row['tur'] ?? 'bildirim'),
            durum:        (string) ($row['durum'] ?? self::DURUM_KUYRUKTA),
            hata:         (string) ($row['hata'] ?? ''),
            deneme:       (int) ($row['deneme'] ?? 0),
            kullaniciId:  isset($row['kullanici_id']) ? (int) $row['kullanici_id'] : null,
            gonderenId:   isset($row['gonderen_id']) ? (int) $row['gonderen_id'] : null,
            topluId:      (string) ($row['toplu_id'] ?? ''),
            gonderildiAt: isset($row['gonderildi_at']) ? (string) $row['gonderildi_at'] : null,
            createdAt:    isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }

    /** @return array<string,string> Durum → arayüzde görünen etiket */
    public static function statusLabels(): array
    {
        return [
            self::DURUM_KUYRUKTA   => 'Kuyrukta',
            self::DURUM_GONDERILIYOR => 'Gönderiliyor',
            self::DURUM_GONDERILDI => 'Gönderildi',
            self::DURUM_BASARISIZ  => 'Başarısız',
        ];
    }

    /** @return array<string,string> Tür → arayüzde görünen etiket */
    public static function typeLabels(): array
    {
        return [
            'bildirim' => 'Bildirim',
            'iletisim' => 'İletişim Formu',
            'otomatik' => 'Otomatik Yanıt',
            'toplu'    => 'Toplu Gönderim',
            'test'     => 'Sınama',
            'sistem'   => 'Sistem',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->durum] ?? $this->durum;
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->tur] ?? $this->tur;
    }

    /** cy-status bileşeninin renk sınıfı. */
    public function statusVariant(): string
    {
        return match ($this->durum) {
            self::DURUM_GONDERILDI => 'is-active',
            self::DURUM_BASARISIZ  => 'is-danger',
            default                => 'is-hold',
        };
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
