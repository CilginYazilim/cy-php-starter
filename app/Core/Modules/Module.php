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
    /**
     * @param array<string,array<int,string>> $yetkiler rol => yetki adları (module.json → "yetkiler")
     * @param array{route:string,icon:string,label:string,can:string}|null $menu
     *        Sol menü bağlantısı (module.json → "menu"); "menu": false ise null
     */
    public function __construct(
        public readonly string $ad,
        public readonly string $yol,
        public readonly string $baslik,
        public readonly string $aciklama,
        public readonly string $surum,
        public readonly bool   $aktif,
        public readonly array  $yetkiler = [],
        public readonly ?array $menu = null,
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
            yetkiler: self::parseAbilities($data['yetkiler'] ?? []),
            menu:     self::parseMenu($data, $ad, (string) ($data['baslik'] ?? $ad)),
        );
    }

    /**
     * Sol menü bağlantısı. Tanımlanmayan alanlar addan türetilir:
     * StokTakip → "panel/stok-takip", yetki "stok_takip.view".
     * "menu": false yazan modül menüde görünmek istemiyordur → null.
     *
     * @param array<string,mixed> $data
     * @return array{route:string,icon:string,label:string,can:string}|null
     */
    private static function parseMenu(array $data, string $ad, string $baslik): ?array
    {
        if (array_key_exists('menu', $data) && $data['menu'] === false) {
            return null;
        }

        $tanim = is_array($data['menu'] ?? null) ? $data['menu'] : [];
        $slug  = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $ad) ?? $ad);

        return [
            'route' => (string) ($tanim['route'] ?? 'panel/' . $slug),
            'icon'  => (string) ($tanim['icon']  ?? 'server'),
            'label' => (string) ($tanim['label'] ?? $baslik),
            'can'   => (string) ($tanim['can']   ?? str_replace('-', '_', $slug) . '.view'),
        ];
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

    /** Örnek veri tohumlayıcıları (php cy db:seed ve sihirbazın "örnek veri"si çalıştırır). */
    public function seedersPath(): string
    {
        return $this->yol . DIRECTORY_SEPARATOR . 'seeders';
    }

    /**
     * module.json → "yetkiler": { "editor": ["stok.view"], "uye": [...] }
     *
     * MODÜL KENDİ YETKİLERİNİ DAĞITIR. Eskiden editöre bir modülü açmak
     * için çekirdeğin Role.php'sini düzenlemek gerekiyordu; modül
     * silindiğinde o satırlar orada unutuluyordu. Yönetici zaten her
     * yetkiye sahiptir (Role::can), listede yazmasına gerek yoktur.
     *
     * Bozuk girdi sessizce atlanır: künyedeki bir yazım hatası paneli
     * çökertmemeli. Yetki adı "modul.islem" biçiminde olmalıdır.
     *
     * @return array<string,array<int,string>>
     */
    public static function parseAbilities(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $sonuc = [];

        foreach ($raw as $rol => $liste) {
            if (!is_string($rol) || !is_array($liste)) {
                continue;
            }

            $gecerli = array_values(array_filter(
                $liste,
                static fn (mixed $y): bool => is_string($y) && preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+\z/', $y) === 1
            ));

            if ($gecerli !== []) {
                $sonuc[$rol] = $gecerli;
            }
        }

        return $sonuc;
    }
}
