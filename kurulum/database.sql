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
DROP TABLE IF EXISTS `sayfalar`;
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
('site_dil',        'tr',         'genel', 'secim',      'Dil',             'HTML lang özniteliği.', '["tr","en"]', 50),

-- "site_logo" ve "site_favicon" GÖRÜNMEZ ("dahili" grubu) çünkü ikisi
-- de dosya adı tutar ve elle yazılacak değerler değildir: Genel
-- ayarlar sayfasının yan sütunundaki YÜKLEME KARTLARINDAN değişirler.
-- Formda düz metin alanı olarak dursalardı kullanıcı upload/ altındaki
-- dosya adını bilmek zorunda kalırdı; boş bir kaydetme de logoyu
-- sessizce silerdi.
('site_logo',       '',                   'dahili', 'metin', 'Logo Dosyası',    NULL, NULL, 60),
('site_favicon',    '',                   'dahili', 'metin', 'Favicon Dosyası', NULL, NULL, 65),

-- ---- İLETİŞİM ----
('iletisim_eposta', '',           'iletisim', 'eposta',  'İletişim E-postası', 'İletişim formundan gelen mesajların bildirimi bu adrese gider.', NULL, 10),
('iletisim_telefon','',           'iletisim', 'metin',   'Telefon',            'Ön yüzde tıklanabilir bağlantı olur. Örn: +90 212 000 00 00', NULL, 20),
('iletisim_whatsapp','+90 541 509 05 83', 'iletisim', 'metin', 'WhatsApp Numarası',  'Ülke koduyla yazın: +90 541 509 05 83. Boşsa WhatsApp düğmesi hiç görünmez.', NULL, 25),
('iletisim_whatsapp_mesaj', 'Merhaba, siteniz üzerinden yazıyorum. Bilgi almak istiyorum.', 'iletisim', 'uzun_metin', 'WhatsApp Hazır Mesajı', 'Ziyaretçi düğmeye bastığında sohbet kutusuna hazır gelecek metin.', NULL, 27),
('iletisim_adres',  '',           'iletisim', 'uzun_metin', 'Adres',           'Alt bilgide ve iletişim sayfasında görünür.', NULL, 30),
('iletisim_saatler','',           'iletisim', 'metin',   'Çalışma Saatleri',   'Örn: Hafta içi 09:00 – 18:00', NULL, 40),
('iletisim_harita', '',           'iletisim', 'uzun_metin', 'Harita Bağlantısı', 'Google Haritalar "paylaş" adresi. Boşsa harita kartı görünmez.', NULL, 50),

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
('seo_baslik_sablonu',    '%sayfa% · %site%', 'seo', 'metin', 'Başlık Şablonu', 'Sekmede görünecek biçim. %sayfa% ve %site% yer tutucularını kullanın.', NULL, 5),
('seo_anahtar_kelimeler', '',     'seo', 'uzun_metin', 'Anahtar Kelimeler', 'Virgülle ayırın.', NULL, 10),
('seo_analytics',         '',     'seo', 'uzun_metin', 'Analytics Kodu',    'Google Analytics vb. izleme kodu. Olduğu gibi <head> içine basılır.', NULL, 20),
('seo_indeksleme',        '1',    'seo', 'onay',       'Arama Motoru İndekslemesi', 'Kapatırsanız hem sayfalara "noindex" eklenir hem de robots.txt tüm siteyi kapatır.', NULL, 30),
('seo_google_dogrulama',  '',     'seo', 'metin',      'Google Site Doğrulama', 'Search Console''un verdiği "content" değeri. Yalnızca kod, etiketin tamamı değil.', NULL, 40),
('seo_og_gorsel',         '',     'seo', 'metin',      'Paylaşım Görseli',  'upload/ altındaki dosya adı ya da tam adres. Boşsa site logosu kullanılır.', NULL, 50),
('seo_sitemap_aktif',     '1',    'seo', 'onay',       'Site Haritası',     'sitemap.xml üretilsin mi? Yayınlanan sayfalar haritaya otomatik girer.', NULL, 60),
('seo_robots_ek',         '',     'seo', 'uzun_metin', 'robots.txt Ek Kuralları', 'Otomatik üretilen robots.txt dosyasının SONUNA eklenir. Her satır bir kural.', NULL, 70),

