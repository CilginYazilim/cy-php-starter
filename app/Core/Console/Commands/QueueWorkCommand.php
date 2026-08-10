<?php
/**
 * =====================================================================
 *  php cy queue:work – Kuyruktaki işleri çalıştırır
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Queue\Queue;
use App\Core\Setting;

final class QueueWorkCommand extends Command
{
    public function name(): string
    {
        return 'queue:work';
    }

    public function description(): string
    {
        return 'Kuyruktaki işleri çalıştırır.';
    }

    public function help(): string
    {
        return "  php cy queue:work                  Varsayılan sayıda iş çalıştırır\n"
             . "  php cy queue:work --max=100        En fazla 100 iş\n"
             . "  php cy queue:work --queue=rapor    Yalnızca \"rapor\" kuyruğu\n"
             . "  php cy queue:work --once           Tek bir iş çalıştırıp çıkar\n"
             . "\n"
             . "  Cron (her dakika):\n"
             . "      * * * * * cd /yol/site && php cy queue:work --max=30\n"
             . "\n"
             . "  Yoğun sistemlerde kalıcı bir işçi (supervisor) tercih edin;\n"
             . "  --max sınırına ulaşınca süreç temiz biçimde sonlanır ki\n"
             . "  bellek sızıntıları birikmesin.";
    }

    public function handle(): int
    {
        // İşler ayar/e-posta okuyabilir; CLI'da ayarları biz yükleriz.
        Setting::load($this->db());
        Queue::useConnection($this->db());

        $queue = $this->input->option('queue');
        $max   = $this->input->hasOption('once')
            ? 1
            : max(1, $this->input->intOption('max', (int) Config::get('queue.max_jobs', 25)));

        $serbest = Queue::releaseStuck();

        if ($serbest > 0) {
            $this->out->warn($serbest . ' takılı kalmış iş yeniden kuyruğa alındı.');
        }

        $calisan = 0;

        while ($calisan < $max) {
            if (!Queue::runNext($queue)) {
                break;
            }

            $calisan++;
        }

        if ($calisan === 0) {
            $this->out->info('Kuyrukta iş yok.');

            return self::BASARILI;
        }

        $stats = Queue::stats();

        $this->out->success($calisan . ' iş işlendi.');
        $this->out->muted(sprintf(
            '  Bekleyen: %d · Başarısız: %d',
            $stats['bekleyen'],
            $stats['basarisiz']
        ));

        return self::BASARILI;
    }
}
