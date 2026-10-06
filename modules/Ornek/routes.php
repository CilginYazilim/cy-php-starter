<?php
/**
 * =====================================================================
 *  Ornek MODÜLÜ – Rotaları
 * ---------------------------------------------------------------------
 *  Bu dosya, modül AÇIKKEN çekirdek rotalarından SONRA yüklenir.
 *  $router değişkeni hazır gelir.
 *
 *  Yetkiler ROL düzeyindedir ("bu rol kayıt ekleyebilir mi?"). Editör
 *  ve üyenin yetkileri module.json → "yetkiler" bloğundan gelir;
 *  yönetici her yetkiye sahiptir. "Bu KAYDI silebilir mi?" sorusunu
 *  denetleyici OrnekPolicy'ye sorar.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Router;
use Modules\Ornek\Controllers\OrnekController;

/** @var Router $router */

$router->get('panel/ornek', OrnekController::class, 'index',
    ['installed', 'auth', 'can:ornek.view']);

$router->post('panel/ornek/kaydet', OrnekController::class, 'store',
    ['installed', 'auth', 'csrf', 'can:ornek.create']);

$router->post('panel/ornek/durum/{id}', OrnekController::class, 'toggle',
    ['installed', 'auth', 'csrf', 'can:ornek.update.own|ornek.manage']);

$router->post('panel/ornek/sil/{id}', OrnekController::class, 'destroy',
    ['installed', 'auth', 'csrf', 'can:ornek.update.own|ornek.manage']);

$router->post('panel/ornek/ornek-uret', OrnekController::class, 'seed',
    ['installed', 'auth', 'csrf', 'can:ornek.manage']);
