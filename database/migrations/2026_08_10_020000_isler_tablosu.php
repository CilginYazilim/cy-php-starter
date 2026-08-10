<?php
/**
 * =====================================================================
 *  MIGRATION: isler (kuyruk) tablosu
 * ---------------------------------------------------------------------
 *  Arka planda çalışacak işler burada bekler. Tamamlanan iş SİLİNİR;
 *  yalnızca başarısız olanlar durum='basarisiz' ile kalır, böylece
 *  tablo şişmez ama hiçbir hata kaybolmaz.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `isler` (
                `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `kuyruk`     VARCHAR(60) NOT NULL DEFAULT 'varsayilan',
                `sinif`      VARCHAR(191) NOT NULL,
                `veri`       JSON NULL,
                `durum`      ENUM('bekliyor','calisiyor','basarisiz') NOT NULL DEFAULT 'bekliyor',
                `deneme`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `max_deneme` TINYINT UNSIGNED NOT NULL DEFAULT 3,
                `hata`       VARCHAR(500) NULL,
                `hazir_at`   DATETIME NOT NULL,
                `ayrildi_at` DATETIME NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_isler_sira` (`durum`, `hazir_at`, `id`),
                KEY `idx_isler_kuyruk` (`kuyruk`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci"
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `isler`');
    }
};
