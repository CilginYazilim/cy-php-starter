<?php
/**
 * =====================================================================
 *  Surum16 – 1.6.0 ile gelen ayar satırları ve tablolar (TEK KAYNAK)
 * ---------------------------------------------------------------------
 *  database/migrations/2026_10_07_020000_surum_1_6.php bunları yazar.
 *  Taze kurulumda da sihirbaz migration'ları çalıştırdığı için aynı
 *  satırlar kurulum/database.sql'e AYRICA kopyalanmaz: bir ayarın tek
 *  bir tanımı olmalı, iki yerde durunca biri bayatlıyordu.
 *
 *  YENİ AYAR TİPLERİ
 *    liste → satır satır düzenlenen JSON dizi. "secenekler" sütunu
 *            satırın alanlarını tanımlar:
 *              {"alanlar":[{"ad":"baslik","etiket":"Başlık"},
 *                          {"ad":"metin","etiket":"Metin","tip":"uzun"}],
 *               "en_fazla":8}
 *            Alan tipleri: metin (varsayılan), uzun, ikon, adres.
 *    coklu → onay kutusu listesi; değer JSON dizi (["hero","sss"]).
 *            "secenekler" {"deger":"Etiket", …} sözlüğüdür.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Support;

final class Surum16
{
    /** Ana sayfa bölümleri: değer => panelde görünen etiket (sıra = sayfadaki sıra). */
    public const ANASAYFA_BOLUMLERI = [
        'hero'       => 'Karşılama (başlık, düğmeler, görsel)',
        'teknoloji'  => 'Teknoloji şeridi',
        'adimlar'    => 'Adımlar',
        'ozellikler' => 'Özellikler',
        'roller'     => 'Rolleri deneyin (yalnızca demo modunda)',
        'kod'        => 'Kod örneği',
        'sss'        => 'Sık sorulan sorular',
        'hakkimizda' => 'Hakkımızda özeti',
        'iletisim'   => 'İletişim kartları',
        'cta'        => 'Son çağrı bandı',
    ];

    /**
     * anahtar, varsayılan değer (NÖTR — marka yalnızca DemoSeeder'da),
     * grup, tip, etiket, açıklama, seçenekler (JSON|null), sıra
     *
     * @var array<int,array{0:string,1:string,2:string,3:string,4:string,5:?string,6:?string,7:int}>
     */
    public const AYARLAR = [
        // ---- GENEL ----
        ['site_marka', '', 'genel', 'metin', 'Marka / Kurum Adı',
            'Panelde site adının altında ve sayfaların künyesinde (author) görünür. Boşsa site adı kullanılır.', null, 15],
        ['site_imza_goster', '1', 'genel', 'onay', 'Şablon İmzası',
            'Alt bilgide küçük bir "CY PHP Starter ile geliştirildi" bağlantısı. İsterseniz kapatın.', null, 60],
        ['vitrin_tanitim_goster', '0', 'genel', 'onay', 'Şablon Vitrin Bağlantıları',
            'Alt bilgide CY PHP Starter belge ve kod bağlantıları (demo vitrini için). Örnek veriyle kurulumda açık gelir.', null, 61],

        // ---- SOSYAL ----
        ['sosyal_pinterest', '', 'sosyal', 'url', 'Pinterest', 'Tam adres: https://www.pinterest.com/hesabiniz', null, 55],

        // ---- SİSTEM ----
        ['sistem_parola_sifirlama', '1', 'sistem', 'onay', 'Parola Sıfırlama',
            '"Parolamı unuttum" bağlantısı. E-posta gönderimi yapılandırılmadıysa kendiliğinden gizlenir.', null, 22],
        ['sistem_kvkk_sayfa', 'gizlilik-ve-kvkk', 'sistem', 'metin', 'Aydınlatma Metni Sayfası',
            'Formlardaki onay kutusunun bağlantı verdiği sayfanın adresi (slug). Boşsa onay kutusu gösterilmez.', null, 26],
        ['sistem_kvkk_onay', '1', 'sistem', 'onay', 'Aydınlatma Metni Onayı',
            'Kayıt ve iletişim formlarında "Aydınlatma metnini okudum" kutusu zorunlu olsun.', null, 27],
        ['sistem_ip_saklama', '180', 'sistem', 'sayi', 'IP Saklama Süresi (gün)',
            'İletişim mesajlarındaki IP ve tarayıcı bilgisi bu süre dolunca silinir (zamanlanmış görev). 0 = hiç silinmez.', null, 28],
        ['sistem_hesap_silme', '1', 'sistem', 'onay', 'Hesap Silme',
            'Üyeler Hesabım ekranından hesaplarını silebilsin. Silme 7 gün sonra gerçekleşir; bu sürede giriş yapan üye iptal eder.', null, 29],

        // ---- DAHİLİ (ekranda görünmez) ----
        ['demo_son_sifirlama', '', 'dahili', 'metin', 'Demo Son Sıfırlama', null, null, 0],

        // ---- ANA SAYFA ----
        ['anasayfa_bolumler', '["hero","ozellikler","hakkimizda","iletisim","cta"]', 'anasayfa', 'coklu', 'Görünen Bölümler',
            'İşaretli bölümler ana sayfada sabit sırayla görünür. İçeriği boş olan bölüm kendiliğinden gizlenir.', 'BOLUMLER', 10],
        ['anasayfa_rozet', '', 'anasayfa', 'metin', 'Rozet',
            'Başlığın üstündeki küçük etiket. {surum}, {php}, {komut} ve {test} yer tutucuları kullanılabilir.', null, 20],
        ['anasayfa_baslik', '', 'anasayfa', 'metin', 'Başlık', 'Boşsa site adı.', null, 30],
        ['anasayfa_metin', '', 'anasayfa', 'uzun_metin', 'Tanıtım Metni', 'Bir iki cümle. Boşsa site sloganı ya da açıklaması.', null, 40],
        ['anasayfa_birincil_metin', 'Bize Ulaşın', 'anasayfa', 'metin', 'Birinci Düğme',
            'Karşılama bölümündeki ana düğmenin yazısı. Boşsa düğme gösterilmez.', null, 50],
        ['anasayfa_birincil_adres', 'iletisim', 'anasayfa', 'metin', 'Birinci Düğmenin Adresi',
            'Site içi yol (örn. iletisim) ya da https:// ile başlayan tam adres.', null, 51],
        ['anasayfa_ikincil_metin', 'Hakkımızda', 'anasayfa', 'metin', 'İkinci Düğme', 'Boşsa gösterilmez.', null, 52],
        ['anasayfa_ikincil_adres', 'hakkimizda', 'anasayfa', 'metin', 'İkinci Düğmenin Adresi',
            'Site içi yol (örn. hakkimizda) ya da https:// ile başlayan tam adres.', null, 53],
        ['anasayfa_guven', '', 'anasayfa', 'metin', 'Güven Satırı',
            'Düğmelerin altındaki küçük yazı, örn. "PHP {php} · {test} test · MIT".', null, 54],
        ['anasayfa_gorsel', '', 'anasayfa', 'metin', 'Karşılama Görseli',
            'Boşsa logo kartı. "vitrin" yazarsanız panelin ekran görüntüsü (açık/koyu tema), ya da upload/ altındaki bir dosya adı.', null, 55],
        ['anasayfa_ozellikler', '[]', 'anasayfa', 'liste', 'Özellikler',
            'İkon, başlık ve tek cümle. İlk iki kart büyük gösterilir.',
            '{"alanlar":[{"ad":"ikon","etiket":"İkon","tip":"ikon"},{"ad":"baslik","etiket":"Başlık"},{"ad":"metin","etiket":"Açıklama","tip":"uzun"}],"en_fazla":8}', 60],
        ['anasayfa_adimlar', '[]', 'anasayfa', 'liste', 'Adımlar', 'Numaralı adımlar (en fazla 4).',
            '{"alanlar":[{"ad":"baslik","etiket":"Başlık"},{"ad":"metin","etiket":"Açıklama","tip":"uzun"}],"en_fazla":4}', 70],
        ['anasayfa_sss', '[]', 'anasayfa', 'liste', 'Sık Sorulan Sorular',
            'Soru ve cevap çiftleri (en fazla 10). Cevaplar düz metindir; boş liste bölümü gizler.',
            '{"alanlar":[{"ad":"soru","etiket":"Soru"},{"ad":"cevap","etiket":"Cevap","tip":"uzun"}],"en_fazla":10}', 80],
        ['anasayfa_cta_baslik', 'Bir sorunuz mu var?', 'anasayfa', 'metin', 'Son Bant Başlığı',
            'Sayfanın sonundaki renkli bandın başlığı. Boşsa bant gösterilmez.', null, 90],
        ['anasayfa_cta_metin', 'Formu doldurun, en kısa sürede dönüş yapalım.', 'anasayfa', 'metin', 'Son Bant Metni',
            'Bant başlığının altındaki tek cümle.', null, 91],
        ['anasayfa_cta_dugmeler', '[{"metin":"İletişim Formu","adres":"iletisim"}]', 'anasayfa', 'liste', 'Son Bant Düğmeleri',
            'En fazla 3 düğme. Adres site içi yol (örn. iletisim) ya da https:// ile başlayan tam adres olabilir.',
            '{"alanlar":[{"ad":"metin","etiket":"Düğme metni"},{"ad":"adres","etiket":"Adres","tip":"adres"}],"en_fazla":3}', 92],
    ];

    /** Yer tutucu "BOLUMLER" yerine bölüm listesinin JSON'u yazılır. */
    public static function secenekler(?string $deger): ?string
    {
        return $deger === 'BOLUMLER'
            ? (string) json_encode(self::ANASAYFA_BOLUMLERI, JSON_UNESCAPED_UNICODE)
            : $deger;
    }

    public const PAROLA_SIFIRLAMA_TABLOSU = <<<'SQL'
