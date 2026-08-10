<?php
/**
 * =====================================================================
 *  OLAY: Kullanıcı çıkış yaptı
 * ---------------------------------------------------------------------
 *  Oturum YOK EDİLMEDEN ÖNCE yayınlanır; dinleyiciler oturumdaki
 *  veriye hâlâ erişebilir. Kullanıcı nesnesi yerine yalnızca numarası
 *  taşınır — çıkış anında veritabanına gitmeye değmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Events;

use App\Core\Events\Event;

final class UserLoggedOut extends Event
{
    public function __construct(public readonly int $userId)
    {
        parent::__construct();
    }

    public function toArray(): array
    {
        return ['kullanici' => $this->userId];
    }
}
