<?php
/**
 * =====================================================================
 *  DİNLEYİCİ: Yeni üyeye karşılama mektubu
 * ---------------------------------------------------------------------
 *  Bu sınıf, olay sisteminin NEDEN var olduğunun canlı örneğidir.
 *
 *  ÖNCEDEN: AuthController::register() içinde doğrudan
 *      Notifier::hosgeldin($user);
 *  satırı vardı. Denetleyici hem "kullanıcı oluştur" hem de "mektup
 *  gönder" işini biliyordu.
 *
 *  ŞİMDİ: Denetleyici yalnızca UserRegistered yayınlıyor. Karşılama
 *  mektubunu isteyen bu dinleyici. Yarın "yöneticiye de haber ver" ya
 *  da "CRM'de müşteri kartı aç" gerekirse denetleyiciye DOKUNULMAZ;
 *  routes/events.php'ye bir satır eklenir.
 *
 *  Karşılama mektubunu tamamen kapatmak isterseniz o satırı silmeniz
 *  yeterli — çekirdek kodda hiçbir değişiklik gerekmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Mail\Notifier;
use App\Events\UserRegistered;

final class HosgeldinMailiGonder
{
    public function handle(UserRegistered $event): void
    {
        /* Notifier SESSİZDİR: posta sunucusu kapalıysa istisna
         * fırlatmaz, sonucu mail_kayitlari tablosuna yazar. Kayıt
         * işlemi bu yüzden hiçbir koşulda etkilenmez. */
        Notifier::hosgeldin($event->user);
    }
}
