<?php
/**
 * =====================================================================
 *  MIGRATION: 1.6.1 — bildirimler
 * ---------------------------------------------------------------------
 *  · ayarlar: "Yeni Üye Bildirimi" (E-posta grubu, varsayılan kapalı)
 *  · kullanicilar.bildirim_tercihleri: üyenin duyuruları kapatabilmesi
 *    (JSON, NULL = varsayılan). Taze kurulumda sütun database.sql ile
 *    gelir; burada yalnızca eksikse eklenir.
 *
 *  Birden fazla kez çalıştırılabilir.
 * =====================================================================
 */

declare(strict_types=1);

use App\Support\Surum161;

return new class extends App\Core\Database\Migration
{
    /** Çekirdek migration: temel partiye (0) yazılır, geri alınmaz (bkz. Migration::baseline). */
    public function baseline(): bool
    {
        return true;
    }

    public function up(): void
    {
        // INSERT IGNORE: var olan değere (yönetici değiştirmiş olabilir) dokunulmaz.
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO ayarlar (anahtar, deger, grup, tip, etiket, aciklama, secenekler, sira)
             VALUES (:anahtar, :deger, :grup, :tip, :etiket, :aciklama, :secenekler, :sira)'
        );

        foreach (Surum161::AYARLAR as [$anahtar, $deger, $grup, $tip, $etiket, $aciklama, $secenekler, $sira]) {
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

        if (!$this->columnExists('kullanicilar', 'bildirim_tercihleri')) {
            $this->execute('ALTER TABLE kullanicilar ' . Surum161::TERCIH_SUTUNU);
        }
    }

    public function down(): void
    {
        /* Baseline migration'dır, rollback ile çalışmaz; yine de elle
         * çağrılırsa sütunu kaldırır (ayar satırı kalır). */
        if ($this->columnExists('kullanicilar', 'bildirim_tercihleri')) {
            $this->execute('ALTER TABLE kullanicilar DROP COLUMN bildirim_tercihleri');
        }
    }
};
