<?php
/**
 * =====================================================================
 *  MIGRATION: onbellek tablosu
 * ---------------------------------------------------------------------
 *  Yalnızca CACHE_DRIVER=veritabani seçilirse kullanılır. Tabloyu her
 *  kurulumda oluşturuyoruz çünkü sürücüyü sonradan değiştirmek tek
 *  satırlık bir .env düzenlemesi olmalı — o an migration aramamalısınız.
 *
 *  NEDEN MEDIUMBLOB? serialize() çıktısı ikili veri içerebilir; TEXT
 *  sütunları karakter kümesi dönüşümü yaparak onu sessizce bozar.
 *
 *  NEDEN VARCHAR(191)? utf8mb4'te her karakter 4 bayt sayılır ve
 *  MySQL'in eski sürümlerinde indeks sınırı 767 bayttır
 *  (767 / 4 = 191). Daha uzun anahtarlar sürücü tarafından hash'lenir.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `onbellek` (
                `anahtar`        VARCHAR(191) NOT NULL,
                `deger`          MEDIUMBLOB NOT NULL,
                `son_gecerlilik` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = süresiz',
                PRIMARY KEY (`anahtar`),
                KEY `idx_onbellek_son_gecerlilik` (`son_gecerlilik`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci"
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `onbellek`');
    }
};
