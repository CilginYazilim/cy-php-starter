-- ===============================================================
--  ÖRNEK (DEMO) VERİ — İSTEĞE BAĞLI
--  cilginyazilim.com – PHP Başlangıç Şablonu
-- ---------------------------------------------------------------
--  Bu dosya kurulum sihirbazında "Örnek verileri yükle" kutusu
--  işaretlenirse database.sql'den SONRA çalıştırılır.
--
--  NEDEN AYRI BİR DOSYA?
--  Şablonu yeni bir projeye kurarken temiz bir veritabanı
--  istersiniz. Ama şablonu ilk kez deniyorsanız boş bir kullanıcı
--  listesinde filtrelerin, sayfalamanın ve rol rozetlerinin ne işe
--  yaradığını göremezsiniz. İkisini de karşılamak için örnek veri
--  kuruluma dahil ama VARSAYILAN OLARAK KAPALIDIR.
--
--  TÜM DEMO KULLANICILARIN PAROLASI:  Demo1234!
--  Canlıya çıkmadan önce bunları SİLİN:
--      DELETE FROM kullanicilar WHERE eposta LIKE '%@ornek.com';
-- ===============================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
--  Kullanıcılar — listeyi rol ve durum filtreleriyle denemek için
--  her rolden ve her durumdan birer örnek.
-- ---------------------------------------------------------------
INSERT INTO `kullanicilar`
    (`ad`, `soyad`, `kullanici_adi`, `eposta`, `sifre`, `rol`, `durum`) VALUES
('Elif',  'Demir',  'elif.editor', 'elif.demo@ornek.com',   '$2y$10$cm2Mn/e2yxT/xSGmiefaEuCZy/Vg8SOoVNMtR8i6NKVxLmAAbP79q', 'editor', 'aktif'),
('Mehmet','Kaya',   'mehmet.uye',  'mehmet.demo@ornek.com', '$2y$10$cm2Mn/e2yxT/xSGmiefaEuCZy/Vg8SOoVNMtR8i6NKVxLmAAbP79q', 'uye',    'aktif'),
('Ayşe',  'Şahin',  'ayse.pasif',  'ayse.demo@ornek.com',   '$2y$10$cm2Mn/e2yxT/xSGmiefaEuCZy/Vg8SOoVNMtR8i6NKVxLmAAbP79q', 'uye',    'pasif'),
('Can',   'Yıldız', 'can.askida',  'can.demo@ornek.com',    '$2y$10$cm2Mn/e2yxT/xSGmiefaEuCZy/Vg8SOoVNMtR8i6NKVxLmAAbP79q', 'uye',    'askida');

-- ---------------------------------------------------------------
--  İletişim mesajları — biri okunmuş, biri okunmamış. Panelin
--  "okunmamış" rozetinin ve toplu işlemlerin çalıştığını görürsünüz.
-- ---------------------------------------------------------------
INSERT INTO `mesajlar`
    (`ad`, `eposta`, `konu`, `mesaj`, `okundu`, `ip`) VALUES
('Zeynep Arslan', 'zeynep.demo@ornek.com', 'Teklif talebi',
 'Merhaba, kurumsal web sitesi yenileme projemiz için fiyat teklifi almak istiyoruz. Dönüş yapabilir misiniz?', 0, '127.0.0.1'),
('Burak Öztürk',  'burak.demo@ornek.com',  'Destek',
 'Panelde e-posta ayarlarını yaptım ancak test mektubu ulaşmadı. Yardımcı olabilir misiniz?', 1, '127.0.0.1');
