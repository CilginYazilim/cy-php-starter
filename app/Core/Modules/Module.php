<?php
/**
 * =====================================================================
 *  Module – Diskteki tek bir modülün künyesi
 * ---------------------------------------------------------------------
 *  Bir modül, modules/ altındaki kendi kendine yeten bir klasördür:
 *
 *      modules/Ornek/
 *        ├── module.json          künye (ad, sürüm, açıklama)
 *        ├── routes.php           kendi rotaları
 *        ├── events.php           kendi olay dinleyicileri (opsiyonel)
 *        ├── migrations/          kendi tabloları
 *        ├── src/                 Modules\Ornek\... sınıfları
 *        └── views/               kendi görünümleri
 *
 *  ÇEKİRDEK MODÜLÜ BİLMEZ, MODÜL ÇEKİRDEĞİ BİLİR. Bir modülü silmek
 *  ya da kapatmak çekirdekte tek satır değişiklik gerektirmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Modules;

final class Module
{
    public function __construct(
        public readonly string $ad,
        public readonly string $yol,
        public readonly string $baslik,
        public readonly string $aciklama,
        public readonly string $surum,
        public readonly bool   $aktif,
    ) {
    }

    /**
     * module.json dosyasından okur.
     *
     * Dosya bozuksa ya da eksikse makul varsayılanlarla devam ederiz:
     * bir modülün künyesindeki yazım hatası tüm paneli çökertmemeli.
     */
    public static function fromDirectory(string $directory, bool $aktif): self
    {
        $ad   = basename($directory);
        $json = $directory . DIRECTORY_SEPARATOR . 'module.json';

        $data = [];

        if (is_file($json)) {
            $decoded = json_decode((string) @file_get_contents($json), true);
            $data    = is_array($decoded) ? $decoded : [];
        }

        return new self(
            ad:       $ad,
            yol:      $directory,
            baslik:   (string) ($data['baslik'] ?? $ad),
            aciklama: (string) ($data['aciklama'] ?? ''),
            surum:    (string) ($data['surum'] ?? '1.0.0'),
            aktif:    $aktif,
        );
    }

    public function routesFile(): string
    {
        return $this->yol . DIRECTORY_SEPARATOR . 'routes.php';
    }

    public function eventsFile(): string
    {
        return $this->yol . DIRECTORY_SEPARATOR . 'events.php';
    }

    public function migrationsPath(): string
    {
        return $this->yol . DIRECTORY_SEPARATOR . 'migrations';
    }

    public function viewsPath(): string
    {
        return $this->yol . DIRECTORY_SEPARATOR . 'views';
    }

    public function sourcePath(): string
    {
        return $this->yol . DIRECTORY_SEPARATOR . 'src';
    }

    public function hasMigrations(): bool
    {
        return is_dir($this->migrationsPath())
            && (glob($this->migrationsPath() . DIRECTORY_SEPARATOR . '*.php') ?: []) !== [];
    }
}
