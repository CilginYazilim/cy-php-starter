<?php
/**
 * =====================================================================
 *  KUYRUK                       →  Config::get('queue.*')
 * ---------------------------------------------------------------------
 *  Kullanıcıyı bekletmemesi gereken işler kuyruğa alınır ve arka
 *  planda çalıştırılır:
 *
 *      * * * * * cd /yol/site && php cy queue:work --max=30
 *
 *  Sürücü veritabanıdır ("isler" tablosu, migration ile gelir).
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    'table' => 'isler',

    /* -----------------------------------------------------------------
     *  ZAMAN AŞIMI (saniye)
     * -----------------------------------------------------------------
     *  Bir işçi çökerse elindeki iş "çalışıyor" durumunda kilitli
     *  kalır. Bu süreden uzun süredir bitmemiş işler serbest bırakılır
     *  ve yeniden denenir.
     *
     *  EN UZUN İŞİNİZDEN BÜYÜK OLMALIDIR; aksi halde çalışmakta olan
     *  bir iş ikinci kez başlatılır.
     * -------------------------------------------------------------- */
    'timeout' => Env::int('QUEUE_TIMEOUT', 300),

    /* Tek bir "queue:work" çağrısında en fazla kaç iş çalıştırılsın? */
    'max_jobs' => Env::int('QUEUE_MAX_JOBS', 25),
];
