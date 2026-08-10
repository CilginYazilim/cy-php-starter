<?php
/**
 * =====================================================================
 *  GÜNLÜK KAYITLARI             →  Config::get('log.*')
 * ---------------------------------------------------------------------
 *  Her kanal ayrı dosyaya, her gün yeni dosyaya yazar:
 *      storage/logs/app-2026-08-09.log
 *      storage/logs/security-2026-08-09.log
 *
 *  "level" eşiğin altındaki kayıtları hiç yazmaz. Geliştirmede
 *  "debug", yayında "info" (gürültü fazlaysa "warning") uygundur.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [
    'enabled' => Env::bool('LOG_ENABLED', true),
    'level'   => Env::get('LOG_LEVEL', 'debug'),
    'dir'     => CY_BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',

    // Kaç günlük kayıt saklansın? (Logger::purge bunu kullanır.)
    'days' => Env::int('LOG_DAYS', 30),
];
