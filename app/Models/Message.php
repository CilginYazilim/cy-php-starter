<?php
/**
 * =====================================================================
 *  Message – İletişim formundan gelen mesaj
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class Message
{
    public function __construct(
        public readonly int    $id,
        public readonly string $ad,
        public readonly string $eposta,
        public readonly string $konu,
        public readonly string $mesaj,
        public readonly bool   $okundu,
        public readonly ?int   $kullaniciId,
        public readonly string $ip,
        public readonly string $tarayici,
        public readonly ?string $createdAt,
        public readonly ?string $gonderenKullaniciAdi = null,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int) ($row['id'] ?? 0),
            ad:           (string) ($row['ad'] ?? ''),
            eposta:       (string) ($row['eposta'] ?? ''),
            konu:         (string) ($row['konu'] ?? ''),
            mesaj:        (string) ($row['mesaj'] ?? ''),
            okundu:       ((int) ($row['okundu'] ?? 0)) === 1,
            kullaniciId:  isset($row['kullanici_id']) ? (int) $row['kullanici_id'] : null,
            ip:           (string) ($row['ip'] ?? ''),
            tarayici:     (string) ($row['tarayici'] ?? ''),
            createdAt:    isset($row['created_at']) ? (string) $row['created_at'] : null,
            gonderenKullaniciAdi: isset($row['kullanici_adi']) ? (string) $row['kullanici_adi'] : null,
        );
    }

    public function preview(int $length = 70): string
    {
        return mb_substr(trim($this->mesaj), 0, $length, 'UTF-8');
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
