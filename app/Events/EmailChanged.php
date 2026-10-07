<?php
/**
 * =====================================================================
 *  OLAY: E-posta adresi değişti
 * ---------------------------------------------------------------------
 *  GÜVENLİK AÇISINDAN ÖNEMLİ BİR OLAYDIR: e-posta adresi hesabın
 *  kurtarma kanalıdır. Ele geçirilen bir oturumla adres değiştirilirse
 *  "parolamı unuttum" bağlantıları artık saldırgana gider; asıl sahibin
 *  bunu fark etmesinin yolu ESKİ adrese giden bilgilendirmedir.
 *
 *  $kaynak: değişiklik nereden yapıldı ('profil' | 'yonetici').
 *
 *  Varsayılan dinleyici: App\Listeners\EpostaDegistiBildir — eski
 *  adrese "e-posta adresiniz değiştirildi" mektubu (bkz. routes/events.php).
 *
 *  Günlüğe (toArray) TAM ADRES YAZILMAZ; yalnızca maskelenmiş hâli.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Events;

use App\Core\Events\Event;
use App\Models\MailLog;

final class EmailChanged extends Event
{
    public const PROFIL   = 'profil';
    public const YONETICI = 'yonetici';

    public function __construct(
        public readonly int $userId,
        public readonly string $eskiEposta,
        public readonly string $yeniEposta,
        public readonly string $kaynak = self::PROFIL,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'kullanici' => $this->userId,
            'eski'      => MailLog::maskEmail($this->eskiEposta),
            'yeni'      => MailLog::maskEmail($this->yeniEposta),
            'kaynak'    => $this->kaynak,
        ];
    }
}
