<?php
/**
 * =====================================================================
 *  DİNLEYİCİ: "Hesabınız silinecek" bilgilendirmesi
 * ---------------------------------------------------------------------
 *  İsteği gerçekten hesabın sahibi mi verdi? Açık bırakılmış bir
 *  oturumdan verilen silme isteğini sahibi bu mektupla fark eder ve
 *  bekleme süresi içinde giriş yaparak iptal eder.
 *
 *  Kapatmak için routes/events.php içindeki satırı silmeniz yeterlidir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Database;
use App\Core\Mail\Notifier;
use App\Events\AccountDeletionScheduled;
use App\Repositories\UserRepository;

final class HesapSilmeBildir
{
    public function handle(AccountDeletionScheduled $event): void
    {
        $user = (new UserRepository(Database::connection()))->find($event->userId);

        if ($user !== null) {
            Notifier::hesapSilinecek($user, $event->tarih);
        }
    }
}
