<?php
/**
 * =====================================================================
 *  OLAY: Hesap silme isteği planlandı
 * ---------------------------------------------------------------------
 *  Üye Hesabım → Hesabımı Sil ile isteği verdi; silme bekleme süresi
 *  sonunda zamanlanmış görevle yapılır (bkz. App\Core\AccountDeletion).
 *  $tarih kullanıcıya gösterilen biçimdedir ("14.10.2026 09:30").
 *
 *  Varsayılan dinleyici: App\Listeners\HesapSilmeBildir — sahibine
 *  silinme tarihini ve nasıl vazgeçeceğini anlatan mektup.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Events;

use App\Core\Events\Event;

final class AccountDeletionScheduled extends Event
{
    public function __construct(
        public readonly int $userId,
        public readonly string $tarih,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return ['kullanici' => $this->userId, 'tarih' => $this->tarih];
    }
}
