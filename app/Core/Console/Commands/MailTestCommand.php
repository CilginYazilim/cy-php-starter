<?php
/**
 * =====================================================================
 *  php cy mail:test <adres> – Posta ayarlarını sınar
 * ---------------------------------------------------------------------
 *  Panelden de yapılabilir (Ayarlar → E-posta Sınama) ama sunucuya
 *  SSH ile bağlıyken ya da panel açılmıyorken terminal daha hızlıdır;
 *  hata metni de doğrudan SMTP sunucusundan geldiği gibi görünür.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;
use App\Core\Mail\Mailer;
use App\Core\Mail\Notifier;
use App\Core\Setting;
use Throwable;

final class MailTestCommand extends Command
{
    public function name(): string
    {
        return 'mail:test';
    }

    public function description(): string
    {
        return 'Posta ayarlarını sınar ve örnek mektup gönderir.';
    }

    public function help(): string
    {
        return "  php cy mail:test ali@ornek.com     Sınama mektubu gönderir\n"
             . "  php cy mail:test --baglanti        Yalnızca SMTP bağlantısını dener\n"
             . "\n"
             . "  Ayarlar veritabanındaki \"ayarlar\" tablosundan okunur\n"
             . "  (Panel → Site Ayarları → E-posta).";
    }

    public function handle(): int
    {
        Setting::load($this->db());

        $config = Mailer::config();

        $this->out->title('Posta yapılandırması');
        $this->out->table(['Ayar', 'Değer'], [
            ['Yöntem',   $this->driverLabel($config['surucu'])],
            ['Gönderen', trim($config['gonderenAd'] . ' <' . $config['gonderen'] . '>')],
            ['Sunucu',   $config['surucu'] === 'smtp' ? $config['host'] . ':' . $config['port'] : '—'],
            ['Şifreleme', $config['surucu'] === 'smtp' ? strtoupper($config['guvenlik']) : '—'],
        ]);
        $this->out->blank();

        if ($this->input->hasOption('baglanti')) {
            return $this->verifyOnly();
        }

        $to = trim($this->input->argument(0));

        if ($to === '') {
            $this->out->error('Bir e-posta adresi verin.');
            $this->out->muted('  Kullanım: php cy mail:test ali@ornek.com');

            return self::HATA;
        }

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->out->error('Geçersiz e-posta adresi: ' . $to);

            return self::HATA;
        }

        try {
            Notifier::sinama($to);
        } catch (Throwable $e) {
            $this->out->error('Gönderilemedi: ' . $e->getMessage());

            return self::HATA;
        }

        if ($config['surucu'] === 'kayit') {
            $this->out->success('Mektup storage/mail/ klasörüne .eml olarak yazıldı.');
            $this->out->muted('  Gerçekten göndermek için gönderim yöntemini SMTP yapın.');

            return self::BASARILI;
        }

        $this->out->success('Sınama mektubu ' . $to . ' adresine gönderildi.');

        return self::BASARILI;
    }

    private function verifyOnly(): int
    {
        try {
            Mailer::verify();
        } catch (Throwable $e) {
            $this->out->error('Bağlantı kurulamadı: ' . $e->getMessage());

            return self::HATA;
        }

        $this->out->success('Bağlantı başarılı: ' . Mailer::transport()->name() . ' hazır.');

        return self::BASARILI;
    }

    private function driverLabel(string $driver): string
    {
        return match ($driver) {
            'smtp'  => 'SMTP',
            'php'   => 'PHP mail()',
            default => 'Kayıt (diske yazar)',
        };
    }
}
