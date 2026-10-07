<?php
/**
 * =====================================================================
 *  DİNLEYİCİ: "Parolanız değiştirildi" bilgilendirmesi
 * ---------------------------------------------------------------------
 *  Hesabı ele geçirilen kişinin bunu fark etmesinin çoğu zaman TEK
 *  yolu bu mektuptur: parolayı kendisi değiştirmediyse hemen
 *  "parolamı unuttum" ile hesabını geri alabilir.
 *
 *  Mektup, parola profilden, e-posta bağlantısıyla ya da bir yönetici
 *  tarafından değiştirildiğinde gider. Kapatmak için routes/events.php
 *  içindeki satırı silmeniz yeterlidir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Database;
use App\Core\Mail\Notifier;
use App\Events\PasswordChanged;
use App\Repositories\UserRepository;

final class ParolaDegistiBildir
{
    public function handle(PasswordChanged $event): void
    {
        /* Hesap açılış bağlantısıyla belirlenen İLK parola bir değişiklik
         * değildir; kişi az önce "Hesabınız açıldı" mektubunu aldı. */
        if ($event->kaynak === PasswordChanged::ILK) {
            return;
        }

        $user = (new UserRepository(Database::connection()))->find($event->userId);

        if ($user !== null) {
            Notifier::parolaDegisti($user, $event->kaynak);
        }
    }
}
