<?php
/**
 * =====================================================================
 *  php cy migrate:status – Hangi migration çalıştı, hangisi bekliyor?
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Database\Migrator;

final class MigrateStatusCommand extends Command
{
    public function name(): string
    {
        return 'migrate:status';
    }

    public function description(): string
    {
        return 'Migration durumunu listeler.';
    }

    /** Temel parti (0) kurulumla gelir ve geri alınamaz; tabloda öyle görünsün. */
    private static function partiEtiketi(int $parti): string
    {
        return $parti === Migrator::BASELINE ? '0 (kurulum)' : (string) $parti;
    }

    public function handle(): int
    {
        $migrator = \App\Core\Modules\Modules::migrator($this->db(), (string) Config::get('db.migrations'));

        $available = $migrator->available();
        $completed = $migrator->completed();

        if ($available === [] && $completed === []) {
            $this->out->info('Hiç migration dosyası yok.');
            $this->out->muted('  Oluşturmak için: php cy make:migration <ad>');

            return self::BASARILI;
        }

        $rows      = [];
        $bekleyen  = 0;

        foreach ($available as $name) {
            $done = array_key_exists($name, $completed);

            if (!$done) {
                $bekleyen++;
            }

            $rows[] = [
                $done ? 'uygulandı' : 'BEKLİYOR',
                $done ? self::partiEtiketi($completed[$name]) : '—',
                $name,
            ];
        }

        /* Dosyası silinmiş ama tabloda kayıtlı olanlar: bunlar
         * rollback edilemez (down() kodu artık yok). Uyarmazsak
         * geliştirici bunu ancak geri alma denediğinde fark eder. */
        $kayip = array_diff(array_keys($completed), $available);

        foreach ($kayip as $name) {
            $rows[] = ['DOSYA YOK', self::partiEtiketi($completed[$name]), $name];
        }

        $this->out->title('Migration durumu');
        $this->out->table(['Durum', 'Parti', 'Dosya'], $rows);
        $this->out->blank();

        $this->out->muted(sprintf(
            '  Toplam %d dosya · %d uygulandı · %d bekliyor',
            count($available),
            count($available) - $bekleyen,
            $bekleyen
        ));

        if ($kayip !== []) {
            $this->out->warn(count($kayip) . ' kayıt için dosya bulunamadı; bunlar geri alınamaz.');
        }

        $this->out->blank();

        return self::BASARILI;
    }
}
