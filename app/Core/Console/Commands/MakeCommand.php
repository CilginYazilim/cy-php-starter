<?php
/**
 * =====================================================================
 *  MakeCommand – Dosya üreten komutların ortak atası
 * ---------------------------------------------------------------------
 *  Kalıp (stub) dosyaları app/Core/Console/stubs/ altındadır. İçlerinde
 *  {{AD}} gibi yer tutucular bulunur; burada gerçek değerlerle
 *  değiştirilirler.
 *
 *  Kalıpları kendi zevkinize göre düzenleyebilirsiniz — üretilen her
 *  dosya o kalıptan çıkar, kodun içine gömülü değildir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;
use RuntimeException;

abstract class MakeCommand extends Command
{
    /**
     * Kullanıcının verdiği adı sınıf adına çevirir.
     *      "urun kategori" → "UrunKategori"
     *      "urun-kategori" → "UrunKategori"
     */
    protected function studly(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]+/', ' ', $name) ?? '';

        return str_replace(' ', '', ucwords(trim($clean)));
    }

    /** "UrunKategori" → "urun_kategori" (tablo/dosya adları için) */
    protected function snake(string $name): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $this->studly($name)) ?? '';

        return strtolower($snake);
    }

    protected function stub(string $name): string
    {
        $file = CY_BASE . '/app/Core/Console/stubs/' . $name . '.stub';

        if (!is_file($file)) {
            throw new RuntimeException('Kalıp dosyası bulunamadı: ' . $name . '.stub');
        }

        return (string) file_get_contents($file);
    }

    /**
     * Kalıbı doldurup diske yazar.
     *
     * @param array<string,string> $replacements
     * @return bool false → dosya zaten var (üzerine YAZILMAZ)
     */
    protected function writeFile(string $path, string $stub, array $replacements): bool
    {
        if (is_file($path)) {
            $this->out->error('Dosya zaten var: ' . \App\Core\ErrorHandler::relative($path));

            return false;
        }

        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Klasör oluşturulamadı: ' . $dir);
        }

        $content = str_replace(
            array_map(static fn (string $key): string => '{{' . $key . '}}', array_keys($replacements)),
            array_values($replacements),
            $this->stub($stub)
        );

        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException('Dosya yazılamadı: ' . $path);
        }

        $this->out->success('Oluşturuldu: ' . \App\Core\ErrorHandler::relative($path));

        return true;
    }

    /** Argüman verilmemişse anlaşılır bir hata bas. */
    protected function requireName(string $usage): string
    {
        $name = trim($this->input->argument(0));

        if ($name === '') {
            $this->out->error('Bir ad vermelisiniz.');
            $this->out->muted('  Kullanım: ' . $usage);

            exit(self::HATA);
        }

        return $name;
    }
}
