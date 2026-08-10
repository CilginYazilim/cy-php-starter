<?php
/**
 * =====================================================================
 *  Config – Ayarlara nokta notasyonuyla erişim
 * ---------------------------------------------------------------------
 *      Config::load(CY_BASE . '/config');   // klasörün tamamı
 *      Config::get('db.host');
 *      Config::get('upload.max_bytes');
 *
 *  KLASÖR MANTIĞI
 *  config/ içindeki her dosya bir üst anahtar olur:
 *
 *      config/app.php      → Config::get('app.name')
 *      config/db.php       → Config::get('db.host')
 *      config/log.php      → Config::get('log.level')
 *
 *  Tek dosya vermek de çalışır: Config::load('.../config.php') dosyanın
 *  döndürdüğü diziyi olduğu gibi kök kabul eder.
 *
 *  YEREL GEÇERSİZ KILMA
 *  config/config.local.php varsa EN SON okunur ve derin birleştirilir.
 *  .gitignore içindedir: kendi makinenizde bir ayarı değiştirmek için
 *  takım arkadaşlarınızın dosyalarına dokunmanız gerekmez.
 *
 *  ÖNBELLEK
 *  Config::cache() bütün diziyi tek bir PHP dosyasına yazar; yayında
 *  onlarca require yerine tek dosya okunur. Önbellek yalnızca
 *  APP_DEBUG=false iken kullanılır ve .env değişince ELLE
 *  tazelenmelidir (php cy config:clear).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    private static bool $fromCache = false;

    /* =================================================================
     *  YÜKLEME
     * ============================================================== */

    /** @param string $path Bir .php dosyası ya da config klasörü */
    public static function load(string $path): void
    {
        if (self::loadFromCache()) {
            return;
        }

        self::$items = is_dir($path) ? self::readDirectory($path) : self::readFile($path);

        self::applyLocalOverrides(is_dir($path) ? $path : dirname($path));
    }

    /**
     * Klasördeki her .php dosyasını okur; dosya adı üst anahtar olur.
     *
     * @return array<string,mixed>
     */
    private static function readDirectory(string $dir): array
    {
        $items = [];

        foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            // Yerel geçersiz kılma dosyası SONA bırakılır (aşağıda).
            if ($name === 'config.local') {
                continue;
            }

            $data = self::readFile($file);

            // Eski tek dosyalı düzenden gelenler için: "config.php"
            // kendi içinde zaten üst anahtarları taşır, sarmalamayız.
            if ($name === 'config') {
                $items = array_replace_recursive($items, $data);
                continue;
            }

            $items[$name] = $data;
        }

        return $items;
    }

    /** @return array<string,mixed> */
    private static function readFile(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $data = require $file;

        return is_array($data) ? $data : [];
    }

    /** config/config.local.php varsa üzerine yazar (derin birleştirme). */
    private static function applyLocalOverrides(string $dir): void
    {
        $local = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'config.local.php';

        if (is_file($local)) {
            self::$items = array_replace_recursive(self::$items, self::readFile($local));
        }
    }

    /* =================================================================
     *  ERİŞİM
     * ============================================================== */

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function has(string $key): bool
    {
        return self::get($key, "\0yok") !== "\0yok";
    }

    /**
     * Ayarı ÇALIŞMA ANINDA değiştirir. Diske yazmaz; yalnızca bu
     * isteği etkiler (testlerde ve kurulum sihirbazında işe yarar).
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref      = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        return self::$items;
    }

    /* =================================================================
     *  ORTAM
     * ============================================================== */

    public static function environment(): string
    {
        return (string) self::get('app.env', 'local');
    }

    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    public static function isDebug(): bool
    {
        return (bool) self::get('app.debug', false);
    }

    /**
     * Yayın ortamında açık kalmış hata ayıklama, bu starter'ın
     * yapabileceği EN TEHLİKELİ yapılandırma hatasıdır: ziyaretçiler
     * kaynak kodunu, dosya yollarını ve veritabanı hatalarını görür.
     * Sessizce düzeltmiyoruz (geliştirici bilerek açmış olabilir) ama
     * her istekte kaydı düşüyoruz.
     */
    public static function warnIfUnsafe(): void
    {
        if (self::isProduction() && self::isDebug()) {
            Log\Logger::security(
                'GÜVENLİK: Yayın ortamında APP_DEBUG=true. Ziyaretçiler hata ayrıntılarını görebilir; '
                . '.env dosyasında APP_DEBUG=false yapın.'
            );
        }
    }

    /* =================================================================
     *  ÖNBELLEK
     * ============================================================== */

    public static function cacheFile(): string
    {
        return defined('CY_BASE')
            ? CY_BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'config.php'
            : '';
    }

    public static function isCached(): bool
    {
        return self::$fromCache;
    }

    /**
     * Önbelleği okumayı dener.
     *
     * Hata ayıklama açıkken ASLA kullanılmaz: geliştirici config
     * dosyasını değiştirip "neden bir şey olmuyor" diye saatlerce
     * aramamalı. Bu kontrol Config yüklenmeden önce yapıldığı için
     * doğrudan .env'e bakıyoruz.
     */
    private static function loadFromCache(): bool
    {
        $file = self::cacheFile();

        if ($file === '' || Env::bool('APP_DEBUG', true) || !is_file($file)) {
            return false;
        }

        $data = self::readFile($file);

        /* Önbellek MUTLAK YOLLARI içerir (storage.disks.*.root gibi). Proje
         * başka bir klasöre taşındıysa bu yollar yanlıştır; parmak
         * izi tutmayınca önbelleği yok sayarız. */
        if (($data['__kok'] ?? null) !== CY_BASE) {
            return false;
        }

        unset($data['__kok']);

        self::$items     = $data;
        self::$fromCache = true;

        return true;
    }

    /**
     * O anki yapılandırmayı tek dosyaya yazar.
     *
     * @return string Yazılan dosyanın yolu ('' → yazılamadı)
     */
    public static function cache(): string
    {
        $file = self::cacheFile();

        if ($file === '') {
            return '';
        }

        $dir = dirname($file);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return '';
        }

        $payload = self::$items;
        $payload['__kok'] = CY_BASE;

        $php = "<?php\n"
             . "/* OTOMATİK ÜRETİLDİ — elle düzenlemeyin.\n"
             . "   Tazelemek için: php cy config:cache | Silmek için: php cy config:clear */\n\n"
             . 'return ' . var_export($payload, true) . ";\n";

        return @file_put_contents($file, $php) !== false ? $file : '';
    }

    public static function clearCache(): bool
    {
        $file = self::cacheFile();

        return $file !== '' && is_file($file) ? @unlink($file) : true;
    }
}
