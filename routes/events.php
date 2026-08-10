<?php
/**
 * =====================================================================
 *  OLAY TABLOSU – Hangi olayı kim dinliyor?
 * ---------------------------------------------------------------------
 *  routes/web.php nasıl "hangi adres hangi denetleyiciye gider"
 *  sorusunu yanıtlıyorsa, bu dosya da "ne olduğunda ne yapılır"
 *  sorusunu yanıtlar. Tek bakışta görülebilmesi için hepsi burada.
 *
 *  DİNLEYİCİ BİÇİMLERİ
 *      Events::listen(Olay::class, DinleyiciSinifi::class);   // handle()
 *      Events::listen(Olay::class, [Sinif::class, 'metot']);
 *      Events::listen(Olay::class, function (Olay $e) { ... });
 *      Events::listen('*', fn ($e) => ...);                   // hepsi
 *
 *  Dinleyiciler KAYIT SIRASINA göre çalışır.
 *
 *  BİR DİNLEYİCİNİN HATASI OLAYI YAYINLAYAN İŞLEMİ BOZMAZ: hata
 *  loglanır, diğerleri çalışmaya devam eder. (Geliştirmede
 *  APP_DEBUG=true iken hata fırlatılır ki gözden kaçmasın —
 *  config/events.php → strict.)
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Events\Events;
use App\Core\Log\Logger;
use App\Events\ContactMessageReceived;
use App\Events\FileUploaded;
use App\Events\PasswordChanged;
use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Events\UserRegistered;
use App\Listeners\HosgeldinMailiGonder;
use App\Listeners\IletisimMesajiniBildir;

/* ---------------------------------------------------------------------
 *  ÜYELİK
 * ------------------------------------------------------------------ */

// Karşılama mektubunu kapatmak için bu satırı silmeniz yeterli;
// çekirdek kodda hiçbir değişiklik gerekmez.
Events::listen(UserRegistered::class, HosgeldinMailiGonder::class);

/* ---------------------------------------------------------------------
 *  İLETİŞİM FORMU
 * ------------------------------------------------------------------ */

Events::listen(ContactMessageReceived::class, IletisimMesajiniBildir::class);

/* ---------------------------------------------------------------------
 *  GELİŞTİRME: bütün olayları günlüğe yaz
 * ---------------------------------------------------------------------
 *  '*' joker dinleyicisi HER olayı yakalar. Yalnızca hata ayıklama
 *  açıkken kaydediyoruz; yayında her olay için bir log satırı
 *  gereksiz gürültü olurdu.
 * ------------------------------------------------------------------ */
if (App\Core\Config::isDebug()) {
    Events::listen('*', static function (App\Core\Events\Event $event): void {
        Logger::debug('Olay: ' . $event->shortName(), $event->toArray(), 'app');
    });
}

/* ---------------------------------------------------------------------
 *  ÖRNEKLER — kendi projenizde açabilirsiniz
 * ---------------------------------------------------------------------
 *  Aşağıdakiler çalışır durumda DEĞİLDİR; olay sisteminin nasıl
 *  kullanılacağını göstermek için buradalar.
 *
 *  // Parola değişince kullanıcıyı bilgilendir (hesabı çalınmışsa
 *  // fark etmesinin tek yolu budur):
 *  Events::listen(PasswordChanged::class, function (PasswordChanged $e) {
 *      if ($e->kendisi) { return; }
 *      // Notifier::parolaDegisti($e->userId);
 *  });
 *
 *  // Yüklenen her görselden küçük resim üret:
 *  Events::listen(FileUploaded::class, [ThumbnailUret::class, 'handle']);
 *
 *  // Giriş yapınca modülünün önbelleğini hazırla:
 *  Events::listen(UserLoggedIn::class, function (UserLoggedIn $e) {
 *      // Cache::put('menu:' . $e->user->id, ..., 600);
 *  });
 *
 *  // Çıkışta modül verisini temizle:
 *  Events::listen(UserLoggedOut::class, function (UserLoggedOut $e) {
 *      // Cache::forget('menu:' . $e->userId);
 *  });
 *
 *  BİR MODÜL NASIL DİNLER? Modülünüz kendi olay dosyasını yükler
 *  (Faz 10 – Modül Sistemi). O zamana kadar buraya bir satır
 *  eklemeniz yeterlidir.
 * ------------------------------------------------------------------ */
