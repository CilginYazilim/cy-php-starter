<?php
/**
 * =====================================================================
 *  ÖN DENETLEYİCİ (Front Controller) – Uygulamanın TEK giriş noktası
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Tarayıcıdan gelen HER istek (ön yüz + yönetim paneli) bu dosyadan
 *  geçer. kurulum/ klasörü BUNUN DIŞINDADIR: .env ve veritabanı henüz
 *  yokken kendi başına çalışabilmelidir.
 * =====================================================================
 */

declare(strict_types=1);

define('CY_BASE', __DIR__);
define('CY_START', microtime(true));

/* Sınıf yükleyici, .env, yapılandırma, saat dilimi, hata yönetimi ve
 * yardımcılar — hepsi CLI ile ORTAK (bkz. app/bootstrap.php). */
require CY_BASE . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Setting;
use App\Core\View;

/* Buradan itibaren WEB'e özgü katman. */
Session::start();

/* Kurulum tamamlanmışsa (.env var) veritabanına bağlan ve ayarları
 * yükle. "installed" ara katmanı bundan sonra çalışıp .env yoksa
 * kurulum sihirbazına yönlendirir; burada tekrar dokunmuyoruz çünkü
 * rota henüz çözülmedi. */
if (Env::exists(CY_BASE . '/.env')) {
    try {
        Setting::load(Database::connection());

        /* Saat dilimi: yönetici panelden değiştirebilsin diye ayarlar
         * tablosu, .env'deki değerin üzerine yazar. Geçersiz bir
         * değer PHP'de uyarı üretir; bu yüzden önce doğruluyoruz. */
        $zaman = Setting::get('sistem_zaman_dilimi');

        if ($zaman !== '' && in_array($zaman, timezone_identifiers_list(), true)) {
            date_default_timezone_set($zaman);
        }
    } catch (Throwable) {
        // Veritabanı geçici olarak erişilemezse ayarlar boş kalır;
        // sayfa yine de açılabilir (varsayılan metinlerle).
    }
}

/* KURULUM SİHİRBAZININ SON SÖZÜ
 *
 * Sihirbaz kendini sildikten sonra buraya "?kurulum=temizlendi" ile
 * döner. Eskiden hiçbir şey göstermiyorduk: kullanıcı "Kurulum
 * Klasörünü Sil ve Bitir" düğmesine basıyor, sıradan bir ana sayfaya
 * düşüyor ve klasörün gerçekten silinip silinmediğini anlamıyordu.
 * Tek seferlik bir bildirim, o belirsizliği ortadan kaldırıyor. */
if (isset($_GET['kurulum']) && $_GET['kurulum'] === 'temizlendi') {
    App\Core\Flash::success(
        is_dir(CY_BASE . '/kurulum')
            ? 'Kurulum tamamlandı ancak "kurulum/" klasörü silinemedi — lütfen sunucudan elle silin.'
            : 'Kurulum tamamlandı ve "kurulum/" klasörü silindi. Siteniz yayında!'
    );
}

/* Güvenlik başlıkları ayarlardan SONRA gönderilir: İçerik Güvenliği
 * Politikası, panele girilen analytics kodunun adreslerini de
 * kapsamak zorundadır (bkz. Response::csp). Buraya kadar hiçbir
 * çıktı üretilmediği için başlıkları göndermek güvenlidir. */
Response::securityHeaders();

/* Açık modüllerin sınıfları ve olay dinleyicileri devreye girer.
 * Ayarlar okunduktan SONRA çalışmalı: açık modül listesi "ayarlar"
 * tablosundadır. */
App\Core\Modules\Modules::boot();

$request = new Request();

View::share([
    'appName'     => Config::get('app.name'),
    'appBrand'    => Config::get('app.brand'),
    'currentUser' => Env::exists(CY_BASE . '/.env') ? Auth::user() : null,
]);

/** @var App\Core\Router $router */
$router = require CY_BASE . '/routes/web.php';

/* Modül rotaları çekirdek rotalarından SONRA eklenir; böylece bir
 * modül yanlışlıkla "giris" gibi temel bir adresi ele geçiremez
 * (aynı adres iki kez tanımlanırsa ilki geçerli kalır). */
App\Core\Modules\Modules::loadRoutes($router);

$router->dispatch($request);
