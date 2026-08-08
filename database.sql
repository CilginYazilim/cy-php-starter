-- ===============================================================
--  VERİTABANI ŞABLONU
--  cilginyazilim.com – PHP Başlangıç Şablonu
-- ---------------------------------------------------------------
--  ► YENİ PROJEDE:
--    1. Aşağıdaki "yeni_proje" adını değiştirin
--    2. system/config.php içindeki DB_NAME ile AYNI olmalı
--    3. items tablosunu kendi tablonuzla değiştirin
--
--  KURULUM:
--    Terminal   :  mysql -u root -p < database.sql
--    phpMyAdmin :  İçe Aktar > Dosya seç > database.sql > Başlat
-- ===============================================================

-- AUTO_INCREMENT sütuna 0 yazılırsa otomatik değer üretilmesin.
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
-- Tarihler Türkiye saat dilimine göre yorumlansın.
SET time_zone = "+03:00";
-- Bağlantı karakter setini utf8mb4 yap (Türkçe karakter + emoji).
SET NAMES utf8mb4;

-- ---------------------------------------------------------------
--  1) Veritabanı
-- ---------------------------------------------------------------
--  utf8mb4   : Her Unicode karakteri (ç, ğ, ş, emoji...) saklar.
--  unicode_ci: Sıralama dile duyarlı, büyük-küçük harf ayrımı yok.
CREATE DATABASE IF NOT EXISTS `yeni_proje`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `yeni_proje`;

-- ---------------------------------------------------------------
--  2) Örnek tablo
-- ---------------------------------------------------------------
--  DİKKAT: DROP satırı mevcut verilerinizi siler.
--  Canlıda çalıştırmayın.
DROP TABLE IF EXISTS `items`;

CREATE TABLE `items` (
  -- INT UNSIGNED   : Negatif olmayan tam sayı, ID için ideal.
  -- AUTO_INCREMENT : Her yeni kayıtta otomatik 1 artar.
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,

  `title`      VARCHAR(150) NOT NULL,
  `description` TEXT        NULL,

  -- Dosya yükleme kullanıyorsanız: sadece dosya ADI tutulur
  -- (örn. "a1b2c3.png"), tam yol değil. Böylece klasör yapısı
  -- değişirse veritabanına dokunmak gerekmez.
  `image`      VARCHAR(191) NOT NULL DEFAULT '',

  -- Kayıt oluşturulduğunda o anın tarih/saati otomatik yazılır.
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Kayıt her güncellendiğinde otomatik tazelenir.
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  -- İNDEKSLER: Arama ve sıralama yapılan sütunlara indeks eklemek,
  -- tablo büyüdükçe sorguları kat kat hızlandırır.
  KEY `idx_items_title`      (`title`),
  KEY `idx_items_created_at` (`created_at`)
)
-- InnoDB: Transaction ve foreign key destekler; MyISAM kullanmayın.
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
--  3) Örnek veriler
-- ---------------------------------------------------------------
INSERT INTO `items` (`title`, `description`) VALUES
('İlk örnek kayıt',   'Şablonun çalıştığını doğrulamak için eklendi.'),
('İkinci örnek kayıt', 'Bu satırları silip kendi verilerinizi ekleyin.');
