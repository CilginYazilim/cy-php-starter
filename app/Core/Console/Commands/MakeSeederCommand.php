<?php
/**
 * =====================================================================
 *  php cy make:seeder <ad> – Yeni seeder dosyası oluşturur
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;

final class MakeSeederCommand extends MakeCommand
{
    public function name(): string
    {
        return 'make:seeder';
    }

    public function description(): string
    {
        return 'Yeni bir seeder dosyası oluşturur.';
    }

    public function help(): string
    {
        return "  php cy make:seeder Urun\n"
             . "  → database/seeders/UrunSeeder.php";
    }

    public function handle(): int
    {
        $name  = $this->studly($this->requireName('php cy make:seeder <ad>'));
        $class = str_ends_with($name, 'Seeder') ? $name : $name . 'Seeder';

        $path = rtrim((string) Config::get('db.seeders'), '/\\') . DIRECTORY_SEPARATOR . $class . '.php';

        if (!$this->writeFile($path, 'seeder', ['AD' => $class])) {
            return self::HATA;
        }

        $this->out->muted('  Çalıştırmak için: php cy db:seed --class=' . $class);

        return self::BASARILI;
    }
}
