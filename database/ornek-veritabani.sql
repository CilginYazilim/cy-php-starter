-- =====================================================================
--  CY PHP Starter 1.6.2 — örnek veritabanı (şema + örnek veri)
-- ---------------------------------------------------------------------
--  Kurulum sihirbazında "Örnek veriyle kur" seçildiğinde kurulan
--  veritabanının aynısıdır: bütün tablolar, ayarlar, sayfalar, 6 örnek
--  hesap, mesajlar, e-posta geçmişi ve Örnek Modül kayıtları.
--
--  ÖNERİLEN KURULUM SİHİRBAZDIR (kurulum/index.php): .env'i yazar,
--  güvenli anahtarları üretir ve size ait bir yönetici hesabı açar.
--  Bu dosya sihirbazı çalıştıramadığınız durumlar ve veriyi incelemek
--  içindir.
--
--  NASIL KURULUR?
--    1. BOŞ bir veritabanı açın (utf8mb4). Dolu bir veritabanına
--       aktarmayın: aynı adlı tablo varsa aktarma durur.
--    2. Bu dosyayı içe aktarın: phpMyAdmin → İçe Aktar, ya da
--         mysql -u KULLANICI -p VERITABANI < database/ornek-veritabani.sql
--    3. .env.example'ı .env adıyla kopyalayın; DB_HOST, DB_NAME,
--       DB_USER, DB_PASS ve APP_URL'i doldurun. APP_KEY için:
--         php -r "echo bin2hex(random_bytes(32));"
--    4. kurulum/ klasörünü silin.
--    5. Giriş: ali.yonetici / Demo1234!  (yönetici)
--
--  YAYINA ALMADAN ÖNCE: örnek hesapların parolası herkesçe bilinir.
--  Kendinize bir yönetici hesabı açın, ardından Panel → Sistem →
--  "Örnek veriyi kaldır" ile örnek hesapları ve içeriği silin.
--
--  Görseller SQL'de değildir; isterseniz kopyalayın:
--    database/seeders/demo/og-cy-php-starter.png → upload/img/
--    database/seeders/demo/dosyalar/*            → storage/files/ornek/
--
--  Bu dosya elle düzenlenmez; tests/ornek-sql.php üretir.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- api_anahtarlari
-- --------------------------------------------------------

CREATE TABLE `api_anahtarlari` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(10) unsigned NOT NULL,
  `ad` varchar(100) NOT NULL,
  `onek` varchar(16) NOT NULL,
  `ozet` char(64) NOT NULL,
  `kapsam` enum('okuma','yazma') NOT NULL DEFAULT 'yazma',
  `tur` enum('anahtar','oturum') NOT NULL DEFAULT 'anahtar',
  `cihaz` varchar(100) NOT NULL DEFAULT '',
  `son_gecerlilik` datetime DEFAULT NULL COMMENT 'NULL = süresiz',
  `son_kullanim` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_api_onek` (`onek`),
  KEY `idx_api_kullanici` (`kullanici_id`),
  CONSTRAINT `fk_api_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
-- ayarlar
-- --------------------------------------------------------

CREATE TABLE `ayarlar` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `anahtar` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deger` text DEFAULT NULL,
  `grup` varchar(50) NOT NULL DEFAULT 'genel',
  `tip` enum('metin','uzun_metin','sayi','eposta','url','secim','onay','renk','sifre','liste','coklu') NOT NULL DEFAULT 'metin',
  `etiket` varchar(150) NOT NULL,
  `aciklama` varchar(255) DEFAULT NULL,
  `secenekler` text DEFAULT NULL,
  `sira` smallint(5) unsigned NOT NULL DEFAULT 0,
  `duzenlenebilir` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ayarlar_anahtar` (`anahtar`),
  KEY `idx_ayarlar_grup` (`grup`,`sira`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO `ayarlar` (`id`, `anahtar`, `deger`, `grup`, `tip`, `etiket`, `aciklama`, `secenekler`, `sira`, `duzenlenebilir`, `created_at`, `updated_at`) VALUES
(1, 'site_adi', 'CY PHP Starter', 'genel', 'metin', 'Site Adı', 'Tarayıcı sekmesinde ve başlıkta görünür.', NULL, 10, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(2, 'site_aciklama', 'Kurulum sihirbazı, rol tabanlı yönetim paneli, REST API, kuyruk ve PWA ile gelen, Composer gerektirmeyen açık kaynak PHP 8 başlangıç şablonu.', 'genel', 'uzun_metin', 'Site Açıklaması', 'Arama sonuçlarında ve sosyal medya paylaşımlarında görünen tanıtım. 120–160 karakter idealdir.', NULL, 20, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(3, 'site_slogan', 'Her yeni PHP projesine buradan başlayın', 'genel', 'metin', 'Slogan', 'Ana sayfada başlığın altında ve alt bilgide görünen kısa cümle.', NULL, 30, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(4, 'site_dil', 'tr', 'genel', 'secim', 'Dil', 'Sayfanın dil etiketi (lang). Arayüz metinlerini çevirmez; arama motorları ve ekran okuyucular için.', '[\"tr\",\"en\"]', 50, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(5, 'site_logo', '', 'dahili', 'metin', 'Logo Dosyası', NULL, NULL, 60, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(6, 'site_favicon', '', 'dahili', 'metin', 'Favicon Dosyası', NULL, NULL, 65, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(7, 'iletisim_eposta', '', 'iletisim', 'eposta', 'İletişim E-postası', 'İletişim formundan gelen mesajların bildirimi bu adrese gider.', NULL, 10, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(8, 'iletisim_telefon', '', 'iletisim', 'metin', 'Telefon', 'Ön yüzde tıklanabilir bağlantı olur. Örn: +90 212 000 00 00', NULL, 20, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(9, 'iletisim_whatsapp', '', 'iletisim', 'metin', 'WhatsApp Numarası', 'Ülke koduyla yazın: +90 5XX XXX XX XX. Boşsa WhatsApp düğmesi hiç görünmez.', NULL, 25, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(10, 'iletisim_whatsapp_mesaj', 'Merhaba, siteniz üzerinden yazıyorum. Bilgi almak istiyorum.', 'iletisim', 'uzun_metin', 'WhatsApp Hazır Mesajı', 'Ziyaretçi düğmeye bastığında sohbet kutusuna hazır gelecek metin.', NULL, 27, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(11, 'iletisim_adres', 'İstanbul', 'iletisim', 'uzun_metin', 'Adres', 'Alt bilgide ve iletişim sayfasında görünür.', NULL, 30, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(12, 'iletisim_saatler', 'Hafta içi 09:00 – 18:00', 'iletisim', 'metin', 'Çalışma Saatleri', 'Örn: Hafta içi 09:00 – 18:00', NULL, 40, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(13, 'iletisim_harita', '', 'iletisim', 'uzun_metin', 'Harita Bağlantısı', 'Google Haritalar \"paylaş\" adresi. Boşsa harita kartı görünmez.', NULL, 50, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(14, 'mail_surucu', 'kayit', 'eposta', 'secim', 'Gönderim Yöntemi', 'kayit: mektuplar gönderilmez, storage/mail/ klasörüne yazılır (geliştirme). smtp: gerçek gönderim (yayında bunu seçin). php: sunucunun mail() işlevi.', '[\"kayit\",\"smtp\",\"php\"]', 10, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(15, 'mail_host', '', 'eposta', 'metin', 'SMTP Sunucusu', 'Örn: smtp.gmail.com veya mail.siteniz.com', NULL, 20, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(16, 'mail_port', '587', 'eposta', 'sayi', 'Kapı (Port)', 'TLS için 587, SSL için 465.', NULL, 30, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(17, 'mail_guvenlik', 'tls', 'eposta', 'secim', 'Şifreleme', '587 → tls, 465 → ssl. \"yok\" yalnızca yerel test içindir.', '[\"tls\",\"ssl\",\"yok\"]', 40, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(18, 'mail_kullanici', '', 'eposta', 'metin', 'SMTP Kullanıcı Adı', 'Genellikle e-posta adresinizin tamamı.', NULL, 50, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(19, 'mail_sifre', '', 'eposta', 'sifre', 'SMTP Parolası', 'Gmail kullanıyorsanız normal parolanızı değil \"uygulama parolası\" girin.', NULL, 60, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(20, 'mail_gonderen', '', 'eposta', 'eposta', 'Gönderen Adresi', 'Mektupların \"Kimden\" adresi. Boşsa iletişim e-postası kullanılır.', NULL, 70, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(21, 'mail_gonderen_adi', 'CY PHP Starter', 'eposta', 'metin', 'Gönderen Adı', 'Örn: Yeni Proje Destek. Boşsa site adı kullanılır.', NULL, 80, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(22, 'mail_bildirim_yeni_mesaj', '1', 'eposta', 'onay', 'Yeni Mesaj Bildirimi', 'İletişim formu doldurulduğunda yöneticiye e-posta gitsin mi?', NULL, 90, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(23, 'mail_otomatik_yanit', '0', 'eposta', 'onay', 'Otomatik Yanıt', 'Mesajı gönderen ziyaretçiye \"aldık\" e-postası gitsin mi? Aynı adrese günde en fazla bir kez gider ve ziyaretçinin yazdığı metni İÇERMEZ.', NULL, 100, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(24, 'mail_hosgeldin', '1', 'eposta', 'onay', 'Hoş Geldiniz E-postası', 'Yeni kayıt olan üyeye karşılama e-postası gitsin mi?', NULL, 110, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(25, 'mail_parti_boyutu', '15', 'eposta', 'sayi', 'Parti Boyutu', 'Toplu gönderimde her turda kaç mektup gönderilsin? Sunucunuz yavaşsa düşürün.', NULL, 120, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(26, 'mail_alt_bilgi', '', 'eposta', 'uzun_metin', 'E-posta Alt Bilgisi', 'Her mektubun altında görünecek metin. Boşsa site adı yazılır.', NULL, 130, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(27, 'sosyal_facebook', 'https://www.facebook.com/cilginyazilim/', 'sosyal', 'url', 'Facebook', 'Tam adres: https://www.facebook.com/hesabiniz', NULL, 10, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(28, 'sosyal_x', 'https://x.com/cilginyazilim', 'sosyal', 'url', 'X (Twitter)', 'Tam adres: https://x.com/hesabiniz', NULL, 20, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(29, 'sosyal_instagram', 'https://www.instagram.com/cilginyazilim/', 'sosyal', 'url', 'Instagram', 'Tam adres: https://www.instagram.com/hesabiniz', NULL, 30, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(30, 'sosyal_linkedin', '', 'sosyal', 'url', 'LinkedIn', 'Tam adres: https://www.linkedin.com/in/hesabiniz', NULL, 40, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(31, 'sosyal_youtube', '', 'sosyal', 'url', 'YouTube', 'Tam adres: https://www.youtube.com/@kanaliniz', NULL, 50, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(32, 'sosyal_github', 'https://github.com/CilginYazilim/cy-php-starter', 'sosyal', 'url', 'GitHub', 'Tam adres: https://github.com/hesabiniz', NULL, 60, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(33, 'seo_baslik_sablonu', '%sayfa% · %site%', 'seo', 'metin', 'Başlık Şablonu', 'Sekmede görünecek biçim. %sayfa% ve %site% yer tutucularını kullanın.', NULL, 5, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(34, 'seo_anahtar_kelimeler', 'php başlangıç şablonu, php starter kit, php boilerplate, php yönetim paneli, php kurulum sihirbazı', 'seo', 'uzun_metin', 'Anahtar Kelimeler', 'Virgülle ayırın. Google bu alanı sıralamada kullanmaz; yalnızca bazı arama motorları ve site içi araçlar okur.', NULL, 10, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(35, 'seo_analytics', '', 'seo', 'uzun_metin', 'Analytics Kodu', 'Google Analytics vb. izleme kodu. Olduğu gibi <head> içine basılır.', NULL, 20, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(36, 'seo_indeksleme', '1', 'seo', 'onay', 'Arama Motoru İndekslemesi', 'Kapatırsanız hem sayfalara \"noindex\" eklenir hem de robots.txt tüm siteyi kapatır.', NULL, 30, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(37, 'seo_google_dogrulama', '', 'seo', 'metin', 'Google Site Doğrulama', 'Search Console\'un verdiği \"content\" değeri. Yalnızca kod, etiketin tamamı değil.', NULL, 40, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(38, 'seo_og_gorsel', 'img/og-cy-php-starter.png', 'seo', 'metin', 'Paylaşım Görseli', 'upload/ altındaki dosya adı ya da tam adres. Boşsa site logosu kullanılır.', NULL, 50, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(39, 'seo_sitemap_aktif', '1', 'seo', 'onay', 'Site Haritası', 'sitemap.xml üretilsin mi? Yayınlanan sayfalar haritaya otomatik girer.', NULL, 60, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(40, 'seo_robots_ek', '', 'seo', 'uzun_metin', 'robots.txt Ek Kuralları', 'Otomatik üretilen robots.txt dosyasının SONUNA eklenir. Her satır bir kural.', NULL, 70, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(41, 'sistem_bakim_modu', '0', 'sistem', 'onay', 'Bakım Modu', 'Açıkken ziyaretçiler bakım sayfasını görür; yalnızca yöneticiler ve editörler siteyi gezebilir. Panelde uyarı şeridi çıkar.', NULL, 10, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(42, 'sistem_kayit_acik', '1', 'sistem', 'onay', 'Yeni Kayıtlara Açık', 'Ziyaretçiler kendi hesabını oluşturabilsin mi?', NULL, 20, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(43, 'sistem_kayit_dogrulama', '1', 'sistem', 'onay', 'Kayıtta E-posta Doğrulaması', 'Yeni hesap, e-postadaki bağlantıya tıklanana kadar giriş yapamaz. E-posta ayarları eksikse kayıt formu kapanır.', NULL, 21, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(44, 'sistem_iletisim_formu', '1', 'sistem', 'onay', 'İletişim Formu Açık', 'Kapatırsanız iletişim sayfasında sadece bilgiler görünür.', NULL, 25, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(45, 'sistem_sayfa_basina', '10', 'sistem', 'sayi', 'Sayfa Başına Kayıt', 'Paneldeki listelerin (kullanıcılar, mesajlar…) ilk açılıştaki satır sayısı. 5–500 arası.', NULL, 30, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(46, 'sistem_zaman_dilimi', 'Europe/Istanbul', 'sistem', 'metin', 'Zaman Dilimi', 'Tarih ve saatler bu bölgeye göre gösterilir. IANA biçiminde yazın, örn. Europe/Istanbul, Europe/Berlin.', NULL, 40, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(47, 'sistem_tema_rengi', '#0b5cb5', 'sistem', 'renk', 'Tema Rengi', 'Panelin ve sitenin ana rengi. Butonlar, bağlantılar, aktif menü ve gradyanlar bu renkten türetilir; kaydettiğiniz anda her yerde geçerli olur.', NULL, 50, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(48, 'pwa_aktif', '1', 'pwa', 'onay', 'Uygulama Modu (PWA)', 'Açıkken ziyaretçi siteyi telefonuna uygulama olarak kurabilir. Kapalıyken sayfalara PWA ile ilgili tek bir etiket bile eklenmez.', NULL, 10, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(49, 'pwa_ad', 'CY PHP Starter', 'pwa', 'metin', 'Uygulama Adı', 'Kurulum penceresinde ve uygulama listesinde görünen tam ad. Boşsa site adı kullanılır.', NULL, 20, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(50, 'pwa_kisa_ad', 'CY Starter', 'pwa', 'metin', 'Kısa Ad', 'Ana ekranda simgenin altında yazar; 12 karakteri geçmesin. Boşsa uygulama adının başı kullanılır.', NULL, 30, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(51, 'pwa_aciklama', 'Kurulum sihirbazı, rol tabanlı yönetim paneli, REST API, kuyruk ve PWA ile gelen, Composer gerektirmeyen açık kaynak PHP 8 başlangıç şablonu.', 'pwa', 'uzun_metin', 'Uygulama Açıklaması', 'Kurulum penceresinde görünür. Boşsa site açıklaması kullanılır.', NULL, 40, 1, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(52, 'pwa_baslangic', '', 'pwa', 'metin', 'Açılış Adresi', 'Uygulama açıldığında gidilecek sayfa; site köküne göre yazın (örn. panel). Boşsa ana sayfa açılır.', NULL, 50, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(53, 'pwa_gorunum', 'standalone', 'pwa', 'secim', 'Görüntüleme Modu', 'standalone: adres çubuğu olmadan, ayrı bir uygulama gibi. fullscreen: tam ekran. minimal-ui: ince gezinme çubuğuyla. browser: normal sekmede.', '[\"standalone\",\"fullscreen\",\"minimal-ui\",\"browser\"]', 60, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(54, 'pwa_yon', 'any', 'pwa', 'secim', 'Ekran Yönü', 'any: cihaz nasıl tutulursa. portrait: yalnızca dikey. landscape: yalnızca yatay.', '[\"any\",\"portrait\",\"landscape\"]', 70, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(55, 'pwa_arka_renk', '#ffffff', 'pwa', 'renk', 'Açılış Arka Plan Rengi', 'Uygulama açılırken simgenin arkasında görünen renk. Tema rengi buradan değil, Sistem ayarlarından gelir.', NULL, 80, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(56, 'pwa_cevrimdisi', '1', 'pwa', 'onay', 'Çevrimdışı Çalışma', 'Servis çalışanı sayfaları önbelleğe alır; ağ yokken site yine açılır. Kapatırsanız ziyaretçilerin tarayıcısındaki kayıtlı servis çalışanı ve önbellek de temizlenir.', NULL, 90, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(57, 'pwa_simge', '', 'dahili', 'metin', 'Uygulama Simgesi', NULL, NULL, 95, 1, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(58, 'site_marka', 'ÇILGIN Yazılım', 'genel', 'metin', 'Marka / Kurum Adı', 'Panelde site adının altında ve sayfaların künyesinde (author) görünür. Boşsa site adı kullanılır.', NULL, 15, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(59, 'site_imza_goster', '1', 'genel', 'onay', 'Şablon İmzası', 'Alt bilgide küçük bir \"CY PHP Starter ile geliştirildi\" bağlantısı. İsterseniz kapatın.', NULL, 60, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(60, 'vitrin_tanitim_goster', '1', 'genel', 'onay', 'Şablon Vitrin Bağlantıları', 'Alt bilgide CY PHP Starter belge ve kod bağlantıları (demo vitrini için). Örnek veriyle kurulumda açık gelir.', NULL, 61, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(61, 'sosyal_pinterest', '', 'sosyal', 'url', 'Pinterest', 'Tam adres: https://www.pinterest.com/hesabiniz', NULL, 55, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(62, 'sistem_parola_sifirlama', '1', 'sistem', 'onay', 'Parola Sıfırlama', '\"Parolamı unuttum\" bağlantısı. E-posta gönderimi yapılandırılmadıysa kendiliğinden gizlenir; geliştirme modunda açık kalır, bağlantı Panel → E-posta geçmişinde görünür.', NULL, 22, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(63, 'sistem_kvkk_sayfa', 'gizlilik-ve-kvkk', 'sistem', 'metin', 'Aydınlatma Metni Sayfası', 'Formlardaki onay kutusunun bağlantı verdiği sayfanın adresi (slug). Boşsa onay kutusu gösterilmez.', NULL, 26, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(64, 'sistem_kvkk_onay', '1', 'sistem', 'onay', 'Aydınlatma Metni Onayı', 'Kayıt ve iletişim formlarında \"Aydınlatma metnini okudum\" kutusu zorunlu olsun.', NULL, 27, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(65, 'sistem_ip_saklama', '180', 'sistem', 'sayi', 'IP Saklama Süresi (gün)', 'İletişim mesajlarındaki IP ve tarayıcı bilgisi bu süre dolunca silinir (zamanlanmış görev). 0 = hiç silinmez.', NULL, 28, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(66, 'sistem_hesap_silme', '1', 'sistem', 'onay', 'Hesap Silme', 'Üyeler Hesabım ekranından hesaplarını silebilsin. Silme 7 gün sonra gerçekleşir; bu sürede giriş yapan üye iptal eder.', NULL, 29, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(67, 'demo_son_sifirlama', '', 'dahili', 'metin', 'Demo Son Sıfırlama', NULL, NULL, 0, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(68, 'anasayfa_bolumler', '[\"hero\",\"teknoloji\",\"adimlar\",\"ozellikler\",\"roller\",\"kod\",\"sss\",\"cta\"]', 'anasayfa', 'coklu', 'Görünen Bölümler', 'İşaretli bölümler ana sayfada sabit sırayla görünür. İçeriği boş olan bölüm kendiliğinden gizlenir.', '{\"hero\":\"Karşılama (başlık, düğmeler, görsel)\",\"teknoloji\":\"Teknoloji şeridi\",\"adimlar\":\"Adımlar\",\"ozellikler\":\"Özellikler\",\"roller\":\"Rolleri deneyin (yalnızca demo modunda)\",\"kod\":\"Kod örneği\",\"sss\":\"Sık sorulan sorular\",\"hakkimizda\":\"Hakkımızda özeti\",\"iletisim\":\"İletişim kartları\",\"cta\":\"Son çağrı bandı\"}', 10, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(69, 'anasayfa_rozet', 'v{surum} · Açık kaynak · MIT', 'anasayfa', 'metin', 'Rozet', 'Başlığın üstündeki küçük etiket. {surum}, {php}, {komut} ve {test} yer tutucuları kullanılabilir.', NULL, 20, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(70, 'anasayfa_baslik', 'Her yeni PHP projesine buradan başlayın', 'anasayfa', 'metin', 'Başlık', 'Boşsa site adı.', NULL, 30, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(71, 'anasayfa_metin', 'Kurulum sihirbazı, rol tabanlı yönetim paneli, REST API, kuyruk ve PWA hazır gelir. Composer, framework ya da CDN gerekmez; yüklediğiniz her sunucuda çalışır.', 'anasayfa', 'uzun_metin', 'Tanıtım Metni', 'Bir iki cümle. Boşsa site sloganı ya da açıklaması.', NULL, 40, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(72, 'anasayfa_birincil_metin', 'Canlı demoyu aç', 'anasayfa', 'metin', 'Birinci Düğme', 'Karşılama bölümündeki ana düğmenin yazısı. Boşsa düğme gösterilmez.', NULL, 50, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(73, 'anasayfa_birincil_adres', 'giris', 'anasayfa', 'metin', 'Birinci Düğmenin Adresi', 'Site içi yol (örn. iletisim) ya da https:// ile başlayan tam adres.', NULL, 51, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(74, 'anasayfa_ikincil_metin', 'GitHub\'da incele', 'anasayfa', 'metin', 'İkinci Düğme', 'Boşsa gösterilmez.', NULL, 52, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(75, 'anasayfa_ikincil_adres', 'https://github.com/CilginYazilim/cy-php-starter', 'anasayfa', 'metin', 'İkinci Düğmenin Adresi', 'Site içi yol (örn. hakkimizda) ya da https:// ile başlayan tam adres.', NULL, 53, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(76, 'anasayfa_guven', 'PHP 8.1–8.4 · 0 bağımlılık · {test} birim testi · MIT', 'anasayfa', 'metin', 'Güven Satırı', 'Düğmelerin altındaki küçük yazı, örn. \"PHP {php} · {test} test · MIT\".', NULL, 54, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(77, 'anasayfa_gorsel', 'vitrin', 'anasayfa', 'metin', 'Karşılama Görseli', 'Boşsa logo kartı. \"vitrin\" yazarsanız panelin ekran görüntüsü (açık/koyu tema), ya da upload/ altındaki bir dosya adı.', NULL, 55, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(78, 'anasayfa_ozellikler', '[{\"ikon\":\"shield\",\"baslik\":\"Güvenlik baştan hazır\",\"metin\":\"CSRF, XSS ve SQL enjeksiyonu korumaları, kaba kuvvet kilidi ve sertleştirilmiş oturum kurulumla gelir.\"},{\"ikon\":\"users\",\"baslik\":\"Rol ve yetki\",\"metin\":\"Yönetici, editör ve üye; modüller kendi yetkilerini dağıtır, kayıt düzeyi kurallar tek sınıfta durur.\"},{\"ikon\":\"code\",\"baslik\":\"REST API\",\"metin\":\"Süreli Bearer anahtarları, okuma/yazma kapsamları ve mobil uygulama için oturum uçları.\"},{\"ikon\":\"send\",\"baslik\":\"E-posta ve kuyruk\",\"metin\":\"SMTP, toplu gönderim ve arka plan kuyruğu; giden her mektup kayıt altında.\"},{\"ikon\":\"box\",\"baslik\":\"Modüller\",\"metin\":\"php cy make:module çalışan bir iskelet üretir; panelden tek tıkla açılır.\"},{\"ikon\":\"mobil\",\"baslik\":\"PWA ve mobil\",\"metin\":\"Telefona kurulabilen uygulama modu ve baştan sona mobil uyumlu panel.\"}]', 'anasayfa', 'liste', 'Özellikler', 'İkon, başlık ve tek cümle. İlk iki kart büyük gösterilir.', '{\"alanlar\":[{\"ad\":\"ikon\",\"etiket\":\"İkon\",\"tip\":\"ikon\"},{\"ad\":\"baslik\",\"etiket\":\"Başlık\"},{\"ad\":\"metin\",\"etiket\":\"Açıklama\",\"tip\":\"uzun\"}],\"en_fazla\":8}', 60, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(79, 'anasayfa_adimlar', '[{\"baslik\":\"İndirin\",\"metin\":\"GitHub\'dan ZIP olarak indirin ve sunucunuza yükleyin.\"},{\"baslik\":\"kurulum/ adresini açın\",\"metin\":\"Sihirbaz veritabanını, yönetici hesabını ve ayarları iki ekranda kurar.\"},{\"baslik\":\"Panele girin\",\"metin\":\"Örnek veriyle kurduysanız demo hesaplarla her rolü hemen deneyin.\"}]', 'anasayfa', 'liste', 'Adımlar', 'Numaralı adımlar (en fazla 4).', '{\"alanlar\":[{\"ad\":\"baslik\",\"etiket\":\"Başlık\"},{\"ad\":\"metin\",\"etiket\":\"Açıklama\",\"tip\":\"uzun\"}],\"en_fazla\":4}', 70, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(80, 'anasayfa_sss', '[{\"soru\":\"Composer gerekiyor mu?\",\"cevap\":\"Hayır. Şablonun hiçbir dış bağımlılığı yok; dosyaları yükleyip kurulum sihirbazını açmanız yeterli.\"},{\"soru\":\"Hangi PHP sürümleriyle çalışır?\",\"cevap\":\"PHP 8.1, 8.2, 8.3 ve 8.4 ile her sürümde test edilir; MySQL 5.7+ ya da MariaDB 10.3+ ister.\"},{\"soru\":\"Paylaşımlı hostingde çalışır mı?\",\"cevap\":\"Evet. SSH gerekmez: migration\'ları çalıştırmak ve modülleri açmak panelden yapılır.\"},{\"soru\":\"Ticari projede kullanabilir miyim?\",\"cevap\":\"Evet, MIT lisanslıdır. Alt bilgideki imzayı Ayarlar → Genel\'den kapatabilirsiniz.\"},{\"soru\":\"Yeni bir özelliği nasıl eklerim?\",\"cevap\":\"\\\"php cy make:module Stok\\\" çalışan bir modül iskeleti üretir; Örnek Modül adım adım anlatılmış bir şablondur.\"},{\"soru\":\"Mobil uygulama yazabilir miyim?\",\"cevap\":\"Evet. REST API; oturum açma, cihaz oturumları ve örnek dosya uçlarıyla gelir. Ayrıntılar README\'deki \\\"Mobil uygulama\\\" bölümünde.\"}]', 'anasayfa', 'liste', 'Sık Sorulan Sorular', 'Soru ve cevap çiftleri (en fazla 10). Cevaplar düz metindir; boş liste bölümü gizler.', '{\"alanlar\":[{\"ad\":\"soru\",\"etiket\":\"Soru\"},{\"ad\":\"cevap\",\"etiket\":\"Cevap\",\"tip\":\"uzun\"}],\"en_fazla\":10}', 80, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(81, 'anasayfa_cta_baslik', 'Bir sonraki projenize bugün başlayın', 'anasayfa', 'metin', 'Son Bant Başlığı', 'Sayfanın sonundaki renkli bandın başlığı. Boşsa bant gösterilmez.', NULL, 90, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(82, 'anasayfa_cta_metin', 'Açık kaynak, ücretsiz ve Türkçe. İndirin, kurun, geliştirmeye başlayın.', 'anasayfa', 'metin', 'Son Bant Metni', 'Bant başlığının altındaki tek cümle.', NULL, 91, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(83, 'anasayfa_cta_dugmeler', '[{\"metin\":\"ZIP indir\",\"adres\":\"https://github.com/CilginYazilim/cy-php-starter/archive/refs/heads/main.zip\"},{\"metin\":\"GitHub\",\"adres\":\"https://github.com/CilginYazilim/cy-php-starter\"},{\"metin\":\"Belgeler\",\"adres\":\"https://cilginyazilim.com/kutuphane/php-baslangic-sablonu\"}]', 'anasayfa', 'liste', 'Son Bant Düğmeleri', 'En fazla 3 düğme. Adres site içi yol (örn. iletisim) ya da https:// ile başlayan tam adres olabilir.', '{\"alanlar\":[{\"ad\":\"metin\",\"etiket\":\"Düğme metni\"},{\"ad\":\"adres\",\"etiket\":\"Adres\",\"tip\":\"adres\"}],\"en_fazla\":3}', 92, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(84, 'mail_bildirim_yeni_uye', '0', 'eposta', 'onay', 'Yeni Üye Bildirimi', 'Biri kayıt olunca iletişim e-postasına haber verilsin mi? Saatte en fazla 10 mektup gider.', NULL, 95, 1, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(85, 'aktif_moduller', '[\"Ornek\"]', 'dahili', 'metin', 'aktif_moduller', NULL, NULL, 0, 0, '2026-10-07 15:06:00', '2026-10-07 15:06:00');

-- --------------------------------------------------------
-- isler
-- --------------------------------------------------------

CREATE TABLE `isler` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kuyruk` varchar(60) NOT NULL DEFAULT 'varsayilan',
  `sinif` varchar(191) NOT NULL,
  `veri` json DEFAULT NULL,
  `durum` enum('bekliyor','calisiyor','basarisiz') NOT NULL DEFAULT 'bekliyor',
  `deneme` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_deneme` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `hata` varchar(500) DEFAULT NULL,
  `hazir_at` datetime NOT NULL,
  `ayrildi_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_isler_sira` (`durum`,`hazir_at`,`id`),
  KEY `idx_isler_kuyruk` (`kuyruk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
-- kullanicilar
-- --------------------------------------------------------

CREATE TABLE `kullanicilar` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ad` varchar(100) NOT NULL,
  `soyad` varchar(100) NOT NULL,
  `kullanici_adi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `eposta` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sifre` varchar(255) NOT NULL,
  `rol` enum('admin','editor','uye') NOT NULL DEFAULT 'uye',
  `durum` enum('aktif','pasif','askida','onay_bekliyor') NOT NULL DEFAULT 'aktif',
  `tema` enum('acik','koyu') NOT NULL DEFAULT 'acik',
  `avatar` varchar(191) NOT NULL DEFAULT '',
  `telefon` varchar(30) NOT NULL DEFAULT '',
  `hakkinda` text DEFAULT NULL,
  `hatirla_token` char(64) DEFAULT NULL,
  `hatirla_bitis` datetime DEFAULT NULL,
  `oturum_surumu` int(10) unsigned NOT NULL DEFAULT 0,
  `son_giris` timestamp NULL DEFAULT NULL,
  `son_giris_ip` varchar(45) NOT NULL DEFAULT '',
  `giris_sayisi` int(10) unsigned NOT NULL DEFAULT 0,
  `silinme_at` datetime DEFAULT NULL COMMENT 'Üyenin istediği silme zamanı (bekleme süresi sonunda silinir)',
  `bildirim_tercihleri` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kullanicilar_eposta` (`eposta`),
  UNIQUE KEY `uq_kullanicilar_kadi` (`kullanici_adi`),
  KEY `idx_kullanicilar_rol` (`rol`),
  KEY `idx_kullanicilar_durum` (`durum`),
  KEY `idx_kullanicilar_hatirla` (`hatirla_token`),
  KEY `idx_kullanicilar_kayit` (`created_at`),
  KEY `idx_kullanicilar_silinme` (`silinme_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO `kullanicilar` (`id`, `ad`, `soyad`, `kullanici_adi`, `eposta`, `sifre`, `rol`, `durum`, `tema`, `avatar`, `telefon`, `hakkinda`, `hatirla_token`, `hatirla_bitis`, `oturum_surumu`, `son_giris`, `son_giris_ip`, `giris_sayisi`, `silinme_at`, `bildirim_tercihleri`, `created_at`, `updated_at`) VALUES
(2, 'Ali', 'Yılmaz', 'ali.yonetici', 'ali.demo@ornek.com', '$2y$10$tP/cxO7S3sdE.908eCOskeTLGzLesEf0H4/gb8QZl5AXRKQob/tlC', 'admin', 'aktif', 'acik', '', '+90 555 000 00 01', 'Demo yönetici hesabı: her ekranı gezebilir; parola, kullanıcı ve ayar işlemleri demo modunda kilitli.', NULL, NULL, 0, '2026-10-07 02:06:00', '127.0.0.1', 16, NULL, NULL, '2026-09-24 15:06:00', '2026-10-07 15:06:00'),
(3, 'Elif', 'Demir', 'elif.editor', 'elif.demo@ornek.com', '$2y$10$tP/cxO7S3sdE.908eCOskeTLGzLesEf0H4/gb8QZl5AXRKQob/tlC', 'editor', 'aktif', 'acik', '', '+90 555 000 00 02', 'İçerik editörü: sayfaları yazar, mesajları yanıtlar, Örnek Modül\'de onay bekleyen kayıtları yayınlar.', NULL, NULL, 0, '2026-10-07 04:06:00', '127.0.0.1', 14, NULL, NULL, '2026-09-26 15:06:00', '2026-10-07 15:06:00'),
(4, 'Mehmet', 'Kaya', 'mehmet.uye', 'mehmet.demo@ornek.com', '$2y$10$tP/cxO7S3sdE.908eCOskeTLGzLesEf0H4/gb8QZl5AXRKQob/tlC', 'uye', 'aktif', 'acik', '', '', '', NULL, NULL, 0, '2026-10-07 07:06:00', '127.0.0.1', 11, NULL, NULL, '2026-09-29 15:06:00', '2026-10-07 15:06:00'),
(5, 'Ayşe', 'Şahin', 'ayse.pasif', 'ayse.demo@ornek.com', '$2y$10$tP/cxO7S3sdE.908eCOskeTLGzLesEf0H4/gb8QZl5AXRKQob/tlC', 'uye', 'pasif', 'acik', '', '', '', NULL, NULL, 0, NULL, '', 0, NULL, NULL, '2026-10-01 15:06:00', '2026-10-07 15:06:00'),
(6, 'Can', 'Yıldız', 'can.askida', 'can.demo@ornek.com', '$2y$10$tP/cxO7S3sdE.908eCOskeTLGzLesEf0H4/gb8QZl5AXRKQob/tlC', 'uye', 'askida', 'acik', '', '', '', NULL, NULL, 0, NULL, '', 0, NULL, NULL, '2026-10-03 15:06:00', '2026-10-07 15:06:00'),
(7, 'Zeynep', 'Arslan', 'zeynep.onay', 'zeynep.demo@ornek.com', '$2y$10$tP/cxO7S3sdE.908eCOskeTLGzLesEf0H4/gb8QZl5AXRKQob/tlC', 'uye', 'onay_bekliyor', 'acik', '', '', '', NULL, NULL, 0, NULL, '', 0, NULL, NULL, '2026-10-06 15:06:00', '2026-10-07 15:06:00');

-- --------------------------------------------------------
-- login_attempts
-- --------------------------------------------------------

CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` char(64) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_lookup` (`identifier`,`attempted_at`),
  KEY `idx_attempts_ip` (`ip`,`attempted_at`),
  KEY `idx_attempts_tarih` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- mail_kayitlari
-- --------------------------------------------------------

CREATE TABLE `mail_kayitlari` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `alici_eposta` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alici_ad` varchar(150) NOT NULL DEFAULT '',
  `konu` varchar(255) NOT NULL,
  `govde` mediumtext DEFAULT NULL,
  `yanit_eposta` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `yanit_ad` varchar(150) NOT NULL DEFAULT '',
  `sablon` varchar(60) NOT NULL DEFAULT 'genel',
  `tur` enum('bildirim','iletisim','otomatik','toplu','test','sistem') NOT NULL DEFAULT 'bildirim',
  `durum` enum('kuyrukta','gonderiliyor','gonderildi','basarisiz') NOT NULL DEFAULT 'kuyrukta',
  `hata` varchar(255) NOT NULL DEFAULT '',
  `deneme` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `kullanici_id` int(10) unsigned DEFAULT NULL COMMENT 'Alıcı üye (varsa)',
  `gonderen_id` int(10) unsigned DEFAULT NULL COMMENT 'Gönderimi başlatan yönetici',
  `toplu_id` char(32) NOT NULL DEFAULT '' COMMENT 'Aynı toplu gönderimin parçalarını bağlar',
  `gonderildi_at` datetime DEFAULT NULL,
  `ayrildi_at` datetime DEFAULT NULL COMMENT 'Gönderim için ayrıldığı an (yarıda kalanları bulmak için)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mail_durum` (`durum`,`id`),
  KEY `idx_mail_gonderim` (`durum`,`gonderildi_at`),
  KEY `idx_mail_alici` (`alici_eposta`,`created_at`),
  KEY `idx_mail_tarih` (`created_at`),
  KEY `idx_mail_tur` (`tur`),
  KEY `idx_mail_toplu` (`toplu_id`),
  KEY `idx_mail_kullanici` (`kullanici_id`),
  KEY `idx_mail_gonderen` (`gonderen_id`),
  CONSTRAINT `fk_mail_gonderen` FOREIGN KEY (`gonderen_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mail_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO `mail_kayitlari` (`id`, `alici_eposta`, `alici_ad`, `konu`, `govde`, `yanit_eposta`, `yanit_ad`, `sablon`, `tur`, `durum`, `hata`, `deneme`, `kullanici_id`, `gonderen_id`, `toplu_id`, `gonderildi_at`, `ayrildi_at`, `created_at`) VALUES
(1, 'mehmet.demo@ornek.com', 'Mehmet Kaya', 'Hoş geldiniz!', '<!DOCTYPE html><html lang=\"tr\"><body style=\"font-family:system-ui,sans-serif;background:#f6f8fb;padding:24px\"><div style=\"max-width:560px;margin:auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #e6ebf2\"><h1 style=\"font-size:20px;margin:0 0 12px\">Hoş geldiniz!</h1><p>Merhaba,</p><p>Bu, CY PHP Starter demo verisindeki örnek bir mektuptur. Gerçek bir sitede şablon (hosgeldin) burada kendi içeriğiyle görünür.</p><p style=\"color:#64748b;font-size:13px;margin-top:24px\">CY PHP Starter · ÇILGIN Yazılım</p></div></body></html>', '', '', 'hosgeldin', 'otomatik', 'gonderildi', '', 1, NULL, NULL, '', '2026-09-29 20:06:00', NULL, '2026-09-29 17:06:00'),
(2, 'deniz.demo@ornek.com', 'Deniz Aksoy', 'Mesajınızı aldık', '<!DOCTYPE html><html lang=\"tr\"><body style=\"font-family:system-ui,sans-serif;background:#f6f8fb;padding:24px\"><div style=\"max-width:560px;margin:auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #e6ebf2\"><h1 style=\"font-size:20px;margin:0 0 12px\">Mesajınızı aldık</h1><p>Merhaba,</p><p>Bu, CY PHP Starter demo verisindeki örnek bir mektuptur. Gerçek bir sitede şablon (iletisim-yanit) burada kendi içeriğiyle görünür.</p><p style=\"color:#64748b;font-size:13px;margin-top:24px\">CY PHP Starter · ÇILGIN Yazılım</p></div></body></html>', '', '', 'iletisim-yanit', 'otomatik', 'gonderildi', '', 1, NULL, NULL, '', '2026-10-07 15:06:00', NULL, '2026-10-07 12:06:00'),
(3, 'elif.demo@ornek.com', 'Elif Demir', 'Yeni iletişim mesajı: Teklif talebi', '<!DOCTYPE html><html lang=\"tr\"><body style=\"font-family:system-ui,sans-serif;background:#f6f8fb;padding:24px\"><div style=\"max-width:560px;margin:auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #e6ebf2\"><h1 style=\"font-size:20px;margin:0 0 12px\">Yeni iletişim mesajı: Teklif talebi</h1><p>Merhaba,</p><p>Bu, CY PHP Starter demo verisindeki örnek bir mektuptur. Gerçek bir sitede şablon (iletisim-bildirim) burada kendi içeriğiyle görünür.</p><p style=\"color:#64748b;font-size:13px;margin-top:24px\">CY PHP Starter · ÇILGIN Yazılım</p></div></body></html>', '', '', 'iletisim-bildirim', 'bildirim', 'gonderildi', '', 1, NULL, NULL, '', '2026-10-07 15:06:00', NULL, '2026-10-07 12:06:00'),
(4, 'zeynep.demo@ornek.com', 'Zeynep Arslan', 'E-posta adresinizi doğrulayın', '<!DOCTYPE html><html lang=\"tr\"><body style=\"font-family:system-ui,sans-serif;background:#f6f8fb;padding:24px\"><div style=\"max-width:560px;margin:auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #e6ebf2\"><h1 style=\"font-size:20px;margin:0 0 12px\">E-posta adresinizi doğrulayın</h1><p>Hesabınızı etkinleştirmek için aşağıdaki bağlantıya tıklayın:</p><p><a href=\"https://ornek.com/kayit/dogrula?k=0&amp;s=0&amp;i=demo\">E-postamı doğrula</a></p><p>Bu bir demo mektubudur; bağlantı çalışmaz.</p><p style=\"color:#64748b;font-size:13px;margin-top:24px\">CY PHP Starter · ÇILGIN Yazılım</p></div></body></html>', '', '', 'dogrulama', 'sistem', 'gonderildi', '', 1, NULL, NULL, '', '2026-10-06 22:06:00', NULL, '2026-10-06 19:06:00'),
(5, 'ali.demo@ornek.com', 'Ali Yılmaz', 'Ekim bülteni: yeni özellikler', '<!DOCTYPE html><html lang=\"tr\"><body style=\"font-family:system-ui,sans-serif;background:#f6f8fb;padding:24px\"><div style=\"max-width:560px;margin:auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #e6ebf2\"><h1 style=\"font-size:20px;margin:0 0 12px\">Ekim bülteni: yeni özellikler</h1><p>Merhaba,</p><p>Bu, CY PHP Starter demo verisindeki örnek bir mektuptur. Gerçek bir sitede şablon (duyuru) burada kendi içeriğiyle görünür.</p><p style=\"color:#64748b;font-size:13px;margin-top:24px\">CY PHP Starter · ÇILGIN Yazılım</p></div></body></html>', '', '', 'duyuru', 'toplu', 'gonderildi', '', 1, NULL, NULL, '', '2026-10-05 20:06:00', NULL, '2026-10-05 17:06:00'),
(6, 'burak.demo@ornek.com', 'Burak Öztürk', 'Ekim bülteni: yeni özellikler', '<!DOCTYPE html><html lang=\"tr\"><body style=\"font-family:system-ui,sans-serif;background:#f6f8fb;padding:24px\"><div style=\"max-width:560px;margin:auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #e6ebf2\"><h1 style=\"font-size:20px;margin:0 0 12px\">Ekim bülteni: yeni özellikler</h1><p>Merhaba,</p><p>Bu, CY PHP Starter demo verisindeki örnek bir mektuptur. Gerçek bir sitede şablon (duyuru) burada kendi içeriğiyle görünür.</p><p style=\"color:#64748b;font-size:13px;margin-top:24px\">CY PHP Starter · ÇILGIN Yazılım</p></div></body></html>', '', '', 'duyuru', 'toplu', 'basarisiz', '550 Mailbox unavailable', 3, NULL, NULL, '', NULL, NULL, '2026-10-05 17:06:00'),
(7, 'selin.demo@ornek.com', 'Selin Kara', 'Ekim bülteni: yeni özellikler', '<!DOCTYPE html><html lang=\"tr\"><body style=\"font-family:system-ui,sans-serif;background:#f6f8fb;padding:24px\"><div style=\"max-width:560px;margin:auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #e6ebf2\"><h1 style=\"font-size:20px;margin:0 0 12px\">Ekim bülteni: yeni özellikler</h1><p>Merhaba,</p><p>Bu, CY PHP Starter demo verisindeki örnek bir mektuptur. Gerçek bir sitede şablon (duyuru) burada kendi içeriğiyle görünür.</p><p style=\"color:#64748b;font-size:13px;margin-top:24px\">CY PHP Starter · ÇILGIN Yazılım</p></div></body></html>', '', '', 'duyuru', 'toplu', 'gonderildi', '', 1, NULL, NULL, '', '2026-10-05 21:06:00', NULL, '2026-10-05 18:06:00');

-- --------------------------------------------------------
-- mesajlar
-- --------------------------------------------------------

CREATE TABLE `mesajlar` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ad` varchar(150) NOT NULL,
  `eposta` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `konu` varchar(190) NOT NULL DEFAULT '',
  `mesaj` text NOT NULL,
  `okundu` tinyint(1) NOT NULL DEFAULT 0,
  `kullanici_id` int(10) unsigned DEFAULT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `tarayici` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mesajlar_okundu` (`okundu`),
  KEY `idx_mesajlar_tarih` (`created_at`),
  KEY `idx_mesajlar_kullanici` (`kullanici_id`),
  KEY `idx_mesajlar_ip` (`ip`,`created_at`),
  CONSTRAINT `fk_mesajlar_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO `mesajlar` (`id`, `ad`, `eposta`, `konu`, `mesaj`, `okundu`, `kullanici_id`, `ip`, `tarayici`, `created_at`) VALUES
(1, 'Deniz Aksoy', 'deniz.demo@ornek.com', 'Teklif talebi', 'Merhaba, kurumsal web sitemizi bu şablonla yenilemeyi düşünüyoruz. Kurulum ve özelleştirme için fiyat teklifi alabilir miyiz?', 0, NULL, '127.0.0.1', 'Demo verisi', '2026-10-07 12:06:00'),
(2, 'Burak Öztürk', 'burak.demo@ornek.com', 'Destek: SMTP ayarı', 'Panelde e-posta ayarlarını yaptım ama test mektubu ulaşmadı. 465 ve 587 kapılarını denedim; ne kontrol etmeliyim?', 0, NULL, '127.0.0.1', 'Demo verisi', '2026-10-07 06:06:00'),
(3, 'Selin Kara', 'selin.demo@ornek.com', 'İş birliği önerisi', 'Ajansımızın projelerinde şablonu kullanmak istiyoruz. Modül geliştirme konusunda birlikte çalışabilir miyiz?', 1, NULL, '127.0.0.1', 'Demo verisi', '2026-10-06 13:06:00'),
(4, 'Emre Çelik', 'emre.demo@ornek.com', 'Hata bildirimi', 'Sayfalar ekranında çok uzun bir başlık yazınca tablonun hücresi taşıyor. Ekran görüntüsünü ekleyebilirim.', 1, NULL, '127.0.0.1', 'Demo verisi', '2026-10-05 13:06:00'),
(5, 'Gizem Aydın', 'gizem.demo@ornek.com', 'Eğitim', 'Üniversitede web programlama dersinde şablonu örnek olarak göstermek istiyorum. Ders notu hazırlamamda sakınca var mı?', 0, NULL, '127.0.0.1', 'Demo verisi', '2026-10-04 13:06:00'),
(6, 'Ozan Polat', 'ozan.demo@ornek.com', 'Mobil uygulama', 'REST API ile mobil uygulamaya giriş yaptırmak istiyorum. Oturum anahtarının süresi nasıl ayarlanıyor?', 1, NULL, '127.0.0.1', 'Demo verisi', '2026-10-02 15:06:00'),
(7, 'Ebru Koç', 'ebru.demo@ornek.com', 'Kurulum sorusu', 'Paylaşımlı hostingde kurulum sihirbazı \"yazma izni yok\" diyor. Hangi klasörlere izin vermem gerekiyor?', 1, NULL, '127.0.0.1', 'Demo verisi', '2026-09-30 13:06:00'),
(8, 'Kerem Şen', 'kerem.demo@ornek.com', 'Fatura bilgisi', 'Geçen ayki destek hizmeti için faturayı şirket unvanımızla yeniden düzenleyebilir misiniz?', 1, NULL, '127.0.0.1', 'Demo verisi', '2026-09-28 01:06:00'),
(9, 'Nazlı Er', 'nazli.demo@ornek.com', 'Öneri', 'Panelde koyu tema çok güzel olmuş. Ayarlar sayfasına arama kutusu eklenirse çok iyi olur.', 1, NULL, '127.0.0.1', 'Demo verisi', '2026-09-25 03:06:00');

-- --------------------------------------------------------
-- migrasyonlar
-- --------------------------------------------------------

CREATE TABLE `migrasyonlar` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dosya` varchar(255) NOT NULL,
  `parti` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migrasyon_dosya` (`dosya`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO `migrasyonlar` (`id`, `dosya`, `parti`, `created_at`) VALUES
(1, '2026_08_10_040000_mesajlar_ip_indeksi', 0, '2026-10-07 15:05:59'),
(2, '2026_08_10_050000_sayfalar_tablosu', 0, '2026-10-07 15:05:59'),
(3, '2026_08_10_060000_beni_hatirla_sutunlari', 0, '2026-10-07 15:05:59'),
(4, '2026_08_10_070000_yeni_ayarlar', 0, '2026-10-07 15:05:59'),
(5, '2026_08_10_080000_pwa_ayarlari', 0, '2026-10-07 15:05:59'),
(6, '2026_08_30_010000_surum_ayarini_kaldir', 0, '2026-10-07 15:05:59'),
(7, '2026_10_06_010000_oturum_surumu', 0, '2026-10-07 15:05:59'),
(8, '2026_10_06_020000_mail_kuyrugu_kilidi', 0, '2026-10-07 15:05:59'),
(9, '2026_10_06_030000_giris_denemeleri_ip_indeksi', 0, '2026-10-07 15:05:59'),
(10, '2026_10_06_040000_eposta_dogrulama', 0, '2026-10-07 15:05:59'),
(11, '2026_10_07_010000_sorgu_indeksleri', 0, '2026-10-07 15:05:59'),
(12, '2026_10_08_010000_ayar_aciklamalari', 0, '2026-10-07 15:05:59'),
(13, '2026_08_10_010000_onbellek_tablosu', 0, '2026-10-07 15:06:00'),
(14, '2026_08_10_020000_isler_tablosu', 0, '2026-10-07 15:06:00'),
(15, '2026_08_10_030000_api_anahtarlari_tablosu', 0, '2026-10-07 15:06:00'),
(16, '2026_10_06_090000_cekirdek_temel_parti', 0, '2026-10-07 15:06:00'),
(17, '2026_10_07_020000_surum_1_6', 0, '2026-10-07 15:06:00'),
(18, '2026_10_08_020000_surum_1_6_1', 0, '2026-10-07 15:06:00'),
(19, '2026_10_09_010000_surum_1_6_2', 0, '2026-10-07 15:06:00'),
(20, 'Ornek/2026_08_10_062032_ornek_tablosu', 1, '2026-10-07 15:06:00'),
(21, 'Ornek/2026_10_06_100000_ornek_sahip_ve_durum', 1, '2026-10-07 15:06:00');

-- --------------------------------------------------------
-- onbellek
-- --------------------------------------------------------

CREATE TABLE `onbellek` (
  `anahtar` varchar(191) NOT NULL,
  `deger` mediumblob NOT NULL,
  `son_gecerlilik` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = süresiz',
  PRIMARY KEY (`anahtar`),
  KEY `idx_onbellek_son_gecerlilik` (`son_gecerlilik`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
-- ornek
-- --------------------------------------------------------

CREATE TABLE `ornek` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `baslik` varchar(150) NOT NULL,
  `aciklama` varchar(255) NOT NULL DEFAULT '',
  `durum` varchar(20) NOT NULL DEFAULT 'yayinda',
  `kullanici_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ornek_kullanici` (`kullanici_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO `ornek` (`id`, `baslik`, `aciklama`, `durum`, `kullanici_id`, `created_at`, `updated_at`) VALUES
(1, 'Haftalık stok sayımı', 'Cuma gününe kadar gözden geçirilecek.', 'yayinda', 2, '2026-10-07 13:06:00', NULL),
(2, 'Müşteri geri bildirim raporu', 'Geçen ayın verileriyle karşılaştırıldı.', 'yayinda', 3, '2026-10-06 20:06:00', NULL),
(3, 'Kampanya görseli taslağı', 'İlk sürüm hazır; ekipten yorum bekleniyor.', 'yayinda', 4, '2026-10-06 03:06:00', NULL),
(4, 'Sunucu bakım notları', 'Pazar gecesi 02:00–03:00 arası planlandı.', 'onay', 2, '2026-10-05 10:06:00', NULL),
(5, 'Yeni ürün açıklaması', 'Müşteri temsilcisiyle birlikte hazırlandı.', 'onay', 3, '2026-10-04 17:06:00', NULL),
(6, 'Teklif şablonu güncellemesi', 'Fiyat tablosu yeni KDV oranına göre düzenlendi.', 'onay', 4, '2026-10-04 00:06:00', NULL),
(7, 'SSS sayfası için sorular', 'Destek taleplerinden en sık gelen on soru.', 'taslak', 2, '2026-10-03 07:06:00', NULL),
(8, 'Mobil uygulama test listesi', 'Giriş, çıkış ve oturum listesi senaryoları.', 'taslak', 3, '2026-10-02 14:06:00', NULL),
(9, 'Fatura hatırlatma metni', 'Eksik maddeler işaretlendi, tamamlanacak.', 'taslak', 4, '2026-10-01 21:06:00', NULL),
(10, 'Bülten konu başlıkları', 'Ekim bülteni için beş öneri.', 'yayinda', 2, '2026-10-01 04:06:00', NULL),
(11, 'Destek talebi özeti', 'Haftalık en çok sorulan konular.', 'yayinda', 3, '2026-09-30 11:06:00', NULL),
(12, 'Toplantı karar notları', 'Bir sonraki toplantıda görüşülecek.', 'yayinda', 4, '2026-09-29 18:06:00', NULL),
(13, 'Fiyat listesi revizyonu', 'Yönetim onayından sonra yayına alınacak.', 'onay', 2, '2026-09-29 01:06:00', NULL),
(14, 'Blog yazısı fikirleri', 'Modül geliştirme üzerine üç yazı.', 'onay', 3, '2026-09-28 08:06:00', NULL),
(15, 'Sosyal medya takvimi', 'Kasım ayının paylaşım planı.', 'onay', 4, '2026-09-27 15:06:00', NULL),
(16, 'Kargo süreç akışı', 'Depo ve müşteri hizmetleriyle paylaşılacak.', 'taslak', 2, '2026-09-26 22:06:00', NULL),
(17, 'Personel eğitim planı', 'Yeni panel ekranları için kısa eğitim.', 'taslak', 3, '2026-09-26 05:06:00', NULL),
(18, 'Yedekleme kontrol listesi', 'Veritabanı ve upload klasörü haftalık.', 'taslak', 4, '2026-09-25 12:06:00', NULL);

-- --------------------------------------------------------
-- parola_sifirlama
-- --------------------------------------------------------

CREATE TABLE `parola_sifirlama` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(10) unsigned NOT NULL,
  `ozet` char(64) NOT NULL,
  `tur` enum('sifirlama','acilis') NOT NULL DEFAULT 'sifirlama',
  `son_gecerlilik` datetime NOT NULL,
  `kullanildi_at` datetime DEFAULT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parola_sifirlama_ozet` (`ozet`),
  KEY `idx_parola_sifirlama_kullanici` (`kullanici_id`,`created_at`),
  CONSTRAINT `fk_parola_sifirlama_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
-- sayfalar
-- --------------------------------------------------------

CREATE TABLE `sayfalar` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `baslik` varchar(190) NOT NULL,
  `slug` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ozet` varchar(255) NOT NULL DEFAULT '',
  `icerik` mediumtext DEFAULT NULL,
  `kapak` varchar(191) NOT NULL DEFAULT '',
  `durum` enum('taslak','yayin') NOT NULL DEFAULT 'taslak',
  `menude` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Üst menüde görünsün mü?',
  `korumali` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Silinemeyen çekirdek sayfa',
  `sira` smallint(5) unsigned NOT NULL DEFAULT 0,
  `seo_baslik` varchar(190) NOT NULL DEFAULT '',
  `seo_aciklama` varchar(255) NOT NULL DEFAULT '',
  `yazar_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sayfalar_slug` (`slug`),
  KEY `idx_sayfalar_durum` (`durum`,`sira`),
  KEY `fk_sayfalar_yazar` (`yazar_id`),
  CONSTRAINT `fk_sayfalar_yazar` FOREIGN KEY (`yazar_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO `sayfalar` (`id`, `baslik`, `slug`, `ozet`, `icerik`, `kapak`, `durum`, `menude`, `korumali`, `sira`, `seo_baslik`, `seo_aciklama`, `yazar_id`, `created_at`, `updated_at`) VALUES
(1, 'Hakkımızda', 'hakkimizda', 'CY PHP Starter nedir, kimin için yapıldı ve nasıl bir felsefeyle geliştiriliyor.', '<h2>CY PHP Starter nedir?</h2><p>CY PHP Starter, ÇILGIN Yazılım\'ın her yeni PHP projesinde tekrar tekrar yazdığı altyapıyı tek pakette topladığı <strong>açık kaynak bir başlangıç şablonudur</strong>: kurulum sihirbazı, rol tabanlı yönetim paneli, REST API, e-posta kuyruğu, modül sistemi ve PWA. Composer, framework ya da CDN gerektirmez.</p><h3>Kimin için?</h3><ul><li>Kurumsal site, yönetim paneli ya da küçük bir SaaS projesine hızlı başlamak isteyen geliştiriciler</li><li>Composer kurulamayan paylaşımlı hosting kullananlar</li><li>PHP öğrenen ve gerçek bir projenin iskeletini satır satır incelemek isteyenler</li></ul><h3>Felsefe</h3><blockquote>Çekirdek ne iş yaptığınızı bilmez; işinizin nasıl çalışacağını sağlar.</blockquote><p>Her parça okunabilir, açıklamalı ve tek başına değiştirilebilir. Bir özelliği eklemek için çekirdeğe dokunmazsınız; modül yazarsınız.</p><h3>ÇILGIN Yazılım</h3><p>Şablonun ayrıntılı tanıtımı <a href=\"https://cilginyazilim.com/kutuphane/php-baslangic-sablonu\">PHP Başlangıç Şablonu</a> sayfasında; diğer açık kaynak çalışmalarımız <a href=\"https://cilginyazilim.com/kutuphane\">cilginyazilim.com/kutuphane</a> adresinde.</p>', '', 'yayin', 1, 1, 10, '', 'CY PHP Starter, ÇILGIN Yazılım\'ın açık kaynak PHP 8 başlangıç şablonu: kurulum sihirbazı, yönetim paneli, REST API ve modül sistemi.', NULL, '2026-10-07 15:05:59', '2026-10-07 15:06:00'),
(2, 'İletişim', 'iletisim', 'Bize ulaşmanın tüm yolları: telefon, e-posta, adres ve iletişim formu.', '<p>Sorularınız, teklif talepleriniz ve iş birliği önerileriniz için aşağıdaki formu doldurabilir ya da doğrudan iletişim bilgilerimizi kullanabilirsiniz. Mesajlarınıza <strong>en geç bir iş günü içinde</strong> dönüş yapıyoruz.</p>', '', 'yayin', 1, 1, 20, '', '', NULL, '2026-10-07 15:05:59', '2026-10-07 15:05:59'),
(3, 'Gizlilik ve KVKK', 'gizlilik-ve-kvkk', 'Kişisel verilerinizin nasıl işlendiğini anlatan aydınlatma metni.', '<p><strong>Bu metin bir şablondur.</strong> Sitenizi yayına almadan önce kendi kurum bilgilerinizle ve gerçekten işlediğiniz verilerle güncelleyin; gerekirse bir hukuk danışmanına gösterin. Panel → Sayfalar → Gizlilik ve KVKK.</p><h2>Veri sorumlusu</h2><p>[Kurum unvanı], [adres], [e-posta adresi].</p><h2>Hangi verileri işliyoruz?</h2><ul><li><strong>İletişim formu:</strong> ad, e-posta, konu ve mesaj; kötüye kullanımı önlemek için IP adresi ve tarayıcı bilgisi.</li><li><strong>Üyelik:</strong> ad, soyad, kullanıcı adı, e-posta, isteğe bağlı telefon; parolanın yalnızca geri döndürülemez özeti.</li><li><strong>Güvenlik kayıtları:</strong> giriş denemeleri, IP adresi ve tarih.</li></ul><h2>Neden işliyoruz?</h2><p>Sorularınızı yanıtlamak, hesabınızı yönetmek ve sitenin güvenliğini sağlamak için. Verileriniz pazarlama amacıyla üçüncü kişilerle paylaşılmaz.</p><h2>Ne kadar saklıyoruz?</h2><p>İletişim mesajlarındaki IP ve tarayıcı bilgisi [180] gün sonra silinir. Hesabınızı silmek istediğinizde bilgileriniz 7 günlük bekleme süresinin sonunda kaldırılır.</p><h2>Haklarınız</h2><p>6698 sayılı Kişisel Verilerin Korunması Kanunu\'nun 11. maddesi uyarınca verilerinize erişme, düzeltilmesini ve silinmesini isteme haklarınız vardır. Talepleriniz için [e-posta adresi] adresine yazabilirsiniz.</p><h2>Çerezler</h2><p>Site yalnızca oturumunuzu ve tercihlerinizi (tema, menü) hatırlamak için zorunlu çerezler kullanır. Bir analiz aracı eklediyseniz bu bölümü güncelleyin.</p>', '', 'yayin', 0, 0, 90, '', '', NULL, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(5, 'Başlarken', 'baslarken', 'Dört adımda kurulum, demo hesaplar ve ilk modülünüz.', '<h2>Dört adımda çalışan proje</h2><ol><li><strong>İndirin:</strong> GitHub\'dan ZIP olarak indirin ya da <code>git clone</code> ile alın, sunucunuzun web klasörüne koyun.</li><li><strong>Boş bir veritabanı açın:</strong> phpMyAdmin ya da hosting panelinden; karakter seti utf8mb4.</li><li><strong>Sihirbazı çalıştırın:</strong> tarayıcıda <code>/kurulum</code> adresini açın; veritabanı ve yönetici bilgilerini girin.</li><li><strong>Kurulum klasörünü silin:</strong> son ekrandaki düğmeyle ya da Panel → Sistem Bilgisi\'nden.</li></ol><h2>Demo hesaplar</h2><p>Örnek veriyle kurduysanız şu hesaplarla her rolü deneyebilirsiniz. Demo modunda giriş ekranı bu hesapları tek tıkla girişle listeler.</p><ul><li><strong>Yönetici:</strong> ali.yonetici — her ekran</li><li><strong>Editör:</strong> elif.editor — sayfalar, mesajlar, e-posta geçmişi</li><li><strong>Üye:</strong> mehmet.uye — kendi profili ve Örnek Modül</li></ul><h2>İlk modülünüz</h2><p>Terminalde <code>php cy make:module Stok</code> yazın: listeleme, ekleme ve silmeyle çalışan bir modül oluşur. Panel → Sistem Bilgisi → Modüller\'den açın.</p><h2>Belgeler</h2><ul><li><a href=\"https://github.com/CilginYazilim/cy-php-starter/blob/main/README.md\">README</a> — kurulum, özellikler, API</li><li><a href=\"https://github.com/CilginYazilim/cy-php-starter/blob/main/SISTEM.md\">SISTEM.md</a> — mimari ve genişletme rehberi</li></ul>', '', 'yayin', 1, 0, 20, '', 'CY PHP Starter kurulumu dört adımda: indirin, kurulum sihirbazını açın, demo hesaplarla deneyin, ilk modülünüzü üretin.', NULL, '2026-10-07 15:06:00', '2026-10-07 15:06:00'),
(6, 'Özellikler', 'ozellikler', 'Modüller, roller, REST API, kuyruk ve PWA: şablonla hazır gelen her şeyin özeti.', '<h2>Hazır gelenler</h2><table><thead><tr><th>Alan</th><th>Ne var?</th></tr></thead><tbody><tr><td>Modüller</td><td>Kendi rotası, tabloları, görünümü, menüsü ve yetkileriyle gelen klasörler; panelden aç/kapa.</td></tr><tr><td>Roller</td><td>Yönetici, editör, üye; kayıt düzeyi kurallar için Policy sınıfları.</td></tr><tr><td>REST API</td><td>Bearer anahtarları, okuma/yazma kapsamı, mobil uygulama için oturum uçları, hız sınırı.</td></tr><tr><td>Kuyruk</td><td>Veritabanı kuyruğu, katlanan yeniden deneme, tek cron satırıyla zamanlayıcı.</td></tr><tr><td>E-posta</td><td>SMTP ya da mail(), toplu gönderim, giden her mektubun kaydı.</td></tr><tr><td>PWA</td><td>Panelden yönetilen uygulama künyesi, servis çalışanı, çevrimdışı sayfa.</td></tr><tr><td>Güvenlik</td><td>CSRF, katı CSP, kaba kuvvet kilidi, parola sıfırlama, KVKK onayı.</td></tr></tbody></table>', '', 'yayin', 1, 0, 30, '', 'CY PHP Starter özellikleri: modül sistemi, rol tabanlı yetki, REST API, e-posta kuyruğu, zamanlayıcı ve PWA.', NULL, '2026-10-07 15:06:00', '2026-10-07 15:06:00');

SET FOREIGN_KEY_CHECKS = 1;
