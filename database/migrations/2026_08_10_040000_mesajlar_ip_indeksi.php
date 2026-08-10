<?php
/**
 * =====================================================================
 *  MIGRATION: mesajlar.ip indeksi
 * ---------------------------------------------------------------------
 *  İletişim formu artık "bu IP son bir saatte kaç mesaj bıraktı?"
 *  diye soruyor (bkz. MessageRepository::countFromIp). İndekssiz bu
 *  sorgu tabloyu baştan sona tarar — üstelik tam da spam sırasında,
 *  yani sorgunun en sık çalıştığı anda.
 *
 *  (ip, created_at) sırası bilinçli: eşitlik koşulu olan sütun önce,
 *  aralık koşulu olan sonra gelir; MySQL ancak o zaman indeksin
 *  ikinci sütununu da kullanabilir.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        if ($this->indexExists('mesajlar', 'idx_mesajlar_ip')) {
            return;
        }

        $this->execute('ALTER TABLE `mesajlar` ADD KEY `idx_mesajlar_ip` (`ip`, `created_at`)');
    }

    public function down(): void
    {
        if (!$this->indexExists('mesajlar', 'idx_mesajlar_ip')) {
            return;
        }

        $this->execute('ALTER TABLE `mesajlar` DROP INDEX `idx_mesajlar_ip`');
    }
};
