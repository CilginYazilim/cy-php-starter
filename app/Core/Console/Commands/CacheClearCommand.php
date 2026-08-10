<?php
/**
 * =====================================================================
 *  php cy cache:clear – Önbelleği temizler
 * ---------------------------------------------------------------------
 *  --expired ile yalnızca süresi geçmiş kayıtları siler; bunu cron'a
 *  koyun. Tam temizlik ise dağıtım (deploy) sonrası içindir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Cache\Cache;
use App\Core\Console\Command;

final class CacheClearCommand extends Command
{
    public function name(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Uygulama önbelleğini temizler.';
    }

    public function help(): string
    {
        return "  php cy cache:clear              Önbelleğin TAMAMINI siler\n"
             . "  php cy cache:clear --expired    Yalnızca süresi geçmişleri siler\n"
             . "  php cy cache:clear --store=veritabani\n"
             . "\n"
             . "  Yapılandırma önbelleği AYRIDIR: php cy config:clear\n"
             . "\n"
             . "  Cron örneği (gecede bir süresi geçmişleri temizle):\n"
             . "      0 4 * * * cd /yol/site && php cy cache:clear --expired";
    }

    public function handle(): int
    {
        $name  = $this->input->option('store') ?: null;
        $store = Cache::store($name);

        if ($this->input->hasOption('expired')) {
            $deleted = $store->purgeExpired();

            $this->out->success($deleted . ' süresi geçmiş kayıt silindi. (sürücü: ' . $store->name() . ')');

            return self::BASARILI;
        }

        if (!$store->flush()) {
            $this->out->error('Önbellek temizlenemedi. (sürücü: ' . $store->name() . ')');

            return self::HATA;
        }

        $this->out->success('Önbellek temizlendi. (sürücü: ' . $store->name() . ')');

        return self::BASARILI;
    }
}
