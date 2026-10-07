<?php
/**
 * =====================================================================
 *  DİNLEYİCİ: "E-posta adresiniz değiştirildi" bilgilendirmesi
 * ---------------------------------------------------------------------
 *  Mektup ESKİ adrese gider: yeni adres saldırgana ait olabilir.
 *  Hesabını kaybeden kişi, parola sıfırlama bağlantıları artık kendisine
 *  gelmediği için bunu başka türlü fark edemez.
 *
 *  Kapatmak için routes/events.php içindeki satırı silmeniz yeterlidir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Database;
use App\Core\Mail\Notifier;
use App\Events\EmailChanged;
use App\Repositories\UserRepository;

final class EpostaDegistiBildir
{
    public function handle(EmailChanged $event): void
    {
        $user = (new UserRepository(Database::connection()))->find($event->userId);

        if ($user !== null) {
            Notifier::epostaDegisti($user, $event->eskiEposta, $event->yeniEposta, $event->kaynak);
        }
    }
}
