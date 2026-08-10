<?php
/**
 * =====================================================================
 *  API                          →  Config::get('api.*')
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    /* Adres öneki: /api/v1/... — sürüm numarası vermek, ileride
     * kırıcı değişiklik yaparken eski istemcileri yaşatmanızı sağlar. */
    'prefix' => 'api/v1',

    /* -----------------------------------------------------------------
     *  HIZ SINIRI
     * -----------------------------------------------------------------
     *  Anahtar (ya da anonim istekte IP) başına, pencere başına
     *  izin verilen istek sayısı.
     * -------------------------------------------------------------- */
    'rate_limit'  => Env::int('API_RATE_LIMIT', 120),
    'rate_window' => Env::int('API_RATE_WINDOW', 60), // saniye

    /* Sayfalama */
    'per_page'     => 25,
    'max_per_page' => 100,
];
