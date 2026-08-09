<?php
/**
 * =====================================================================
 *  ROTA TABLOSU – Hangi adres hangi denetleyiciye gider?
 * ---------------------------------------------------------------------
 *  Üçüncü parametre ARA KATMANLARDIR (middleware); denetleyici
 *  çalışmadan ÖNCE sırayla uygulanırlar:
 *
 *      'installed'         → .env yoksa kurulum sihirbazına yönlendir
 *      'guest'              → yalnızca giriş YAPMAMIŞ ziyaretçi
 *      'auth'               → giriş zorunlu
 *      'csrf'               → sahte istek koruması (veri değiştiren her POST)
 *      'can:users.delete'   → belirli yetki zorunlu
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Http\Controllers\Api\MessageApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserController;

$router = new Router();

/* ---------------------------------------------------------------------
 *  ÖN YÜZ (herkese açık)
 * ------------------------------------------------------------------ */
$router->get('',            HomeController::class,    'index', ['installed']);
$router->get('hakkimizda',  HomeController::class,    'about', ['installed']);
$router->get('iletisim',    ContactController::class, 'show',  ['installed']);
$router->post('api/iletisim/gonder', ContactController::class, 'send', ['installed', 'csrf']);

/* ---------------------------------------------------------------------
 *  KİMLİK DOĞRULAMA
 * ------------------------------------------------------------------ */
$router->get('giris',   AuthController::class, 'showLogin', ['installed', 'guest']);
$router->post('giris',  AuthController::class, 'login',     ['installed', 'guest', 'csrf']);

$router->get('kayit',   AuthController::class, 'showRegister', ['installed', 'guest']);
$router->post('kayit',  AuthController::class, 'register',     ['installed', 'guest', 'csrf']);

// Çıkış POST ile yapılır: bir <img> etiketinin oturumunuzu kapatmasını
// engellemek için (CSRF koruması).
$router->post('cikis', AuthController::class, 'logout', ['installed', 'auth', 'csrf']);

/* ---------------------------------------------------------------------
 *  PANEL SAYFALARI
 * ------------------------------------------------------------------ */
$router->get('panel', DashboardController::class, 'index', ['installed', 'auth', 'can:dashboard.view']);

$router->get('panel/kullanicilar', UserController::class, 'index', ['installed', 'auth', 'can:users.view']);
$router->get('panel/mesajlar',     MessageController::class, 'index', ['installed', 'auth', 'can:messages.view']);

$router->get('panel/ayarlar',        SettingsController::class, 'index',      ['installed', 'auth', 'can:settings.view']);
$router->post('panel/ayarlar',       SettingsController::class, 'update',     ['installed', 'auth', 'csrf', 'can:settings.manage']);
$router->post('panel/ayarlar/logo',  SettingsController::class, 'uploadLogo', ['installed', 'auth', 'csrf', 'can:settings.manage']);

$router->get('panel/sistem', SystemController::class, 'index', ['installed', 'auth', 'can:system.view']);

$router->get('panel/hesabim',              ProfileController::class, 'index',        ['installed', 'auth', 'can:profile.view']);
$router->post('panel/hesabim/guncelle',    ProfileController::class, 'update',       ['installed', 'auth', 'csrf', 'can:profile.update']);
$router->post('panel/hesabim/parola',      ProfileController::class, 'password',     ['installed', 'auth', 'csrf', 'can:profile.update']);
$router->post('panel/hesabim/avatar',      ProfileController::class, 'uploadAvatar', ['installed', 'auth', 'csrf', 'can:profile.update']);
$router->post('panel/hesabim/avatar-sil',  ProfileController::class, 'removeAvatar', ['installed', 'auth', 'csrf', 'can:profile.update']);

/* ---------------------------------------------------------------------
 *  AJAX UÇ NOKTALARI (hepsi POST + CSRF)
 * ------------------------------------------------------------------ */

// Tema tercihi: özel bir yetki gerektirmez, her giriş yapmış kullanıcı
// kendi görünümünü değiştirebilmelidir.
$router->post('api/tema', ProfileController::class, 'updateTheme', ['installed', 'auth', 'csrf']);

$router->post('api/kullanicilar/list',   UserApiController::class, 'list',   ['installed', 'auth', 'csrf', 'can:users.view']);
$router->post('api/kullanicilar/fetch',  UserApiController::class, 'fetch',  ['installed', 'auth', 'csrf', 'can:users.view']);
$router->post('api/kullanicilar/save',   UserApiController::class, 'save',   ['installed', 'auth', 'csrf', 'can:users.create|users.update']);
$router->post('api/kullanicilar/delete', UserApiController::class, 'delete', ['installed', 'auth', 'csrf', 'can:users.delete']);
$router->post('api/kullanicilar/status', UserApiController::class, 'status', ['installed', 'auth', 'csrf', 'can:users.status']);

$router->post('api/mesajlar/list',   MessageApiController::class, 'list',     ['installed', 'auth', 'csrf', 'can:messages.view']);
$router->post('api/mesajlar/fetch',  MessageApiController::class, 'fetch',    ['installed', 'auth', 'csrf', 'can:messages.view']);
$router->post('api/mesajlar/okundu', MessageApiController::class, 'markRead', ['installed', 'auth', 'csrf', 'can:messages.manage']);
$router->post('api/mesajlar/delete', MessageApiController::class, 'delete',   ['installed', 'auth', 'csrf', 'can:messages.manage']);
$router->post('api/mesajlar/toplu',  MessageApiController::class, 'bulk',     ['installed', 'auth', 'csrf', 'can:messages.manage']);

/* ---------------------------------------------------------------------
 *  BULUNAMAYAN ADRESLER
 * ------------------------------------------------------------------ */
$router->fallback(static function (Request $request, string $path): void {
    if ($request->isAjax()) {
        App\Core\Response::error('İstenen uç nokta bulunamadı.', 404);
    }

    http_response_code(404);

    View::render('errors/404', [
        'title' => 'Sayfa Bulunamadı',
        'path'  => $path,
    ], App\Core\Auth::check() ? 'layouts/admin' : 'layouts/site');
});

return $router;
