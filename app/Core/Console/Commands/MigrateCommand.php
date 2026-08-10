<?php
/**
 * =====================================================================
 *  php cy migrate – Bekleyen şema değişikliklerini uygular
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Database\Migrator;

final class MigrateCommand extends Command
{
    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Bekleyen migration dosyalarını çalıştırır.';
    }

    public function help(): string
    {
        return "  php cy migrate\n"
             . "  php cy migrate --pretend    Ne çalışacağını gösterir, DEĞİŞTİRMEZ";
    }

    public function handle(): int
    {
        $migrator = new Migrator($this->db(), (string) Config::get('db.migrations'), \App\Core\Modules\Modules::migrationPaths());

        $pending = $migrator->pending();

        if ($pending === []) {
            $this->out->info('Bekleyen migration yok — veritabanı güncel.');

            return self::BASARILI;
        }

        if ($this->input->hasOption('pretend')) {
            $this->out->title('Çalıştırılacak migration\'lar (deneme)');

            foreach ($pending as $name) {
                $this->out->line('  · ' . $name);
            }

            $this->out->blank();

            return self::BASARILI;
        }

        $this->out->title(count($pending) . ' migration çalıştırılıyor');

        /* Dosya adını çalıştırmadan ÖNCE basıyoruz: bir migration
         * yarıda patlarsa terminalde en son hangi dosyada kalındığı
         * görünsün. */
        $done = $migrator->run(function (string $name): void {
            $this->out->line('  → ' . $name);
        });

        $this->out->blank();
        $this->out->success(count($done) . ' migration uygulandı.');

        return self::BASARILI;
    }
}
