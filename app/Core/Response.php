<?php
/**
 * =====================================================================
 *  Response – JSON / yönlendirme / güvenlik başlıkları
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        header_remove('X-Powered-By');

        // HTML sayfaları önbelleğe alınmasın (çıkıştan sonra "geri" ile
        // özel içerik görünmesin; ayrıca geliştirmede eski sayfa takılı kalmaz).
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        if (Config::get('security.csp_enabled', true)) {
            header('Content-Security-Policy: ' . self::csp());
        }
    }

    /**
     * İçerik Güvenliği Politikasını üretir.
     *
     * ÜÇ KAYNAKTAN BESLENİR:
     *   1. Aşağıdaki katı varsayılanlar
     *   2. config/security.php → csp_extra (dış servisler için)
     *   3. Panelden girilen "Analytics Kodu" — içindeki adresler ve
     *      satır içi betiklerin özetleri OTOMATİK eklenir
     *
     *  (3) olmasaydı yönetici analytics kodunu panele yapıştırır,
     *  hiçbir hata görmez ve kodun sessizce engellendiğini haftalarca
     *  fark etmezdi. Politikayı gevşetmek yerine tam olarak o kodun
     *  ihtiyacı kadarını açıyoruz.
     */
    private static function csp(): string
    {
        $directives = [
            'default-src'     => ["'self'"],
            'script-src'      => ["'self'"],
            'style-src'       => ["'self'", "'unsafe-inline'"],
            'img-src'         => ["'self'", 'data:'],
            'font-src'        => ["'self'", 'data:'],
            'connect-src'     => ["'self'"],
            'frame-src'       => ["'self'"],
            'form-action'     => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri'        => ["'self'"],
            'object-src'      => ["'none'"],
        ];

        /** @var array<string,array<int,string>> $extra */
        $extra = (array) Config::get('security.csp_extra', []);

        foreach (self::analyticsSources() as $directive => $sources) {
            $extra[$directive] = array_merge($extra[$directive] ?? [], $sources);
        }

        foreach ($extra as $directive => $sources) {
            if (!isset($directives[$directive]) || !is_array($sources)) {
                continue;
            }

            $directives[$directive] = array_values(array_unique(
                array_merge($directives[$directive], $sources)
            ));
        }

        $parts = [];

        foreach ($directives as $directive => $sources) {
            $parts[] = $directive . ' ' . implode(' ', $sources);
        }

        return implode('; ', $parts);
    }

    /**
     * "Analytics Kodu" ayarının çalışabilmesi için gereken CSP
     * kaynaklarını çıkarır.
     *
     *   <script src="https://www.googletagmanager.com/...">
     *        → script-src ve connect-src'e https://www.googletagmanager.com
     *   <script>window.dataLayer = …</script>
     *        → script-src'e o metnin 'sha256-…' özeti
     *
     * @return array<string,array<int,string>>
     */
    private static function analyticsSources(): array
    {
        $kod = trim(Setting::get('seo_analytics'));

        if ($kod === '') {
            return [];
        }

        $script = [];
        $connect = [];

        // Dış betik adresleri → kaynak (origin) düzeyinde izin.
        if (preg_match_all('/<script[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $kod, $eslesme)) {
            foreach ($eslesme[1] as $adres) {
                $parcalar = parse_url($adres);

                if (empty($parcalar['host'])) {
                    continue;
                }

                $origin = ($parcalar['scheme'] ?? 'https') . '://' . $parcalar['host'];

                $script[]  = $origin;
                $connect[] = $origin;
            }
        }

        /* Satır içi betikler → yalnızca o metnin özeti. Tek bir
         * boşluk değişse özet tutmaz; bu bilinçlidir, "unsafe-inline"
         * açmaktan çok daha dardır. */
        if (preg_match_all('/<script(?![^>]*\ssrc\s*=)[^>]*>(.*?)<\/script>/is', $kod, $eslesme)) {
            foreach ($eslesme[1] as $govde) {
                if (trim($govde) === '') {
                    continue;
                }

                $script[] = "'sha256-" . base64_encode(hash('sha256', $govde, true)) . "'";
            }
        }

        return array_filter([
            'script-src'  => $script,
            'connect-src' => $connect,
            'img-src'     => $connect,
        ]);
    }

    /** @param array<string,mixed> $payload */
    public static function json(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        /* JSON_INVALID_UTF8_SUBSTITUTE olmadan tek bir bozuk bayt
         * bütün yanıtı yok eder: json_encode() false döner, echo
         * hiçbir şey basmaz ve tarayıcı gövdesiz bir yanıt alır.
         * Bu, uydurma bir ihtimal değildir — işletim sisteminden
         * gelen hata metinleri (soket hataları, dosya adları) Windows'ta
         * sistem kod sayfasıyla gelir ve UTF-8 sayılmaz. */
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false) {
            $json = json_encode([
                'success'     => false,
                'type'        => 'danger',
                'description' => 'Sunucu yanıtı hazırlanamadı.',
            ], JSON_UNESCAPED_UNICODE);
        }

        echo $json;
        exit;
    }

    /** @param array<string,mixed> $extra */
    public static function success(string $description, array $extra = []): never
    {
        self::json(array_merge([
            'success'     => true,
            'type'        => 'success',
            'description' => $description,
        ], $extra));
    }

    /** @param array<string,mixed> $extra */
    public static function error(string $description, int $status = 400, array $extra = []): never
    {
        self::json(array_merge([
            'success'     => false,
            'type'        => 'danger',
            'description' => $description,
        ], $extra), $status);
    }

    /**
     * Özel (private) diskteki bir dosyayı tarayıcıya gönderir.
     *
     * PRIVATE DOSYALAR NEDEN BÖYLE SERVİS EDİLİR? storage/ klasörü
     * web'e kapalıdır; oraya yazılmış bir faturaya adres vererek
     * ulaşılamaz. İndirme bir denetleyiciden geçer, böylece "bu
     * kullanıcı bu faturayı görebilir mi?" sorusu SORULABİLİR:
     *
     *      $router->get('fatura/indir', FaturaController::class, 'indir',
     *          ['installed', 'auth', 'can:fatura.view']);
     *
     *      // Denetleyicide, kaydın sahibi kontrol edildikten SONRA:
     *      Response::download(Storage::disk('private'), $kayit->yol, $kayit->ad);
     *
     * @param string $disposition 'attachment' indirir, 'inline' tarayıcıda açar
     */
    public static function download(
        Storage\Disk $disk,
        string $path,
        string $displayName = '',
        string $disposition = 'attachment',
    ): never {
        // path(mustExist: true) yol güvenliğini ve varlığı doğrular;
        // geçersizse StorageException fırlar ve ErrorHandler yakalar.
        $absolute = $disk->path($path, true);

        $name = Storage\Storage::safeDisplayName(
            $displayName !== '' ? $displayName : basename($path)
        );

        $disposition = $disposition === 'inline' ? 'inline' : 'attachment';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $disk->mime($path));
            header('Content-Length: ' . (string) filesize($absolute));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');

            /* İki ad birden veriyoruz: ASCII'ye indirgenmiş "filename"
             * eski istemciler için, RFC 5987 "filename*" ise Türkçe
             * karakterleri doğru gösterebilen modern tarayıcılar için. */
            $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'dosya';
            $ascii = str_replace('"', '', $ascii);

            header(sprintf(
                '%s; filename="%s"; filename*=UTF-8\'\'%s',
                'Content-Disposition: ' . $disposition,
                $ascii,
                rawurlencode($name)
            ));
        }

        readfile($absolute);

        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Location: ' . $url);
        }

        exit;
    }
}
