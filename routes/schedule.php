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

Schedule::call('parola-sifirlama-temizle', static function (): void {
    App\Core\PasswordReset::purge();
})->daily('04:20')->describe('Bir günden eski parola sıfırlama kayıtlarını siler');

// KVKK: saklama süresi (Ayarlar → Sistem, varsayılan 180 gün) dolan mesajlarda IP/tarayıcı boşaltılır.
Schedule::call('mesaj-anonimlestir', static function (): void {
    $adet = App\Core\Privacy::anonymizeMessages();

    if ($adet > 0) {
        Logger::info('Eski mesajlarda IP/tarayıcı bilgisi silindi (KVKK)', ['adet' => $adet], 'app');
    }
})->daily('04:30')->describe('Saklama süresi dolan mesajlarda IP ve tarayıcı bilgisini siler');

// Üyenin "Hesabımı sil" isteği: 7 günlük bekleme dolunca hesap silinir (bkz. AccountDeletion).
Schedule::call('hesap-sil', static function (): void {
    App\Core\AccountDeletion::purgeDue();
})->hourly()->describe('Bekleme süresi dolan hesap silme isteklerini uygular');

/* ---------------------------------------------------------------------
 *  CANLI DEMO SIFIRLAMA (yalnızca APP_DEMO=true)
 * ---------------------------------------------------------------------
 *  Herkese açık demoda ziyaretçilerin değişikliklerini geri alır ve örnek
 *  veriyi baştan kurar (bkz. App\Core\DemoData::reset). Demo modu kapalı
 *  bir sitede hiçbir şey yapmaz. Panel bildirimi bir sonraki sıfırlama
 *  saatini "demo_son_sifirlama" ayarından hesaplar.
 * ------------------------------------------------------------------ */
Schedule::call('demo-sifirla', static function (): void {
    if (!App\Core\Demo::enabled()) {
        return;
    }

    $demo = new App\Core\DemoData(App\Core\Database::connection());
    $demo->reset();
    $demo->seedModules();

    Logger::info('Demo sıfırlandı (zamanlanmış)', [], 'app');
})->everyMinutes(App\Core\Demo::SIFIRLAMA_DAKIKA)->describe('Canlı demoyu sıfırlar (yalnızca demo modunda)');

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
