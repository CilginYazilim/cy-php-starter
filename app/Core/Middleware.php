<?php
/**
 * =====================================================================
 *  Middleware – Rota öncesi kontroller
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

final class Middleware
{
    /** @param string $rule "auth" | "guest" | "role:admin" | "can:users.delete" | "csrf" | "installed" | "bakim" */
    public static function handle(string $rule, Request $request): void
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        // API kuralları ayrı bir kapıcıya devredilir (Bearer anahtarı,
        // hız sınırı, JSON hata biçimi). Bkz. App\Core\Api\ApiGuard.
        if ($name === 'api' || str_starts_with((string) $name, 'api.')) {
            \App\Core\Api\ApiGuard::handle($rule, $request);

            return;
        }

        /* BİLİNMEYEN AD = HATA. Eskiden "default => null" idi: rotada
         * 'Auth' ya da 'cann:users.delete' gibi bir yazım hatası
         * sessizce geçiyor ve rota HERKESE AÇIK kalıyordu. Güvenlik
         * kontrolü belirsiz kaldığında "kapalı" yönde bozulmalıdır. */
        match ($name) {
            'auth'      => self::auth($request),
            'guest'     => self::guest(),
            'role'      => self::role((string) $parameter, $request),
            'can'       => self::can((string) $parameter, $request),
            'csrf'      => self::csrf($request),
            'installed' => self::installed(),
            'bakim'     => self::maintenance(),
            default     => throw new \LogicException(sprintf(
                'Bilinmeyen ara katman: "%s". Geçerli adlar: auth, guest, role:…, can:…, csrf, installed, bakim, api, api.can:…, api.guest',
                $rule
            )),
        };
    }

    private static function auth(Request $request): void
    {
        if (Auth::check()) {
            /* BAKIM MODU girişli kullanıcıyı da kapsar: ayarın açıklaması
             * "siteyi yalnızca yöneticiler görebilir" der. Muaf olmayan
             * üye panelde 503 görür; yalnızca çıkış yapabilir. */
            if (Url::current() !== 'cikis') {
                self::maintenance();
            }

            return;
        }

        // AJAX isteği yönlendirilemez: tarayıcı giriş sayfasının HTML'ini
        // JSON sanıp çöker. 401 döner, JavaScript sayfayı yeniler.
        if ($request->isAjax()) {
            throw HttpException::unauthorized();
        }

        /* "GİRİŞTEN SONRA BURAYA DÖN" adresi.
         *
         * Yalnızca GET isteklerinde ve İSTEĞİN KENDİ YOLUNDAN yazılır.
         * Eskiden ham "r" parametresi (GET ya da POST) olduğu gibi
         * saklanıyordu. "auth" ara katmanı "csrf"ten ÖNCE çalıştığı
         * için başka bir site, ziyaretçinin tarayıcısına POST ile
         * "r=/\evil.example" yerleştirebiliyor; giriş sonrası yanıt
         * "Location: /\evil.example" oluyordu — tarayıcı bunu
         * //evil.example, yani BAŞKA BİR SİTE sayar (açık yönlendirme).
         * Ayrıca temiz adreslerde "r" hiç gelmediği için özellik zaten
         * çalışmıyordu. Url::current() yolu dar bir karakter kümesine
         * indirger; AuthController de okurken yeniden doğrular. */
        if ($request->method() === 'GET') {
            Session::set('_intended', self::safeIntended(Url::current()));
        }

        Response::redirect(url('giris'));
    }

    /**
     * Girişten sonra dönülecek yolu doğrular. Geçersizse '' döner.
     *
     * Kabul edilen: "panel/kullanicilar" gibi UYGULAMA İÇİ, göreli bir
     * rota yolu. Şema, alan adı, ters bölü, "//" ya da ".." içeren her
     * şey reddedilir; url() de sonucu her zaman uygulamanın taban
     * yolunun altına yerleştirir.
     */
    public static function safeIntended(mixed $path): string
    {
        /* "//alan.adi" ve "/\alan.adi" tarayıcıda BAŞKA SİTE demektir;
         * kırpmadan önce reddedilir (kırpılınca masum görünürlerdi). */
        if (!is_string($path) || preg_match('#^\s*[/\\\\]{2}#', $path) === 1) {
            return '';
        }

        $path = trim($path, '/');

        if ($path === ''
            || strlen($path) > 200
            || preg_match('#^[A-Za-z0-9/_.-]+\z#', $path) !== 1
            || str_contains($path, '//')
            || str_contains($path, '..')) {
            return '';
        }

        return $path;
    }

    private static function guest(): void
    {
        if (Auth::check()) {
            Response::redirect(url('panel'));
        }
    }

    /** "role:admin" veya "role:admin|editor" */
    private static function role(string $roles, Request $request): void
    {
        $allowed = explode('|', $roles);

        if (Auth::user() !== null && in_array(Auth::user()->rol, $allowed, true)) {
            return;
        }

        self::deny($request, 'rol:' . $roles);
    }

    /** "can:users.delete" — birden fazlası "|" ile VEYA anlamına gelir. */
    private static function can(string $abilities, Request $request): void
    {
        foreach (explode('|', $abilities) as $ability) {
            if (Auth::can($ability)) {
                return;
            }
        }

        self::deny($request, $abilities);
    }

    private static function csrf(Request $request): void
    {
        if (Csrf::check()) {
            return;
        }

        // Bağlamı istisnaya koyuyoruz; kaydı ErrorHandler tek satırda
        // "security" kanalına yazar (bkz. ErrorHandler::log).
        throw HttpException::pageExpired([
            'yontem'  => $request->method(),
            'referer' => mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 200),
        ]);
    }

    /**
     * Kurulum tamamlanmadıysa siteyi kapatır, kurulum sihirbazına yönlendirir.
     * kurulum/ klasörü kendi başına çalışır; bu kontrol SADECE ana
     * uygulamanın .env'siz açılmasını (500 hatası yerine) engeller.
     */
    private static function installed(): void
    {
        if (Env::exists(CY_BASE . '/.env')) {
            return;
        }

        Response::redirect(Url::base() . '/kurulum/');
    }

    /**
     * Bakım modu açıkken herkese açık YAZMA uçlarını kapatır.
     *
     * Bakım modu uzun süre yalnızca bir GÖRSEL ÖRTÜYDÜ: layouts/site
     * içeriği gizliyordu ama form gönderimleri JSON uçlarına gidip
     * layout'a hiç uğramadığı için iletişim mesajı kaydedilmeye,
     * yeni üyeler kaydolmaya devam ediyordu. Kapıyı rotanın önüne
     * koyuyoruz.
     *
     * Yalnızca "maintenance.bypass" yetkisi olanlar (yönetici ve
     * editör) muaftır: bakım sırasında sistemi düzeltebilmeleri
     * gerekir. Eskiden muafiyet "dashboard.view"e bağlıydı; o yetki
     * HER ÜYEDE var ve kayıt da açık olduğu için herkes kayıt olup
     * bakım modunu atlayabiliyordu.
     */
    private static function maintenance(): void
    {
        if (!Setting::bool('sistem_bakim_modu', false) || Auth::can('maintenance.bypass')) {
            return;
        }

        throw HttpException::maintenance();
    }

    /**
     * Yetkisiz erişim. Yanıtın nasıl görüneceğine (JSON mu, hata
     * sayfası mı) burada değil ErrorHandler karar verir; biz yalnızca
     * "bu istek 403 ile bitmeli" deriz.
     */
    private static function deny(Request $request, string $ability = ''): never
    {
        throw HttpException::forbidden(
            ability: $ability,
            context: ['kullanici' => Auth::id() ?? 'konuk']
        );
    }
}
