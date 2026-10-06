<?php
/**
 * =====================================================================
 *  php cy demo:reset – Canlı demoyu baştan kurar
 * ---------------------------------------------------------------------
 *  Herkese açık bir demoda ziyaretçiler sayfa ekler, mesaj siler, Örnek
 *  Modül'e kayıt yazar. Bu komut hepsini geri alır ve örnek veriyi
 *  (App\Core\DemoData) yeniden kurar. Kurulumdaki gerçek yönetici
 *  hesabına dokunmaz.
 *
 *  APP_DEMO=true iken zamanlayıcı her 3 saatte bir çalıştırır (bkz.
 *  routes/schedule.php). Demo modu kapalı bir sitede yanlışlıkla
 *  çalışmasın diye --zorla olmadan reddeder.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;
use App\Core\Demo;
use App\Core\DemoData;
use App\Core\Log\Logger;
use App\Core\Setting;

final class DemoResetCommand extends Command
{
    public function name(): string
    {
        return 'demo:reset';
    }

    public function description(): string
    {
        return 'Canlı demoyu sıfırlar: ziyaretçi değişikliklerini siler, örnek veriyi yeniden kurar.';
    }

    public function help(): string
    {
        return "  php cy demo:reset           Yalnızca APP_DEMO=true iken çalışır\n"
             . "  php cy demo:reset --zorla   Demo modu kapalıyken de (geliştirme)\n"
             . "\n"
             . "  Silinenler: bütün mesajlar ve e-posta kayıtları, giriş denemeleri,\n"
             . "  ziyaretçinin eklediği sayfalar, son sıfırlamadan sonra açılan üye\n"
             . "  hesapları, Örnek Modül kayıtları, kullanılmayan yüklemeler.\n"
             . "  Kalanlar: kurulumdaki yönetici hesabı, korunan sayfalar, ayarlar.";
    }

    public function handle(): int
    {
        if (!Demo::enabled() && !$this->input->hasOption('zorla')) {
            $this->out->error('Demo modu kapalı (APP_DEMO=false). Gerçek bir sitede çalıştırmak verileri siler; yine de istiyorsanız --zorla ekleyin.');

            return self::HATA;
        }

        $db   = $this->db();
        Setting::load($db);

        $demo = new DemoData($db, fn (string $mesaj) => $this->out->muted('  ' . $mesaj));
        $demo->reset();

        foreach ($demo->seedModules() as $hata) {
            $this->out->error('  ' . $hata);
        }

        Logger::info('Demo sıfırlandı', [], 'app');
        $this->out->success('Demo sıfırlandı.');

        return self::BASARILI;
    }
}
