-- ===============================================================
--  VERİTABANI ŞEMASI
--  cilginyazilim.com – PHP Başlangıç Şablonu
-- ---------------------------------------------------------------
--  ÜÇ TEMEL TABLO:
--    ayarlar      → Site geneli ayarlar (anahtar/değer, tipli)
--    kullanicilar → Kullanıcı hesapları, roller, oturum bilgisi
--    mesajlar     → İletişim formundan gelen mesajlar
--
--  ► EN KOLAY KURULUM: Tarayıcıda "kurulum/" adresini açın.
--    Sihirbaz bu dosyayı çalıştırır, ayarları formdan aldığı
--    değerlerle günceller ve yönetici hesabını oluşturur.
--
--  ► ELLE KURULUM:
--    Terminal   :  mysql -u root -p < kurulum/database.sql
--    phpMyAdmin :  İçe Aktar > Dosya seç > database.sql > Başlat
--    (Bu durumda yönetici hesabını kendiniz eklemeniz gerekir.)
--
--  NOT: Sihirbaz bu dosyadaki CREATE DATABASE / USE satırlarını
--  görmezden gelir; veritabanını zaten kendisi oluşturup seçer.
-- ===============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";
SET NAMES utf8mb4;

-- ---------------------------------------------------------------
--  1) Veritabanı
-- ---------------------------------------------------------------
--  KARAKTER SETİ (charset) ile KARŞILAŞTIRMA (collation) FARKLI
--  ŞEYLERDİR — sık karıştırılır:
--    charset   → hangi karakterleri SAKLAYABİLİRSİN
--    collation → onları nasıl SIRALAR ve KARŞILAŞTIRIRSIN
--
--  ► Neden utf8mb4, "utf8" değil?
--    MySQL'deki "utf8" aslında utf8mb3'tür: karakter başına en fazla
--    3 bayt. Türkçe harfler (ç ğ ı ö ş ü) 2 bayt olduğu için sorunsuz
--    saklanır, AMA emoji 4 bayt olduğu için kaybolur:
--        utf8mb3'e  "Çılgın 🚀" yazarsanız  → "Çılgın ?" olur
--        utf8mb4'e  aynı veri              → aynen korunur
--    Ayrıca utf8mb3 MySQL 8'de kullanımdan kaldırılmıştır.
--
--  ► Neden turkish_ci?
--    Türkçe alfabede C < Ç, G < Ğ, I < İ sırası vardır ve i/İ ile
--    ı/I ayrı çiftlerdir. Genel Unicode sıralaması bunu bilmez:
--      unicode_ci : Cahit Çilek [Ğ-test] Gül  Ilgın [İrem] Irmak   ✗
--      turkish_ci : Cahit Çilek Gül [Ğ-test]  Ilgın Irmak [İrem]   ✓
--    (Yukarıdaki çıktılar bu makinede gerçekten test edilmiştir.)
--
--  NOT: Türkçe karakterlerin "Ã§" gibi bozuk görünmesi (mojibake)
--  collation ile İLGİLİ DEĞİLDİR. O, bağlantı/HTML karakter seti
--  uyuşmazlığından olur. Bu şablonda üç yerde de utf8mb4 kullanılır:
--  PDO DSN (charset=utf8mb4), bu dosya (SET NAMES) ve <meta charset>.
CREATE DATABASE IF NOT EXISTS `yeni_proje`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_turkish_ci;

USE `yeni_proje`;


-- ===============================================================
--  2) AYARLAR TABLOSU
-- ===============================================================
--  Neden ayrı bir tablo? Site adı, e-posta, sosyal medya adresleri
--  gibi bilgileri koda gömerseniz her değişiklikte dosya düzenlemek
--  ve yeniden yüklemek gerekir. Veritabanında tutunca yönetim
--  panelinden değiştirilebilir hale gelir.
--
--  TASARIM: Bu sadece "anahtar/değer" değil, TİPLİ bir tablodur.
--  "tip" ve "secenekler" sütunları sayesinde yönetim panelindeki
--  ayarlar formu OTOMATİK ÜRETİLİR — yeni bir ayar eklemek için
--  buraya bir satır eklemeniz yeterli, HTML yazmanız gerekmez.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `ayarlar`;

