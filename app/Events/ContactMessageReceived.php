<?php
/**
 * =====================================================================
 *  OLAY: İletişim formundan mesaj geldi
 * ---------------------------------------------------------------------
 *  Mesaj VERİTABANINA YAZILDIKTAN sonra yayınlanır. Bu sıralama
 *  bilinçlidir: posta sunucusu kapalı olsa bile mesaj kaybolmaz,
 *  ziyaretçi hata görmez.
 *
 *  TİPİK DİNLEYİCİLER
 *    · Yöneticiye bildirim e-postası (bu şablonda hazır)
 *    · Ziyaretçiye otomatik yanıt (bu şablonda hazır)
 *    · CRM modülünde talep (ticket) açmak
 *    · Slack/Telegram bildirimi
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Events;

use App\Core\Events\Event;
use App\Models\Message;

final class ContactMessageReceived extends Event
{
    public function __construct(public readonly Message $message)
    {
        parent::__construct();
    }

    public function toArray(): array
    {
        return ['mesaj' => $this->message->id, 'eposta' => $this->message->eposta];
    }
}
