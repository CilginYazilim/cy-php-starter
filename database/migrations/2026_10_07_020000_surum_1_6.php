<?php
/**
 * =====================================================================
 *  MIGRATION: 1.6.0 — ana sayfa ayarları, parola sıfırlama, KVKK,
 *  hesap silme ve mobil uygulama için API oturumları
 * ---------------------------------------------------------------------
 *  · ayarlar: "Ana Sayfa" grubu (metinler panelden), marka adı,
 *    şablon imzası, vitrin bağlantıları, Pinterest, parola sıfırlama,
 *    KVKK onayı, IP saklama süresi, hesap silme
 *  · parola_sifirlama: tek kullanımlık sıfırlama jetonlarının ÖZETİ
 *  · kullanicilar.silinme_at: üyenin istediği silme (7 gün bekleme)
 *  · api_anahtarlari: kapsam (okuma/yazma), tür (anahtar/oturum),
 *    cihaz adı — mobil uygulama oturum açınca "oturum" türünde anahtar
 *    alır, kullanıcı cihazlarını Hesabım ekranında görüp kapatır
 *  · sayfalar: "Gizlilik ve KVKK" şablon sayfası (yoksa)
 *
 *  Taze kurulumda hepsi kurulum/database.sql ile gelir; bu migration
 *  var olan kurulumları yükseltir. Birden fazla kez çalıştırılabilir.
 * =====================================================================
 */

declare(strict_types=1);

use App\Support\Surum16;

return new class extends App\Core\Database\Migration
{
    /** Çekirdek migration: temel partiye (0) yazılır, geri alınmaz (bkz. Migration::baseline). */
    public function baseline(): bool
    {
        return true;
    }

    public function up(): void
    {
        /* ÖNCE ayar tipi listesi genişletilir (yeni satırlar bu tipleri kullanır):: liste (satır satır düzenlenen
         * JSON) ve coklu (onay kutusu listesi). ENUM genişletilir;
         * var olan değerler korunur. */
        $this->execute(
            "ALTER TABLE ayarlar MODIFY tip ENUM('metin','uzun_metin','sayi','eposta','url','secim','onay','renk','sifre','liste','coklu')
             NOT NULL DEFAULT 'metin'"
        );

        /* AYARLAR — INSERT IGNORE: var olan değere (yönetici
         * değiştirmiş olabilir) dokunulmaz. */
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO ayarlar (anahtar, deger, grup, tip, etiket, aciklama, secenekler, sira)
             VALUES (:anahtar, :deger, :grup, :tip, :etiket, :aciklama, :secenekler, :sira)'
        );

        foreach (Surum16::AYARLAR as [$anahtar, $deger, $grup, $tip, $etiket, $aciklama, $secenekler, $sira]) {
            $stmt->execute([
                ':anahtar'    => $anahtar,
                ':deger'      => $deger,
                ':grup'       => $grup,
                ':tip'        => $tip,
                ':etiket'     => $etiket,
                ':aciklama'   => $aciklama,
                ':secenekler' => Surum16::secenekler($secenekler),
                ':sira'       => $sira,
            ]);
        }

        if (!$this->tableExists('parola_sifirlama')) {
            $this->execute(Surum16::PAROLA_SIFIRLAMA_TABLOSU);
        }

        if (!$this->columnExists('kullanicilar', 'silinme_at')) {
            $this->execute("ALTER TABLE kullanicilar ADD COLUMN silinme_at DATETIME NULL DEFAULT NULL COMMENT 'Üyenin istediği silme zamanı (bekleme süresi sonunda silinir)' AFTER giris_sayisi");
        }
        if (!$this->indexExists('kullanicilar', 'idx_kullanicilar_silinme')) {
            $this->execute('ALTER TABLE kullanicilar ADD KEY idx_kullanicilar_silinme (silinme_at)');
        }

        if ($this->tableExists('api_anahtarlari')) {
            if (!$this->columnExists('api_anahtarlari', 'kapsam')) {
                // Var olan anahtarlar eskisi gibi her şeyi yapabilsin: yazma.
                $this->execute("ALTER TABLE api_anahtarlari ADD COLUMN kapsam ENUM('okuma','yazma') NOT NULL DEFAULT 'yazma' AFTER ozet");
            }
            if (!$this->columnExists('api_anahtarlari', 'tur')) {
                $this->execute("ALTER TABLE api_anahtarlari ADD COLUMN tur ENUM('anahtar','oturum') NOT NULL DEFAULT 'anahtar' AFTER kapsam");
            }
            if (!$this->columnExists('api_anahtarlari', 'cihaz')) {
                $this->execute("ALTER TABLE api_anahtarlari ADD COLUMN cihaz VARCHAR(100) NOT NULL DEFAULT '' AFTER tur");
            }
        }

        $sayfa = $this->db->prepare('SELECT COUNT(*) FROM sayfalar WHERE slug = :slug');
        $sayfa->execute([':slug' => Surum16::KVKK_SAYFA['slug']]);

        if ((int) $sayfa->fetchColumn() === 0) {
            $this->db->prepare(
                'INSERT INTO sayfalar (baslik, slug, ozet, icerik, durum, menude, korumali, sira)
                 VALUES (:baslik, :slug, :ozet, :icerik, \'yayin\', 0, 0, 90)'
            )->execute([
                ':baslik' => Surum16::KVKK_SAYFA['baslik'],
                ':slug'   => Surum16::KVKK_SAYFA['slug'],
                ':ozet'   => Surum16::KVKK_SAYFA['ozet'],
                ':icerik' => Surum16::KVKK_SAYFA['icerik'],
            ]);
        }
    }

    public function down(): void
    {
        /* Baseline migration'dır, rollback ile çalışmaz; yine de elle
         * çağrılırsa yapıyı geri alır (ayar satırları ve sayfa kalır). */
        if ($this->tableExists('parola_sifirlama')) {
            $this->execute('DROP TABLE parola_sifirlama');
        }
    }
};
