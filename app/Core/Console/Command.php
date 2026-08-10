<?php
/**
 * =====================================================================
 *  Command – Bütün konsol komutlarının atası
 * ---------------------------------------------------------------------
 *  YENİ KOMUT NASIL YAZILIR?
 *
 *      final class TemizleCommand extends Command
 *      {
 *          public function name(): string        { return 'cache:clear'; }
 *          public function description(): string { return 'Önbelleği temizler.'; }
 *
 *          public function handle(): int
 *          {
 *              $this->out->success('Temizlendi.');
 *
 *              return self::BASARILI;
 *          }
 *      }
 *
 *  Sonra Kernel::commands() listesine ekleyin.
 *
 *  ÇIKIŞ KODU ÖNEMLİDİR: 0 başarı, 0'dan farklı hata demektir. Cron
 *  ve CI bu koda bakar; "php cy migrate || bildir" gibi zincirler
 *  ancak doğru kod döndürürseniz çalışır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Database;
use PDO;

abstract class Command
{
    public const BASARILI = 0;
    public const HATA     = 1;

    protected Input $input;
    protected Output $out;

    abstract public function name(): string;

    abstract public function description(): string;

    abstract public function handle(): int;

    /** Uzun açıklama / kullanım örneği (php cy yardim <komut>). */
    public function help(): string
    {
        return '';
    }

    public function setContext(Input $input, Output $out): void
    {
        $this->input = $input;
        $this->out   = $out;
    }

    /* =================================================================
     *  YARDIMCILAR
     * ============================================================== */

    /**
     * Veritabanı bağlantısı.
     *
     * Bağlantı kurulamazsa anlaşılır bir mesaj basıp komutu
     * sonlandırırız; terminalde yığın izi görmek kimseye yardımcı
     * olmaz — çoğu zaman .env'de yanlış bir parola vardır.
     */
    protected function db(): PDO
    {
        try {
            return Database::connection();
        } catch (\Throwable $e) {
            $this->out->error('Veritabanına bağlanılamadı: ' . $e->getMessage());
            $this->out->muted('  .env dosyasındaki DB_HOST / DB_NAME / DB_USER / DB_PASS değerlerini kontrol edin.');

            exit(self::HATA);
        }
    }

    /**
     * Yıkıcı işlemler için onay kapısı.
     *
     * Yayın ortamında SORU SORULMAZ, doğrudan "--force" istenir:
     * bir sunucuda yanlışlıkla "e" tuşuna basmak veritabanını
     * silmek için yeterli olmamalıdır.
     */
    protected function confirmDestructive(string $question): bool
    {
        if ($this->input->hasOption('force')) {
            return true;
        }

        if (\App\Core\Config::isProduction()) {
            $this->out->error('Bu komut yayın ortamında (APP_ENV=production) onay istemez.');
            $this->out->muted('  Gerçekten istiyorsanız --force ekleyin.');

            /* Sıfırdan farklı kodla çıkıyoruz: "çalıştırmayı REDDETTİM"
             * ile "kullanıcı hayır dedi" farklı şeylerdir. Cron ve CI
             * bu ayrımı yalnızca çıkış kodundan anlayabilir. */
            exit(self::HATA);
        }

        return $this->out->confirm($question, false);
    }
}
