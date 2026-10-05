<?php
/**
 * =====================================================================
 *  php cy migrate:fresh – Tümünü geri alıp baştan uygular
 * ---------------------------------------------------------------------
 *  YIKICI KOMUT. Yalnızca kurulumdan SONRA uygulanan migration'ları
 *  geri alır. Kurulumla gelen her şey (kurulum/database.sql ve
 *  sihirbazın çalıştırdığı migration'lar) "temel parti" (0) olarak
 *  işaretlidir ve bu komuttan etkilenmez.
 *
 *  Eskiden bu açıklama "çekirdek tablolara dokunmaz" diyordu ama
 *  kurulumun migration'ları 1. partiye yazıldığı için doğru değildi:
 *  komut "sayfalar" tablosunu düşürüp ayar satırlarını siliyordu.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;

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
             . "  Yalnızca kurulumdan SONRA uygulanan migration'ları etkiler.\n"
             . "  Kurulumla gelen tablolar (parti 0) bu komuttan etkilenmez;\n"
             . "  durumu görmek için: php cy migrate:status";
    }

    public function handle(): int
    {
        if (!$this->confirmDestructive('TÜM migration\'lar geri alınıp yeniden uygulanacak. Emin misiniz?')) {
            $this->out->info('Vazgeçildi.');

            return self::BASARILI;
        }

        $migrator = \App\Core\Modules\Modules::migrator($this->db(), (string) Config::get('db.migrations'));

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
