<?php
/**
 * =====================================================================
 *  Input – Komut satırı argümanlarını okur
 * ---------------------------------------------------------------------
 *      php cy migrate:rollback --step=3 --force
 *                              └─ seçenek ─┘  └ bayrak ┘
 *
 *      php cy make:controller Urun
 *                             └ argüman ┘
 *
 *  DESTEKLENEN BİÇİMLER
 *      --anahtar=deger      seçenek
 *      --anahtar            bayrak (değeri "1" sayılır)
 *      -f                   kısa bayrak
 *      serbest metin        sıralı argüman
 *
 *  Bilerek sade tutulmuştur: "-abc" birleşik kısa bayrakları ya da
 *  "--anahtar deger" (boşluklu) biçimi desteklenmez. İkisi de bu
 *  ölçekte kazandırdığından fazla karışıklık üretir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console;

final class Input
{
    /** @var array<int,string> Komut adından SONRAKİ sıralı argümanlar */
    private array $arguments = [];

    /** @var array<string,string> */
    private array $options = [];

    private string $command = '';

    /** @param array<int,string> $argv Ham $argv (ilk eleman betik adıdır) */
    public function __construct(array $argv)
    {
        // İlk eleman betiğin kendi adıdır ("cy"), atlanır.
        array_shift($argv);

        foreach ($argv as $token) {
            if (str_starts_with($token, '--')) {
                $this->addLongOption(substr($token, 2));
                continue;
            }

            if (str_starts_with($token, '-') && strlen($token) > 1) {
                $this->options[substr($token, 1)] = '1';
                continue;
            }

            if ($this->command === '') {
                $this->command = $token;
                continue;
            }

            $this->arguments[] = $token;
        }
    }

    private function addLongOption(string $token): void
    {
        if ($token === '') {
            return;
        }

        if (str_contains($token, '=')) {
            [$key, $value] = explode('=', $token, 2);

            $this->options[trim($key)] = $value;

            return;
        }

        $this->options[$token] = '1';
    }

    public function command(): string
    {
        return $this->command;
    }

    /** Sıralı argüman: argument(0) ilk serbest metindir. */
    public function argument(int $index, string $default = ''): string
    {
        return $this->arguments[$index] ?? $default;
    }

    /** @return array<int,string> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function option(string $key, string $default = ''): string
    {
        return $this->options[$key] ?? $default;
    }

    public function intOption(string $key, int $default = 0): int
    {
        $raw = $this->option($key, (string) $default);

        return is_numeric($raw) ? (int) $raw : $default;
    }

    /** Bayrak verilmiş mi? (--force) */
    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    /** @return array<string,string> */
    public function options(): array
    {
        return $this->options;
    }
}
