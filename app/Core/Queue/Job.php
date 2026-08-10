<?php
/**
 * =====================================================================
 *  Job – Arka planda çalışacak tek bir iş
 * ---------------------------------------------------------------------
 *  Kullanıcıyı bekletmemesi gereken her şey buraya girer: toplu
 *  e-posta, rapor üretimi, yedekleme, dış servise bildirim, görsel
 *  işleme.
 *
 *      final class RaporUret extends Job
 *      {
 *          public function __construct(private int $ay) {}
 *
 *          public function handle(): void { ... }
 *
 *          public function toPayload(): array { return ['ay' => $this->ay]; }
 *
 *          public static function fromPayload(array $veri): static
 *          {
 *              return new static((int) $veri['ay']);
 *          }
 *      }
 *
 *      Queue::push(new RaporUret(3));            // hemen sıraya
 *      Queue::later(new RaporUret(3), 600);      // 10 dk sonra
 *
 *  NEDEN toPayload/fromPayload? İş veritabanına JSON olarak yazılır.
 *  PHP nesnesini serialize etmek cazip görünür ama sınıfı sonradan
 *  değiştirdiğinizde kuyruktaki eski kayıtlar açılamaz hale gelir.
 *  Açık bir dizi sözleşmesi hem okunabilir hem de sürüm dostudur.
 *
 *  İŞLER TEKRAR ÇALIŞABİLİR OLMALIDIR. Bir iş yarıda kalırsa yeniden
 *  denenir; iki kez çalıştığında iki fatura kesmemelidir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Queue;

use Throwable;

abstract class Job
{
    /** Hangi kuyruğa gitsin? Ayrı işçilerle önceliklendirme yaparsınız. */
    public function queue(): string
    {
        return 'varsayilan';
    }

    /** Başarısız olursa kaç kez daha denensin? */
    public function tries(): int
    {
        return 3;
    }

    /**
     * Denemeler arasındaki bekleme (saniye).
     * Her denemede katlanarak artar: 60, 120, 240…
     */
    public function backoff(): int
    {
        return 60;
    }

    abstract public function handle(): void;

    /**
     * Veritabanına yazılacak veri.
     *
     * @return array<string,mixed>
     */
    abstract public function toPayload(): array;

    /**
     * Veriden işi yeniden kurar.
     *
     * @param array<string,mixed> $payload
     */
    abstract public static function fromPayload(array $payload): static;

    /**
     * Bütün denemeler tükendiğinde çağrılır.
     * Kullanıcıyı bilgilendirmek ya da temizlik yapmak için.
     */
    public function failed(Throwable $e): void
    {
    }
}
