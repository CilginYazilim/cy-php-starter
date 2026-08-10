<?php
/**
 * =====================================================================
 *  OLAY SİSTEMİ                 →  Config::get('events.*')
 * ---------------------------------------------------------------------
 *  Dinleyiciler burada değil routes/events.php içinde tanımlanır.
 *  Bu dosya yalnızca sistemin DAVRANIŞINI ayarlar.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    /* -----------------------------------------------------------------
     *  KATI (strict) MOD
     * -----------------------------------------------------------------
     *  Kapalıyken: bir dinleyici hata verirse loglanır ve diğerleri
     *  çalışmaya devam eder. Bir modülün hatası, kullanıcı kaydını
     *  ya da iletişim formunu ÇÖKERTMEZ. Yayında olması gereken
     *  davranış budur.
     *
     *  Açıkken: hata fırlatılır. Kendi dinleyicinizdeki yazım hatasını
     *  log dosyasında aramak yerine ekranda görürsünüz.
     *
     *  Varsayılan olarak APP_DEBUG'ı izler: geliştirmede açık,
     *  yayında kapalı.
     * -------------------------------------------------------------- */
    'strict' => Env::bool('EVENTS_STRICT', Env::bool('APP_DEBUG', true)),
];
