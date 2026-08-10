<?php
/**
 * =====================================================================
 *  StoredFile – Diske yazılmış bir dosyanın künyesi
 * ---------------------------------------------------------------------
 *  Uploader::store() bunu döndürür. Veritabanına genelde şu dördü
 *  yazılır:
 *
 *      yol   → diskteki rastgele ad ("belge/ab12….pdf")
 *      disk  → hangi diskte ("public" / "private")
 *      ad    → kullanıcının gördüğü özgün ad ("Mart Faturası.pdf")
 *      mime  → gerçek içerik türü
 *
 *  Diskteki ad ile görünen adı AYRI tutmak önemlidir: dosyayı
 *  indirirken kullanıcıya kendi verdiği adı gösterirsiniz, ama
 *  sunucuda hiçbir zaman onun seçtiği ad kullanılmaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Storage;

final class StoredFile
{
    public function __construct(
        public readonly string $yol,
        public readonly string $disk,
        public readonly string $ad,
        public readonly string $mime,
        public readonly int    $boyut,
    ) {
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->yol);
    }

    public function humanSize(): string
    {
        return Storage::humanSize($this->boyut);
    }

    public function uzanti(): string
    {
        return strtolower(pathinfo($this->yol, PATHINFO_EXTENSION));
    }

    /** @return array<string,mixed> Veritabanına yazmaya hazır dizi */
    public function toArray(): array
    {
        return [
            'yol'   => $this->yol,
            'disk'  => $this->disk,
            'ad'    => $this->ad,
            'mime'  => $this->mime,
            'boyut' => $this->boyut,
        ];
    }
}
