<?php
/**
 * =====================================================================
 *  Logger – Uygulama günlükleri
 * ---------------------------------------------------------------------
 *  KULLANIMI
 *      Logger::info('Kullanıcı giriş yaptı', ['id' => 3], 'auth');
 *      Logger::warning('Şüpheli istek', ['ip' => $ip], 'security');
 *      Logger::exception($e);
 *
 *  KANALLAR
 *  Her kanal AYRI bir dosyaya yazar; "neden mail gitmedi" sorusunu
 *  yanıtlarken 50 bin satırlık tek bir dosyayı taramak zorunda
 *  kalmazsınız. Kanal adı serbesttir (modüller kendi kanalını açabilir),
 *  yalnızca dosya adına uygun hale getirilir.
 *
 *      storage/logs/app-2026-08-09.log
 *      storage/logs/security-2026-08-09.log
 *
 *  DÖNGÜ (rotation): Dosya adında tarih vardır; her gün yenisi açılır.
 *  Eskiler purge() ile silinir (CLI ve zamanlanmış görev bunu çağırır).
 *
 *  ALTIN KURAL: Logger ASLA istisna fırlatmaz. Disk doluysa ya da
 *  klasör yazılabilir değilse uygulama çalışmaya devam etmelidir —
 *  log tutamamak, sayfayı çökertmek için bir gerekçe değildir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Log;

use App\Core\Config;
use Throwable;

final class Logger
{
    public const DEBUG    = 'debug';
    public const INFO     = 'info';
    public const WARNING  = 'warning';
    public const ERROR    = 'error';
    public const CRITICAL = 'critical';

    /** Seviye ağırlıkları: yapılandırmadaki eşiğin altındakiler yazılmaz. */
    private const SEVERITY = [
        self::DEBUG    => 10,
        self::INFO     => 20,
        self::WARNING  => 30,
        self::ERROR    => 40,
        self::CRITICAL => 50,
    ];

    /**
     * Bağlam içinde GÖRÜLMEMESİ gereken anahtarlar. Bir parolayı
     * yanlışlıkla loglamak, onu düz metin olarak diske yazmaktır;
     * log dosyaları da yedeklere ve hata raporlarına karışır.
     */
    private const MASKED = [
        'sifre', 'parola', 'password', 'password_confirmation', 'pass',
        'token', 'csrf_token', 'secret', 'api_key', 'apikey',
        'authorization', 'cookie', 'session', 'mail_sifre', 'db_pass',
    ];

    private static ?string $dir = null;

    /* =================================================================
     *  KISAYOLLAR
     * ============================================================== */

    /** @param array<string,mixed> $context */
    public static function debug(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write(self::DEBUG, $message, $context, $channel);
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write(self::INFO, $message, $context, $channel);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write(self::WARNING, $message, $context, $channel);
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = [], string $channel = 'error'): void
    {
        self::write(self::ERROR, $message, $context, $channel);
    }

    /** @param array<string,mixed> $context */
    public static function critical(string $message, array $context = [], string $channel = 'error'): void
    {
        self::write(self::CRITICAL, $message, $context, $channel);
    }

    /**
     * Güvenlikle ilgili olaylar: başarısız giriş, CSRF ihlali, yetkisiz
     * erişim denemesi, hız sınırı aşımı. Ayrı kanalda tutulur ki
     * saldırı incelemesi yaparken gürültüye boğulmayasınız.
     *
     * @param array<string,mixed> $context
     */
    public static function security(string $message, array $context = []): void
    {
        self::write(self::WARNING, $message, $context, 'security');
    }

    /**
     * Bir istisnayı sınıfı, konumu ve yığın iziyle birlikte yazar.
     *
     * @param array<string,mixed> $context
     */
    public static function exception(Throwable $e, string $channel = 'error', array $context = []): void
    {
        self::write(
            self::CRITICAL,
            $e::class . ': ' . $e->getMessage(),
            array_merge($context, [
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => self::compactTrace($e),
            ]),
            $channel
        );
    }

    /* =================================================================
     *  YAZMA
     * ============================================================== */

    /** @param array<string,mixed> $context */
    public static function write(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        try {
            if (!self::enabled() || !self::passesThreshold($level)) {
                return;
            }

            $dir = self::dir();

            if ($dir === '') {
                return;
            }

            $file = $dir . DIRECTORY_SEPARATOR . self::safeChannel($channel) . '-' . date('Y-m-d') . '.log';

            @file_put_contents($file, self::format($level, $message, $context), FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Bilerek yutulur: bkz. sınıf başlığındaki "ALTIN KURAL".
        }
    }

    /** @param array<string,mixed> $context */
    private static function format(string $level, string $message, array $context): string
    {
        $line = sprintf(
            '[%s] %-8s %s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            self::singleLine($message)
        );

        $context = self::mask($context);

        if ($context !== []) {
            $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

            $line .= ' | ' . self::singleLine((string) $json);
        }

        $stamp = self::requestStamp();

        if ($stamp !== '') {
            $line .= ' | ' . $stamp;
        }

        return $line . PHP_EOL;
    }

    /**
     * Her satıra isteğin kimliğini ekler: hangi IP, hangi adres, hangi
     * kullanıcı. Log satırını tek başına anlamlı yapan şey budur.
     */
    private static function requestStamp(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }

        $parts = ['ip=' . (string) ($_SERVER['REMOTE_ADDR'] ?? '-')];

        $path = (string) ($_GET['r'] ?? '');
        $parts[] = 'yol=' . ($path !== '' ? self::singleLine(mb_substr($path, 0, 120)) : '/');

        // Oturuma DOĞRUDAN bakıyoruz; Auth::user() veritabanına gider
        // ve hata zaten veritabanı kaynaklıysa döngüye girerdik.
        $userId = $_SESSION['_auth_user_id'] ?? null;

        if (is_scalar($userId)) {
            $parts[] = 'uid=' . (int) $userId;
        }

        return implode(' ', $parts);
    }

    /**
     * Hassas anahtarları maskeler; iç içe dizilerde de çalışır.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function mask(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if (in_array(mb_strtolower((string) $key), self::MASKED, true)) {
                $clean[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::mask($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
                continue;
            }

            $clean[$key] = get_debug_type($value);
        }

        return $clean;
    }

    /**
     * Yığın izini kısaltır. Tam iz onlarca satırdır ve genelde ilk
     * birkaç kare sorunun nerede olduğunu zaten söyler.
     *
     * @return array<int,string>
     */
    private static function compactTrace(Throwable $e, int $limit = 8): array
    {
        $frames = [];
        $base   = defined('CY_BASE') ? CY_BASE : '';

        foreach (array_slice($e->getTrace(), 0, $limit) as $frame) {
            $file = (string) ($frame['file'] ?? '[internal]');

            if ($base !== '' && str_starts_with($file, $base)) {
                $file = ltrim(substr($file, strlen($base)), '/\\');
            }

            $frames[] = $file . ':' . (string) ($frame['line'] ?? '0')
                      . ' ' . (string) ($frame['function'] ?? '');
        }

        return $frames;
    }

    /** Satır sonları log dosyasının "her satır bir kayıt" düzenini bozar. */
    private static function singleLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /* =================================================================
     *  BAKIM
     * ============================================================== */

    /**
     * Saklama süresini geçmiş log dosyalarını siler.
     *
     * @param int $days 0 verilirse yapılandırmadaki değer kullanılır.
     * @return int Silinen dosya sayısı
     */
    public static function purge(int $days = 0): int
    {
        $days = $days > 0 ? $days : (int) Config::get('log.days', 30);
        $dir  = self::dir();

        if ($dir === '' || $days < 1) {
            return 0;
        }

        $limit   = time() - ($days * 86400);
        $deleted = 0;

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $limit && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Mevcut log dosyaları (yönetim panelinde listelemek için).
     *
     * @return array<int,array{ad:string,boyut:int,tarih:int}>
     */
    public static function files(): array
    {
        $dir = self::dir();

        if ($dir === '') {
            return [];
        }

        $files = [];

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $file) {
            $files[] = [
                'ad'     => basename($file),
                'boyut'  => (int) filesize($file),
                'tarih'  => (int) filemtime($file),
            ];
        }

        usort($files, static fn (array $a, array $b): int => $b['tarih'] <=> $a['tarih']);

        return $files;
    }

    /* =================================================================
     *  YAPILANDIRMA
     * ============================================================== */

    private static function enabled(): bool
    {
        return (bool) Config::get('log.enabled', true);
    }

    private static function passesThreshold(string $level): bool
    {
        $minimum = (string) Config::get('log.level', self::DEBUG);

        return (self::SEVERITY[$level] ?? 0) >= (self::SEVERITY[$minimum] ?? 0);
    }

    /** Kanal adı dosya adına gider; yalnızca güvenli karakterlere izin verilir. */
    private static function safeChannel(string $channel): string
    {
        $clean = preg_replace('/[^a-z0-9_-]/', '', mb_strtolower($channel)) ?? '';

        return $clean !== '' ? mb_substr($clean, 0, 40) : 'app';
    }

    /**
     * Log klasörü. Yoksa oluşturulur ve web erişimine kapatılır.
     * Oluşturulamazsa boş string döner (yazma sessizce atlanır).
     */
    public static function dir(): string
    {
        if (self::$dir !== null) {
            return self::$dir;
        }

        $dir = (string) Config::get('log.dir', '');

        if ($dir === '' && defined('CY_BASE')) {
            $dir = CY_BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        }

        if ($dir === '') {
            return self::$dir = '';
        }

        $dir = rtrim($dir, '/\\');

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return self::$dir = '';
        }

        self::guardDirectory($dir);

        return self::$dir = (is_writable($dir) ? $dir : '');
    }

    /**
     * storage/ zaten .htaccess ile kapalıdır; buradaki ikinci kilit,
     * klasör başka bir sunucu yapılandırmasıyla (Nginx, alt alan adı)
     * servis edilirse diye "kemer + askı" görevi görür.
     */
    private static function guardDirectory(string $dir): void
    {
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';

        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }

        $index = $dir . DIRECTORY_SEPARATOR . 'index.html';

        if (!is_file($index)) {
            @file_put_contents($index, '');
        }
    }

    /**
     * Günlük klasörünü elle belirler (testler ve özel kurulumlar).
     *
     * Klasör OLUŞTURULUR ve web erişimine KAPATILIR — tıpkı
     * yapılandırmadan gelen klasör gibi. Bu adımı atlasaydık, elle
     * verilen bir klasör korumasız kalır ve "log dosyalarım
     * tarayıcıdan okunuyor" gibi sessiz bir açık doğardı.
     */
    public static function useDirectory(?string $dir): void
    {
        if ($dir === null) {
            self::$dir = null;

            return;
        }

        $dir = rtrim($dir, '/\\');

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            self::$dir = '';

            return;
        }

        self::guardDirectory($dir);

        self::$dir = is_writable($dir) ? $dir : '';
    }
}
