<?php
/**
 * =====================================================================
 *  DİNLEYİCİ: Yeni üye bildirimi (yöneticiye)
 * ---------------------------------------------------------------------
 *  Biri kayıt olup hesabı etkinleşince iletişim e-postasına haber
 *  verir. Ayarla açılır (Ayarlar → E-posta → Yeni Üye Bildirimi,
 *  varsayılan kapalı); saatte en fazla 10 mektup gider — kayıt formunu
 *  bir bot doldurursa yöneticinin gelen kutusu dolmasın.
 *
 *  Kapatmak için ayarı kapatın ya da routes/events.php'deki satırı silin.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Mail\Notifier;
use App\Events\UserRegistered;

final class YeniUyeyiBildir
{
    public function handle(UserRegistered $event): void
    {
        Notifier::yeniUye($event->user);
    }
}