-- ---- SİSTEM ----
('sistem_bakim_modu',     '0',    'sistem', 'onay',  'Bakım Modu',           'Açıkken siteyi sadece yöneticiler görebilir.', NULL, 10),
-- Kurulum sonrası AÇIK gelir: yeni bir siteyi ilk kez gezen kişinin
-- "Kayıt Ol" düğmesini görebilmesi beklenen davranıştır. Kapalı bir
-- sistem isteyen yönetici bunu tek tıkla kapatır; tersi durumda
-- (kapalı gelseydi) kayıt bağlantısının neden yok olduğu ayarlar
-- ekranı taranmadan anlaşılmıyordu.
('sistem_kayit_acik',     '1',    'sistem', 'onay',  'Yeni Kayıtlara Açık',  'Ziyaretçiler kendi hesabını oluşturabilsin mi?', NULL, 20),
('sistem_iletisim_formu', '1',    'sistem', 'onay',  'İletişim Formu Açık',  'Kapatırsanız iletişim sayfasında sadece bilgiler görünür.', NULL, 25),
('sistem_sayfa_basina',   '10',   'sistem', 'sayi',  'Sayfa Başına Kayıt',   'Listelerde varsayılan sayfa boyutu.', NULL, 30),
('sistem_zaman_dilimi',   'Europe/Istanbul', 'sistem', 'metin', 'Zaman Dilimi', 'Örn: Europe/Istanbul', NULL, 40),
('sistem_tema_rengi',     '#0b5cb5', 'sistem', 'renk', 'Tema Rengi',          'Panelin ve sitenin ana rengi. Butonlar, bağlantılar, aktif menü ve gradyanlar bu renkten türetilir; kaydettiğiniz anda her yerde geçerli olur.', NULL, 50),

-- ---- UYGULAMA (PWA) ----
-- Künye (manifest) alanlarının hepsi BOŞ gelir. Bu bir eksiklik DEĞİL,
-- kurulumun tasarımıdır: PwaController boş her alan için site
-- ayarlarına düşer (ad → site_adi, açıklama → site_aciklama,
-- simge → site logosu). Böylece kurulum sihirbazında yazılan site adı
-- uygulamanın da adı olur ve site adı sonradan değiştiğinde uygulama
-- adı da onunla birlikte değişir. Buraya sabit bir ad yazsaydık her
-- yeni kurulum şablonun adıyla kurulur, yöneticinin girdiği ad
-- yok sayılırdı. Yönetici yalnızca FARKLI olmasını istediği alanı
-- doldurur.
--
-- "pwa_aktif" AÇIK GELİR: uygulama modunun açık olması ziyaretçiye
-- yalnızca "ana ekrana ekle" seçeneği sunar, hiçbir şeyi zorlamaz ve
-- kurulum sihirbazından tek tıkla kapatılabilir. Kapalı geldiğinde
-- ise özelliğin var olduğu ayarlar ekranı taranmadan fark edilmiyordu.
('pwa_aktif',      '1', 'pwa', 'onay',  'Uygulama Modu (PWA)', 'Açıkken ziyaretçi siteyi telefonuna uygulama olarak kurabilir. Kapalıyken sayfalara PWA ile ilgili tek bir etiket bile eklenmez.', NULL, 10),
('pwa_ad',         '',  'pwa', 'metin', 'Uygulama Adı',        'Kurulum penceresinde ve uygulama listesinde görünen tam ad. Boşsa site adı kullanılır.', NULL, 20),
('pwa_kisa_ad',    '',  'pwa', 'metin', 'Kısa Ad',             'Ana ekranda simgenin altında yazar; 12 karakteri geçmesin. Boşsa uygulama adının başı kullanılır.', NULL, 30),
('pwa_aciklama',   '',  'pwa', 'uzun_metin', 'Uygulama Açıklaması', 'Kurulum penceresinde görünür. Boşsa site açıklaması kullanılır.', NULL, 40),
('pwa_baslangic',  '',  'pwa', 'metin', 'Açılış Adresi',       'Uygulama açıldığında gidilecek sayfa; site köküne göre yazın (örn. panel). Boşsa ana sayfa açılır.', NULL, 50),
('pwa_gorunum',    'standalone', 'pwa', 'secim', 'Görüntüleme Modu', 'standalone: adres çubuğu olmadan, ayrı bir uygulama gibi. fullscreen: tam ekran. minimal-ui: ince gezinme çubuğuyla. browser: normal sekmede.', '["standalone","fullscreen","minimal-ui","browser"]', 60),
('pwa_yon',        'any', 'pwa', 'secim', 'Ekran Yönü',        'any: cihaz nasıl tutulursa. portrait: yalnızca dikey. landscape: yalnızca yatay.', '["any","portrait","landscape"]', 70),
('pwa_arka_renk',  '#ffffff', 'pwa', 'renk', 'Açılış Arka Plan Rengi', 'Uygulama açılırken simgenin arkasında görünen renk. Tema rengi buradan değil, Sistem ayarlarından gelir.', NULL, 80),
('pwa_cevrimdisi', '1', 'pwa', 'onay',  'Çevrimdışı Çalışma',  'Servis çalışanı sayfaları önbelleğe alır; ağ yokken site yine açılır. Kapatırsanız ziyaretçilerin tarayıcısındaki kayıtlı servis çalışanı ve önbellek de temizlenir.', NULL, 90),

