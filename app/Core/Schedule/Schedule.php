<?php
/**
 * =====================================================================
 *  Schedule – Zamanlanmış görevler
 * ---------------------------------------------------------------------
 *  SUNUCUYA TEK BİR CRON SATIRI YAZARSINIZ:
 *
 *      * * * * * cd /yol/site && php cy schedule:run >> /dev/null 2>&1
 *
 *  Geri kalan her şey routes/schedule.php içinde PHP olarak tanımlanır.
 *  Yeni bir görev eklemek için sunucuya SSH ile bağlanıp crontab
 *  düzenlemeniz gerekmez — kodu dağıtmanız yeterlidir.
 *
 *      Schedule::call('onbellek-temizle', fn () => Cache::purgeExpired())
 *              ->daily('04:00');
 *
 *      Schedule::call('kuyruk', fn () => Queue::runNext())
 *              ->everyMinute();
 *
 *  SON ÇALIŞMA ZAMANI NEREDE TUTULUR?
 *  storage/cache/schedule.json dosyasında. Bilerek uygulama
 *  önbelleğinde DEĞİL: "php cy cache:clear" çalıştırdığınızda
 *  günlük görevlerin aynı gün ikinci kez çalışmasını istemeyiz.
 *
 *  GÖREVLER ÜST ÜSTE BİNMEZ: çalışan bir görev bitene kadar aynı
 *  görev yeniden başlatılmaz (kilit dosyası). Uzun süren bir rapor,
 *  bir sonraki dakikada ikinci kez başlamaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Schedule;

final class Schedule
{
    /** @var array<int,Task> */
    private static array $tasks = [];

    private static ?string $stateFile = null;

    /**
     * Yeni bir görev tanımlar.
     *
     * @param string   $name     Benzersiz ad (son çalışma bununla izlenir)
     * @param callable $callback Görevin kendisi
     */
    public static function call(string $name, callable $callback): Task
    {
        $task = new Task($name, $callback);

        self::$tasks[] = $task;

        return $task;
    }

    /** @return array<int,Task> */
    public static function tasks(): array
    {
        return self::$tasks;
    }

    public static function flush(): void
    {
        self::$tasks = [];
    }

    /* =================================================================
     *  DURUM DOSYASI
     * ============================================================== */

    public static function stateFile(): string
    {
        if (self::$stateFile !== null) {
            return self::$stateFile;
        }

        $dir = CY_BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return self::$stateFile = $dir . DIRECTORY_SEPARATOR . 'schedule.json';
    }

    public static function useStateFile(?string $path): void
    {
        self::$stateFile = $path;
    }

    /** @return array<string,int> görev adı => son çalışma (unix) */
    public static function state(): array
    {
        $file = self::stateFile();

        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? array_map('intval', $data) : [];
    }

    public static function markRun(string $name, int $at): void
    {
        $state        = self::state();
        $state[$name] = $at;

        @file_put_contents(self::stateFile(), json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
