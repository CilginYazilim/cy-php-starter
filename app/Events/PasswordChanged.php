<?php
/**
 * =====================================================================
 *  OLAY: Parola değişti
 * ---------------------------------------------------------------------
 *  GÜVENLİK AÇISINDAN ÖNEMLİ BİR OLAYDIR. Parolayı kullanıcının
 *  kendisi mi değiştirdi, yoksa bir yönetici mi sıfırladı — $kendisi
 *  bunu söyler.
 *
 *  TİPİK DİNLEYİCİLER
 *    · "Parolanız değişti" bilgilendirme e-postası (hesap çalındıysa
 *      kullanıcının fark etmesinin tek yolu budur)
 *    · Diğer cihazlardaki oturumları sonlandırmak
 *
 *  PAROLANIN KENDİSİ (ne düz metni ne hash'i) BU OLAYDA TAŞINMAZ.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Events;

use App\Core\Events\Event;

final class PasswordChanged extends Event
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $kendisi = true,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return ['kullanici' => $this->userId, 'kendisi' => $this->kendisi];
    }
}
