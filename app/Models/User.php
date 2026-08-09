<?php
/**
 * =====================================================================
 *  User – Kullanıcı varlığı (Entity)
 * ---------------------------------------------------------------------
 *  Veritabanı sütun adları TÜRKÇE kalır (ad, soyad, eposta ...) — bu
 *  şablonun tüm veritabanı sözlüğü Türkçedir. Nesne özellik adları da
 *  tutarlılık için aynı isimleri kullanır.
 *
 *  DURUM: 'aktif' | 'pasif' | 'askida'
 *  Yalnızca 'aktif' olan hesap giriş yapabilir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Uploader;
use DateTimeImmutable;

final class User
{
    public function __construct(
        public readonly int    $id,
        public readonly string $ad,
        public readonly string $soyad,
        public readonly string $kullaniciAdi,
        public readonly string $eposta,
        public readonly string $rol,
        public readonly string $durum   = 'aktif',
        public readonly string $tema    = 'acik',
        public readonly string $avatar  = '',
        public readonly string $telefon = '',
        public readonly string $hakkinda = '',
        public readonly ?string $sonGiris   = null,
        public readonly string  $sonGirisIp = '',
        public readonly int     $girisSayisi = 0,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        private readonly string $sifreHash = '',
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int) ($row['id'] ?? 0),
            ad:           (string) ($row['ad'] ?? ''),
            soyad:        (string) ($row['soyad'] ?? ''),
            kullaniciAdi: (string) ($row['kullanici_adi'] ?? ''),
            eposta:       (string) ($row['eposta'] ?? ''),
            rol:          (string) ($row['rol'] ?? Role::MEMBER),
            durum:        (string) ($row['durum'] ?? 'aktif'),
            tema:         (string) ($row['tema'] ?? 'acik'),
            avatar:       (string) ($row['avatar'] ?? ''),
            telefon:      (string) ($row['telefon'] ?? ''),
            hakkinda:     (string) ($row['hakkinda'] ?? ''),
            sonGiris:     isset($row['son_giris']) ? (string) $row['son_giris'] : null,
            sonGirisIp:   (string) ($row['son_giris_ip'] ?? ''),
            girisSayisi:  (int) ($row['giris_sayisi'] ?? 0),
            createdAt:    isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt:    isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            sifreHash:    (string) ($row['sifre'] ?? ''),
        );
    }

    public function fullName(): string
    {
        return trim($this->ad . ' ' . $this->soyad);
    }

    public function initials(): string
    {
        $first = mb_substr($this->ad, 0, 1, 'UTF-8');
        $last  = mb_substr($this->soyad, 0, 1, 'UTF-8');
        $value = trim($first . $last);

        return $value === '' ? '?' : mb_strtoupper($value, 'UTF-8');
    }

    public function avatarUrl(): string
    {
        return $this->avatar !== '' ? Uploader::url($this->avatar) : '';
    }

    public function roleLabel(): string
    {
        return Role::label($this->rol);
    }

    public function statusLabel(): string
    {
        return match ($this->durum) {
            'aktif'  => 'Aktif',
            'pasif'  => 'Pasif',
            'askida' => 'Askıda',
            default  => $this->durum,
        };
    }

    public function isActive(): bool
    {
        return $this->durum === 'aktif';
    }

    /** "dark" | "light" — HTML'deki data-cy-theme özniteliğinde doğrudan kullanılır. */
    public function themeAttr(): string
    {
        return $this->tema === 'koyu' ? 'dark' : 'light';
    }

    public function isAdmin(): bool
    {
        return $this->rol === Role::ADMIN;
    }

    public function can(string $ability): bool
    {
        return $this->isActive() && Role::can($this->rol, $ability);
    }

    public function verifyPassword(string $plain): bool
    {
        return $this->sifreHash !== '' && password_verify($plain, $this->sifreHash);
    }

    public function needsRehash(): bool
    {
        return $this->sifreHash !== '' && password_needs_rehash($this->sifreHash, PASSWORD_DEFAULT);
    }

    /** @return array<string,mixed> DİKKAT: sifreHash bilerek yok. */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'ad'            => $this->ad,
            'soyad'         => $this->soyad,
            'ad_soyad'      => $this->fullName(),
            'kullanici_adi' => $this->kullaniciAdi,
            'eposta'        => $this->eposta,
            'rol'           => $this->rol,
            'rol_etiket'    => $this->roleLabel(),
            'durum'         => $this->durum,
            'durum_etiket'  => $this->statusLabel(),
            'telefon'       => $this->telefon,
            'hakkinda'      => $this->hakkinda,
            'avatar'        => $this->avatar,
            'avatar_url'    => $this->avatarUrl(),
            'son_giris'     => self::formatDate($this->sonGiris),
            'created_at'    => self::formatDate($this->createdAt),
        ];
    }

    public static function formatDate(?string $value): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format('d.m.Y H:i');
        } catch (\Exception) {
            return $value;
        }
    }
}
