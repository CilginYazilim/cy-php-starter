<?php
/**
 * =====================================================================
 *  Output – Terminale yazar
 * ---------------------------------------------------------------------
 *  Renkler ANSI kaçış dizileriyle verilir. Windows 10'un modern
 *  konsolu (Windows Terminal, PowerShell 5.1+) bunları destekler; eski
 *  cmd.exe desteklemez ve ekranda "[32m" gibi çöp görünür. Bu yüzden
 *  renk YALNIZCA gerçek bir terminale yazarken açılır:
 *
 *      php cy migrate            → renkli
 *      php cy migrate > log.txt  → renksiz (dosyada çöp kalmaz)
 *      php cy migrate --no-color → renksiz (zorla)
 *
 *  Hatalar STDERR'e gider: "php cy migrate:status | grep bekliyor"
 *  gibi bir boru hattında hata mesajı çıktıya karışmasın.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console;

final class Output
{
    private const COLORS = [
        'siyah'   => '0;30',
        'kirmizi' => '0;31',
        'yesil'   => '0;32',
        'sari'    => '0;33',
        'mavi'    => '0;34',
        'mor'     => '0;35',
        'turkuaz' => '0;36',
        'gri'     => '1;30',
        'beyaz'   => '1;37',
    ];

    public function __construct(private bool $colors = true)
    {
        if ($colors) {
            $this->colors = self::terminalSupportsColor();
        }
    }

    private static function terminalSupportsColor(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        // Çıktı bir dosyaya/boruya yönlendirilmişse renk KOYMA.
        if (function_exists('stream_isatty') && defined('STDOUT')) {
            return @stream_isatty(STDOUT);
        }

        return false;
    }

    public function withoutColors(): self
    {
        $this->colors = false;

        return $this;
    }

    /* =================================================================
     *  TEMEL YAZMA
     * ============================================================== */

    public function write(string $text = '', string $color = ''): void
    {
        fwrite(STDOUT, $this->paint($text, $color));
    }

    public function line(string $text = '', string $color = ''): void
    {
        $this->write($text . PHP_EOL, $color);
    }

    public function blank(int $count = 1): void
    {
        $this->write(str_repeat(PHP_EOL, max(1, $count)));
    }

    /* =================================================================
     *  ANLAMLI KISAYOLLAR
     * ============================================================== */

    public function success(string $text): void
    {
        $this->line('  ✔ ' . $text, 'yesil');
    }

    public function error(string $text): void
    {
        fwrite(STDERR, $this->paint('  ✖ ' . $text . PHP_EOL, 'kirmizi'));
    }

    public function warn(string $text): void
    {
        $this->line('  ! ' . $text, 'sari');
    }

    public function info(string $text): void
    {
        $this->line('  · ' . $text, 'turkuaz');
    }

    public function muted(string $text): void
    {
        $this->line($text, 'gri');
    }

    public function title(string $text): void
    {
        $this->blank();
        $this->line('  ' . $text, 'beyaz');
        $this->line('  ' . str_repeat('─', max(4, mb_strlen($text))), 'gri');
    }

    /* =================================================================
     *  TABLO
     * ============================================================== */

    /**
     * Basit, hizalanmış tablo. Sütun genişlikleri içeriğe göre
     * hesaplanır; mb_strlen kullanılır ki Türkçe karakterler
     * hizalamayı bozmasın.
     *
     * @param array<int,string>            $headers
     * @param array<int,array<int,string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $widths = [];

        foreach ($headers as $index => $header) {
            $widths[$index] = mb_strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen((string) $cell));
            }
        }

        $this->line('  ' . $this->row($headers, $widths), 'beyaz');
        $this->line('  ' . implode('──', array_map(
            static fn (int $width): string => str_repeat('─', $width),
            $widths
        )), 'gri');

        foreach ($rows as $row) {
            $this->line('  ' . $this->row($row, $widths));
        }
    }

    /**
     * @param array<int,string> $cells
     * @param array<int,int>    $widths
     */
    private function row(array $cells, array $widths): string
    {
        $parts = [];

        foreach ($widths as $index => $width) {
            $cell    = (string) ($cells[$index] ?? '');
            $padding = max(0, $width - mb_strlen($cell));

            $parts[] = $cell . str_repeat(' ', $padding);
        }

        return rtrim(implode('  ', $parts));
    }

    /* =================================================================
     *  ONAY
     * ============================================================== */

    /**
     * Kullanıcıya evet/hayır sorar.
     *
     * Girdi bir terminalden gelmiyorsa (cron, CI) soru sorulamaz;
     * o durumda $default döner. Yıkıcı komutlar bu yüzden ayrıca
     * "--force" ister — sessiz bir ortamda kazara "evet" sayılmasın.
     */
    public function confirm(string $question, bool $default = false): bool
    {
        if (!defined('STDIN') || (function_exists('stream_isatty') && !@stream_isatty(STDIN))) {
            return $default;
        }

        $this->write('  ' . $question . ' [' . ($default ? 'E/h' : 'e/H') . '] ', 'sari');

        $answer = strtolower(trim((string) fgets(STDIN)));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['e', 'evet', 'y', 'yes'], true);
    }

    private function paint(string $text, string $color): string
    {
        if (!$this->colors || $color === '' || !isset(self::COLORS[$color])) {
            return $text;
        }

        return "\033[" . self::COLORS[$color] . 'm' . $text . "\033[0m";
    }
}
