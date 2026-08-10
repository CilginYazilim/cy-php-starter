<?php
/**
 * =====================================================================
 *  Kernel – Komutları kaydeder ve doğru olanı çalıştırır
 * ---------------------------------------------------------------------
 *      php cy                    → komut listesi
 *      php cy migrate            → MigrateCommand::handle()
 *      php cy yardim migrate     → komutun ayrıntılı yardımı
 *
 *  Komutlar burada ELLE listelenir. Klasör tarayıp otomatik bulmak
 *  "sihirli" görünür ama her çalıştırmada dosya sistemi taraması
 *  yapar ve hangi komutun nereden geldiğini gizler. Tek bir dizi
 *  hem hızlı hem de okunur.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Console\Commands\CacheClearCommand;
use App\Core\Console\Commands\ConfigCacheCommand;
use App\Core\Console\Commands\ConfigClearCommand;
use App\Core\Console\Commands\DbSeedCommand;
use App\Core\Console\Commands\LogPurgeCommand;
use App\Core\Console\Commands\MailTestCommand;
use App\Core\Console\Commands\MailWorkCommand;
use App\Core\Console\Commands\MakeControllerCommand;
use App\Core\Console\Commands\MakeMigrationCommand;
use App\Core\Console\Commands\MakeModelCommand;
use App\Core\Console\Commands\MakeModuleCommand;
use App\Core\Console\Commands\MakeSeederCommand;
use App\Core\Console\Commands\ModuleCommand;
use App\Core\Console\Commands\MigrateCommand;
use App\Core\Console\Commands\MigrateFreshCommand;
use App\Core\Console\Commands\MigrateRollbackCommand;
use App\Core\Console\Commands\MigrateStatusCommand;
use App\Core\Console\Commands\QueueStatusCommand;
use App\Core\Console\Commands\QueueWorkCommand;
use App\Core\Console\Commands\ScheduleRunCommand;
use Throwable;

final class Kernel
{
    /** @var array<int,class-string<Command>> */
    private const COMMANDS = [
        MigrateCommand::class,
        MigrateRollbackCommand::class,
        MigrateStatusCommand::class,
        MigrateFreshCommand::class,
        DbSeedCommand::class,

        MakeMigrationCommand::class,
        MakeSeederCommand::class,
        MakeControllerCommand::class,
        MakeModelCommand::class,
        MakeModuleCommand::class,

        ModuleCommand::class,

        CacheClearCommand::class,
        ConfigCacheCommand::class,
        ConfigClearCommand::class,
        LogPurgeCommand::class,

        QueueWorkCommand::class,
        QueueStatusCommand::class,
        ScheduleRunCommand::class,

        MailWorkCommand::class,
        MailTestCommand::class,
    ];

    /** @param array<int,string> $argv */
    public function run(array $argv): int
    {
        $input = new Input($argv);
        $out   = new Output(!$input->hasOption('no-color'));

        /* Ayarlar (ve dolayısıyla açık modül listesi) veritabanındadır.
         * Web isteğinde index.php yükler; komut satırında burada
         * yükleriz ki "php cy migrate" modül migration'larını da
         * görsün. Veritabanı yoksa sessizce devam ederiz: config:clear
         * gibi komutlar veritabanı olmadan da çalışmalıdır. */
        try {
            \App\Core\Setting::load(\App\Core\Database::connection());
            \App\Core\Modules\Modules::boot();
        } catch (Throwable) {
        }

        $name = $input->command();

        if ($name === '' || $name === 'list' || $name === 'liste') {
            $this->showList($out);

            return Command::BASARILI;
        }

        if ($name === 'yardim' || $name === 'help') {
            return $this->showHelp($input->argument(0), $out);
        }

        $command = $this->resolve($name);

        if ($command === null) {
            $out->error('Bilinmeyen komut: ' . $name);
            $this->suggest($name, $out);

            return Command::HATA;
        }

        $command->setContext($input, $out);

        try {
            return $command->handle();
        } catch (Throwable $e) {
            // ErrorHandler da yakalar ve loglar; burada terminalde
            // okunur bir özet bırakıyoruz.
            $out->error($e->getMessage());

            if (\App\Core\Config::isDebug()) {
                $out->muted('  ' . \App\Core\ErrorHandler::relative($e->getFile()) . ':' . $e->getLine());
            }

            return Command::HATA;
        }
    }

    private function resolve(string $name): ?Command
    {
        foreach (self::COMMANDS as $class) {
            /** @var Command $command */
            $command = new $class();

            if ($command->name() === $name) {
                return $command;
            }
        }

        return null;
    }

    /** Yazım hatası olabilir mi? En yakın komutu önerir. */
    private function suggest(string $name, Output $out): void
    {
        $best     = '';
        $distance = PHP_INT_MAX;

        foreach (self::COMMANDS as $class) {
            $candidate = (new $class())->name();
            $current   = levenshtein($name, $candidate);

            if ($current < $distance) {
                $distance = $current;
                $best     = $candidate;
            }
        }

        if ($best !== '' && $distance <= 4) {
            $out->muted('  Şunu mu demek istediniz: php cy ' . $best);
        }

        $out->muted('  Tüm komutlar: php cy list');
    }

    private function showList(Output $out): void
    {
        $out->blank();
        $out->line('  CY PHP Starter – Konsol', 'beyaz');
        $out->muted('  Kullanım: php cy <komut> [argümanlar] [--secenekler]');

        /** @var array<string,array<int,array{0:string,1:string}>> $groups */
        $groups = [];

        foreach (self::COMMANDS as $class) {
            /** @var Command $command */
            $command = new $class();
            $name    = $command->name();

            // "migrate" ve "migrate:rollback" AYNI gruba girsin.
            $group = str_contains($name, ':') ? explode(':', $name, 2)[0] : $name;

            $groups[$group][] = [$name, $command->description()];
        }

        ksort($groups);

        foreach ($groups as $group => $items) {
            $out->title($group);

            $out->table(['Komut', 'Açıklama'], $items);
        }

        $out->blank();
        $out->muted('  Bir komutun ayrıntısı için: php cy yardim <komut>');
        $out->blank();
    }

    private function showHelp(string $name, Output $out): int
    {
        if ($name === '') {
            $this->showList($out);

            return Command::BASARILI;
        }

        $command = $this->resolve($name);

        if ($command === null) {
            $out->error('Bilinmeyen komut: ' . $name);

            return Command::HATA;
        }

        $out->title($command->name());
        $out->line('  ' . $command->description());

        $help = $command->help();

        if ($help !== '') {
            $out->blank();
            $out->line($help);
        }

        $out->blank();

        return Command::BASARILI;
    }
}
