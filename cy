<?php
/**
 * =====================================================================
 *  cy – Komut satırı giriş noktası
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *      php cy                    Komut listesi
 *      php cy migrate            Bekleyen şema değişikliklerini uygula
 *      php cy make:controller X  Dosya üret
 *      php cy yardim <komut>     Ayrıntılı yardım
 *
 *  Bu dosyanın uzantısı YOKTUR ve web sunucusundan erişilemez
 *  (.htaccess uzantısız dosyaları servis etmez, ayrıca aşağıdaki
 *  SAPI kontrolü tarayıcıdan çalıştırılmasını kesin olarak engeller).
 * =====================================================================
 */

declare(strict_types=1);

/* GÜVENLİK: Bu betik yalnızca terminalden çalışır. Bir yapılandırma
 * hatası yüzünden web'den erişilebilir hale gelirse, ziyaretçilerin
 * "migrate:fresh" çalıştırabilmesi felaket olurdu. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

define('CY_BASE', __DIR__);
define('CY_START', microtime(true));

require CY_BASE . '/app/bootstrap.php';

exit((new App\Core\Console\Kernel())->run($argv ?? []));
