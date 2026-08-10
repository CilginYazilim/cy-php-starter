<?php
/**
 * =====================================================================
 *  php cy migrate:rollback – Son partiyi geri alır
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Database\Migrator;

final class MigrateRollbackCommand extends Command
{
    public function name(): string
    {
        return 'migrate:rollback';
    }

    public function description(): string
    {
        return 'Son migration partisini geri alır.';
    }

    public function help(): string
    {
        return "  php cy migrate:rollback              Son partiyi geri alır\n"
             . "  php cy migrate:rollback --step=3     Son 3 partiyi geri alır\n"
             . "\n"
             . "  Bir \"parti\", tek bir 'php cy migrate' çağrısında uygulanan\n"
             . "  migration'ların tamamıdır; üç dosyalık bir dağıtım tek\n"
             . "  komutla geri sarılır.";
    }

    public function handle(): int
    {
        $steps = max(1, $this->input->intOption('step', 1));

        if (!$this->confirmDestructive($steps . ' partilik migration geri alınacak. Devam edilsin mi?')) {
            $this->out->info('Vazgeçildi.');

            return self::BASARILI;
        }

        $migrator = new Migrator($this->db(), (string) Config::get('db.migrations'), \App\Core\Modules\Modules::migrationPaths());

        $this->out->title('Geri alınıyor');

        $undone = $migrator->rollback($steps, function (string $name): void {
            $this->out->line('  ← ' . $name);
        });

        $this->out->blank();

        if ($undone === []) {
            $this->out->info('Geri alınacak migration yok.');

            return self::BASARILI;
        }

        $this->out->success(count($undone) . ' migration geri alındı.');

        return self::BASARILI;
    }
}
