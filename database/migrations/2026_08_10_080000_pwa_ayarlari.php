<?php
/**
 * =====================================================================
 *  MIGRATION: PWA ayar alanları ve kendi ayar grubu
 * ---------------------------------------------------------------------
 *  Sistemde PWA vardı ama tek bir açma/kapama anahtarından ibaretti:
 *  uygulama adı, simgesi, açılış adresi ve görüntüleme modu koda
 *  gömülüydü. Bu migration künyenin (manifest) her alanını panele
 *  taşır ve hepsini "pwa" adlı yeni bir gruba toplar.
 *
 *  NEDEN AYRI GRUP? "Sistem" bölümü bakım modu, kayıt, sayfalama ve
 *  zaman dilimini barındırıyor; PWA'nın on alanı oraya girseydi bölüm
 *  iki farklı konunun karışımı olurdu. Ayrı grup aynı zamanda sol
 *  menüde ve genel bakış kartlarında kendiliğinden görünür
 *  (bkz. Setting::groupLabels()).
 *
 *  "pwa_aktif" YENİDEN OLUŞTURULMAZ, TAŞINIR: değeri korunmalı —
 *  PWA'yı açmış bir sitede migration çalıştığında uygulama modu
 *  kapanmamalı.
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

    /** @var array<int,array{0:string,1:string,2:string,3:string,4:string,5:?string,6:?string,7:int}> */
    private const YENI = [
        // anahtar, deger, grup, tip, etiket, aciklama, secenekler, sira
        ['pwa_ad', '', 'pwa', 'metin', 'Uygulama Adı',
            'Kurulum penceresinde ve uygulama listesinde görünen tam ad. Boşsa site adı kullanılır.', null, 20],
        ['pwa_kisa_ad', '', 'pwa', 'metin', 'Kısa Ad',
            'Ana ekranda simgenin altında yazar; 12 karakteri geçmesin. Boşsa uygulama adının başı kullanılır.', null, 30],
        ['pwa_aciklama', '', 'pwa', 'uzun_metin', 'Uygulama Açıklaması',
            'Kurulum penceresinde görünür. Boşsa site açıklaması kullanılır.', null, 40],
        ['pwa_baslangic', '', 'pwa', 'metin', 'Açılış Adresi',
            'Uygulama açıldığında gidilecek sayfa; site köküne göre yazın (örn. panel). Boşsa ana sayfa açılır.', null, 50],
        ['pwa_gorunum', 'standalone', 'pwa', 'secim', 'Görüntüleme Modu',
            'standalone: adres çubuğu olmadan, ayrı bir uygulama gibi. fullscreen: tam ekran. minimal-ui: ince gezinme çubuğuyla. browser: normal sekmede.',
            '["standalone","fullscreen","minimal-ui","browser"]', 60],
        ['pwa_yon', 'any', 'pwa', 'secim', 'Ekran Yönü',
            'any: cihaz nasıl tutulursa. portrait: yalnızca dikey. landscape: yalnızca yatay.',
            '["any","portrait","landscape"]', 70],
        ['pwa_arka_renk', '#ffffff', 'pwa', 'renk', 'Açılış Arka Plan Rengi',
            'Uygulama açılırken simgenin arkasında görünen renk. Tema rengi buradan değil, Sistem ayarlarından gelir.', null, 80],
        ['pwa_cevrimdisi', '1', 'pwa', 'onay', 'Çevrimdışı Çalışma',
            'Servis çalışanı sayfaları önbelleğe alır; ağ yokken site yine açılır. Kapatırsanız ziyaretçilerin tarayıcısındaki kayıtlı servis çalışanı ve önbellek de temizlenir.', null, 90],

        // Dosya adı tutar, elle yazılmaz: yan sütundaki yükleme kartından değişir.
        ['pwa_simge', '', 'dahili', 'metin', 'Uygulama Simgesi', null, null, 95],
    ];

    public function up(): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO ayarlar (anahtar, deger, grup, tip, etiket, aciklama, secenekler, sira)
             VALUES (:anahtar, :deger, :grup, :tip, :etiket, :aciklama, :secenekler, :sira)'
        );

        foreach (self::YENI as [$anahtar, $deger, $grup, $tip, $etiket, $aciklama, $secenekler, $sira]) {
            $stmt->execute([
                ':anahtar'    => $anahtar,
                ':deger'      => $deger,
                ':grup'       => $grup,
                ':tip'        => $tip,
                ':etiket'     => $etiket,
                ':aciklama'   => $aciklama,
                ':secenekler' => $secenekler,
                ':sira'       => $sira,
            ]);
        }

        /* Var olan anahtarı yeni grubun başına taşı — değerine dokunmadan. */
        $this->execute(
            "UPDATE ayarlar
                SET grup = 'pwa',
                    sira = 10,
                    aciklama = 'Açıkken ziyaretçi siteyi telefonuna uygulama olarak kurabilir. Kapalıyken sayfalara PWA ile ilgili tek bir etiket bile eklenmez.'
              WHERE anahtar = 'pwa_aktif'"
        );
    }

    public function down(): void
    {
        $anahtarlar = array_column(self::YENI, 0);
        $isaretler  = implode(',', array_fill(0, count($anahtarlar), '?'));

        $this->db->prepare("DELETE FROM ayarlar WHERE anahtar IN ($isaretler)")->execute($anahtarlar);

        $this->execute("UPDATE ayarlar SET grup = 'sistem', sira = 60 WHERE anahtar = 'pwa_aktif'");
    }
};
