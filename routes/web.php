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

use App\Core\Router;
use App\Http\Controllers\Api\MailApiController;
use App\Http\Controllers\Api\MessageApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController as SitePageController;
use App\Http\Controllers\Site\SeoController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserController;

$router = new Router();

/* ---------------------------------------------------------------------
 *  ÖN YÜZ (herkese açık)
 * ------------------------------------------------------------------ */
$router->get('',            HomeController::class,    'index', ['installed']);
$router->get('manifest.webmanifest', PwaController::class, 'manifest', ['installed']);
$router->get('sitemap.xml', SeoController::class, 'sitemap', ['installed']);
$router->get('robots.txt',  SeoController::class, 'robots',  ['installed']);

$router->get('cevrimdisi',          PwaController::class, 'offline',  ['installed']);

// "bakim": bakım modu açıkken bu uç kapanır. Sayfanın kendisi
// (GET iletisim) açık kalır — ziyaretçi bakım örtüsünü görür — ama
// gönderim JSON ucu olduğu için layout'a hiç uğramaz; kapı burada.
$router->post('api/iletisim/gonder', ContactController::class, 'send', ['installed', 'bakim', 'csrf']);

/* ---------------------------------------------------------------------
 *  KİMLİK DOĞRULAMA
 * ------------------------------------------------------------------ */
$router->get('giris',   AuthController::class, 'showLogin', ['installed', 'guest']);
$router->post('giris',  AuthController::class, 'login',     ['installed', 'guest', 'csrf']);

// Giriş bakım modunda da AÇIKTIR: yönetici siteyi düzeltebilmek için
// panele girebilmelidir. Yeni kayıt ise kapanır.
$router->get('kayit',   AuthController::class, 'showRegister', ['installed', 'bakim', 'guest']);
$router->post('kayit',  AuthController::class, 'register',     ['installed', 'bakim', 'guest', 'csrf']);

// Çıkış POST ile yapılır: bir <img> etiketinin oturumunuzu kapatmasını
// engellemek için (CSRF koruması).
$router->post('cikis', AuthController::class, 'logout', ['installed', 'auth', 'csrf']);

/* ---------------------------------------------------------------------
 *  PANEL SAYFALARI
 * ------------------------------------------------------------------ */
$router->get('panel', DashboardController::class, 'index', ['installed', 'auth', 'can:dashboard.view']);

$router->get('panel/kullanicilar', UserController::class, 'index', ['installed', 'auth', 'can:users.view']);
$router->get('panel/mesajlar',     MessageController::class, 'index', ['installed', 'auth', 'can:messages.view']);
$router->get('panel/eposta',       MailController::class,    'index', ['installed', 'auth', 'can:mail.view']);

// --- İÇERİK SAYFALARI ---
// "yeni" SABİT bir rotadır ve "{id}" kalıbından önce yazılmıştır;
// Router zaten sabitlere öncelik verir ama okurken de belli olsun.
$router->get('panel/sayfalar',          PageController::class, 'index',   ['installed', 'auth', 'can:pages.view']);
$router->get('panel/sayfalar/yeni',     PageController::class, 'create',  ['installed', 'auth', 'can:pages.manage']);
$router->post('panel/sayfalar/yeni',    PageController::class, 'store',   ['installed', 'auth', 'csrf', 'can:pages.manage']);
$router->get('panel/sayfalar/{id}',     PageController::class, 'edit',    ['installed', 'auth', 'can:pages.manage']);
$router->post('panel/sayfalar/{id}',    PageController::class, 'update',  ['installed', 'auth', 'csrf', 'can:pages.manage']);
$router->post('panel/sayfalar/{id}/sil', PageController::class, 'destroy', ['installed', 'auth', 'csrf', 'can:pages.manage']);

// Ayarlar bölüm bölümdür: /panel/ayarlar genel bakış, /panel/ayarlar/eposta
// yalnızca o grubu gösterir ve YALNIZCA onu kaydeder.
$router->get('panel/ayarlar',            SettingsController::class, 'index',      ['installed', 'auth', 'can:settings.view']);
$router->post('panel/ayarlar/logo',      SettingsController::class, 'uploadLogo', ['installed', 'auth', 'csrf', 'can:settings.manage']);
$router->post('panel/ayarlar/logo-sil',  SettingsController::class, 'removeLogo', ['installed', 'auth', 'csrf', 'can:settings.manage']);
$router->post('panel/ayarlar/favicon',     SettingsController::class, 'uploadFavicon', ['installed', 'auth', 'csrf', 'can:settings.manage']);
$router->post('panel/ayarlar/favicon-sil', SettingsController::class, 'removeFavicon', ['installed', 'auth', 'csrf', 'can:settings.manage']);
$router->post('panel/ayarlar/pwa-simge',     SettingsController::class, 'uploadIcon', ['installed', 'auth', 'csrf', 'can:settings.manage']);
$router->post('panel/ayarlar/pwa-simge-sil', SettingsController::class, 'removeIcon', ['installed', 'auth', 'csrf', 'can:settings.manage']);