-- Simge dosya adı tutar ve "dahili"dir: değeri Uygulama ayarları
-- sayfasının yan sütunundaki YÜKLEME KARTINDAN değişir, elle yazılmaz.
('pwa_simge',      '',  'dahili', 'metin', 'Uygulama Simgesi', NULL, NULL, 95);



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
  -- "Beni hatırla" jetonunun SHA-256 özeti. Çerezde ham jeton durur,
  -- burada yalnızca özeti: veritabanı sızsa bile çerez üretilemez.
  `hatirla_token` CHAR(64) NULL DEFAULT NULL,
  `hatirla_bitis` DATETIME NULL DEFAULT NULL,
  `son_giris`     TIMESTAMP NULL DEFAULT NULL,
  `son_giris_ip`  VARCHAR(45)  NOT NULL DEFAULT '',
  `giris_sayisi`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kullanicilar_eposta` (`eposta`),
  UNIQUE KEY `uq_kullanicilar_kadi`   (`kullanici_adi`),
  KEY `idx_kullanicilar_rol`     (`rol`),
  KEY `idx_kullanicilar_durum`   (`durum`),
  KEY `idx_kullanicilar_hatirla` (`hatirla_token`)
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
--  4.5) SAYFALAR TABLOSU (içerik sayfaları)
-- ---------------------------------------------------------------
--  "Hakkımızda", "Gizlilik", "KVKK" gibi DURAĞAN sayfalar. Eskiden
--  Hakkımızda metni tek bir ayar satırıydı; ikinci bir sayfa
--  isteyen herkes kod yazmak zorundaydı. Artık panelden sayfa
--  açılır, içerik zengin metin editöründen yazılır.
--
--  "slug" adresin son parçasıdır: /hakkimizda, /gizlilik…
--  Çekirdek adreslerle (giris, panel…) çakışması PageRepository
--  tarafından engellenir; router zaten önce SABİT rotalara bakar.
--
--  "korumali = 1" olan satırlar SİLİNEMEZ: menüde ve ön yüzde
--  adları geçen sayfaların (hakkimizda, iletisim) yanlışlıkla yok
--  edilmesi siteyi kırık bağlantılarla bırakırdı.
-- ===============================================================
CREATE TABLE `sayfalar` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `baslik`        VARCHAR(190) NOT NULL,
  `slug`          VARCHAR(190) NOT NULL COLLATE utf8mb4_unicode_ci,
  `ozet`          VARCHAR(255) NOT NULL DEFAULT '',
  `icerik`        MEDIUMTEXT NULL,
  `kapak`         VARCHAR(191) NOT NULL DEFAULT '',
  `durum`         ENUM('taslak','yayin') NOT NULL DEFAULT 'taslak',
  `menude`        TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Üst menüde görünsün mü?',
  `korumali`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Silinemeyen çekirdek sayfa',
  `sira`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `seo_baslik`    VARCHAR(190) NOT NULL DEFAULT '',
  `seo_aciklama`  VARCHAR(255) NOT NULL DEFAULT '',
  `yazar_id`      INT UNSIGNED NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sayfalar_slug` (`slug`),
  KEY `idx_sayfalar_durum` (`durum`, `sira`),

  CONSTRAINT `fk_sayfalar_yazar`
    FOREIGN KEY (`yazar_id`) REFERENCES `kullanicilar` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_turkish_ci;

-- Kurulumla birlikte gelen iki sayfa. İçerikleri örnek metindir;
-- yönetici panelden düzenler. "korumali = 1" oldukları için
-- silinemezler — üst menü ve alt bilgi onlara bağlantı verir.
INSERT INTO `sayfalar` (`baslik`, `slug`, `ozet`, `icerik`, `durum`, `menude`, `korumali`, `sira`) VALUES
('Hakkımızda', 'hakkimizda',
 'Kim olduğumuzu, ne yaptığımızı ve nasıl çalıştığımızı anlatan kısa bir tanıtım.',
 '<h2>Biz kimiz?</h2><p>Bu metni <strong>Panel → Sayfalar → Hakkımızda</strong> ekranından değiştirebilirsiniz. Zengin metin editörü başlık, kalın/italik yazı, listeler, bağlantılar ve alıntı desteği sunar.</p><h3>Ne yapıyoruz?</h3><ul><li>İhtiyaca göre kurumsal web çözümleri geliştiriyoruz.</li><li>Var olan sistemleri bakım ve destek altına alıyoruz.</li><li>Sürecin her adımında ölçülebilir sonuç hedefliyoruz.</li></ul><h3>Neden biz?</h3><p>İşimizi sade, hızlı ve sürdürülebilir yapmaya çalışıyoruz. Sorularınız için <a href="iletisim">iletişim sayfamızdan</a> bize yazabilirsiniz.</p>',
 'yayin', 1, 1, 10),
('İletişim', 'iletisim',
 'Bize ulaşmanın tüm yolları: telefon, e-posta, adres ve iletişim formu.',
 '<p>Sorularınız, teklif talepleriniz ve iş birliği önerileriniz için aşağıdaki formu doldurabilir ya da doğrudan iletişim bilgilerimizi kullanabilirsiniz. Mesajlarınıza <strong>en geç bir iş günü içinde</strong> dönüş yapıyoruz.</p>',
 'yayin', 1, 1, 20);


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