CREATE TABLE `parola_sifirlama` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id`   INT UNSIGNED NOT NULL,
  -- Jetonun SHA-256 özeti. Jetonun kendisi yalnızca mektupta durur:
  -- veritabanı sızsa bile bağlantı üretilemez.
  `ozet`           CHAR(64)     NOT NULL,
  `son_gecerlilik` DATETIME     NOT NULL,
  `kullanildi_at`  DATETIME     NULL DEFAULT NULL,
  `ip`             VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parola_sifirlama_ozet` (`ozet`),
  KEY `idx_parola_sifirlama_kullanici` (`kullanici_id`, `created_at`),
  CONSTRAINT `fk_parola_sifirlama_kullanici`
    FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
SQL;

    /** Nötr, şablon niteliğinde aydınlatma metni. Kendi bilgilerinizle güncelleyin. */
    public const KVKK_SAYFA = [
        'baslik' => 'Gizlilik ve KVKK',
        'slug'   => 'gizlilik-ve-kvkk',
        'ozet'   => 'Kişisel verilerinizin nasıl işlendiğini anlatan aydınlatma metni.',
        'icerik' => '<p><strong>Bu metin bir şablondur.</strong> Sitenizi yayına almadan önce kendi kurum bilgilerinizle ve gerçekten işlediğiniz verilerle güncelleyin; gerekirse bir hukuk danışmanına gösterin. Panel → Sayfalar → Gizlilik ve KVKK.</p>'
            . '<h2>Veri sorumlusu</h2><p>[Kurum unvanı], [adres], [e-posta adresi].</p>'
            . '<h2>Hangi verileri işliyoruz?</h2><ul>'
            . '<li><strong>İletişim formu:</strong> ad, e-posta, konu ve mesaj; kötüye kullanımı önlemek için IP adresi ve tarayıcı bilgisi.</li>'
            . '<li><strong>Üyelik:</strong> ad, soyad, kullanıcı adı, e-posta, isteğe bağlı telefon; parolanın yalnızca geri döndürülemez özeti.</li>'
            . '<li><strong>Güvenlik kayıtları:</strong> giriş denemeleri, IP adresi ve tarih.</li></ul>'
            . '<h2>Neden işliyoruz?</h2><p>Sorularınızı yanıtlamak, hesabınızı yönetmek ve sitenin güvenliğini sağlamak için. Verileriniz pazarlama amacıyla üçüncü kişilerle paylaşılmaz.</p>'
            . '<h2>Ne kadar saklıyoruz?</h2><p>İletişim mesajlarındaki IP ve tarayıcı bilgisi [180] gün sonra silinir. Hesabınızı silmek istediğinizde bilgileriniz 7 günlük bekleme süresinin sonunda kaldırılır.</p>'
            . '<h2>Haklarınız</h2><p>6698 sayılı Kişisel Verilerin Korunması Kanunu\'nun 11. maddesi uyarınca verilerinize erişme, düzeltilmesini ve silinmesini isteme haklarınız vardır. Talepleriniz için [e-posta adresi] adresine yazabilirsiniz.</p>'
            . '<h2>Çerezler</h2><p>Site yalnızca oturumunuzu ve tercihlerinizi (tema, menü) hatırlamak için zorunlu çerezler kullanır. Bir analiz aracı eklediyseniz bu bölümü güncelleyin.</p>',
    ];
}
