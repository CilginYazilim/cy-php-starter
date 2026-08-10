<?php
/**
 * =====================================================================
 *  GÜVENLİK                     →  Config::get('security.*')
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    /* Kaba kuvvet koruması: kaç hatalı denemeden sonra, ne kadar
     * süreyle kilitlensin? (app/Core/RateLimiter.php) */
    'login_max_attempts' => Env::int('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout'      => Env::int('LOGIN_LOCKOUT', 900), // 15 dakika
    'login_window'       => Env::int('LOGIN_WINDOW', 900),

    'password_min' => 8,

    /* -----------------------------------------------------------------
     *  İÇERİK GÜVENLİĞİ POLİTİKASI (CSP)
     * -----------------------------------------------------------------
     *  Varsayılan politika katıdır: sayfa yalnızca KENDİ sunucusundan
     *  betik yükleyebilir ve satır içi <script> çalışmaz. XSS'e karşı
     *  en güçlü savunmanız budur.
     *
     *  Bir dış servis (harita, ödeme çerçevesi, sohbet penceresi)
     *  engellendiğinde politikayı KAPATMAYIN — yalnızca o servisin
     *  adresini aşağıya ekleyin:
     *
     *      CSP_SCRIPT_SRC="https://js.stripe.com"
     *      CSP_FRAME_SRC="https://js.stripe.com"
     *
     *  Birden fazla adres virgülle ayrılır.
     *
     *  NOT: Panelden girilen "Analytics Kodu" ayarı için buraya bir
     *  şey yazmanız GEREKMEZ. Response::securityHeaders() o kodun
     *  içindeki adresleri ve satır içi betiklerin sha256 özetlerini
     *  otomatik olarak politikaya ekler (bkz. app/Core/Response.php).
     * -------------------------------------------------------------- */
    'csp_enabled' => Env::bool('CSP_ENABLED', true),

    'csp_extra' => [
        'script-src'  => Env::list('CSP_SCRIPT_SRC'),
        'style-src'   => Env::list('CSP_STYLE_SRC'),
        'img-src'     => Env::list('CSP_IMG_SRC'),
        'connect-src' => Env::list('CSP_CONNECT_SRC'),
        'frame-src'   => Env::list('CSP_FRAME_SRC'),
        'font-src'    => Env::list('CSP_FONT_SRC'),
    ],
];