CREATE TABLE `ayarlar` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Kodda setting('site_adi') şeklinde çağıracağınız benzersiz anahtar.
  -- KİMLİK ALANI: Türkçe sıralama kuralları burada işimize yaramaz,
  -- aksine zarar verir. turkish_ci'de 'i' ile 'I' FARKLI sayılır;
  -- bu da 'site_adi' ve 'SITE_ADI' anahtarlarının ayrı kayıtlar
  -- olmasına yol açardı. Bu yüzden bu sütunu unicode_ci'ye sabitliyoruz.
  `anahtar`        VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,

  -- Değer her zaman metin olarak saklanır; tipe göre yorumlanır.
  `deger`          TEXT NULL,

  -- Yönetim panelinde sekmelere ayırmak için: genel, iletisim, sosyal, seo, sistem
  `grup`           VARCHAR(50) NOT NULL DEFAULT 'genel',

  -- Form alanının nasıl çizileceğini belirler:
  --   metin       → <input type="text">
  --   uzun_metin  → <textarea>
  --   sayi        → <input type="number">
  --   eposta      → <input type="email">
  --   url         → <input type="url">
  --   secim       → <select> (seçenekler "secenekler" sütununda)
  --   onay        → açma/kapama anahtarı (değer "1" veya "0")
  --   renk        → <input type="color">
  `tip`            ENUM('metin','uzun_metin','sayi','eposta','url','secim','onay','renk')
                   NOT NULL DEFAULT 'metin',

  -- Formda alanın üstünde görünecek etiket.
  `etiket`         VARCHAR(150) NOT NULL,

  -- Alanın altında görünecek küçük açıklama (opsiyonel).
  `aciklama`       VARCHAR(255) NULL,

  -- tip='secim' için JSON dizisi. Örn: ["Açık","Kapalı"]
  `secenekler`     TEXT NULL,

  -- Grup içindeki gösterim sırası (küçük olan üstte).
  `sira`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  -- 0 ise yönetim panelinde salt okunur gösterilir (örn. kurulum sürümü).
  `duzenlenebilir` TINYINT(1) NOT NULL DEFAULT 1,

  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  -- UNIQUE: Aynı anahtardan iki tane olamaz. Bu sayede
  -- "INSERT ... ON DUPLICATE KEY UPDATE" ile güvenle yazabiliriz.
  UNIQUE KEY `uq_ayarlar_anahtar` (`anahtar`),
  KEY `idx_ayarlar_grup` (`grup`, `sira`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_turkish_ci;


-- ---------------------------------------------------------------
--  Varsayılan ayarlar
-- ---------------------------------------------------------------
--  Sihirbaz bu satırları ekledikten SONRA, formdan aldığı
--  değerlerle site_adi / site_aciklama gibi anahtarları günceller.
--  Yeni bir ayar eklemek isterseniz buraya bir satır ekleyin —
--  yönetim panelinde otomatik görünür.
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
('sistem_surum',          '1.0.0', 'sistem', 'metin', 'Sürüm',                'Şablon sürümü.', NULL, 90);

-- Sürüm alanı elle değiştirilmesin.
UPDATE `ayarlar` SET `duzenlenebilir` = 0 WHERE `anahtar` = 'sistem_surum';


-- ===============================================================
--  3) KULLANICILAR TABLOSU
-- ===============================================================
--  Oturum açma, rol ve yetki kontrolü bu tabloya dayanır.
--
--  GÜVENLİK: "sifre" sütununda parolanın KENDİSİ DEĞİL, PHP'nin
--  password_hash() fonksiyonuyla üretilmiş özeti (hash) saklanır.
--  Veritabanı çalınsa bile parolalar okunamaz. Asla düz metin
--  parola veya md5/sha1 kullanmayın.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `kullanicilar`;

