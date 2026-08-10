<?php
/**
 * =====================================================================
 *  Cache – Önbelleğe erişimin ön kapısı
 * ---------------------------------------------------------------------
 *      Cache::put('doviz', $kurlar, 3600);
 *      $kurlar = Cache::get('doviz');
 *
 *      // En sık kullanılan kalıp: varsa getir, yoksa üret ve sakla
 *      $rapor = Cache::remember('aylik-rapor', 900, function () {
 *          return $this->agirHesaplama();
 *      });
 *
 *  NEYİ ÖNBELLEĞE ALMALI?
 *    ✔ Üretmesi pahalı, değişmesi seyrek şeyler: raporlar, dış
 *      servisten çekilen veriler, ağır toplama sorguları
 *    ✘ Kullanıcıya özel ve güncelliği kritik veriler: sepet, bakiye,
 *      yetki durumu. Yanlış kullanıcıya yanlış veri göstermek,
 *      kazandığınız milisaniyelere değmez.
 *
 *  ANAHTAR SEÇİMİ
 *  Anahtara ait olduğu her şeyi koyun: 'rapor:satis:2026-03:kullanici-7'.
 *  Kullanıcıya özel bir şeyi önbelleğe alıyorsanız anahtarda kullanıcı
 *  numarası MUTLAKA bulunmalıdır — yoksa ilk kullanıcının verisi
 *  herkese gider.
 *
 *  SÜRÜCÜLER (config/cache.php)
 *    dosya       → storage/cache/data   (varsayılan, kurulum gerektirmez)
 *    veritabani  → "onbellek" tablosu   (çok sunuculu kurulumlar için)
 *    kapali      → hiçbir şey saklamaz  (geliştirme/test)
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Cache;

use App\Core\Config;
use App\Core\Database;
use Throwable;

final class Cache
{
    /** @var array<string,CacheStore> */
    private static array $stores = [];

    public static function store(?string $name = null): CacheStore
    {
        $name ??= (string) Config::get('cache.default', 'dosya');

        if (isset(self::$stores[$name])) {
            return self::$stores[$name];
        }

        return self::$stores[$name] = self::build($name);
    }

    private static function build(string $name): CacheStore
    {
        return match ($name) {
            'veritabani' => self::databaseStore(),
            'kapali'     => new NullStore(),
            default      => new FileStore((string) Config::get(
                'cache.dir',
                CY_BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data'
            )),
        };
    }

    /**
     * Veritabanı sürücüsü kurulamıyorsa (bağlantı yok, tablo yok)
     * sessizce "kapalı"ya düşeriz. Önbellek bir hızlandırmadır;
     * kurulmamış bir tablo yüzünden site açılmamalıdır.
     */
    private static function databaseStore(): CacheStore
    {
        try {
            return new DatabaseStore(
                Database::connection(),
                (string) Config::get('cache.table', 'onbellek')
            );
        } catch (Throwable) {
            return new NullStore();
        }
    }

    /** Testlerde sürücüyü elle değiştirmek için. */
    public static function useStore(string $name, ?CacheStore $store): void
    {
        if ($store === null) {
            unset(self::$stores[$name]);

            return;
        }

        self::$stores[$name] = $store;
    }

    /* =================================================================
     *  TEMEL İŞLEMLER (varsayılan sürücü)
     * ============================================================== */

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::store()->get(self::key($key));

        return $value ?? $default;
    }

    public static function has(string $key): bool
    {
        return self::store()->has(self::key($key));
    }

    /** @param int|null $seconds null → config/cache.php içindeki varsayılan süre */
    public static function put(string $key, mixed $value, ?int $seconds = null): bool
    {
        $seconds ??= (int) Config::get('cache.ttl', 3600);

        return self::store()->put(self::key($key), $value, max(0, $seconds));
    }

    /** Süresiz saklar. flush() ya da forget() ile silinir. */
    public static function forever(string $key, mixed $value): bool
    {
        return self::store()->put(self::key($key), $value, 0);
    }

    public static function forget(string $key): bool
    {
        return self::store()->forget(self::key($key));
    }

    public static function flush(): bool
    {
        return self::store()->flush();
    }

    public static function purgeExpired(): int
    {
        return self::store()->purgeExpired();
    }

    /* =================================================================
     *  KOLAYLIKLAR
     * ============================================================== */

    /**
     * Önbellekte varsa döndürür; yoksa geri çağırımı çalıştırıp
     * sonucunu saklar. Önbellek kullanımının %90'ı budur.
     *
     * DİKKAT: Geri çağırım null döndürürse sonuç SAKLANMAZ (null,
     * bu sözleşmede "yok" demektir) ve her istekte yeniden çalışır.
     * Pahalı bir işlem null dönebiliyorsa false ya da [] döndürün.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function remember(string $key, ?int $seconds, callable $callback): mixed
    {
        $cached = self::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();

        if ($value !== null) {
            self::put($key, $value, $seconds);
        }

        return $value;
    }

    /** remember() gibi ama süresiz saklar. */
    public static function rememberForever(string $key, callable $callback): mixed
    {
        $cached = self::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();

        if ($value !== null) {
            self::forever($key, $value);
        }

        return $value;
    }

    /** Değeri okur ve aynı anda siler (tek kullanımlık veriler için). */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);

        self::forget($key);

        return $value;
    }

    /* =================================================================
     *  ANAHTAR
     * ============================================================== */

    /**
     * Anahtara önek ekler.
     *
     * Aynı veritabanını ya da önbellek klasörünü paylaşan iki proje,
     * önek olmadan birbirinin verisini okur. Önek config/cache.php
     * içinde tanımlıdır.
     */
    private static function key(string $key): string
    {
        $prefix = (string) Config::get('cache.prefix', '');

        return $prefix === '' ? $key : $prefix . ':' . $key;
    }
}
