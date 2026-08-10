<?php
/**
 * =====================================================================
 *  HttpException – "Bu istek şu HTTP durumuyla bitmeli" demenin yolu
 * ---------------------------------------------------------------------
 *  KULLANIMI
 *      throw HttpException::notFound($path);
 *      throw HttpException::forbidden('Bu kaydı silemezsiniz.');
 *
 *  NEDEN İSTİSNA? Denetleyicinin ortasından "403 döndür ve çık" demek
 *  için her katmanın exit çağırması gerekirdi; o zaman da hiçbir yerde
 *  ortak loglama, ortak görünüm ya da JSON/HTML ayrımı yapamazdık.
 *  İstisna fırlatınca karar tek yere — ErrorHandler'a — taşınır.
 *
 *  MESAJ GÜVENLİĞİ: Buradaki $message KULLANICIYA GÖSTERİLİR.
 *  Yalnızca 4xx (istemci hatası) için anlamlıdır ve içine veritabanı
 *  hatası, dosya yolu ya da SQL yazılmamalıdır. Ayrıntıyı $context'e
 *  koyun; o yalnızca log'a gider.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;
use Throwable;

final class HttpException extends RuntimeException
{
    /** @param array<string,mixed> $context Log'a yazılır, ekrana ASLA basılmaz. */
    public function __construct(
        private readonly int $status,
        string $message = '',
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status, $previous);
    }

    /* =================================================================
     *  ÜRETİCİLER
     * ============================================================== */

    public static function notFound(string $path = ''): self
    {
        return new self(404, 'Aradığınız sayfa bulunamadı.', $path !== '' ? ['yol' => $path] : []);
    }

    /** @param array<string,mixed> $context */
    public static function forbidden(string $message = '', string $ability = '', array $context = []): self
    {
        return new self(
            403,
            $message !== '' ? $message : 'Bu işlem için yetkiniz bulunmuyor.',
            $ability !== '' ? array_merge(['yetki' => $ability], $context) : $context
        );
    }

    public static function unauthorized(string $message = ''): self
    {
        return new self(401, $message !== '' ? $message : 'Oturumunuz sonlandı. Lütfen tekrar giriş yapın.');
    }

    /** @param array<int,string> $allowed */
    public static function methodNotAllowed(string $method = '', array $allowed = []): self
    {
        return new self(405, 'Bu adres için geçersiz istek yöntemi.', array_filter([
            'yontem' => $method,
            'izinli' => $allowed !== [] ? implode(',', $allowed) : '',
        ]));
    }

    /**
     * 419 – CSRF anahtarı geçersiz ("Page Expired").
     *
     * @param array<string,mixed> $context
     */
    public static function pageExpired(array $context = []): self
    {
        return new self(419, 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.', $context);
    }

    public static function tooManyRequests(int $retryAfter = 0): self
    {
        return new self(
            429,
            'Çok fazla istek gönderdiniz. Lütfen biraz bekleyin.',
            $retryAfter > 0 ? ['tekrar_dene' => $retryAfter] : []
        );
    }

    /**
     * 503 – Site bakımda.
     *
     * 200 ile "bakımdayız" sayfası basmak arama motorlarına "sitenin
     * içeriği artık bu" der ve gerçek sayfalar dizinden düşer. 503,
     * "geçici, sonra tekrar gel" demenin standart yoludur.
     */
    public static function maintenance(string $message = ''): self
    {
        return new self(
            503,
            $message !== '' ? $message : 'Site şu anda bakımda. Kısa süre içinde geri döneceğiz.'
        );
    }

    /** @param array<string,mixed> $context */
    public static function serverError(string $message = '', array $context = [], ?Throwable $previous = null): self
    {
        return new self(500, $message, $context, $previous);
    }

    /* =================================================================
     *  OKUYUCULAR
     * ============================================================== */

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        return $this->context;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500;
    }

    /**
     * Bu hata bir güvenlik olayı mı? (Ayrı log kanalına yazılır.)
     * 404 değildir — o yalnızca yanlış adrestir.
     */
    public function isSecurityEvent(): bool
    {
        return in_array($this->status, [401, 403, 419, 429], true);
    }

    /** Hangi görünüm dosyası basılacak? Karşılığı yoksa 500'e düşer. */
    public function view(): string
    {
        return match ($this->status) {
            404     => 'errors/404',
            401, 403, 419 => 'errors/403',
            default => 'errors/500',
        };
    }

    public function title(): string
    {
        return match ($this->status) {
            401     => 'Oturum Gerekli',
            403     => 'Yetkisiz Erişim',
            404     => 'Sayfa Bulunamadı',
            405     => 'Geçersiz İstek',
            419     => 'Oturum Süresi Doldu',
            429     => 'Çok Fazla İstek',
            503     => 'Bakım Çalışması',
            default => 'Sunucu Hatası',
        };
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400     => 'İstek anlaşılamadı.',
            401     => 'Oturumunuz sonlandı. Lütfen tekrar giriş yapın.',
            403     => 'Bu işlem için yetkiniz bulunmuyor.',
            404     => 'Aradığınız sayfa bulunamadı.',
            405     => 'Bu adres için geçersiz istek yöntemi.',
            419     => 'Güvenlik doğrulaması başarısız oldu.',
            422     => 'Gönderilen bilgiler geçerli değil.',
            429     => 'Çok fazla istek gönderdiniz.',
            503     => 'Site şu anda bakımda. Kısa süre içinde geri döneceğiz.',
            default => 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.',
        };
    }
}
