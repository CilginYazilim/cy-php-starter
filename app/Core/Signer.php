<?php
/**
 * =====================================================================
 *  Signer – Kuruluma özel HMAC imzası
 * ---------------------------------------------------------------------
 *  Veritabanına tablo açmadan "bu değeri BİZ ürettik ve kimse
 *  değiştirmedi" diyebilmek için:
 *
 *    · güvenilen cihaz çerezi       (bkz. Auth, giriş kilidi)
 *    · e-posta doğrulama bağlantısı (bkz. AuthController::verify)
 *    · formun üretildiği an          (bot süresi kontrolü)
 *
 *  ANAHTAR: .env'deki APP_KEY. Boşsa (eski kurulumlar) bir kereliğine
 *  rastgele bir anahtar üretilip storage/app.key dosyasına yazılır;
 *  imzalar yine tahmin edilemez olur. APP_KEY sonradan doldurulursa
 *  eski imzalar (doğrulama bağlantıları, cihaz çerezleri) geçersiz
 *  kalır — başka hiçbir şey bozulmaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Signer
{
    private static ?string $key = null;

    /** "değer" için imza (64 onaltılık karakter). */
    public static function sign(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    public static function check(string $value, string $signature): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $signature) === 1
            && hash_equals(self::sign($value), $signature);
    }

    /* =================================================================
     *  ZAMAN DAMGASI (form ne zaman üretildi?)
     * -----------------------------------------------------------------
     *  Bot süresi kontrolü için forma "şu an" yazılır. İmzasız bir
     *  sayı, bot tarafından "1 saat önce" diye uydurulabilirdi; alan
     *  hiç gönderilmezse de kontrol atlanıyordu. Damga artık imzalıdır
     *  ve ZORUNLUDUR.
     * ============================================================== */

    /** "1730000000.<imza>" */
    public static function stamp(string $purpose): string
    {
        $now = (string) time();

        return $now . '.' . self::sign('damga|' . $purpose . '|' . $now);
    }

    /**
     * Damganın yaşı (saniye). Geçersiz ya da eksikse null.
     */
    public static function stampAge(string $purpose, string $stamp): ?int
    {
        if (preg_match('/\A(\d{9,11})\.([a-f0-9]{64})\z/', $stamp, $m) !== 1) {
            return null;
        }

        if (!self::check('damga|' . $purpose . '|' . $m[1], $m[2])) {
            return null;
        }

        $age = time() - (int) $m[1];

        // Gelecekten gelen damga (saat oynatması) da geçersizdir.
        return $age < -5 ? null : max(0, $age);
    }

    /** Testler için anahtarı elle vermek. */
    public static function useKey(?string $key): void
    {
        self::$key = $key;
    }

    private static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $configured = trim((string) Config::get('app.key', ''));

        if ($configured !== '') {
            return self::$key = 'cy|' . $configured;
        }

        return self::$key = 'cy|' . self::fileKey();
    }

    /**
     * APP_KEY boşken kullanılan, diskte saklanan anahtar.
     *
     * Kurulum yolundan türetilmiş bir değer KULLANILMAZ: yol tahmin
     * edilebilir; tahmin edilebilen anahtarla imza üretmek imzasız
     * çalışmakla aynıdır.
     */
    private static function fileKey(): string
    {
        $base = defined('CY_BASE') ? CY_BASE : dirname(__DIR__, 2);
        $file = $base . '/storage/app.key';

        $existing = is_file($file) ? trim((string) @file_get_contents($file)) : '';

        if (preg_match('/\A[a-f0-9]{64}\z/', $existing) === 1) {
            return $existing;
        }

        $fresh = bin2hex(random_bytes(32));

        /* "x" kipi: iki istek aynı anda üretirse yalnızca biri yazar,
         * diğeri onun yazdığını okur. */
        $handle = @fopen($file, 'x');

        if ($handle !== false) {
            fwrite($handle, $fresh);
            fclose($handle);
            @chmod($file, 0600);

            return $fresh;
        }

        $existing = is_file($file) ? trim((string) @file_get_contents($file)) : '';

        if (preg_match('/\A[a-f0-9]{64}\z/', $existing) === 1) {
            return $existing;
        }

        throw new RuntimeException('İmza anahtarı yok: .env dosyasına APP_KEY yazın (php -r "echo bin2hex(random_bytes(32));").');
    }
}
