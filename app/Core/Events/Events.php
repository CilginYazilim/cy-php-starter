<?php
/**
 * =====================================================================
 *  Events – Olay sistemine erişimin ön kapısı
 * ---------------------------------------------------------------------
 *      // Dinleyici kaydı (routes/events.php)
 *      Events::listen(UserRegistered::class, HosgeldinMailiGonder::class);
 *
 *      // Yayın (çekirdek ya da modül)
 *      Events::dispatch(new UserRegistered($user));
 *
 *  ÇEKİRDEK NEDEN OLAY YAYINLAR?
 *  Çekirdek, uygulamanın NE İŞ YAPTIĞINI bilmemelidir. "Kullanıcı
 *  kaydoldu" bilgisi çekirdeğe aittir; "yeni kullanıcı için ERP'de
 *  personel kartı aç" ise ERP modülüne. Çekirdek olayı duyurur,
 *  modül dinler. Böylece modülü kaldırdığınızda çekirdekte tek bir
 *  satır bile değişmez.
 *
 *  YANLIŞ:  UserService içinde ERP personeli oluşturmak
 *  DOĞRU:   UserRegistered yayınlamak, ERP modülünün dinlemesi
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Events;

final class Events
{
    private static ?Dispatcher $dispatcher = null;

    public static function dispatcher(): Dispatcher
    {
        return self::$dispatcher ??= new Dispatcher();
    }

    /** Testlerde dağıtıcıyı değiştirmek/sıfırlamak için. */
    public static function useDispatcher(?Dispatcher $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    /**
     * @param string $event Olay sınıfının tam adı ya da '*' (hepsi)
     * @param callable|string|array{0:string,1:string} $listener
     */
    public static function listen(string $event, callable|string|array $listener): void
    {
        self::dispatcher()->listen($event, $listener);
    }

    public static function dispatch(Event $event): Event
    {
        return self::dispatcher()->dispatch($event);
    }

    public static function hasListeners(string $event): bool
    {
        return self::dispatcher()->hasListeners($event);
    }

    public static function forget(string $event): void
    {
        self::dispatcher()->forget($event);
    }

    public static function flush(): void
    {
        self::dispatcher()->flush();
    }

    /* =================================================================
     *  TEST DESTEĞİ
     * ============================================================== */

    public static function fake(): void
    {
        self::dispatcher()->fake();
    }

    public static function unfake(): void
    {
        self::dispatcher()->unfake();
    }

    /** @return array<int,Event> */
    public static function recorded(?string $event = null): array
    {
        return self::dispatcher()->recorded($event);
    }

    /** Sahte modda: bu tür bir olay yayınlandı mı? */
    public static function dispatched(string $event): bool
    {
        return self::dispatcher()->recorded($event) !== [];
    }
}
