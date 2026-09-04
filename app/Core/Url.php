<?php
/**
 * =====================================================================
 *  Url – Adres üretiminin tek merkezi
 * ---------------------------------------------------------------------
 *  İKİ BİÇİM DESTEKLENİR (config/app.php → pretty_urls):
 *
 *      true  →  /cy-php-starter/panel/ayarlar        (SEO uyumlu)
 *      false →  /cy-php-starter/index.php?r=panel/ayarlar
 *
 *  Uygulama kodu ikisini de bilmez; her yerde url('panel/ayarlar')
 *  yazılır ve biçim tek satırlık bir ayarla değişir.
 *
 *  NEDEN KÖKE GÖRELİ (/ ile başlayan) ADRESLER?
 *  Eskiden "index.php?r=..." gibi GÖRELİ adresler üretiliyordu.
 *  Temiz adreslerde bu ölümcüldür: /panel/ayarlar sayfasındayken
 *  göreli "assets/css/site.css" adresi tarayıcı tarafından
 *  /panel/assets/css/site.css olarak çözülür ve sayfa stilsiz kalır.
 *  Bu yüzden her adres uygulamanın TABAN YOLUYLA başlar.
 *
 *  TABAN YOLU NASIL BULUNUR? SCRIPT_NAME'den. Uygulama sunucu
 *  kökünde de (/) alt klasörde de (/cy-php-starter) çalışır;
 *  hiçbir yere elle adres yazmanız gerekmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Url
{
    private static ?string $base = null;
    private static ?string $current = null;

    /**
     * Uygulamanın taban yolu: "" (kök) ya da "/cy-php-starter".
     * Sonunda bölü işareti YOKTUR.
     */
    public static function base(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }

        // Komut satırında istek yolu yoktur; APP_URL'e düşeriz.
        if (PHP_SAPI === 'cli') {
            $path = (string) parse_url((string) Config::get('app.url', ''), PHP_URL_PATH);

            return self::$base = rtrim($path, '/');
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');

        $dir = ($dir === '' || $dir === '.') ? '' : $dir;

        return self::$base = self::aliasTaban($dir) ?? $dir;
    }

    /**
     * VİTRİN (TAKMA AD) YOLU — uygulama BAŞKA bir adresten servis ediliyorsa.
     *
     * cilginyazilim.com/kutuphane/uygulama/cy-php-starter/ adresi sunucuda
     * demos/cy-php-starter/ klasörüne İÇERİDEN bağlanır (mod_rewrite). Böyle
     * bir istekte SCRIPT_NAME gerçek klasörü ("/demos/cy-php-starter/index.php")
     * gösterir ama REQUEST_URI ziyaretçinin gördüğü adrestir. Taban yalnızca
     * SCRIPT_NAME'den türetilirse ikisi tutmaz: rota "kutuphane/uygulama/..."
     * diye okunur ve uygulama KENDİ 404'ünü basar.
     *
     * Bu yüzden istek gerçek klasörün altından GELMİYORSA taban isteğin
     * kendisinden türetilir: yoldaki klasör adına kadar olan bölüm tabandır.
     * Böylece hem rota doğru çözülür hem de üretilen bütün adresler (assets/,
     * form action'ları, yönlendirmeler) ziyaretçinin bulunduğu adreste kalır.
     *
     * @param string $dir SCRIPT_NAME'den gelen gerçek klasör, ör. "/demos/cy-php-starter"
     *
     * @return string|null Takma ad tabanı; yoksa null (olağan durum)
     */
    private static function aliasTaban(string $dir): ?string
    {
        if ($dir === '') {
            return null;
        }

        $uri = explode('?', (string) ($_SERVER['REQUEST_URI'] ?? ''), 2)[0];
        $uri = rawurldecode($uri);

        // Olağan durum: istek zaten gerçek klasörün altından geliyor.
        if ($uri === '' || $uri === $dir || str_starts_with($uri, $dir . '/')) {
            return null;
        }

        /* İstekten gelen bu yol adres üretiminde kullanılacak; karakter kümesi
         * current() ile aynı dar kümeye indirilir, ".." koşulsuz reddedilir. */
        if (preg_match('#[^a-zA-Z0-9/_.-]#', $uri) === 1 || str_contains($uri, '..')) {
            return null;
        }

        $klasor = basename($dir);

        if ($klasor === '') {
            return null;
        }

        $yer = strpos($uri, '/' . $klasor . '/');

        if ($yer !== false) {
            return substr($uri, 0, $yer + strlen($klasor) + 1);
        }

        return str_ends_with($uri, '/' . $klasor) ? $uri : null;
    }

    /** Testler için tabanı elle belirlemek. */
    public static function useBase(?string $base): void
    {
        self::$base = $base === null ? null : rtrim($base, '/');
    }

    /**
     * Uygulama içi adres üretir.
     *
     * @param array<string,string|int> $params Sorgu dizesi parametreleri
     */
    public static function to(string $path = '', array $params = []): string
    {
        $path = trim($path, '/');
        $base = self::base();

        if ((bool) Config::get('app.pretty_urls', true)) {
            $url = $base . '/' . $path;

            return $params === [] ? $url : $url . '?' . self::query($params);
        }

        $query = $path === '' ? [] : ['r' => $path];
        $query = array_merge($query, $params);

        $url = $base . '/index.php';

        return $query === [] ? $url : $url . '?' . self::query($query);
    }

    /** Tam (mutlak) adres — e-posta ve site haritası için. */
    public static function absolute(string $path = '', array $params = []): string
    {
        $configured = rtrim((string) Config::get('app.url', ''), '/');

        if ($configured !== '') {
            // APP_URL zaten taban yolunu içerebilir; iki kez eklemeyelim.
            $relative = self::to($path, $params);
            $base     = self::base();

            if ($base !== '' && str_ends_with($configured, $base)) {
                return rtrim(substr($configured, 0, -strlen($base)), '/') . $relative;
            }

            /* Taban APP_URL'inkiyle uyuşmuyorsa uygulama vitrin yolundan servis
             * ediliyor demektir (bkz. aliasTaban()). APP_URL'in YOL kısmı o
             * durumda başka bir hedefi gösterir; yalnızca alan adı alınır, yolu
             * istekten türetilen taban verir. Aksi hâlde iki yol üst üste binip
             * /demos/cy-php-starter/kutuphane/uygulama/... gibi adresler çıkar. */
            $yol = rtrim((string) parse_url($configured, PHP_URL_PATH), '/');

            return $yol === $base ? rtrim($configured, '/') . $relative : self::origin() . $relative;
        }

        $scheme = Session::isHttps() ? 'https' : 'http';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host . self::to($path, $params);
    }

    /**
     * Yalnızca şema + alan adı: "https://ornek.com" (sonda bölü YOK).
     *
     * NE ZAMAN GEREKİR? Elinizde ZATEN taban yolunu içeren, köke
     * göreli bir adres varsa (asset() ve Setting::logoUrl() böyle
     * adresler üretir) onu absolute() ile mutlaklaştıramazsınız:
     * absolute() taban yolunu bir kez DAHA ekler ve ortaya
     *     http://localhost/proje/proje/assets/logo.png
     * gibi çalışmayan bir adres çıkar. Böyle durumlarda adresin
     * başına yalnızca bunu ekleyin.
     */
    public static function origin(): string
    {
        $configured = rtrim((string) Config::get('app.url', ''), '/');

        if ($configured !== '') {
            $parts = parse_url($configured);

            if (!empty($parts['host'])) {
                return ($parts['scheme'] ?? 'http') . '://' . $parts['host']
                    . (isset($parts['port']) ? ':' . $parts['port'] : '');
            }
        }

        return (Session::isHttps() ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /**
     * assets/ altındaki dosyaya SÜRÜM DAMGALI adres üretir.
     *
     * filemtime() son değişiklik zamanını adrese ekler; dosya
     * güncellendiğinde tarayıcı eski sürümü göstermeye devam etmez.
     */
    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = CY_BASE . '/assets/' . $path;

        $version = is_file($file) ? (string) filemtime($file) : '';

        return self::base() . '/assets/' . $path . ($version !== '' ? '?v=' . $version : '');
    }

    /**
     * İsteğin çözümlenmiş rota yolu: "panel/ayarlar".
     *
     * Üç kaynağa sırayla bakar:
     *   1. ?r=...            (temiz adres kapalıyken ve eski bağlantılarda)
     *   2. REQUEST_URI       (temiz adres açıkken, taban yolu çıkarılır)
     *   3. PATH_INFO         (index.php/panel biçimi)
     */
    public static function current(): string
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $raw = (string) ($_GET['r'] ?? '');

        if ($raw === '') {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $uri = explode('?', $uri, 2)[0];
            $uri = rawurldecode($uri);

            $base = self::base();

            if ($base !== '' && str_starts_with($uri, $base)) {
                $uri = substr($uri, strlen($base));
            }

            // "/index.php" doğrudan çağrıldıysa rota kök demektir.
            $uri = preg_replace('#^/?index\.php#', '', $uri) ?? '';

            $raw = $uri;
        }

        if ($raw === '' && isset($_SERVER['PATH_INFO'])) {
            $raw = (string) $_SERVER['PATH_INFO'];
        }

        /* GÜVENLİK: Yalnızca harf, rakam, bölü, alt çizgi, tire ve nokta.
         *
         * Nokta gereklidir — "manifest.webmanifest", "sitemap.xml",
         * "robots.txt" gibi adresler onsuz tanımlanamaz. Ama ".."
         * dizin dışına çıkma girişimidir ve KOŞULSUZ reddedilir:
         * içeren bir yol kök sayılır, yani 404 üretir. */
        $clean = preg_replace('#[^a-zA-Z0-9/_.-]#', '', $raw) ?? '';
        $clean = trim((string) preg_replace('#/+#', '/', $clean), '/');

        if (str_contains($clean, '..')) {
            return self::$current = '';
        }

        return self::$current = $clean;
    }

    /** Testler ve yönlendirme sonrası önbelleği sıfırlamak için. */
    public static function forgetCurrent(): void
    {
        self::$current = null;
    }

    /**
     * Şu an bu rotadayız (ya da altındayız) — menüde aktif bağlantıyı
     * işaretlemek için.
     */
    public static function isCurrent(string $path): bool
    {
        $path    = trim($path, '/');
        $current = self::current();

        if ($path === '') {
            return $current === '';
        }

        return $current === $path || str_starts_with($current, $path . '/');
    }

    /**
     * @param array<string,string|int> $params
     */
    private static function query(array $params): string
    {
        /* http_build_query() "/" karakterini %2F olarak kodlar; sorgu
         * DEĞERLERİNDE "/" kodlanmak zorunda değildir (RFC 3986), bu
         * yüzden adres çubuğunda okunaklı olsun diye geri çözüyoruz. */
        return str_replace('%2F', '/', http_build_query($params));
    }
}
