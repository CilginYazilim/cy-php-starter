<?php
/**
 * =====================================================================
 *  MIGRATION: e-posta kuyruğu için "gonderiliyor" durumu
 * ---------------------------------------------------------------------
 *  Kuyruk eskiden satırları KİLİTLEMEDEN okuyordu. Panel ("Kuyruğu
 *  İşle"), cron (schedule:run) ve "php cy mail:work" aynı anda
 *  çalışınca hepsi aynı "kuyrukta" satırlarını alıyor; bir duyuru aynı
 *  kişiye iki-üç kez gidiyordu. Mailer::send() de kaydı gönderimden
 *  ÖNCE "kuyrukta" olarak yazdığı için, o milisaniyelerde çalışan bir
 *  işçi aynı mektubu ikinci kez gönderebiliyordu.
 *
 *  Artık bir satır gönderilmeden önce ATOMİK bir UPDATE ile
 *  "gonderiliyor" durumuna alınır; rowCount() 1 dönen tek süreç onu
 *  gönderir. "ayrildi_at", yarıda kalan (çöken) gönderimleri bulmak
 *  içindir (bkz. MailRepository::releaseStuck).
 *
 *  SKIP LOCKED neden kullanılmadı? MySQL 8.0 / MariaDB 10.6 öncesinde
 *  yoktur; paylaşımlı sunucuların önemli bir kısmı hâlâ daha eski.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        $this->execute(
            "ALTER TABLE `mail_kayitlari`
               MODIFY `durum` ENUM('kuyrukta','gonderiliyor','gonderildi','basarisiz')
                      NOT NULL DEFAULT 'kuyrukta'"
        );

        if (!$this->columnExists('mail_kayitlari', 'ayrildi_at')) {
            $this->execute(
                'ALTER TABLE `mail_kayitlari`
                   ADD COLUMN `ayrildi_at` DATETIME NULL DEFAULT NULL AFTER `gonderildi_at`'
            );
        }
    }

    public function down(): void
    {
        $this->execute("UPDATE `mail_kayitlari` SET durum = 'kuyrukta' WHERE durum = 'gonderiliyor'");

        $this->execute(
            "ALTER TABLE `mail_kayitlari`
               MODIFY `durum` ENUM('kuyrukta','gonderildi','basarisiz')
                      NOT NULL DEFAULT 'kuyrukta'"
        );

        if ($this->columnExists('mail_kayitlari', 'ayrildi_at')) {
            $this->execute('ALTER TABLE `mail_kayitlari` DROP COLUMN `ayrildi_at`');
        }
    }
};
