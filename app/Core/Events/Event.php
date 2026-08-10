<?php
/**
 * =====================================================================
 *  Event – "Şu oldu" duyurusu
 * ---------------------------------------------------------------------
 *  Bir olay, OLUP BİTMİŞ bir şeyi anlatır. Adı geçmiş zamandır:
 *  UserRegistered, PasswordChanged, FileUploaded. "UserRegister" ya da
 *  "SendWelcomeMail" gibi EMİR kipinde bir ad koyuyorsanız, aslında
 *  olay değil komut yazıyorsunuz demektir — onun yeri bir servis
 *  sınıfıdır.
 *
 *  OLAYLAR DEĞİŞMEZDİR (readonly). Bir dinleyicinin olayı değiştirip
 *  sonrakini yanıltması mümkün olmamalıdır.
 *
 *  OLAY İPTAL EDİLEMEZ. Dinleyici "bu kaydı yapma" diyemez; olay
 *  yayınlandığında iş ZATEN BİTMİŞTİR. Bu bilinçli bir sınırdır:
 *  aksi halde bir modül, çekirdeğin davranışını sessizce bozabilirdi.
 *
 *  ÖRNEK
 *      final class UrunSatildi extends Event
 *      {
 *          public function __construct(
 *              public readonly int $urunId,
 *              public readonly int $adet,
 *          ) {
 *              parent::__construct();
 *          }
 *
 *          public function toArray(): array
 *          {
 *              return ['urun' => $this->urunId, 'adet' => $this->adet];
 *          }
 *      }
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Events;

abstract class Event
{
    /** Olayın yayınlandığı an (unix zaman damgası). */
    public readonly int $zaman;

    public function __construct()
    {
        $this->zaman = time();
    }

    /** Dinleyici kaydında kullanılan ad: tam sınıf adı. */
    public function name(): string
    {
        return static::class;
    }

    /** Sınıf adının son parçası — log ve ekranlarda okunaklı görünür. */
    public function shortName(): string
    {
        $parts = explode('\\', static::class);

        return end($parts) ?: static::class;
    }

    /**
     * Olayın log'a yazılacak özeti.
     *
     * PAROLA, TOKEN VE BENZERİ HİÇBİR ŞEY BURAYA KONMAZ. Logger ayrıca
     * maskeleme yapar ama asıl savunma, hassas veriyi olaya hiç
     * koymamaktır.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
