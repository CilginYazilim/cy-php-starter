<?php
/**
 * =====================================================================
 *  php cy migrate:fresh – Tümünü geri alıp baştan uygular
 * ---------------------------------------------------------------------
 *  YIKICI KOMUT. Yalnızca migration'ların oluşturduğu tabloları
 *  etkiler; kurulum/database.sql ile gelen çekirdek tablolara
 *  (kullanicilar, ayarlar…) dokunmaz — onları down() yazan bir
 *  migration yoksa kimse silmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Database\Migrator;

final class MigrateFreshCommand extends Command
{
    public function name(): string
    {
        return 'migrate:fresh';
    }

    public function description(): string
    {
        return 'Tüm migration\'ları geri alıp yeniden uygular. (yıkıcı)';
    }

    public function help(): string
    {
        return "  php cy migrate:fresh            Onay sorar\n"
             . "  php cy migrate:fresh --force    Sormaz (cron/CI için)\n"
             . "  php cy migrate:fresh --seed     Bitince db:seed de çalıştırır\n"
             . "\n"
             . "  Yalnızca migration'ların yarattığı tabloları etkiler.\n"
             . "  Çekirdek tablolar (kullanicilar, ayarlar…) kurulum SQL'inden\n"
             . "  geldiği için bu komuttan etkilenmez.";
    }

    public function handle(): int
    {
        if (!$this->confirmDestructive('TÜM migration\'lar geri alınıp yeniden uygulanacak. Emin misiniz?')) {
            $this->out->info('Vazgeçildi.');

            return self::BASARILI;
        }

        $migrator = new Migrator($this->db(), (string) Config::get('db.migrations'), \App\Core\Modules\Modules::migrationPaths());

        $this->out->title('Geri alınıyor');

        $undone = $migrator->reset(function (string $name): void {
            $this->out->line('  ← ' . $name);
        });

        if ($undone === []) {
            $this->out->muted('  (geri alınacak bir şey yoktu)');
        }

        $this->out->title('Yeniden uygulanıyor');

        $done = $migrator->run(function (string $name): void {
            $this->out->line('  → ' . $name);
        });

        $this->out->blank();
        $this->out->success(count($done) . ' migration yeniden uygulandı.');

        if ($this->input->hasOption('seed')) {
            $seeder = new DbSeedCommand();
            $seeder->setContext($this->input, $this->out);

            return $seeder->handle();
        }

        return self::BASARILI;
    }
}
