<?php
/**
 * =====================================================================
 *  Ornek MODÜLÜ – Rotaları
 * ---------------------------------------------------------------------
 *  Bu dosya, modül AÇIKKEN çekirdek rotalarından SONRA yüklenir.
 *  $router değişkeni hazır gelir.
 *
 *  Yetki adlarını (kendi modülünüze özel) app/Models/Role.php içindeki
 *  ilgili rollere eklemeyi unutmayın; yoksa kimse erişemez.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Router;
use Modules\Ornek\Controllers\OrnekController;

/** @var Router $router */

$router->get('panel/ornek', OrnekController::class, 'index',
    ['installed', 'auth', 'can:ornek.view']);

$router->post('panel/ornek/kaydet', OrnekController::class, 'store',
    ['installed', 'auth', 'csrf', 'can:ornek.manage']);

$router->post('panel/ornek/sil/{id}', OrnekController::class, 'destroy',
    ['installed', 'auth', 'csrf', 'can:ornek.manage']);
