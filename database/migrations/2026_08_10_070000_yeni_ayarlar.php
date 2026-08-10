<?php
/**
 * =====================================================================
 *  MIGRATION: yeni ayar satırları ve grup düzeltmeleri
 * ---------------------------------------------------------------------
 *  Ayarlar formu "ayarlar" tablosundan OTOMATİK üretildiği için yeni
 *  bir alan eklemek = yeni bir satır eklemek. Kod tarafında hiçbir
 *  değişiklik gerekmez.
 *
 *  Bu migration üç iş yapar:
 *    1. WhatsApp, harita, SEO ve favicon ayarlarını ekler
 *    2. Dosya adı tutan ayarları ("site_logo") formdan gizler —
 *       değerleri yükleme kartlarından değişir, elle yazılmaz
 *    3. Artık .env'de tutulan "site_url" satırını kaldırır
 *
 *  INSERT IGNORE: taze kurulumda satırlar kurulum/database.sql ile
 *  zaten gelmiştir; ikinci kez eklemeye çalışmak hata verirdi.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    /** Veri taşıyan migration; DDL yok, işlem güvenli. */
    public function useTransaction(): bool
    {
        return true;
    }

    /** @var array<int,array{0:string,1:string,2:string,3:string,4:string,5:?string,6:int}> */
    private const YENI = [
        // anahtar, deger, grup, tip, etiket, aciklama, sira
        ['site_favicon', '', 'dahili', 'metin', 'Favicon Dosyası', null, 65],

        ['iletisim_whatsapp', '', 'iletisim', 'metin', 'WhatsApp Numarası',
            'Ülke koduyla yazın: +90 541 509 05 83. Boşsa WhatsApp düğmesi hiç görünmez.', 25],
        ['iletisim_whatsapp_mesaj', 'Merhaba, siteniz üzerinden yazıyorum. Bilgi almak istiyorum.',
            'iletisim', 'uzun_metin', 'WhatsApp Hazır Mesajı',
            'Ziyaretçi düğmeye bastığında sohbet kutusuna hazır gelecek metin.', 27],
        ['iletisim_harita', '', 'iletisim', 'uzun_metin', 'Harita Bağlantısı',
            'Google Haritalar "paylaş" adresi. Boşsa harita kartı görünmez.', 50],

        ['seo_baslik_sablonu', '%sayfa% · %site%', 'seo', 'metin', 'Başlık Şablonu',
            'Sekmede görünecek biçim. %sayfa% ve %site% yer tutucularını kullanın.', 5],
        ['seo_google_dogrulama', '', 'seo', 'metin', 'Google Site Doğrulama',
            'Search Console\'un verdiği "content" değeri. Yalnızca kod, etiketin tamamı değil.', 40],
        ['seo_og_gorsel', '', 'seo', 'metin', 'Paylaşım Görseli',
            'upload/ altındaki dosya adı ya da tam adres. Boşsa site logosu kullanılır.', 50],
        ['seo_sitemap_aktif', '1', 'seo', 'onay', 'Site Haritası',
            'sitemap.xml üretilsin mi? Yayınlanan sayfalar haritaya otomatik girer.', 60],
        ['seo_robots_ek', '', 'seo', 'uzun_metin', 'robots.txt Ek Kuralları',
            'Otomatik üretilen robots.txt dosyasının SONUNA eklenir. Her satır bir kural.', 70],
    ];

    public function up(): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO ayarlar (anahtar, deger, grup, tip, etiket, aciklama, sira)
             VALUES (:anahtar, :deger, :grup, :tip, :etiket, :aciklama, :sira)'
        );

        foreach (self::YENI as [$anahtar, $deger, $grup, $tip, $etiket, $aciklama, $sira]) {
            $stmt->execute([
                ':anahtar'  => $anahtar,
                ':deger'    => $deger,
                ':grup'     => $grup,
                ':tip'      => $tip,
                ':etiket'   => $etiket,
                ':aciklama' => $aciklama,
                ':sira'     => $sira,
            ]);
        }

        $this->execute("UPDATE ayarlar SET grup = 'dahili' WHERE anahtar IN ('site_logo', 'site_favicon')");
        $this->execute("DELETE FROM ayarlar WHERE anahtar = 'site_url'");
    }

    public function down(): void
    {
        $anahtarlar = array_column(self::YENI, 0);
        $isaretler  = implode(',', array_fill(0, count($anahtarlar), '?'));

        $this->db->prepare("DELETE FROM ayarlar WHERE anahtar IN ($isaretler)")->execute($anahtarlar);

        $this->execute("UPDATE ayarlar SET grup = 'genel' WHERE anahtar = 'site_logo'");
    }
};