CREATE TABLE `kullanicilar` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,

  `ad`            VARCHAR(100) NOT NULL,
  `soyad`         VARCHAR(100) NOT NULL,

  /* KİMLİK ALANLARI — bilerek unicode_ci
   * ---------------------------------------------------------------
   * Tablonun geneli utf8mb4_turkish_ci (ad/soyad doğru sıralansın diye).
   * Ancak Türkçe kuralında 'i' ile 'I' FARKLI harflerdir. Bu iki sütun
   * turkish_ci kalsaydı, "ilker@ornek.com" ve "Ilker@ornek.com" iki
   * AYRI hesap olarak açılabilirdi — UNIQUE kısıtı bunu yakalayamazdı.
   * Birbirine benzeyen iki hesap ise klasik bir kimlik karışıklığı ve
   * güvenlik riskidir. Bu yüzden kimlik alanlarında 'i' = 'I' kabul
   * eden unicode_ci kullanıyoruz. */
  `kullanici_adi` VARCHAR(50)  NOT NULL COLLATE utf8mb4_unicode_ci,
  `eposta`        VARCHAR(190) NOT NULL COLLATE utf8mb4_unicode_ci,

  -- password_hash() çıktısı. Bugün 60 karakter (bcrypt) ama
  -- gelecekteki algoritmalar daha uzun olabileceği için 255.
  `sifre`         VARCHAR(255) NOT NULL,

  -- ROLLER:
  --   admin  → her şeye erişir, ayarları ve kullanıcıları yönetir
  --   editor → içerik yönetir, ayarlara/kullanıcılara dokunamaz
  --   uye    → sadece kendi profilini görür
  `rol`           ENUM('admin','editor','uye') NOT NULL DEFAULT 'uye',

  -- DURUM:
  --   aktif   → giriş yapabilir
  --   pasif   → giriş engellenir (hesap kapatılmış)
  --   askida  → geçici olarak engellenmiş
  `durum`         ENUM('aktif','pasif','askida') NOT NULL DEFAULT 'aktif',

  -- Sadece dosya adı tutulur (örn. "a1b2c3.png"), tam yol değil.
  `avatar`        VARCHAR(191) NOT NULL DEFAULT '',

  `telefon`       VARCHAR(30)  NOT NULL DEFAULT '',
  `hakkinda`      TEXT NULL,

  -- Oturum bilgileri (giriş yapıldığında güncellenir).
  `son_giris`     TIMESTAMP NULL DEFAULT NULL,
  `son_giris_ip`  VARCHAR(45)  NOT NULL DEFAULT '',  -- IPv6 için 45 karakter
  `giris_sayisi`  INT UNSIGNED NOT NULL DEFAULT 0,

  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  -- Aynı e-posta veya kullanıcı adıyla iki hesap açılamaz.
  -- Bu kontrolü PHP'de yapmak yetmez; iki istek aynı anda gelirse
  -- yalnızca veritabanı seviyesindeki UNIQUE kısıtı garanti verir.
  UNIQUE KEY `uq_kullanicilar_eposta` (`eposta`),
  UNIQUE KEY `uq_kullanicilar_kadi`   (`kullanici_adi`),

  KEY `idx_kullanicilar_rol`   (`rol`),
  KEY `idx_kullanicilar_durum` (`durum`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_turkish_ci;

-- ---------------------------------------------------------------
--  NOT: Yönetici hesabı BİLEREK buraya eklenmedi.
--  Şemaya sabit bir parola özeti gömmek, bu dosyayı GitHub'a
--  gönderen herkesin aynı parolayla girilebilir bir hesap
--  yayınlaması demek olurdu.
--
--  Yönetici hesabını kurulum sihirbazı (kurulum/index.php) oluşturur.
--  Elle kurulum yapıyorsanız şu satırı kendi bilgilerinizle
--  çalıştırın (parola özetini PHP ile üretin):
--
--    php -r "echo password_hash('parolaniz', PASSWORD_DEFAULT);"
--
--    INSERT INTO kullanicilar (ad, soyad, kullanici_adi, eposta, sifre, rol)
--    VALUES ('Ad', 'Soyad', 'admin', 'admin@ornek.com', '<uretilen_ozet>', 'admin');
-- ---------------------------------------------------------------


-- ===============================================================
--  4) MESAJLAR TABLOSU (iletişim formu)
-- ===============================================================
--  Ziyaretçinin "İletişim" sayfasından gönderdiği mesajlar burada
--  birikir; yönetici panelden okur. E-posta sunucusu ayarlamadan
--  çalışan en basit ve en güvenilir yöntem budur.
--
--  GÜVENLİK NOTLARI:
--    • Mesaj metni HAM haliyle saklanır; ekrana basılırken
--      htmlspecialchars() ile kaçışlanır (XSS bu noktada durur).
--    • "ip" ve "tarayici" alanları spam incelemesi içindir.
--    • Kayıtlı kullanıcı gönderdiyse "kullanici_id" dolar; bu sütun
--      hesap silinince NULL olur (ON DELETE SET NULL) — mesaj kaybolmaz.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `mesajlar`;

CREATE TABLE `mesajlar` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,

  `ad`           VARCHAR(150) NOT NULL,
  `eposta`       VARCHAR(190) NOT NULL COLLATE utf8mb4_unicode_ci,
  `konu`         VARCHAR(190) NOT NULL DEFAULT '',
  `mesaj`        TEXT NOT NULL,

  -- Panelde "okundu / okunmadı" ayrımı için.
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
