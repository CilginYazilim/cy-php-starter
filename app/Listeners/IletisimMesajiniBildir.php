<?php
/**
 * =====================================================================
 *  DİNLEYİCİ: İletişim formu bildirimleri
 * ---------------------------------------------------------------------
 *  İki mektup gönderir:
 *    · Yöneticiye  → "yeni mesaj var"
 *    · Ziyaretçiye → "mesajını aldık"
 *
 *  İkisi de ayarlardan (Panel → Site Ayarları → E-posta) kapatılabilir;
 *  denetimi Notifier yapar.
 *
 *  MESAJ ZATEN VERİTABANINDA. Buradan sonrası "olursa iyi olur"
 *  bölümüdür — bu yüzden bir dinleyicide olması doğru yerdir: hata
 *  verse bile ziyaretçinin gördüğü sonuç değişmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Mail\Notifier;
use App\Events\ContactMessageReceived;

final class IletisimMesajiniBildir
{
    public function handle(ContactMessageReceived $event): void
    {
        Notifier::yeniMesaj($event->message);    // yöneticiye haber ver
        Notifier::mesajAlindi($event->message);  // ziyaretçiye "aldık" de
    }
}
