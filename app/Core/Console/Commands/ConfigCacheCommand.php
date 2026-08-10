<?php
/**
 * =====================================================================
 *  php cy config:cache – Yapılandırmayı tek dosyaya derler
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\ErrorHandler;

final class ConfigCacheCommand extends Command
{
    public function name(): string
    {
        return 'config:cache';
    }

    public function description(): string
    {
        return 'config/ klasörünü tek bir önbellek dosyasına derler.';
    }

    public function help(): string
    {
        return "  php cy config:cache\n"
             . "\n"
             . "  Önbellek YALNIZCA APP_DEBUG=false iken kullanılır.\n"
             . "  .env veya config/ dosyalarını değiştirdiğinizde önbelleği\n"
             . "  TAZELEMEZSENİZ eski değerler kullanılmaya devam eder:\n"
             . "      php cy config:cache   (yeniden derle)\n"
             . "      php cy config:clear   (sil, dosyalardan okusun)";
    }

    public function handle(): int
    {
        $file = Config::cache();

        if ($file === '') {
            $this->out->error('Önbellek yazılamadı. storage/cache klasörünün yazılabilir olduğundan emin olun.');

            return self::HATA;
        }

        $this->out->success('Yapılandırma derlendi: ' . ErrorHandler::relative($file));

        if (Config::isDebug()) {
            $this->out->warn('APP_DEBUG=true olduğu için bu önbellek ŞU AN kullanılmayacak.');
            $this->out->muted('  Yayında APP_DEBUG=false yaptığınızda devreye girer.');
        }

        return self::BASARILI;
    }
}
