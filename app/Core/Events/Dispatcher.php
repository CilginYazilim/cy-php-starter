<?php
/**
 * =====================================================================
 *  Dispatcher – Olayları dinleyicilere ulaştırır
 * ---------------------------------------------------------------------
 *  EN ÖNEMLİ TASARIM KARARI: BİR DİNLEYİCİNİN HATASI, OLAYI YAYINLAYAN
 *  İŞLEMİ BOZMAZ.
 *
 *  Kullanıcı kaydı tamamlandı ve UserRegistered yayınlandı. ERP
 *  modülünün dinleyicisi personel kaydı oluştururken patlarsa,
 *  kullanıcının kaydı geri alınmaz — çünkü o iş ZATEN BİTTİ. Hata
 *  loglanır, diğer dinleyiciler çalışmaya devam eder.
 *
 *  Bu davranış olmasaydı, yükleyeceğiniz her modül çekirdeğin en
 *  temel akışlarını çökertme gücüne sahip olurdu.
 *
 *  GELİŞTİRMEDE FARKLI DAVRANIR: config/events.php içindeki "strict"
 *  açıksa (varsayılan: APP_DEBUG) hata YUTULMAZ, fırlatılır. Kendi
 *  dinleyicinizdeki yazım hatasını sessiz bir log satırında değil,
 *  ekranda görmelisiniz.
 *
 *  DİNLEYİCİ BİÇİMLERİ
 *      Events::listen(UserRegistered::class, function ($e) { ... });
 *      Events::listen(UserRegistered::class, HosgeldinMaili::class);
 *      Events::listen(UserRegistered::class, [Rapor::class, 'kaydet']);
 *      Events::listen('*', fn ($e) => Logger::debug($e->shortName()));
 *
 *  Sınıf verirseniz önce handle(), yoksa __invoke() çağrılır.
 *  Dinleyiciler KAYIT SIRASINA göre çalışır; öncelik numarası yoktur
 *  (sıralamaya ihtiyaç duyuyorsanız kayıt sırasını değiştirin).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Config;
use App\Core\Log\Logger;
use Throwable;

final class Dispatcher
{
    /** @var array<string,array<int,callable|string|array{0:string,1:string}>> */
    private array $listeners = [];

    /** @var array<int,callable|string|array{0:string,1:string}> */
    private array $wildcards = [];

    /** @var array<int,Event>|null null = normal mod, dizi = sahte mod */
    private ?array $recorded = null;

    /* =================================================================
     *  KAYIT
     * ============================================================== */

    /**
     * @param string $event Olay sınıfının tam adı ya da '*' (hepsi)
     * @param callable|string|array{0:string,1:string} $listener
     */
    public function listen(string $event, callable|string|array $listener): void
    {
        if ($event === '*') {
            $this->wildcards[] = $listener;

            return;
        }

        $this->listeners[$event][] = $listener;
    }

    public function hasListeners(string $event): bool
    {
        return ($this->listeners[$event] ?? []) !== [] || $this->wildcards !== [];
    }

    /** Bir olayın TÜM dinleyicilerini kaldırır. */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    public function flush(): void
    {
        $this->listeners = [];
        $this->wildcards = [];
    }

    /* =================================================================
     *  YAYIN
     * ============================================================== */

    public function dispatch(Event $event): Event
    {
        // Sahte modda hiçbir dinleyici çalışmaz, olay yalnızca
        // kaydedilir (bkz. fake()).
        if ($this->recorded !== null) {
            $this->recorded[] = $event;

            return $event;
        }

        $listeners = array_merge(
            $this->listeners[$event->name()] ?? [],
            $this->wildcards
        );

        if ($listeners === []) {
            return $event;
        }

        foreach ($listeners as $listener) {
            $this->run($listener, $event);
        }

        return $event;
    }

    /** @param callable|string|array{0:string,1:string} $listener */
    private function run(callable|string|array $listener, Event $event): void
    {
        try {
            $callable = $this->resolve($listener);

            $callable($event);
        } catch (Throwable $e) {
            Logger::error(
                'Olay dinleyicisi hata verdi: ' . $e->getMessage(),
                [
                    'olay'      => $event->name(),
                    'dinleyici' => $this->describe($listener),
                    'dosya'     => $e->getFile() . ':' . $e->getLine(),
                ],
                'error'
            );

            /* Geliştirmede sessiz kalmıyoruz: kendi dinleyicinizdeki
             * hatayı log dosyasında aramak yerine ekranda görün. */
            if ($this->strict()) {
                throw $e;
            }
        }
    }

    /**
     * Dinleyiciyi çağrılabilir hale getirir.
     *
     * @param callable|string|array{0:string,1:string} $listener
     */
    private function resolve(callable|string|array $listener): callable
    {
        if (is_string($listener) && class_exists($listener)) {
            $instance = new $listener();

            if (method_exists($instance, 'handle')) {
                return [$instance, 'handle'];
            }

            if (is_callable($instance)) {
                return $instance;
            }

            throw new EventException(
                $listener . ' sınıfında handle() ya da __invoke() metodu yok.'
            );
        }

        if (is_array($listener) && count($listener) === 2 && is_string($listener[0]) && class_exists($listener[0])) {
            $method = $listener[1];

            // Statik metotsa nesne oluşturmaya gerek yok.
            if (is_callable([$listener[0], $method]) && (new \ReflectionMethod($listener[0], $method))->isStatic()) {
                return [$listener[0], $method];
            }

            return [new $listener[0](), $method];
        }

        if (is_callable($listener)) {
            return $listener;
        }

        throw new EventException('Dinleyici çağrılabilir değil: ' . $this->describe($listener));
    }

    /** @param callable|string|array{0:string,1:string} $listener */
    private function describe(callable|string|array $listener): string
    {
        if (is_string($listener)) {
            return $listener;
        }

        if (is_array($listener)) {
            $class = is_object($listener[0] ?? null) ? $listener[0]::class : (string) ($listener[0] ?? '?');

            return $class . '::' . (string) ($listener[1] ?? '?');
        }

        return 'closure';
    }

    private function strict(): bool
    {
        return (bool) Config::get('events.strict', Config::isDebug());
    }

    /* =================================================================
     *  TEST DESTEĞİ
     * ============================================================== */

    /**
     * Sahte mod: dinleyiciler ÇALIŞMAZ, olaylar yalnızca kaydedilir.
     *
     * Bir denetleyiciyi test ederken gerçekten e-posta gönderilmesini
     * istemezsiniz; olayın yayınlanıp yayınlanmadığını doğrulamak
     * yeterlidir.
     */
    public function fake(): void
    {
        $this->recorded = [];
    }

    /** Sahte moddan çıkar. */
    public function unfake(): void
    {
        $this->recorded = null;
    }

    public function isFaking(): bool
    {
        return $this->recorded !== null;
    }

    /**
     * Sahte modda kaydedilen olaylar.
     *
     * @param string|null $event Verilirse yalnızca o türdekiler
     * @return array<int,Event>
     */
    public function recorded(?string $event = null): array
    {
        $all = $this->recorded ?? [];

        if ($event === null) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            static fn (Event $recorded): bool => $recorded->name() === $event
        ));
    }
}
