-- ===============================================================
--  VERİTABANI ŞEMASI
--  cilginyazilim.com – PHP Başlangıç Şablonu
-- ---------------------------------------------------------------
--  TABLOLAR:
--    ayarlar         → Site geneli ayarlar (anahtar/değer, tipli)
--    kullanicilar    → Kullanıcı hesapları, roller, oturum bilgisi
--    mesajlar        → İletişim formundan gelen mesajlar
--    login_attempts  → Kaba kuvvet (brute force) saldırısı koruması
--
--  ► EN KOLAY KURULUM: Tarayıcıda "install/" adresini açın.
--    Sihirbaz bu dosyayı çalıştırır, ayarları formdan aldığı
--    değerlerle günceller ve yönetici hesabını oluşturur.
--
--  ► ELLE KURULUM:
--    Terminal   :  mysql -u root -p < kurulum/database.sql
--    phpMyAdmin :  İçe Aktar > Dosya seç > database.sql > Başlat
--    (Bu durumda yönetici hesabını kendiniz eklemeniz gerekir.)
-- ===============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";
SET NAMES utf8mb4;

-- ---------------------------------------------------------------
--  1) Veritabanı
-- ---------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `yeni_proje`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_turkish_ci;

USE `yeni_proje`;

-- Yabancı anahtar bağımlılığı olduğu için önce alt tablolar silinir.
DROP TABLE IF EXISTS `mesajlar`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `kullanicilar`;
DROP TABLE IF EXISTS `ayarlar`;


