<?php
/**
 * =====================================================================
 *  php cy module – Modülleri listeler, açar, kapatır
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;
use App\Core\Modules\Modules;

final class ModuleCommand extends Command
{
    public function name(): string
    {
        return 'module';
    }

    public function description(): string
    {
        return 'Modülleri listeler, açar veya kapatır.';
    }

    public function help(): string
    {
        return "  php cy module                    Modülleri listeler\n"
             . "  php cy module --enable=Ornek     Modülü açar\n"
             . "  php cy module --disable=Ornek    Modülü kapatır\n"
             . "\n"
             . "  Modül açıldıktan sonra kendi tablolarını kurmak için:\n"
             . "      php cy migrate\n"
             . "\n"
             . "  KAPALI MODÜL HİÇ YÜKLENMEZ: rotaları tanımlanmaz,\n"
             . "  sınıfları yüklenmez, olayları dinlenmez.";
    }

    public function handle(): int
    {
        // Modül durumu "ayarlar" tablosunda; yazma için bağlantı şart.
        $this->db();

        $enable  = $this->input->option('enable');
        $disable = $this->input->option('disable');

        if ($enable !== '') {
            return $this->toggle($enable, true);
        }

        if ($disable !== '') {
            return $this->toggle($disable, false);
        }

        return $this->listModules();
    }

    private function toggle(string $name, bool $enable): int
    {
        $module = Modules::find($name);

        if ($module === null) {
            $this->out->error('Modül bulunamadı: ' . $name);
            $this->out->muted('  Mevcut modüller: php cy module');

            return self::HATA;
        }

        if ($enable) {
            Modules::enable($name);

            $this->out->success($module->baslik . ' açıldı.');

            if ($module->hasMigrations()) {
                $this->out->muted('  Tablolarını kurmak için: php cy migrate');
            }

            return self::BASARILI;
        }

        Modules::disable($name);

        $this->out->success($module->baslik . ' kapatıldı.');
        $this->out->muted('  Dosyaları ve tabloları yerinde kaldı; istediğinizde tekrar açabilirsiniz.');

        return self::BASARILI;
    }

    private function listModules(): int
    {
        $modules = Modules::all();

        if ($modules === []) {
            $this->out->info('Hiç modül yok.');
            $this->out->muted('  Oluşturmak için: php cy make:module Ornek');

            return self::BASARILI;
        }

        $rows = [];

        foreach ($modules as $module) {
            $rows[] = [
                $module->aktif ? 'AÇIK' : 'kapalı',
                $module->ad,
                $module->surum,
                $module->hasMigrations() ? 'var' : '—',
                $module->aciklama !== '' ? $module->aciklama : $module->baslik,
            ];
        }

        $this->out->title('Modüller');
        $this->out->table(['Durum', 'Ad', 'Sürüm', 'Tablo', 'Açıklama'], $rows);
        $this->out->blank();
        $this->out->muted('  Açmak için:   php cy module --enable=<Ad>');
        $this->out->muted('  Kapatmak için: php cy module --disable=<Ad>');
        $this->out->blank();

        return self::BASARILI;
    }
}
