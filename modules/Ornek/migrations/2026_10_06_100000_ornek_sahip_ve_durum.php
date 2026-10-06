<?php
/**
 * =====================================================================
 *  MIGRATION: ornek → sahip, durum, açıklama (Ornek modülü, 1.1.0)
 * ---------------------------------------------------------------------
 *  RBAC örneği için her kaydın bir SAHİBİ (kullanici_id) ve bir
 *  DURUMU (taslak / yayinda) olur: editör yalnızca kendi kaydını
 *  yönetir, üye taslakları hiç görmez.
 *
 *  kullanici_id için YABANCI ANAHTAR YOK, bilerek: kurulum sihirbazı
 *  "kullanicilar" tablosuna yabancı anahtar veren tabloları görünce
 *  aynı veritabanına yeniden kurulumu reddeder. Sahibi silinen kayıt
 *  "silinmiş kullanıcı" olarak listelenir.
 *
 *  Sütun varsa eklemez: modül tablosu yeniden kurulumda yerinde kalır,
 *  migration kaydı ise sıfırlanır — bu adım iki kez çalışabilir.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        $sutunlar = [
            'aciklama'     => "ADD COLUMN `aciklama` VARCHAR(255) NOT NULL DEFAULT '' AFTER `baslik`",
            'durum'        => "ADD COLUMN `durum` VARCHAR(20) NOT NULL DEFAULT 'yayinda' AFTER `aciklama`",
            'kullanici_id' => 'ADD COLUMN `kullanici_id` INT UNSIGNED NULL AFTER `durum`',
            'updated_at'   => 'ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ($sutunlar as $sutun => $sql) {
            if (!$this->columnExists('ornek', $sutun)) {
                $this->execute('ALTER TABLE `ornek` ' . $sql);
            }
        }

        if (!$this->indexExists('ornek', 'ornek_kullanici')) {
            $this->execute('ALTER TABLE `ornek` ADD INDEX `ornek_kullanici` (`kullanici_id`)');
        }
    }

    public function down(): void
    {
        if ($this->indexExists('ornek', 'ornek_kullanici')) {
            $this->execute('ALTER TABLE `ornek` DROP INDEX `ornek_kullanici`');
        }

        foreach (['updated_at', 'kullanici_id', 'durum', 'aciklama'] as $sutun) {
            if ($this->columnExists('ornek', $sutun)) {
                $this->execute('ALTER TABLE `ornek` DROP COLUMN `' . $sutun . '`');
            }
        }
    }
};
