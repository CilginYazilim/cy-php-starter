<?php
/**
 * =====================================================================
 *  php cy queue:status – Kuyruğun durumu; başarısızları yönet
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;
use App\Core\Queue\Queue;

final class QueueStatusCommand extends Command
{
    public function name(): string
    {
        return 'queue:status';
    }

    public function description(): string
    {
        return 'Kuyruk durumunu gösterir; başarısız işleri yönetir.';
    }

    public function help(): string
    {
        return "  php cy queue:status            Sayıları gösterir\n"
             . "  php cy queue:status --retry    Başarısızları yeniden kuyruğa alır\n"
             . "  php cy queue:status --purge    Başarısızları siler";
    }

    public function handle(): int
    {
        Queue::useConnection($this->db());

        if ($this->input->hasOption('retry')) {
            $this->out->success(Queue::retryFailed() . ' başarısız iş yeniden kuyruğa alındı.');

            return self::BASARILI;
        }

        if ($this->input->hasOption('purge')) {
            if (!$this->confirmDestructive('Başarısız işler kalıcı olarak silinecek. Devam?')) {
                $this->out->info('Vazgeçildi.');

                return self::BASARILI;
            }

            $this->out->success(Queue::purgeFailed() . ' başarısız iş silindi.');

            return self::BASARILI;
        }

        $stats = Queue::stats();

        $this->out->title('Kuyruk durumu');
        $this->out->table(['Durum', 'Adet'], [
            ['Bekleyen',  (string) $stats['bekleyen']],
            ['Çalışıyor', (string) $stats['calisan']],
            ['Başarısız', (string) $stats['basarisiz']],
        ]);
        $this->out->blank();

        if ($stats['basarisiz'] > 0) {
            $this->out->warn('Başarısız işler var. Ayrıntı: storage/logs/queue-*.log');
            $this->out->muted('  Yeniden denemek için: php cy queue:status --retry');
        }

        return self::BASARILI;
    }
}
