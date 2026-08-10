<?php
/**
 * =====================================================================
 *  OTURUM                       →  Config::get('session.*')
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    /* Çerez adı. Aynı sunucuda birden fazla proje varsa
     * birbirlerinin oturumunu ezmesin diye projeye özel olmalıdır. */
    'name' => Env::get('SESSION_NAME', 'CYSTARTERSESS'),

    // Hareketsiz kalınca kaç saniye sonra oturum düşsün?
    'idle_timeout' => Env::int('SESSION_IDLE_TIMEOUT', 1800), // 30 dakika

    // Oturum kimliği kaç saniyede bir yenilensin? (oturum çalmaya karşı)
    'regenerate_every' => Env::int('SESSION_REGENERATE', 900), // 15 dakika
];
