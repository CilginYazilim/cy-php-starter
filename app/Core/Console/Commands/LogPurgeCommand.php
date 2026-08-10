<?php
/**
 * =====================================================================
 *  php cy log:purge – Eski günlük dosyalarını siler
 * ---------------------------------------------------------------------
 *  Zamanlanmış görevden (cron) günde bir çalıştırın; aksi halde
 *  storage/logs zamanla gigabaytlara çıkar.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Log\Logger;

final class LogPurgeCommand extends Command
{
    public function name(): string
    {
        return 'log:purge';
    }

    public function description(): string
    {
        return 'Saklama süresi dolmuş günlük dosyalarını siler.';
    }

    public function help(): string
    {
        return "  php cy log:purge              config/log.php içindeki 'days' kadar saklar\n"
             . "  php cy log:purge --days=7     7 günden eskileri siler\n"
             . "  php cy log:purge --list       Silmeden mevcut dosyaları listeler";
    }

    public function handle(): int
    {
        if ($this->input->hasOption('list')) {
            return $this->listFiles();
        }

        $days    = $this->input->intOption('days', (int) Config::get('log.days', 30));
        $deleted = Logger::purge($days);

        $this->out->success($deleted . ' günlük dosyası silindi (' . $days . ' günden eski).');

        return self::BASARILI;
    }

    private function listFiles(): int
    {
        $files = Logger::files();

        if ($files === []) {
            $this->out->info('Günlük dosyası yok.');

            return self::BASARILI;
        }

        $rows  = [];
        $total = 0;

        foreach ($files as $file) {
            $total += $file['boyut'];

            $rows[] = [
                $file['ad'],
                $this->humanSize($file['boyut']),
                date('d.m.Y H:i', $file['tarih']),
            ];
        }

        $this->out->title('Günlük dosyaları');
        $this->out->table(['Dosya', 'Boyut', 'Son yazma'], $rows);
        $this->out->blank();
        $this->out->muted('  Toplam: ' . $this->humanSize($total));
        $this->out->blank();

        return self::BASARILI;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
