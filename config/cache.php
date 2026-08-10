<?php
/**
 * =====================================================================
 *  ÖNBELLEK                     →  Config::get('cache.*')
 * ---------------------------------------------------------------------
 *  SÜRÜCÜLER
 *    dosya       storage/cache/data — kurulum gerektirmez, tek sunucu
 *                için en hızlısı. VARSAYILAN.
 *    veritabani  "onbellek" tablosu — birden fazla web sunucusu aynı
 *                uygulamayı çalıştırıyorsa. Önce migration'ı çalıştırın:
 *                    php cy migrate
 *    kapali      Hiçbir şey saklamaz. Bir hatanın önbellek kaynaklı
 *                olup olmadığını tek satırda test etmenin yolu.
 *
 *  Redis gibi bir sürücü eklemek isterseniz CacheStore arayüzünü
 *  uygulayan bir sınıf yazıp Cache::build() içine bir satır eklemeniz
 *  yeterlidir; çağıran hiçbir kod değişmez.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    'default' => Env::get('CACHE_DRIVER', 'dosya'),

    /* Süre verilmeden Cache::put() çağrıldığında kullanılır (saniye). */
    'ttl' => Env::int('CACHE_TTL', 3600),

    /* -----------------------------------------------------------------
     *  ÖNEK
     * -----------------------------------------------------------------
     *  Aynı veritabanını ya da önbellek klasörünü paylaşan iki proje,
     *  önek olmadan birbirinin verisini okur. Her projeye kendi
     *  öneğini verin.
     * -------------------------------------------------------------- */
    'prefix' => Env::get('CACHE_PREFIX', 'cy'),

    'dir'   => CY_BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data',
    'table' => 'onbellek',
];
