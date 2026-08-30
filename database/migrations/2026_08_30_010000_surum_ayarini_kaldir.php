<?php
/**
 * =====================================================================
 *  MIGRATION: "sistem_surum" ayarını kaldır
 * ---------------------------------------------------------------------
 *  Şablonun sürüm numarası artık KODDA duruyor: config/app.php →
 *  'version'. Veritabanındaki kopya kaldırılıyor.
 *
 *  NEDEN? Sürüm, kodla birlikte gelen bir bilgidir. Veritabanında
 *  tutulunca üç ayrı numara ortaya çıkmıştı:
 *
 *      kurulum/database.sql ......... 2.1.0
 *      SystemController varsayılanı . 1.0.0
 *      GitHub sürüm etiketi ......... v1.0.0
 *
 *  Daha kötüsü: şablonu yeni sürüme yükselten kişi bu migration'ı
 *  çalıştırmadıkça Sistem Bilgisi sayfası ESKİ numarayı göstermeye
 *  devam ediyordu — yani numara, en çok güvenilmesi gereken yerde
 *  yanıltıyordu. Koddaki değer her zaman doğrudur ve güncellemeyle
 *  kendiliğinden gelir.
 *
 *  Ayar zaten "duzenlenebilir = 0" idi; kimsenin panelden girdiği bir
 *  veri değil, silinmesi veri kaybı sayılmaz.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    /** Tek satırlık veri temizliği; DDL yok, işlem güvenli. */
    public function useTransaction(): bool
    {
        return true;
    }

    public function up(): void
    {
        $this->execute("DELETE FROM ayarlar WHERE anahtar = 'sistem_surum'");
    }

    /**
     * Geri alındığında satır eski haliyle döner. Değer olarak koddaki
     * sürümü yazıyoruz: sabit bir numara yazmak, geri dönen kurulumu
     * yine yanlış bilgiyle baş başa bırakırdı.
     */
    public function down(): void
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO ayarlar
                 (anahtar, deger, grup, tip, etiket, aciklama, sira, duzenlenebilir)
             VALUES
                 ('sistem_surum', :deger, 'sistem', 'metin', 'Sürüm', 'Şablon sürümü.', 90, 0)"
        );

        $stmt->execute([':deger' => (string) App\Core\Config::get('app.version', '1.0.0')]);
    }
};
