<?php
/**
 * =====================================================================
 *  php cy make:migration <ad> – Yeni migration dosyası oluşturur
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;

final class MakeMigrationCommand extends MakeCommand
{
    public function name(): string
    {
        return 'make:migration';
    }

    public function description(): string
    {
        return 'Yeni bir migration dosyası oluşturur.';
    }

    public function help(): string
    {
        return "  php cy make:migration urunler_tablosu\n"
             . "  php cy make:migration \"urunlere aciklama ekle\"\n"
             . "\n"
             . "  Dosya adının başına zaman damgası eklenir; migration'lar\n"
             . "  bu sayede her makinede AYNI sırada çalışır.";
    }

    public function handle(): int
    {
        $name = $this->requireName('php cy make:migration <ad>');

        $slug = $this->snake($name);

        if ($slug === '') {
            $this->out->error('Geçerli bir ad verin (harf ve rakam içermeli).');

            return self::HATA;
        }

        $file = date('Y_m_d_His') . '_' . $slug;
        $path = rtrim((string) Config::get('db.migrations'), '/\\') . DIRECTORY_SEPARATOR . $file . '.php';

        if (!$this->writeFile($path, 'migration', ['AD' => $slug])) {
            return self::HATA;
        }

        $this->out->muted('  Şimdi up() ve down() gövdelerini doldurun, sonra: php cy migrate');

        return self::BASARILI;
    }
}
