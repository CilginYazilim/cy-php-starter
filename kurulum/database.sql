-- ===============================================================
--  VERİTABANI ŞEMASI
--  cilginyazilim.com – PHP Başlangıç Şablonu
-- ---------------------------------------------------------------
--  TABLOLAR:
--    ayarlar         → Site geneli ayarlar (anahtar/değer, tipli)
--    kullanicilar    → Kullanıcı hesapları, roller, oturum bilgisi
--    mesajlar        → İletişim formundan gelen mesajlar
--    mail_kayitlari  → Giden e-postaların geçmişi ve kuyruğu
--    login_attempts  → Kaba kuvvet (brute force) saldırısı koruması
--
--  ► EN KOLAY KURULUM: Tarayıcıda "kurulum/" adresini açın.
--    Sihirbaz bu dosyayı çalıştırır, ayarları formdan aldığı
--    değerlerle günceller, yönetici hesabını oluşturur ve
--    database/migrations altındaki EK tabloları da kurar.
--
--  ► ELLE KURULUM:
--    Terminal   :  mysql -u root -p < kurulum/database.sql
--    phpMyAdmin :  İçe Aktar > Dosya seç > database.sql > Başlat
--    Ardından   :  php cy migrate        (onbellek, isler, api_anahtarlari)
--    (Bu durumda yönetici hesabını kendiniz eklemeniz gerekir.)
--
--  ► ÖRNEK VERİ bu dosyada DEĞİLDİR. Demo kullanıcı ve mesajlar
--    "kurulum/demo.sql" içindedir; sihirbazda onay kutusuyla
--    seçilir. Boş bir projeye başlarken yüklemeyin.
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
DROP TABLE IF EXISTS `mail_kayitlari`;
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
  `tip`            ENUM('metin','uzun_metin','sayi','eposta','url','secim','onay','renk','sifre')
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

-- ---- E-POSTA ----
--  "mail_surucu" üç değer alır:
--    kayit → hiçbir yere göndermez, storage/mail/ klasörüne .eml yazar
--            (GELİŞTİRME için varsayılan; yanlışlıkla gerçek adrese
--             test maili gitmesini imkânsız kılar)
--    smtp  → gerçek bir posta sunucusuna bağlanır (ÖNERİLEN)
--    php   → PHP'nin mail() fonksiyonu (ayar istemez, spam'e düşer)
('mail_surucu',        'kayit', 'eposta', 'secim',  'Gönderim Yöntemi',   'Yayına alırken "smtp" seçin.', '["kayit","smtp","php"]', 10),
('mail_host',          '',      'eposta', 'metin',  'SMTP Sunucusu',      'Örn: smtp.gmail.com veya mail.siteniz.com', NULL, 20),
('mail_port',          '587',   'eposta', 'sayi',   'Kapı (Port)',        'TLS için 587, SSL için 465.', NULL, 30),
('mail_guvenlik',      'tls',   'eposta', 'secim',  'Şifreleme',          '587 → tls, 465 → ssl. "yok" yalnızca yerel test içindir.', '["tls","ssl","yok"]', 40),
('mail_kullanici',     '',      'eposta', 'metin',  'SMTP Kullanıcı Adı', 'Genellikle e-posta adresinizin tamamı.', NULL, 50),
('mail_sifre',         '',      'eposta', 'sifre',  'SMTP Parolası',      'Gmail kullanıyorsanız normal parolanızı değil "uygulama parolası" girin.', NULL, 60),
('mail_gonderen',      '',      'eposta', 'eposta', 'Gönderen Adresi',    'Mektupların "Kimden" adresi. Boşsa iletişim e-postası kullanılır.', NULL, 70),
('mail_gonderen_adi',  '',      'eposta', 'metin',  'Gönderen Adı',       'Örn: Yeni Proje Destek. Boşsa site adı kullanılır.', NULL, 80),
('mail_bildirim_yeni_mesaj', '1', 'eposta', 'onay', 'Yeni Mesaj Bildirimi', 'İletişim formu doldurulduğunda yöneticiye e-posta gitsin mi?', NULL, 90),
('mail_otomatik_yanit',      '1', 'eposta', 'onay', 'Otomatik Yanıt',       'Mesajı gönderen ziyaretçiye "aldık" e-postası gitsin mi?', NULL, 100),
('mail_hosgeldin',           '1', 'eposta', 'onay', 'Hoş Geldiniz E-postası', 'Yeni kayıt olan üyeye karşılama e-postası gitsin mi?', NULL, 110),
('mail_parti_boyutu',        '15','eposta', 'sayi', 'Parti Boyutu',          'Toplu gönderimde her turda kaç mektup gönderilsin? Sunucunuz yavaşsa düşürün.', NULL, 120),
('mail_alt_bilgi',           '',  'eposta', 'uzun_metin', 'E-posta Alt Bilgisi', 'Her mektubun altında görünecek metin. Boşsa site adı yazılır.', NULL, 130),

