<?php
/**
 * =====================================================================
 *  OTURUM                       →  Config::get('session.*')
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    /* Çerez adı. Aynı sunucuda birden fazla proje varsa birbirlerinin
     * oturumunu ezmesin diye projeye özel olmalıdır. Boş bırakılırsa
     * kuruluma özel bir ad üretilir (bkz. App\Core\Session::cookieName).
     * Kurulum sihirbazı bu değeri rastgele üretip .env'e yazar. */
    'name' => Env::get('SESSION_NAME', ''),

    /* Oturum dosyalarının klasörü. Varsayılan: storage/sessions —
     * uygulamanın kendisine ait, web'e kapalı bir klasör. Boş
     * bırakırsanız ("SESSION_PATH=") PHP'nin sistem klasörü kullanılır;
     * oturumları Redis/Memcached gibi bir sürücüde tutan sunucularda
     * bunu tercih edin. */
    'path' => Env::has('SESSION_PATH')
        ? Env::get('SESSION_PATH')
        : CY_BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions',

    // Hareketsiz kalınca kaç saniye sonra oturum düşsün?
    'idle_timeout' => Env::int('SESSION_IDLE_TIMEOUT', 1800), // 30 dakika

    // Oturum kimliği kaç saniyede bir yenilensin? (oturum çalmaya karşı)
    'regenerate_every' => Env::int('SESSION_REGENERATE', 900), // 15 dakika
];
