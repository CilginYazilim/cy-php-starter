<?php
/**
 * =====================================================================
 *  MIGRATION: ornek (Ornek modülü)
 * ---------------------------------------------------------------------
 *  Modül migration'ları "php cy migrate" ile çalışır — ama YALNIZCA
 *  modül AÇIKSA. Kapalı bir modülün tabloları oluşturulmaz.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `ornek` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `baslik`     VARCHAR(150) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci"
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `ornek`');
    }
};