// "{grup}" kalıbı "logo" ve "logo-sil" adreslerini de yakalardı.
// Sorun olmaz: Router önce SABİT rotalara bakar, parametreli olanları
// yalnızca hiçbiri eşleşmezse dener. Yine de okunurluk için sabit
// olanları üste yazıyoruz.
$router->get('panel/ayarlar/{grup}',     SettingsController::class, 'group',      ['installed', 'auth', 'can:settings.view']);
$router->post('panel/ayarlar/{grup}',    SettingsController::class, 'update',     ['installed', 'auth', 'csrf', 'can:settings.manage']);

$router->get('panel/sistem', SystemController::class, 'index', ['installed', 'auth', 'can:system.view']);
$router->post('panel/sistem/kuyruk/tekrar',  SystemController::class, 'queueRetry', ['installed', 'auth', 'csrf', 'can:system.manage']);
$router->post('panel/sistem/kuyruk/temizle', SystemController::class, 'queuePurge', ['installed', 'auth', 'csrf', 'can:system.manage']);

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

// --- E-POSTA MERKEZİ ---
// Geçmişi görmek "mail.view", göndermek "mail.send" ister; editör
// gidenleri görebilir ama toplu duyuru gönderemez.
$router->post('api/eposta/list',    MailApiController::class, 'list',    ['installed', 'auth', 'csrf', 'can:mail.view']);
$router->post('api/eposta/fetch',   MailApiController::class, 'fetch',   ['installed', 'auth', 'csrf', 'can:mail.view']);
$router->post('api/eposta/tekrar',  MailApiController::class, 'requeue', ['installed', 'auth', 'csrf', 'can:mail.send']);
$router->post('api/eposta/sil',     MailApiController::class, 'delete',  ['installed', 'auth', 'csrf', 'can:mail.send']);
$router->post('api/eposta/temizle', MailApiController::class, 'purge',   ['installed', 'auth', 'csrf', 'can:mail.send']);

$router->post('api/eposta/alicilar', MailApiController::class, 'audience', ['installed', 'auth', 'csrf', 'can:mail.send']);
$router->post('api/eposta/onizle',   MailApiController::class, 'preview',  ['installed', 'auth', 'csrf', 'can:mail.send']);
$router->post('api/eposta/gonder',   MailApiController::class, 'send',     ['installed', 'auth', 'csrf', 'can:mail.send']);
$router->post('api/eposta/isle',     MailApiController::class, 'process',  ['installed', 'auth', 'csrf', 'can:mail.send']);

$router->post('api/eposta/sinama',   MailApiController::class, 'test',    ['installed', 'auth', 'csrf', 'can:settings.manage']);
$router->post('api/eposta/baglanti', MailApiController::class, 'verify',  ['installed', 'auth', 'csrf', 'can:settings.manage']);

/* ---------------------------------------------------------------------
 *  İÇERİK SAYFALARI (ön yüz) – EN SONA YAZILIR
 * ---------------------------------------------------------------------
 *  /hakkimizda, /iletisim, /gizlilik … hepsi buraya düşer ve
 *  "sayfalar" tablosundan okunur.
 *
 *  NEDEN EN SONDA? Router parametreli rotaları yalnızca hiçbir SABİT
 *  rota eşleşmediğinde dener; yani "giris" ya da "panel" buraya asla
 *  gelmez. Yine de dosyanın en altında durması niyeti belli eder:
 *  bu bir yakalayıcıdır, üstündeki her şey ondan önceliklidir.
 *
 *  Yayında olmayan ya da var olmayan bir adres 404 verir — sessizce
 *  ana sayfaya yönlendirmek hem ziyaretçiyi hem arama motorunu
 *  yanıltırdı.
 * ------------------------------------------------------------------ */
$router->get('{slug}', SitePageController::class, 'show', ['installed']);

/* ---------------------------------------------------------------------
 *  BULUNAMAYAN ADRESLER
 * ---------------------------------------------------------------------
 *  Burada bir şey tanımlamıyoruz: eşleşmeyen adres için Router
 *  HttpException::notFound() fırlatır, ErrorHandler da isteğin
 *  türüne göre 404 sayfasını ya da JSON yanıtını üretir.
 *
 *  Özel bir davranış isterseniz (ör. bir modülün kendi arşiv
 *  yönlendirmesi) $router->fallback(...) ile devralabilirsiniz.
 * ------------------------------------------------------------------ */

return $router;