-- ---- SOSYAL MEDYA ----
('sosyal_facebook',  'https://www.facebook.com/cilginyazilim',                  'sosyal', 'url', 'Facebook',    NULL, NULL, 10),
('sosyal_x',         'https://x.com/cilginyazilim',                             'sosyal', 'url', 'X (Twitter)', NULL, NULL, 20),
('sosyal_instagram', 'https://www.instagram.com/cilginyazilim',                 'sosyal', 'url', 'Instagram',   NULL, NULL, 30),
('sosyal_linkedin',  'https://tr.linkedin.com/in/evren-%C3%A7ilgin-193262216',  'sosyal', 'url', 'LinkedIn',    NULL, NULL, 40),
('sosyal_youtube',   'https://www.youtube.com/@cilginyazilim',                  'sosyal', 'url', 'YouTube',     NULL, NULL, 50),
('sosyal_github',    'https://github.com/CilginYazilim',                        'sosyal', 'url', 'GitHub',      NULL, NULL, 60),

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
('sistem_tema_rengi',     '#0b5cb5', 'sistem', 'renk', 'Tema Rengi',          'Panelin ve sitenin ana rengi. Butonlar, bağlantılar, aktif menü ve gradyanlar bu renkten türetilir; kaydettiğiniz anda her yerde geçerli olur.', NULL, 50),
('pwa_aktif',             '0',    'sistem', 'onay',  'Uygulama Modu (PWA)',  'Açıkken site telefona "uygulama olarak ekle" ile kurulabilir ve çevrimdışı açılır.', NULL, 60),
('sistem_surum',          '2.1.0', 'sistem', 'metin', 'Sürüm',                'Şablon sürümü.', NULL, 90);

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
  `tema`          ENUM('acik','koyu') NOT NULL DEFAULT 'acik',
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

-- NOT: Bu şema zaten kurulu bir veritabanını GÜNCELLEMEZ (her şeyi
-- baştan oluşturur). Mevcut bir kurulumu güncelliyorsanız "tema"
-- sütununu elle ekleyin:
--   ALTER TABLE kullanicilar ADD COLUMN tema ENUM('acik','koyu')
--     NOT NULL DEFAULT 'acik' AFTER durum;

-- NOT: Yönetici hesabı BİLEREK buraya eklenmedi; kurulum/ sihirbazı
-- son adımda oluşturur. Elle kurulumda kendiniz ekleyin:
--   php -r "echo password_hash('parolaniz', PASSWORD_DEFAULT);"
--   INSERT INTO kullanicilar (ad, soyad, kullanici_adi, eposta, sifre, rol)
--   VALUES ('Ad', 'Soyad', 'admin', 'admin@ornek.com', '<uretilen_ozet>', 'admin');
--
-- Demo kullanıcılar için: kurulum/demo.sql


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


-- ===============================================================
--  6) E-POSTA KAYITLARI (geçmiş + kuyruk)
-- ---------------------------------------------------------------
--  Tablo İKİ iş görür:
--
--    GEÇMİŞ – gönderilen her mektup buraya yazılır. "Ben o e-postayı
--             hiç almadım" diyen kullanıcı için panelden bakarsınız:
--             gitti mi, ne zaman gitti, gitmediyse sunucu ne dedi?
--
--    KUYRUK – durum = 'kuyrukta' olan satırlar HENÜZ GÖNDERİLMEMİŞ
--             mektuplardır. Toplu duyurular buraya yazılır ve parti
--             parti gönderilir; 500 kişilik bir duyuru tek istekte
--             gönderilmeye çalışılsa PHP zaman aşımına uğrardı.
--
--  "govde" sütunu mektubun HTML gövdesini saklar — hem kuyruktakini
--  sonradan gönderebilmek hem de panelde önizleyebilmek için.
-- ===============================================================
CREATE TABLE `mail_kayitlari` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `alici_eposta`  VARCHAR(190) NOT NULL COLLATE utf8mb4_unicode_ci,
  `alici_ad`      VARCHAR(150) NOT NULL DEFAULT '',
  `konu`          VARCHAR(255) NOT NULL,
  `govde`         MEDIUMTEXT NULL,
  `yanit_eposta`  VARCHAR(190) NOT NULL DEFAULT '' COLLATE utf8mb4_unicode_ci,
  `yanit_ad`      VARCHAR(150) NOT NULL DEFAULT '',
  `sablon`        VARCHAR(60)  NOT NULL DEFAULT 'genel',
  `tur`           ENUM('bildirim','iletisim','otomatik','toplu','test','sistem')
                  NOT NULL DEFAULT 'bildirim',
  `durum`         ENUM('kuyrukta','gonderildi','basarisiz')
                  NOT NULL DEFAULT 'kuyrukta',
  `hata`          VARCHAR(255) NOT NULL DEFAULT '',
  `deneme`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `kullanici_id`  INT UNSIGNED NULL DEFAULT NULL COMMENT 'Alıcı üye (varsa)',
  `gonderen_id`   INT UNSIGNED NULL DEFAULT NULL COMMENT 'Gönderimi başlatan yönetici',
  `toplu_id`      CHAR(32) NOT NULL DEFAULT '' COMMENT 'Aynı toplu gönderimin parçalarını bağlar',
  `gonderildi_at` DATETIME NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_mail_durum`     (`durum`, `id`),
  KEY `idx_mail_tarih`     (`created_at`),
  KEY `idx_mail_tur`       (`tur`),
  KEY `idx_mail_toplu`     (`toplu_id`),
  KEY `idx_mail_kullanici` (`kullanici_id`),
  KEY `idx_mail_gonderen`  (`gonderen_id`),

  CONSTRAINT `fk_mail_kullanici`
    FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT `fk_mail_gonderen`
    FOREIGN KEY (`gonderen_id`) REFERENCES `kullanicilar` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_turkish_ci;
