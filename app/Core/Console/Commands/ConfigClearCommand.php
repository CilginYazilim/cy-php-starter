<?php
/**
 * =====================================================================
 *  php cy config:clear – Yapılandırma önbelleğini siler
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;

final class ConfigClearCommand extends Command
{
    public function name(): string
    {
        return 'config:clear';
    }

    public function description(): string
    {
        return 'Yapılandırma önbelleğini siler.';
    }

    public function handle(): int
    {
        if (Config::clearCache()) {
            $this->out->success('Yapılandırma önbelleği temizlendi.');

            return self::BASARILI;
        }

        $this->out->error('Önbellek dosyası silinemedi (izinleri kontrol edin).');

        return self::HATA;
    }
}
