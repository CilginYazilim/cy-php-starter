<?php
/**
 * =====================================================================
 *  Yardımcı fonksiyonlar
 * ---------------------------------------------------------------------
 *  Görünüm dosyalarında sınıf adı yazmak yorucudur. En sık kullanılan
 *  işleri kısa global fonksiyonlara indirgedik.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Setting;
use App\Core\Url;

if (!function_exists('e')) {
    /** Metni HTML'e GÜVENLE basmak için kaçışlar (XSS koruması). */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('resolve_theme')) {
    /**
     * Sayfanın hangi temayla ("dark"/"light"/"") üretileceğine karar
     * verir. Giriş yapmış kullanıcı için VERİTABANI kesin kaynaktır —
     * böylece hangi cihazdan/tarayıcıdan girerse girsin kendi seçtiği
     * temayı görür. Konuk için tarayıcı çerezine bakılır; o da yoksa
     * boş döner ve CSS "prefers-color-scheme" ile sistem tercihine
     * uyar (varsayılan: açık tema).
     */
    function resolve_theme(): string
    {
        $user = Auth::user();

        if ($user !== null) {
            return $user->themeAttr();
        }

        $cookie = (string) ($_COOKIE['cy_theme'] ?? '');

        return $cookie === 'dark' ? 'dark' : ($cookie === 'light' ? 'light' : '');
    }
}

if (!function_exists('url')) {
    /**
     * Uygulama içi adres üretir. Adresler KÖKE GÖRELİDİR ("/..."),
     * böylece /panel/ayarlar gibi derin sayfalarda da doğru çözülür.
     *
     *   pretty_urls açık  → /cy-php-starter/panel/kullanicilar
     *   pretty_urls kapalı → /cy-php-starter/index.php?r=panel/kullanicilar
     *
     * @param array<string,string|int> $params
     */
    function url(string $path = '', array $params = []): string
    {
        return Url::to($path, $params);
    }
}

if (!function_exists('url_full')) {
    /** Tam adres (https://site.com/...) — e-posta ve site haritası için. */
    function url_full(string $path = '', array $params = []): string
    {
        return Url::absolute($path, $params);
    }
}
if (!function_exists('asset')) {
    /** assets/ altındaki dosyaya sürüm damgalı, köke göreli adres. */
    function asset(string $path): string
    {
        return Url::asset($path);
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('auth')) {
    function auth(): ?App\Models\User
    {
        return Auth::user();
    }
}

if (!function_exists('can')) {
    function can(string $ability): bool
    {
        return Auth::can($ability);
    }
}

if (!function_exists('config')) {
    /**
     * Yapılandırma değeri okur (config/ klasörü).
     *
     * setting() ile karıştırmayın: setting() veritabanındaki
     * "ayarlar" tablosundan okur ve panelden değiştirilebilir;
     * config() ise dosyadan gelir ve dağıtımla birlikte sabittir.
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('is_debug')) {
    /** Hata ayıklama açık mı? Görünümlerde tanılama bloğu göstermek için. */
    function is_debug(): bool
    {
        return Config::isDebug();
    }
}

if (!function_exists('setting')) {
    function setting(string $key, string $default = ''): string
    {
        return Setting::get($key, $default);
    }
}

if (!function_exists('setting_bool')) {
    function setting_bool(string $key, bool $default = false): bool
    {
        return Setting::bool($key, $default);
    }
}

if (!function_exists('is_route')) {
    /** Sol menüde aktif bağlantıyı işaretlemek için. */
    function is_route(string $path): bool
    {
        return Url::isCurrent($path);
    }
}
if (!function_exists('old')) {
    /** @param array<string,mixed> $bag */
    function old(array $bag, string $key, string $default = ''): string
    {
        $value = $bag[$key] ?? $default;

        return e(is_scalar($value) ? (string) $value : $default);
    }
}

if (!function_exists('human_date')) {
    /** Tarihi "3 dakika önce" gibi okunur biçime çevirir. */
    function human_date(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        $diff = time() - $timestamp;

        if ($diff < 0) {
            return date('d.m.Y H:i', $timestamp);
        }

        return match (true) {
            $diff < 60     => 'az önce',
            $diff < 3600   => (int) ($diff / 60) . ' dakika önce',
            $diff < 86400  => (int) ($diff / 3600) . ' saat önce',
            $diff < 604800 => (int) ($diff / 86400) . ' gün önce',
            default        => date('d.m.Y', $timestamp),
        };
    }
}

if (!function_exists('icon')) {
    /**
     * Satır içi SVG ikon döndürür.
     *
     * Bir ikon kütüphanesi (Font Awesome vb.) YÜKLEMİYORUZ: internet
     * olmadan da, CDN engelli bir sunucuda da panel eksiksiz açılmalı.
     * Tüm ikonlar 24x24 kutuda, "stroke" (çizgi) tarzındadır ve
     * currentColor kullanır — bulunduğu yerin rengini otomatik alır.
     */
    function icon(string $name, string $class = 'cy-icon'): string
    {
        static $paths = [
            'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
            'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
            'plus'      => '<path d="M12 5v14M5 12h14"/>',
            'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'filter'    => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
            'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
            'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'trash'     => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
            'menu'      => '<path d="M3 12h18M3 6h18M3 18h18"/>',
            'close'     => '<path d="M18 6 6 18M6 6l12 12"/>',
            'moon'      => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
            'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
            'activity'  => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
            'check'     => '<path d="M20 6 9 17l-5-5"/>',
            'alert'     => '<path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>',
            'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
            'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'lock'      => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'upload'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
            'chevron'   => '<path d="m9 18 6-6-6-6"/>',
            'github'    => '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/>',
            'globe'     => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
            'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            'server'    => '<rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01"/><path d="M6 17h.01"/>',
            'database'  => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
            'refresh'   => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
            'save'      => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
            'x-circle'  => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
            'link'      => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
            'send'      => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>',
            'inbox'     => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        ];

        $body = $paths[$name] ?? $paths['alert'];

        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
             . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
             . ' aria-hidden="true" focusable="false">' . $body . '</svg>';
    }
}
