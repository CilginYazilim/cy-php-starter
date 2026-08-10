<?php
/**
 * =====================================================================
 *  MIGRATION: api_anahtarlari
 * ---------------------------------------------------------------------
 *  Anahtarın kendisi DÜZ METİN OLARAK SAKLANMAZ; yalnızca SHA-256
 *  özeti tutulur. "onek" sütunu hızlı arama içindir: hash'lenmiş bir
 *  sütunda WHERE yapılamaz, önce ön ekle aday bulunur sonra özet
 *  doğrulanır.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `api_anahtarlari` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `kullanici_id`   INT UNSIGNED NOT NULL,
                `ad`             VARCHAR(100) NOT NULL,
                `onek`           VARCHAR(16) NOT NULL,
                `ozet`           CHAR(64) NOT NULL,
                `son_gecerlilik` DATETIME NULL COMMENT 'NULL = süresiz',
                `son_kullanim`   DATETIME NULL,
                `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_api_onek` (`onek`),
                KEY `idx_api_kullanici` (`kullanici_id`),
                CONSTRAINT `fk_api_kullanici` FOREIGN KEY (`kullanici_id`)
                    REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci"
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `api_anahtarlari`');
    }
};
