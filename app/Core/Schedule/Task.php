<?php
/**
 * =====================================================================
 *  Task – Zamanlanmış tek bir görev
 * ---------------------------------------------------------------------
 *  Sıklık zincirleme belirlenir:
 *
 *      ->everyMinute()          her dakika
 *      ->everyMinutes(5)        5 dakikada bir
 *      ->hourly()               saat başı
 *      ->daily('04:00')         her gün 04:00'te
 *      ->weekly(1, '03:30')     her pazartesi 03:30
 *      ->monthly(1, '02:00')    her ayın 1'inde 02:00
 *
 *  CRON SÖZDİZİMİ DESTEKLENMİYOR — bilerek. "*​/15 9-17 * * 1-5" gibi
 *  ifadeler güçlüdür ama okunması zordur ve bir ayrıştırıcı yazmayı
 *  gerektirir. Yukarıdaki metotlar gerçek projelerin %95'ini karşılar;
 *  gerisi için görevin içinde bir if yazmak daha anlaşılırdır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Schedule;

final class Task
{
    /** Saniye cinsinden aralık; 0 ise gün/saat kuralları geçerlidir. */
    private int $interval = 60;

    private ?string $atTime = null;

    /** 1 = pazartesi … 7 = pazar */
    private ?int $dayOfWeek = null;

    private ?int $dayOfMonth = null;

    private string $description = '';

    /** @var callable */
    private $callback;

    public function __construct(
        private readonly string $name,
        callable $callback,
    ) {
        $this->callback = $callback;
    }

    /* =================================================================
     *  SIKLIK
     * ============================================================== */

    public function everyMinute(): self
    {
        return $this->everyMinutes(1);
    }

    public function everyMinutes(int $minutes): self
    {
        $this->interval   = max(1, $minutes) * 60;
        $this->atTime     = null;
        $this->dayOfWeek  = null;
        $this->dayOfMonth = null;

        return $this;
    }

    public function hourly(): self
    {
        return $this->everyMinutes(60);
    }

    public function daily(string $at = '00:00'): self
    {
        $this->interval   = 0;
        $this->atTime     = $at;
        $this->dayOfWeek  = null;
        $this->dayOfMonth = null;

        return $this;
    }

    /** @param int $day 1 = pazartesi … 7 = pazar */
    public function weekly(int $day = 1, string $at = '00:00'): self
    {
        $this->daily($at);
        $this->dayOfWeek = max(1, min(7, $day));

        return $this;
    }

    public function monthly(int $day = 1, string $at = '00:00'): self
    {
        $this->daily($at);
        $this->dayOfMonth = max(1, min(28, $day)); // 28: her ayda vardır

        return $this;
    }

    public function describe(string $text): self
    {
        $this->description = $text;

        return $this;
    }

    /* =================================================================
     *  OKUYUCULAR
     * ============================================================== */

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    /** İnsan okunur sıklık ("her 5 dakikada", "her gün 04:00"). */
    public function frequency(): string
    {
        if ($this->interval > 0) {
            $minutes = (int) ($this->interval / 60);

            return $minutes === 1 ? 'her dakika' : 'her ' . $minutes . ' dakikada';
        }

        if ($this->dayOfMonth !== null) {
            return 'her ayın ' . $this->dayOfMonth . '\'inde ' . $this->atTime;
        }

        if ($this->dayOfWeek !== null) {
            $gunler = [1 => 'pazartesi', 'salı', 'çarşamba', 'perşembe', 'cuma', 'cumartesi', 'pazar'];

            return 'her ' . ($gunler[$this->dayOfWeek] ?? '?') . ' ' . $this->atTime;
        }

        return 'her gün ' . $this->atTime;
    }

    /* =================================================================
     *  ZAMANI GELDİ Mİ?
     * ============================================================== */

    public function isDue(int $now, int $lastRun): bool
    {
        // Aralıklı görevler: son çalışmanın üzerinden yeterince geçti mi?
        if ($this->interval > 0) {
            /* 5 saniyelik tolerans: cron tam dakikada değil, birkaç
             * saniye gecikmeli tetiklenir. Tolerans olmasa "her 5
             * dakikada" görevi 10 dakikada bir çalışırdı. */
            return ($now - $lastRun) >= ($this->interval - 5);
        }

        if ($this->dayOfMonth !== null && (int) date('j', $now) !== $this->dayOfMonth) {
            return false;
        }

        if ($this->dayOfWeek !== null && (int) date('N', $now) !== $this->dayOfWeek) {
            return false;
        }

        [$hour, $minute] = array_pad(explode(':', (string) $this->atTime, 2), 2, '0');

        $scheduled = mktime((int) $hour, (int) $minute, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));

        if ($scheduled === false || $now < $scheduled) {
            return false;
        }

        // Bugünkü planlanan saatten sonra ve bugün henüz çalışmadıysa.
        return $lastRun < $scheduled;
    }

    public function run(): void
    {
        ($this->callback)();
    }
}
