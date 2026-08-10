<?php
/**
 * =====================================================================
 *  php cy mail:work – Bekleyen mektupları gönderir (cron için)
 * ---------------------------------------------------------------------
 *  Panelden toplu gönderim yaparken tarayıcı kuyruğu kendisi işler.
 *  Ama sayfayı kapatan bir kullanıcının yarım bıraktığı kuyruk ya da
 *  gece çalışan bir duyuru için sunucu tarafında bir işçi gerekir:
 *
 *      * * * * *  cd /var/www/site && php cy mail:work --limit=20
 *
 *  Her çalıştırmada EN FAZLA --limit kadar mektup gönderir; cron'un
 *  bir sonraki turu kalanı alır. Böylece tek bir çalıştırma PHP'nin
 *  zaman aşımına takılmaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;
use App\Core\Mail\Mailer;
use App\Core\Setting;
use App\Repositories\MailRepository;

final class MailWorkCommand extends Command
{
    public function name(): string
    {
        return 'mail:work';
    }

    public function description(): string
    {
        return 'Kuyrukta bekleyen e-postaları gönderir.';
    }

    public function help(): string
    {
        return "  php cy mail:work                Varsayılan parti (20)\n"
             . "  php cy mail:work --limit=50     Tek turda en fazla 50 mektup\n"
             . "  php cy mail:work --drain        Kuyruk boşalana kadar devam et\n"
             . "\n"
             . "  Cron örneği (her dakika):\n"
             . "      * * * * * cd /yol/site && php cy mail:work --limit=20";
    }

    public function handle(): int
    {
        // Posta ayarları veritabanındaki "ayarlar" tablosundadır;
        // web isteğinde index.php yükler, CLI'da biz yüklemeliyiz.
        Setting::load($this->db());

        $limit = max(1, min($this->input->intOption('limit', 20), 100));

        $toplamGonderilen = 0;
        $toplamBasarisiz  = 0;
        $tur              = 0;

        do {
            $sonuc = Mailer::processQueue($limit);

            $toplamGonderilen += $sonuc['gonderildi'];
            $toplamBasarisiz  += $sonuc['basarisiz'];
            $tur++;

            $islenen = $sonuc['gonderildi'] + $sonuc['basarisiz'];

            /* GÜVENLİK SUBABI: bir turda hiçbir şey işlenmediyse
             * kuyruk ilerlemiyor demektir; --drain sonsuz döngüye
             * dönüşmesin. */
            $devam = $this->input->hasOption('drain') && $sonuc['kalan'] > 0 && $islenen > 0;
        } while ($devam && $tur < 500);

        if ($toplamGonderilen === 0 && $toplamBasarisiz === 0) {
            $this->out->info('Kuyrukta bekleyen mektup yok.');

            return self::BASARILI;
        }

        $this->out->success($toplamGonderilen . ' mektup gönderildi.');

        if ($toplamBasarisiz > 0) {
            $this->out->warn($toplamBasarisiz . ' mektup gönderilemedi. Ayrıntı: storage/logs/mail-*.log');
        }

        /* Kalanı SAYMAK için processQueue çağırmıyoruz: o metot
         * en az bir mektup daha gönderirdi. */
        $kalan = (new MailRepository($this->db()))->countPending();

        if ($kalan > 0) {
            $this->out->muted('  Kuyrukta ' . $kalan . ' mektup kaldı.');
        }

        // Başarısız gönderim cron'a "sorun var" demelidir.
        return $toplamBasarisiz > 0 ? self::HATA : self::BASARILI;
    }
}
