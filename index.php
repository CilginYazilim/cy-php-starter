<?php
/**
 * =====================================================================
 *  ÖN DENETLEYİCİ (Front Controller) – Uygulamanın TEK giriş noktası
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Tarayıcıdan gelen HER istek (ön yüz + yönetim paneli) bu dosyadan
 *  geçer. install/ klasörü BUNUN DIŞINDADIR: .env ve veritabanı henüz
 *  yokken kendi başına çalışabilmelidir.
 * =====================================================================
 */

declare(strict_types=1);

define('CY_BASE', __DIR__);
define('CY_START', microtime(true));

require CY_BASE . '/app/Core/Env.php';
require CY_BASE . '/app/Core/Autoloader.php';

App\Core\Autoloader::register('App', CY_BASE . '/app');

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Setting;
use App\Core\View;

Env::load(CY_BASE . '/.env');
Config::load(CY_BASE . '/config/config.php');

date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Istanbul'));

$debug = (bool) Config::get('app.debug', false);

error_reporting($debug ? E_ALL : 0);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

/* ---------------------------------------------------------------------
 *  MERKEZİ HATA YÖNETİMİ
 * ------------------------------------------------------------------ */
set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log('[CY] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $isAjax  = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $message = $debug
        ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.';

    if ($isAjax) {
        Response::error($message, 500);
    }

    http_response_code(500);

    try {
        View::render('errors/500', ['title' => 'Sunucu Hatası', 'message' => $message], 'layouts/plain');
    } catch (Throwable) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

Session::start();
Response::securityHeaders();

require CY_BASE . '/app/Support/helpers.php';

/* Kurulum tamamlanmışsa (.env var) veritabanına bağlan ve ayarları
 * yükle. install/ ara katmanı bundan önce çalışıp .env yoksa zaten
 * kurulum sihirbazına yönlendirmiş olur; burada tekrar dokunmuyoruz
 * çünkü rota henüz çözülmedi (middleware daha sonra devreye girer). */
if (Env::exists(CY_BASE . '/.env')) {
    try {
        Setting::load(Database::connection());
    } catch (Throwable) {
        // Veritabanı geçici olarak erişilemezse ayarlar boş kalır;
        // sayfa yine de açılabilir (varsayılan metinlerle).
    }
}

$request = new Request();

View::share([
    'appName'     => Config::get('app.name'),
    'appBrand'    => Config::get('app.brand'),
    'currentUser' => Env::exists(CY_BASE . '/.env') ? Auth::user() : null,
]);

/** @var App\Core\Router $router */
$router = require CY_BASE . '/routes/web.php';

$router->dispatch($request);
