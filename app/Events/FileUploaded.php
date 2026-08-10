<?php
/**
 * =====================================================================
 *  OLAY: Dosya yüklendi
 * ---------------------------------------------------------------------
 *  Dosya doğrulanıp diske YAZILDIKTAN sonra yayınlanır.
 *
 *  TİPİK DİNLEYİCİLER
 *    · Virüs taraması kuyruğa almak
 *    · Küçük resim (thumbnail) üretmek
 *    · Belge yönetim modülünde kayıt açmak
 *    · Kota/kullanım sayacı güncellemek
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Events;

use App\Core\Events\Event;
use App\Core\Storage\StoredFile;

final class FileUploaded extends Event
{
    public function __construct(
        public readonly StoredFile $dosya,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'yol'       => $this->dosya->yol,
            'disk'      => $this->dosya->disk,
            'mime'      => $this->dosya->mime,
            'boyut'     => $this->dosya->boyut,
            'kullanici' => $this->userId,
        ];
    }
}
