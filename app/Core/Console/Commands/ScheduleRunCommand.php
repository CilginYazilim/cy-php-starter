<?php
/**
 * =====================================================================
 *  php cy schedule:run – Zamanı gelen görevleri çalıştırır
 * ---------------------------------------------------------------------
 *  Sunucuda TEK bir cron satırıyla her dakika çağrılır:
 *      * * * * * cd /yol/site && php cy schedule:run >> /dev/null 2>&1
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;
use App\Core\Log\Logger;
use App\Core\Queue\Queue;
use App\Core\Schedule\Schedule;
use App\Core\Setting;
use Throwable;

final class ScheduleRunCommand extends Command
{
    public function name(): string
    {
        return 'schedule:run';
    }

    public function description(): string
    {
        return 'Zamanı gelen zamanlanmış görevleri çalıştırır.';
    }

    public function help(): string
    {
        return "  php cy schedule:run           Zamanı gelenleri çalıştırır\n"
             . "  php cy schedule:run --list    Görevleri listeler, ÇALIŞTIRMAZ\n"
             . "  php cy schedule:run --force   Zamanı gelmese de hepsini çalıştırır\n"
             . "\n"
             . "  Görevler routes/schedule.php içinde tanımlıdır.\n"
             . "  Cron: * * * * * cd /yol/site && php cy schedule:run";
    }

    public function handle(): int
    {
        try {
            $db = $this->db();

            Setting::load($db);
            Queue::useConnection($db);
        } catch (Throwable) {
            // Veritabanı yoksa ayar gerektirmeyen görevler yine çalışsın.
        }

        $tasks = Schedule::tasks();

        if ($tasks === []) {
            $this->out->info('Tanımlı görev yok (routes/schedule.php).');

            return self::BASARILI;
        }

        if ($this->input->hasOption('list')) {
            return $this->listTasks();
        }

        $now   = time();
        $state = Schedule::state();
        $ran   = 0;

        foreach ($tasks as $task) {
            $lastRun = $state[$task->name()] ?? 0;

            if (!$this->input->hasOption('force') && !$task->isDue($now, $lastRun)) {
                continue;
            }

            if (!$this->lock($task->name())) {
                $this->out->warn($task->name() . ' hala calisiyor, atlandi.');

                continue;
            }

            $this->out->line('  -> ' . $task->name());

            try {
                $task->run();

                Schedule::markRun($task->name(), $now);
                $ran++;
            } catch (Throwable $e) {
                /* Bir görevin hatası diğerlerini engellemez: yedek
                 * alma çöktü diye kuyruk durmamalıdır. */
                Logger::error('Zamanlanmış görev hata verdi: ' . $e->getMessage(), [
                    'gorev' => $task->name(),
                ], 'error');

                $this->out->error($task->name() . ': ' . $e->getMessage());
            } finally {
                $this->unlock($task->name());
            }
        }

        if ($ran === 0) {
            $this->out->muted('  Zamani gelen gorev yok.');

            return self::BASARILI;
        }

        $this->out->blank();
        $this->out->success($ran . ' gorev calisti.');

        return self::BASARILI;
    }

    private function listTasks(): int
    {
        $state = Schedule::state();
        $rows  = [];

        foreach (Schedule::tasks() as $task) {
            $last = $state[$task->name()] ?? 0;

            $rows[] = [
                $task->name(),
                $task->frequency(),
                $last > 0 ? date('d.m.Y H:i', $last) : 'hic',
                $task->description(),
            ];
        }

        $this->out->title('Zamanlanmis gorevler');
        $this->out->table(['Ad', 'Siklik', 'Son calisma', 'Aciklama'], $rows);
        $this->out->blank();

        return self::BASARILI;
    }

    /* -----------------------------------------------------------------
     *  ÜST ÜSTE BİNME KORUMASI
     * -----------------------------------------------------------------
     *  Uzun süren bir görev, bir sonraki dakikada ikinci kez
     *  başlamamalıdır. Kilit basit bir dosyadır; süreç çökse bile
     *  eskimiş kilitler (1 saat) yok sayılır.
     * -------------------------------------------------------------- */

    private function lockPath(string $name): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '', $name) ?: 'gorev';

        return CY_BASE . '/storage/cache/schedule-' . $safe . '.lock';
    }

    private function lock(string $name): bool
    {
        $file = $this->lockPath($name);

        if (is_file($file) && (time() - (int) filemtime($file)) > 3600) {
            @unlink($file);
        }

        if (is_file($file)) {
            return false;
        }

        return @file_put_contents($file, (string) getmypid()) !== false;
    }

    private function unlock(string $name): void
    {
        @unlink($this->lockPath($name));
    }
}
