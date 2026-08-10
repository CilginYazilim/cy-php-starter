<?php
/**
 * =====================================================================
 *  Modules – Modülleri bulur, açar/kapatır ve yükler
 * ---------------------------------------------------------------------
 *  YAŞAM DÖNGÜSÜ
 *      keşif   → modules/ klasörü taranır
 *      açma    → ayarlar tablosundaki listeye eklenir
 *      yükleme → sınıfları, rotaları, olayları devreye girer
 *      kapatma → listeden çıkarılır; dosyalar yerinde kalır
 *      silme   → klasörü siler (tablolarını migration ile geri alın)
 *
 *  AÇIK MODÜL LİSTESİ NEREDE?
 *  "ayarlar" tablosunda "aktif_moduller" anahtarında, JSON dizi
 *  olarak. Ayrı bir tablo açmadık: liste küçüktür, ayarlarla birlikte
 *  zaten her istekte belleğe alınır ve çok sunuculu kurulumlarda
 *  ortaktır (dosyaya yazsaydık sunucular arasında ayrışırdı).
 *
 *  KAPALI MODÜL HİÇ YÜKLENMEZ: rotaları tanımlanmaz, sınıfları
 *  yüklenmez, olayları dinlenmez. "Kapalı ama hâlâ çalışıyor" diye
 *  bir ara durum yoktur.
 *
 *  HATA YALITIMI: Bir modülün routes.php dosyası patlarsa TÜM SİTE
 *  çökmemelidir. Yükleme hataları loglanır ve o modül atlanır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Modules;

use App\Core\Autoloader;
use App\Core\Database;
use App\Core\Log\Logger;
use App\Core\Router;
use App\Core\Setting;
use Throwable;

final class Modules
{
    private const SETTING_KEY = 'aktif_moduller';

    /** @var array<string,Module>|null */
    private static ?array $all = null;

    /** @var array<int,string> */
    private static array $loaded = [];

    public static function path(): string
    {
        return CY_BASE . DIRECTORY_SEPARATOR . 'modules';
    }

    /* =================================================================
     *  KEŞİF
     * ============================================================== */

    /**
     * Diskteki tüm modüller (açık + kapalı).
     *
     * @return array<string,Module>
     */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        $enabled = self::enabledNames();
        $found   = [];

        foreach (glob(self::path() . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);

            // Klasör adı sınıf adı olacak: yalnızca güvenli adlar.
            if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) !== 1) {
                continue;
            }

            $found[$name] = Module::fromDirectory($directory, in_array($name, $enabled, true));
        }

        ksort($found);

        return self::$all = $found;
    }

    public static function find(string $name): ?Module
    {
        return self::all()[$name] ?? null;
    }

    /** @return array<string,Module> */
    public static function enabled(): array
    {
        return array_filter(self::all(), static fn (Module $m): bool => $m->aktif);
    }

    /** @return array<int,string> */
    public static function enabledNames(): array
    {
        $raw = Setting::get(self::SETTING_KEY, '[]');

        $list = json_decode($raw, true);

        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    public static function isEnabled(string $name): bool
    {
        return in_array($name, self::enabledNames(), true);
    }

    /* =================================================================
     *  AÇMA / KAPATMA
     * ============================================================== */

    public static function enable(string $name): bool
    {
        if (self::find($name) === null) {
            return false;
        }

        $list = self::enabledNames();

        if (!in_array($name, $list, true)) {
            $list[] = $name;

            self::persist($list);
        }

        return true;
    }

    public static function disable(string $name): bool
    {
        $list = array_values(array_filter(
            self::enabledNames(),
            static fn (string $item): bool => $item !== $name
        ));

        self::persist($list);

        return true;
    }

    /** @param array<int,string> $list */
    private static function persist(array $list): void
    {
        sort($list);

        /* "dahili" grubu + duzenlenebilir=false: bu liste SİSTEMİN
         * tuttuğu bir değerdir, ayarlar ekranında görünmez ve oradan
         * yanlışlıkla silinemez. (Bir zamanlar "genel" grubundaydı ve
         * Genel ayarlarını kaydetmek tüm modülleri kapatıyordu.) */
        Setting::set(
            Database::connection(),
            self::SETTING_KEY,
            json_encode(array_values(array_unique($list)), JSON_UNESCAPED_UNICODE),
            group: 'dahili',
            editable: false,
        );

        self::$all = null; // önbelleği tazele
    }

    /* =================================================================
     *  YÜKLEME
     * ============================================================== */

    /**
     * Açık modüllerin sınıflarını ve olaylarını devreye alır.
     *
     * Rotalar AYRI yüklenir (bkz. loadRoutes): rota tablosu ancak
     * Router nesnesi oluşturulduktan sonra doldurulabilir.
     */
    public static function boot(): void
    {
        foreach (self::enabled() as $module) {
            try {
                if (is_dir($module->sourcePath())) {
                    Autoloader::register('Modules\\' . $module->ad, $module->sourcePath());
                }

                if (is_file($module->eventsFile())) {
                    require_once $module->eventsFile();
                }

                self::$loaded[] = $module->ad;
            } catch (Throwable $e) {
                /* Bir modülün hatası siteyi çökertmez; o modül
                 * yüklenmez ve kayda geçer. */
                Logger::error('Modül yüklenemedi: ' . $module->ad . ' – ' . $e->getMessage(), [
                    'modul' => $module->ad,
                ], 'error');
            }
        }
    }

    /** Açık modüllerin rotalarını kaydeder. */
    public static function loadRoutes(Router $router): void
    {
        foreach (self::enabled() as $module) {
            if (!is_file($module->routesFile())) {
                continue;
            }

            try {
                (static function (Router $router, string $file): void {
                    require $file;
                })($router, $module->routesFile());
            } catch (Throwable $e) {
                Logger::error('Modül rotaları yüklenemedi: ' . $module->ad . ' – ' . $e->getMessage(), [
                    'modul' => $module->ad,
                ], 'error');
            }
        }
    }

    /**
     * Açık modüllerin migration klasörleri.
     *
     * @return array<string,string> modül adı => klasör yolu
     */
    public static function migrationPaths(): array
    {
        $paths = [];

        foreach (self::enabled() as $module) {
            if ($module->hasMigrations()) {
                $paths[$module->ad] = $module->migrationsPath();
            }
        }

        return $paths;
    }

    /** @return array<int,string> Bu istekte yüklenen modüller */
    public static function loadedNames(): array
    {
        return self::$loaded;
    }

    /** Testlerde keşif önbelleğini sıfırlamak için. */
    public static function forget(): void
    {
        self::$all    = null;
        self::$loaded = [];
    }
}
