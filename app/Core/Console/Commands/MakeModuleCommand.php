<?php
/**
 * =====================================================================
 *  php cy make:module <Ad> – Çalışır durumda bir modül iskeleti üretir
 * ---------------------------------------------------------------------
 *  Üretilen modül HEMEN çalışır: açıp migrate ettiğinizde panelde
 *  listeleyip kayıt ekleyebileceğiniz eksiksiz bir CRUD ekranı gelir.
 *  Kendi modülünüzü yazarken bu iskeleti değiştirerek ilerlersiniz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Modules\Modules;

final class MakeModuleCommand extends MakeCommand
{
    public function name(): string
    {
        return 'make:module';
    }

    public function description(): string
    {
        return 'Çalışır durumda yeni bir modül iskeleti oluşturur.';
    }

    public function help(): string
    {
        return "  php cy make:module Stok\n"
             . "\n"
             . "  Üretilenler:\n"
             . "      modules/Stok/module.json\n"
             . "      modules/Stok/routes.php\n"
             . "      modules/Stok/src/Controllers/StokController.php\n"
             . "      modules/Stok/views/index.php\n"
             . "      modules/Stok/migrations/*_stok_tablosu.php\n"
             . "\n"
             . "  Sonra:\n"
             . "      php cy module --enable=Stok\n"
             . "      php cy migrate";
    }

    public function handle(): int
    {
        $name = $this->studly($this->requireName('php cy make:module <Ad>'));

        if ($name === '') {
            $this->out->error('Geçerli bir modül adı verin (harfle başlamalı).');

            return self::HATA;
        }

        $root = Modules::path() . DIRECTORY_SEPARATOR . $name;

        if (is_dir($root)) {
            $this->out->error('Bu modül zaten var: modules/' . $name);

            return self::HATA;
        }

        $table = $this->snake($name);
        $slug  = str_replace('_', '-', $table);

        $replacements = [
            'AD'    => $name,
            'TABLO' => $table,
            'SLUG'  => $slug,
        ];

        $files = [
            'module.json'                                     => 'module-json',
            'routes.php'                                      => 'module-routes',
            'src/Controllers/' . $name . 'Controller.php'     => 'module-controller',
            'src/Repositories/' . $name . 'Repository.php'    => 'module-repository',
            'views/index.php'                                 => 'module-view',
            'migrations/' . date('Y_m_d_His') . '_' . $table . '_tablosu.php' => 'module-migration',
        ];

        foreach ($files as $relative => $stub) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (!$this->writeFile($path, $stub, $replacements)) {
                return self::HATA;
            }
        }

        $this->out->blank();
        $this->out->success($name . ' modülü oluşturuldu.');
        $this->out->muted('  Etkinleştirmek için:  php cy module --enable=' . $name);
        $this->out->muted('  Tablosunu kurmak için: php cy migrate');
        $this->out->muted('  Sonra panelde: /panel/' . $slug);

        return self::BASARILI;
    }
}
