<?php
/**
 * =====================================================================
 *  php cy db:seed – Örnek/başlangıç verisi yazar
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Database\Seeder;
use RuntimeException;

final class DbSeedCommand extends Command
{
    public function name(): string
    {
        return 'db:seed';
    }

    public function description(): string
    {
        return 'database/seeders içindeki tohumlayıcıları çalıştırır.';
    }

    public function help(): string
    {
        return "  php cy db:seed                    Hepsini çalıştırır\n"
             . "  php cy db:seed --class=OrnekSeeder  Yalnızca birini\n"
             . "\n"
             . "  Seeder'lar ÖRNEK veri içindir (demo kullanıcı, sahte kayıt).\n"
             . "  Uygulamanın çalışması için ZORUNLU veri (roller, varsayılan\n"
             . "  ayarlar) seeder'a değil migration'a yazılmalıdır — böylece\n"
             . "  her ortamda kesinlikle bulunur.";
    }

    public function handle(): int
    {
        $path = (string) Config::get('db.seeders');

        if (!is_dir($path)) {
            $this->out->info('database/seeders klasörü yok.');

            return self::BASARILI;
        }

        /* Örnek veri yayına yazılmamalı: canlı veritabanına "Demo
         * Kullanıcı" eklemek en iyi ihtimalle utanç vericidir. */
        if (!$this->confirmDestructive('Seeder\'lar veritabanına veri yazacak. Devam edilsin mi?')) {
            $this->out->info('Vazgeçildi.');

            return self::BASARILI;
        }

        $only  = $this->input->option('class');
        $files = glob(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];

        sort($files, SORT_STRING);

        if ($only !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (string $file): bool => basename($file, '.php') === $only
            ));

            if ($files === []) {
                $this->out->error('Seeder bulunamadı: ' . $only);

                return self::HATA;
            }
        }

        if ($files === []) {
            $this->out->info('Çalıştırılacak seeder yok.');

            return self::BASARILI;
        }

        $db = $this->db();

        $this->out->title(count($files) . ' seeder çalıştırılıyor');

        foreach ($files as $file) {
            $name = basename($file, '.php');

            $this->out->line('  → ' . $name);

            $seeder = require $file;

            if (!$seeder instanceof Seeder) {
                throw new RuntimeException(
                    $name . '.php bir Seeder nesnesi döndürmüyor. '
                    . 'Dosya "return new class extends App\Core\Database\Seeder { ... };" ile bitmelidir.'
                );
            }

            $seeder->setConnection($db);
            $seeder->setReporter(fn (string $message) => $this->out->muted('      ' . $message));
            $seeder->run();
        }

        $this->out->blank();
        $this->out->success(count($files) . ' seeder çalıştı.');

        return self::BASARILI;
    }
}
