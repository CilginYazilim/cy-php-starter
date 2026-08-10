<?php
/**
 * =====================================================================
 *  ErrorHandler – Uygulamadaki BÜTÜN hataların tek çıkış kapısı
 * ---------------------------------------------------------------------
 *  Üç kaynağı birden yakalar:
 *
 *    1. Yakalanmamış istisnalar   → set_exception_handler
 *    2. PHP uyarıları/notice'ları → set_error_handler
 *    3. Ölümcül hatalar           → register_shutdown_function
 *
 *  Üçüncüsü çok önemlidir: "Allowed memory size exhausted" ya da bir
 *  yazım hatası (parse error) istisna FIRLATMAZ; onlarsız kullanıcı
 *  bembeyaz bir sayfa görür ve log'da hiçbir iz kalmaz.
 *
 *  İKİ AYRI DÜNYA
 *    Geliştirme → hatanın kendisi, dosya, satır ve yığın izi ekranda.
 *    Yayın      → kullanıcıya nötr bir mesaj; ayrıntı yalnızca log'da.
 *
 *  Bu ayrım güvenliğin temelidir: bir SQL hatasının metni tablo ve
 *  sütun adlarınızı, dosya yolu da sunucu düzeninizi ele verir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Core\Log\Logger;
use ErrorException;
use Throwable;

final class ErrorHandler
{
    private static bool $debug = false;

    /** Hata işlerken yeni bir hata çıkarsa sonsuz döngüye girmeyelim. */
    private static bool $handling = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;

        // Her şeyi RAPORLA ama hiçbir şeyi kendiliğinden EKRANA BASMA:
        // çıktının nasıl görüneceğine aşağıdaki metotlar karar verir.
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function isDebug(): bool
    {
        return self::$debug;
    }

    /* =================================================================
     *  1) PHP UYARILARI
     * ============================================================== */

    /**
     * Geliştirmede uyarıyı istisnaya çeviririz — görmezden gelinemesin.
     * Yayında yalnızca loglarız: bir "undefined array key" yüzünden
     * ziyaretçinin sayfasını çökertmek, sorunu çözmez, büyütür.
     */
    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        // "@" ile bastırılmış çağrılar (ör. @unlink) buraya düşmemeli.
        if (!(error_reporting() & $severity)) {
            return false;
        }

        if (self::$debug) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }

        Logger::warning(self::severityName($severity) . ': ' . $message, [
            'file' => self::relative($file) . ':' . $line,
        ], 'error');

        return true;
    }

    /* =================================================================
     *  2) ÖLÜMCÜL HATALAR
     * ============================================================== */

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }

        self::handleException(new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }

    /* =================================================================
     *  3) İSTİSNALAR
     * ============================================================== */

    public static function handleException(Throwable $e): void
    {
        // İkinci kez buraya düştüysek görünüm katmanı da bozuk demektir;
        // en ilkel çıktıya iner ve çıkarız.
        if (self::$handling) {
            self::bail($e);
        }

        self::$handling = true;

        $http   = $e instanceof HttpException ? $e : null;
        $status = $http?->status() ?? 500;

        self::log($e, $http);

        // Terminalde HTML basmanın anlamı yok; okunur bir metin ver.
        if (PHP_SAPI === 'cli') {
            self::respondConsole($e);
        }

        // Kısmen basılmış sayfa parçalarını at; hata sayfası temiz
        // bir gövdeye yazılmalı.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        /* Tarayıcıya giden kod ile UYGULAMA İÇİ kod farklı olabilir:
         * 419 gibi kayıtlı olmayan bir durum kodunu Apache 500'e
         * çevirir (bkz. HttpException::wireStatus). Metin, başlık ve
         * görünüm hâlâ gerçek durumu anlatır. */
        $wire = $http?->wireStatus() ?? $status;

        if (!headers_sent()) {
            http_response_code($wire);
        }

        if (self::wantsJson()) {
            self::respondJson($e, $status, $wire, $http);
        }

        self::respondHtml($e, $http, $status);
    }

    /* =================================================================
     *  LOGLAMA
     * ============================================================== */

    private static function log(Throwable $e, ?HttpException $http): void
    {
        // 404 gürültüdür: her tarayıcı favicon'u, her bot taraması bir
        // tane üretir. INFO seviyesinde ve "app" kanalında tutuyoruz.
        if ($http !== null && $http->status() === 404) {
            Logger::info('404: ' . $e->getMessage(), $http->context(), 'app');
            return;
        }

        if ($http !== null && !$http->isServerError()) {
            Logger::write(
                Logger::WARNING,
                $http->status() . ': ' . $e->getMessage(),
                $http->context(),
                $http->isSecurityEvent() ? 'security' : 'app'
            );

            return;
        }

        Logger::exception($e, 'error', $http?->context() ?? []);
    }

    /* =================================================================
     *  YANITLAR
     * ============================================================== */

    /**
     * İstemci JSON mu bekliyor? AJAX başlığı ya da Accept başlığı
     * yeterlidir; ikisi de yoksa insan tarayıcısı varsayarız.
     */
    private static function wantsJson(): bool
    {
        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return true;
        }

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        return str_contains($accept, 'application/json');
    }

    /**
     * Geliştirmede yanıta "debug" bloğu eklenir; tarayıcı konsolunda
     * hangi istisnanın nereden geldiği görünür.
     *
     * HttpException'lar bunun DIŞINDADIR: 404/403 zaten beklenen
     * sonuçlardır, her AJAX çağrısına yığın bilgisi iliştirmek
     * yalnızca gürültü yapar.
     */
    private static function respondJson(Throwable $e, int $status, ?int $wire = null, ?HttpException $http = null): never
    {
        $extra = [];

        /* JavaScript "oturum düştü mü?" sorusunu durum koduna bakarak
         * yanıtlayamaz artık (419 telde 403'e dönüşüyor). Bayrağı
         * yanıtın içine koyuyoruz; app.js bunu görünce sayfayı
         * kendiliğinden tazeliyor. */
        if ($http !== null && $http->isExpired()) {
            $extra['expired'] = true;
        }

        if (self::$debug && !$e instanceof HttpException) {
            $extra['debug'] = [
                'exception' => $e::class,
                'file'      => self::relative($e->getFile()) . ':' . $e->getLine(),
                'trace'     => array_column(self::frames($e, 5), 'konum'),
            ];
        }

        Response::error(self::publicMessage($e, $status), $wire ?? $status, $extra);
    }

    /**
     * Terminal yanıtı. Yığın izi yalnızca hata ayıklama açıkken
     * basılır; cron çıktısında sayfalarca iz istemeyiz.
     */
    private static function respondConsole(Throwable $e): never
    {
        $stream = defined('STDERR') ? STDERR : fopen('php://stderr', 'w');

        fwrite($stream, PHP_EOL . '  ✖ ' . $e::class . PHP_EOL);
        fwrite($stream, '    ' . $e->getMessage() . PHP_EOL);
        fwrite($stream, '    ' . self::relative($e->getFile()) . ':' . $e->getLine() . PHP_EOL);

        if (self::$debug) {
            fwrite($stream, PHP_EOL . '    Yığın izi:' . PHP_EOL);

            foreach (self::frames($e, 10) as $frame) {
                fwrite($stream, '      ' . $frame['konum'] . '  ' . $frame['cagri'] . PHP_EOL);
            }
        }

        fwrite($stream, PHP_EOL . '    Ayrıntı: storage/logs/error-' . date('Y-m-d') . '.log' . PHP_EOL . PHP_EOL);

        exit(1);
    }

    private static function respondHtml(Throwable $e, ?HttpException $http, int $status): never
    {
        // Geliştirmede beklenmeyen hatalar için ayrıntılı ekran.
        // 4xx'lerde göstermeyiz: onlar zaten "beklenen" durumlardır.
        if (self::$debug && $http === null) {
            self::renderDebug($e);
        }

        $view  = $http?->view() ?? 'errors/500';
        $title = $http?->title() ?? 'Sunucu Hatası';

        try {
            View::render($view, [
                'title'   => $title,
                'status'  => $status,
                'message' => self::publicMessage($e, $status),
                'path'    => (string) ($http?->context()['yol'] ?? ''),
            ], self::layout());
        } catch (Throwable $renderFailure) {
            // Görünüm de patladıysa (veritabanı yok, şablon bozuk…)
            // en azından anlaşılır bir metin bırakalım.
            Logger::exception($renderFailure, 'error', ['asil_hata' => $e->getMessage()]);

            self::bail($e);
        }

        exit;
    }

    /**
     * Hata sayfası hangi düzenle basılsın?
     *
     * Auth::user() VERİTABANINA GİDER. Hata zaten veritabanı kaynaklı
     * olabileceği için burada denemesi risklidir; oturum çerezine
     * doğrudan bakıp yalnızca "giriş yapmış görünüyor mu" diye
     * karar veriyoruz.
     */
    private static function layout(): string
    {
        if (!defined('CY_BASE')) {
            return 'layouts/plain';
        }

        $loggedIn = isset($_SESSION['_auth_user_id']);

        try {
            return $loggedIn ? 'layouts/admin' : 'layouts/site';
        } catch (Throwable) {
            return 'layouts/plain';
        }
    }

    /** Kullanıcının görmesi güvenli olan mesaj. */
    private static function publicMessage(Throwable $e, int $status): string
    {
        if ($e instanceof HttpException) {
            return $e->getMessage();
        }

        if (self::$debug) {
            return $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        }

        return $status >= 500
            ? 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
            : 'İstek işlenemedi.';
    }

    /* =================================================================
     *  GELİŞTİRİCİ EKRANI
     * ============================================================== */

    private static function renderDebug(Throwable $e): never
    {
        try {
            View::render('errors/debug', [
                'title'     => 'Hata: ' . $e::class,
                'exception' => $e,
                'snippet'   => self::snippet($e->getFile(), $e->getLine()),
                'frames'    => self::frames($e),
            ], 'layouts/plain');
        } catch (Throwable) {
            self::bail($e);
        }

        exit;
    }

    /**
     * Hatanın geçtiği satırın çevresinden birkaç satır kod.
     *
     * @return array<int,string> satır numarası => kod
     */
    private static function snippet(string $file, int $line, int $padding = 6): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        $start = max(0, $line - $padding - 1);
        $end   = min(count($lines), $line + $padding);

        $snippet = [];

        for ($i = $start; $i < $end; $i++) {
            $snippet[$i + 1] = $lines[$i];
        }

        return $snippet;
    }

    /**
     * Yığın izini okunabilir karelere çevirir.
     *
     * @return array<int,array{konum:string,cagri:string}>
     */
    private static function frames(Throwable $e, int $limit = 20): array
    {
        $frames = [];

        foreach (array_slice($e->getTrace(), 0, $limit) as $frame) {
            $class = (string) ($frame['class'] ?? '');
            $type  = (string) ($frame['type'] ?? '');

            $frames[] = [
                'konum' => self::relative((string) ($frame['file'] ?? '[internal]')) . ':' . (string) ($frame['line'] ?? '0'),
                'cagri' => $class . $type . (string) ($frame['function'] ?? '') . '()',
            ];
        }

        return $frames;
    }

    /* =================================================================
     *  YARDIMCILAR
     * ============================================================== */

    /** Proje köküne göre kısaltılmış dosya yolu. */
    public static function relative(string $file): string
    {
        if (defined('CY_BASE') && str_starts_with($file, CY_BASE)) {
            return ltrim(substr($file, strlen(CY_BASE)), '/\\');
        }

        return $file;
    }

    private static function severityName(int $severity): string
    {
        return match ($severity) {
            E_WARNING, E_USER_WARNING           => 'Uyarı',
            E_NOTICE, E_USER_NOTICE             => 'Bildirim',
            E_DEPRECATED, E_USER_DEPRECATED     => 'Kullanımdan kaldırıldı',
            E_STRICT                            => 'Sıkı mod',
            default                             => 'Hata',
        };
    }

    /** Son çare: hiçbir şey çalışmıyorsa düz metin bırak ve çık. */
    private static function bail(Throwable $e): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }

        echo self::$debug
            ? $e::class . ': ' . $e->getMessage() . ' @ ' . self::relative($e->getFile()) . ':' . $e->getLine()
            : 'Beklenmeyen bir hata oluştu.';

        exit(1);
    }
}