-- ===============================================================
--  2) AYARLAR TABLOSU
-- ===============================================================
CREATE TABLE `ayarlar` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `anahtar`        VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
  `deger`          TEXT NULL,
  `grup`           VARCHAR(50) NOT NULL DEFAULT 'genel',
  `tip`            ENUM('metin','uzun_metin','sayi','eposta','url','secim','onay','renk')
                   NOT NULL DEFAULT 'metin',
  `etiket`         VARCHAR(150) NOT NULL,
  `aciklama`       VARCHAR(255) NULL,
  `secenekler`     TEXT NULL,
  `sira`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `duzenlenebilir` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ayarlar_anahtar` (`anahtar`),
  KEY `idx_ayarlar_grup` (`grup`, `sira`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_turkish_ci;

INSERT INTO `ayarlar`
    (`anahtar`, `deger`, `grup`, `tip`, `etiket`, `aciklama`, `secenekler`, `sira`) VALUES

-- ---- GENEL ----
('site_adi',        'Yeni Proje', 'genel', 'metin',      'Site Adı',        'Tarayıcı sekmesinde ve başlıkta görünür.', NULL, 10),
('site_aciklama',   'Çılgın Yazılım örnek uygulaması', 'genel', 'uzun_metin', 'Site Açıklaması', 'Arama motorları için kısa tanıtım.', NULL, 20),
('site_slogan',     '',           'genel', 'metin',      'Slogan',          'Başlığın altında görünecek kısa cümle.', NULL, 30),
('site_hakkinda',   '',           'genel', 'uzun_metin', 'Hakkımızda Metni','"Hakkımızda" sayfasında görünür. Boş bırakırsanız örnek metin gösterilir.', NULL, 35),
('site_url',        '',           'genel', 'url',        'Site Adresi',     'Örn: https://ornek.com', NULL, 40),
('site_dil',        'tr',         'genel', 'secim',      'Dil',             'HTML lang özniteliği.', '["tr","en"]', 50),
('site_logo',       '',           'genel', 'metin',      'Logo Dosyası',    'upload/ klasöründeki dosya adı. Boşsa varsayılan logo kullanılır.', NULL, 60),

-- ---- İLETİŞİM ----
('iletisim_eposta', '',           'iletisim', 'eposta',  'İletişim E-postası', 'Formlardan gelen mesajlar buraya gider.', NULL, 10),
('iletisim_telefon','',           'iletisim', 'metin',   'Telefon',            NULL, NULL, 20),
('iletisim_adres',  '',           'iletisim', 'uzun_metin', 'Adres',           NULL, NULL, 30),
('iletisim_saatler','',           'iletisim', 'metin',   'Çalışma Saatleri',   'Örn: Hafta içi 09:00 – 18:00', NULL, 40),

-- ---- SOSYAL MEDYA ----
('sosyal_facebook',  '',          'sosyal', 'url', 'Facebook',  NULL, NULL, 10),
('sosyal_x',         '',          'sosyal', 'url', 'X (Twitter)', NULL, NULL, 20),
('sosyal_instagram', '',          'sosyal', 'url', 'Instagram', NULL, NULL, 30),
('sosyal_linkedin',  '',          'sosyal', 'url', 'LinkedIn',  NULL, NULL, 40),
('sosyal_youtube',   '',          'sosyal', 'url', 'YouTube',   NULL, NULL, 50),
('sosyal_github',    '',          'sosyal', 'url', 'GitHub',    NULL, NULL, 60),

-- ---- SEO ----
('seo_anahtar_kelimeler', '',     'seo', 'uzun_metin', 'Anahtar Kelimeler', 'Virgülle ayırın.', NULL, 10),
('seo_analytics',         '',     'seo', 'uzun_metin', 'Analytics Kodu',    'Google Analytics vb. izleme kodu.', NULL, 20),
('seo_indeksleme',        '1',    'seo', 'onay',       'Arama Motoru İndekslemesi', 'Kapatırsanız robots meta etiketi "noindex" olur.', NULL, 30),

-- ---- SİSTEM ----
('sistem_bakim_modu',     '0',    'sistem', 'onay',  'Bakım Modu',           'Açıkken siteyi sadece yöneticiler görebilir.', NULL, 10),
('sistem_kayit_acik',     '0',    'sistem', 'onay',  'Yeni Kayıtlara Açık',  'Ziyaretçiler kendi hesabını oluşturabilsin mi?', NULL, 20),
('sistem_iletisim_formu', '1',    'sistem', 'onay',  'İletişim Formu Açık',  'Kapatırsanız iletişim sayfasında sadece bilgiler görünür.', NULL, 25),
('sistem_sayfa_basina',   '10',   'sistem', 'sayi',  'Sayfa Başına Kayıt',   'Listelerde varsayılan sayfa boyutu.', NULL, 30),
('sistem_zaman_dilimi',   'Europe/Istanbul', 'sistem', 'metin', 'Zaman Dilimi', 'Örn: Europe/Istanbul', NULL, 40),
('sistem_tema_rengi',     '#0b5cb5', 'sistem', 'renk', 'Tema Rengi',          'Arayüzdeki ana marka rengi.', NULL, 50),
('sistem_surum',          '2.0.0', 'sistem', 'metin', 'Sürüm',                'Şablon sürümü.', NULL, 90);

UPDATE `ayarlar` SET `duzenlenebilir` = 0 WHERE `anahtar` = 'sistem_surum';


-- ===============================================================
--  3) KULLANICILAR TABLOSU
-- ===============================================================
CREATE TABLE `kullanicilar` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad`            VARCHAR(100) NOT NULL,
  `soyad`         VARCHAR(100) NOT NULL,
  `kullanici_adi` VARCHAR(50)  NOT NULL COLLATE utf8mb4_unicode_ci,
  `eposta`        VARCHAR(190) NOT NULL COLLATE utf8mb4_unicode_ci,
  `sifre`         VARCHAR(255) NOT NULL,
  `rol`           ENUM('admin','editor','uye') NOT NULL DEFAULT 'uye',
  `durum`         ENUM('aktif','pasif','askida') NOT NULL DEFAULT 'aktif',
  `avatar`        VARCHAR(191) NOT NULL DEFAULT '',
  `telefon`       VARCHAR(30)  NOT NULL DEFAULT '',
  `hakkinda`      TEXT NULL,
  `son_giris`     TIMESTAMP NULL DEFAULT NULL,
  `son_giris_ip`  VARCHAR(45)  NOT NULL DEFAULT '',
  `giris_sayisi`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kullanicilar_eposta` (`eposta`),
  UNIQUE KEY `uq_kullanicilar_kadi`   (`kullanici_adi`),
  KEY `idx_kullanicilar_rol`   (`rol`),
  KEY `idx_kullanicilar_durum` (`durum`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_turkish_ci;

-- NOT: Yönetici hesabı BİLEREK buraya eklenmedi; install/ sihirbazı
-- son adımda oluşturur. Elle kurulumda kendiniz ekleyin:
--   php -r "echo password_hash('parolaniz', PASSWORD_DEFAULT);"
--   INSERT INTO kullanicilar (ad, soyad, kullanici_adi, eposta, sifre, rol)
--   VALUES ('Ad', 'Soyad', 'admin', 'admin@ornek.com', '<uretilen_ozet>', 'admin');


-- ===============================================================
--  4) LOGIN_ATTEMPTS TABLOSU (kaba kuvvet koruması)
-- ===============================================================
--  Hatalı giriş denemeleri burada sayılır. Sayaç OTURUMDA DEĞİL
--  VERİTABANINDA tutulur; aksi halde saldırgan çerezini silerek
--  sayacı sıfırlayabilirdi.
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- E-posta/kullanıcı adı + IP birleşiminin SHA-256 özeti. Düz metin
  -- saklamıyoruz: veritabanı sızsa bile "kime saldırıldı" bilgisi sızmaz.
  `identifier`   CHAR(64)     NOT NULL,
  `ip`           VARCHAR(45)  NOT NULL DEFAULT '',
  `attempted_at` DATETIME     NOT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_attempts_lookup` (`identifier`, `attempted_at`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ===============================================================
--  5) MESAJLAR TABLOSU (iletişim formu)
-- ===============================================================
CREATE TABLE `mesajlar` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad`           VARCHAR(150) NOT NULL,
  `eposta`       VARCHAR(190) NOT NULL COLLATE utf8mb4_unicode_ci,
  `konu`         VARCHAR(190) NOT NULL DEFAULT '',
  `mesaj`        TEXT NOT NULL,
  `okundu`       TINYINT(1) NOT NULL DEFAULT 0,
  `kullanici_id` INT UNSIGNED NULL DEFAULT NULL,
  `ip`           VARCHAR(45)  NOT NULL DEFAULT '',
  `tarayici`     VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_mesajlar_okundu` (`okundu`),
  KEY `idx_mesajlar_tarih`  (`created_at`),
  KEY `idx_mesajlar_kullanici` (`kullanici_id`),

  CONSTRAINT `fk_mesajlar_kullanici`
    FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_turkish_ci;
