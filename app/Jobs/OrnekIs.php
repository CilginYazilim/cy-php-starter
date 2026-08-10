<?php
/**
 * =====================================================================
 *  ÖRNEK İŞ — silebilirsiniz
 * ---------------------------------------------------------------------
 *  Kuyruk sisteminin nasıl kullanıldığını gösterir:
 *
 *      Queue::push(new App\Jobs\OrnekIs('merhaba'));
 *      php cy queue:work
 *
 *  DİKKAT EDİLECEK ÜÇ ŞEY
 *   1. Yapıcıya verilen her şey toPayload() ile JSON'a yazılabilmeli.
 *      Nesne değil, sade değer taşıyın (id taşıyın, modeli işin
 *      içinde yeniden okuyun).
 *   2. handle() TEKRAR ÇALIŞABİLİR olmalı: iş yarıda kalırsa yeniden
 *      denenir, iki kez fatura kesmemelidir.
 *   3. Hassas veri (parola, token) payload'a KONMAZ — veritabanında
 *      düz metin olarak durur.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Log\Logger;
use App\Core\Queue\Job;
use Throwable;

final class OrnekIs extends Job
{
    public function __construct(private readonly string $mesaj)
    {
    }

    public function handle(): void
    {
        Logger::info('Örnek iş çalıştı: ' . $this->mesaj, [], 'queue');
    }

    public function toPayload(): array
    {
        return ['mesaj' => $this->mesaj];
    }

    public static function fromPayload(array $payload): static
    {
        return new static((string) ($payload['mesaj'] ?? ''));
    }

    public function failed(Throwable $e): void
    {
        Logger::error('Örnek iş tamamen başarısız oldu: ' . $e->getMessage(), [], 'queue');
    }
}
