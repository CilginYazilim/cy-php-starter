<?php
/**
 * =====================================================================
 *  php cy demo:temizle – Örnek veriyi kaldırır
 * ---------------------------------------------------------------------
 *  "Örnek veriyle kur" seçip sonra gerçek projeye geçenler için: örnek
 *  hesapları, demo mesajlarını ve e-postalarını, demo sayfalarını,
 *  Örnek Modül kayıtlarını siler; ana sayfa vitrini nötr ayarlara
 *  döner. Kendi hesaplarınıza, yazdığınız sayfalara ve gelen gerçek
 *  mesajlara DOKUNMAZ.
 *
 *  Panel karşılığı: Sistem Bilgisi → "Demo verisini kaldır".
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;
use App\Core\Demo;
use App\Core\DemoData;
use App\Core\Log\Logger;

final class DemoRemoveCommand extends Command
{
    public function name(): string
    {
        return 'demo:temizle';
    }

    public function description(): string
    {
        return 'Örnek veriyi (demo hesaplar, mesajlar, sayfalar, vitrin) kaldırır; gerçek veriye dokunmaz.';
    }

    public function help(): string
    {
        return "  php cy demo:temizle\n"
             . "\n"
             . "  Demo modu açıkken (APP_DEMO=true) reddeder: önce .env'de kapatın.\n"
             . "  Eski bir öneri olan DELETE … LIKE '%@ornek.com' yerine bunu kullanın;\n"
             . "  o sorgu @ornek.com adresli gerçek bir yöneticiyi de silebilirdi.";
    }

    public function handle(): int
    {
        if (Demo::enabled()) {
            $this->out->error('Demo modu açık. Önce .env dosyasında APP_DEMO=false yapın.');

            return self::HATA;
        }

        if (!DemoData::leavesAdmin($this->db())) {
            $this->out->error('Örnek veri kaldırılırsa panele girebilecek yönetici kalmaz. Önce kendinize etkin bir yönetici hesabı açın.');

            return self::HATA;
        }

        if (!$this->confirmDestructive('Örnek veri kaldırılacak. Devam edilsin mi?')) {
            $this->out->info('Vazgeçildi.');

            return self::BASARILI;
        }

        $sonuc = (new DemoData($this->db()))->remove();

        Logger::info('Demo verisi kaldırıldı', $sonuc, 'app');
        $this->out->success(sprintf(
            '%d hesap, %d mesaj, %d e-posta kaydı, %d sayfa, %d Örnek Modül kaydı kaldırıldı.',
            $sonuc['hesap'], $sonuc['mesaj'], $sonuc['eposta'], $sonuc['sayfa'], $sonuc['kayit']
        ));

        return self::BASARILI;
    }
}
