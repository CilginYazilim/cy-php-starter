<?php
/**
 * =====================================================================
 *  ZAMANLANMIŞ GÖREVLER – "ne zaman ne çalışsın?"
 * ---------------------------------------------------------------------
 *  SUNUCUYA YAZILACAK TEK CRON SATIRI:
 *
 *      * * * * * cd /yol/site && php cy schedule:run >> /dev/null 2>&1
 *
 *  Bundan sonra yeni görev eklemek için sunucuya dokunmanız gerekmez;
 *  bu dosyaya bir satır ekleyip kodu dağıtmanız yeterlidir.
 *
 *  SIKLIK SEÇENEKLERİ
 *      ->everyMinute()  ->everyMinutes(5)  ->hourly()
 *      ->daily('04:00') ->weekly(1,'03:30') ->monthly(1,'02:00')
 *
 *  Görevler ÜST ÜSTE BİNMEZ: çalışan bir görev bitmeden aynısı
 *  yeniden başlatılmaz.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Cache\Cache;
use App\Core\Log\Logger;
use App\Core\Mail\Mailer;
use App\Core\Queue\Queue;
use App\Core\Schedule\Schedule;

/* ---------------------------------------------------------------------
 *  KUYRUK
 * ---------------------------------------------------------------------
 *  Ayrı bir "queue:work" işçisi çalıştırmıyorsanız bu satır yeterlidir:
 *  her dakika birkaç iş işlenir. Yoğun sistemlerde bunu kapatıp
 *  kalıcı bir işçi (supervisor) tercih edin.
 * ------------------------------------------------------------------ */
Schedule::call('kuyruk-isle', static function (): void {
    for ($i = 0; $i < 10; $i++) {
        if (!Queue::runNext()) {
            break;
        }
    }
})->everyMinute()->describe('Kuyruktaki işleri çalıştırır');

/* ---------------------------------------------------------------------
 *  E-POSTA KUYRUĞU
 * ---------------------------------------------------------------------
 *  Toplu duyurular panelden gönderilirken tarayıcı kuyruğu kendisi
 *  işler; bu görev, sayfası kapanmış bir gönderimin yarıda kalmasını
 *  önler.
 * ------------------------------------------------------------------ */
Schedule::call('eposta-kuyrugu', static function (): void {
    Mailer::processQueue(20);
})->everyMinutes(5)->describe('Bekleyen e-postaları gönderir');

/* ---------------------------------------------------------------------
 *  BAKIM
 * ------------------------------------------------------------------ */
Schedule::call('onbellek-temizle', static function (): void {
    Cache::purgeExpired();
})->daily('04:00')->describe('Süresi geçmiş önbellek kayıtlarını siler');

Schedule::call('gunluk-temizle', static function (): void {
    Logger::purge();
})->daily('04:10')->describe('Saklama süresi dolmuş günlükleri siler');

/* ---------------------------------------------------------------------
 *  ÖRNEKLER — kendi projenizde açabilirsiniz
 * ---------------------------------------------------------------------
 *  Schedule::call('gunluk-rapor', function () {
 *      Queue::push(new App\Jobs\GunlukRaporGonder());
 *  })->daily('08:00')->describe('Yöneticilere günlük özet');
 *
 *  Schedule::call('yedek', function () {
 *      // Queue::push(new App\Jobs\VeritabaniYedegiAl());
 *  })->weekly(7, '02:00')->describe('Haftalık yedek (pazar gecesi)');
 * ------------------------------------------------------------------ */
