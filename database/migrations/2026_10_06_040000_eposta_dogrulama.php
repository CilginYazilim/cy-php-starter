<?php
/**
 * =====================================================================
 *  MIGRATION: Kayıtta e-posta doğrulaması
 * ---------------------------------------------------------------------
 *  1. kullanicilar.durum'a "onay_bekliyor" değeri eklenir: kayıt
 *     formundan açılan hesap, e-postadaki bağlantıya tıklanana kadar
 *     giriş yapamaz.
 *  2. "sistem_kayit_dogrulama" ayarı eklenir (varsayılan AÇIK).
 *
 *  NEDEN? Kayıt formu herkese açıktı ve hoş geldin mektubu girilen her
 *  adrese gidiyordu: siteyi, başkalarının adresine kendi adıyla mektup
 *  attırmak için kullanmak mümkündü. Form ayrıca "bu e-posta zaten
 *  kayıtlı" diyerek hangi adreslerin üye olduğunu ele veriyordu.
 *  Doğrulama açıkken iki durumda da aynı yanıt verilir ve mektup
 *  yalnızca adresin sahibinin tıklayacağı bir bağlantı içerir.
 *
 *  Bağlantı tablo gerektirmez: APP_KEY ile imzalanmış "kullanıcı +
 *  e-posta + son kullanma" taşır (bkz. App\Core\Signer).
 *
 *  Taze kurulumda ikisi de kurulum/database.sql ile gelir.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    /** Çekirdek migration: temel partiye (0) yazılır, geri alınmaz (bkz. Migration::baseline). */
    public function baseline(): bool
    {
        return true;
    }

    public function up(): void
    {
        $this->execute(
            "ALTER TABLE `kullanicilar`
               MODIFY `durum` ENUM('aktif','pasif','askida','onay_bekliyor') NOT NULL DEFAULT 'aktif'"
        );

        $this->db->prepare(
            'INSERT IGNORE INTO ayarlar (anahtar, deger, grup, tip, etiket, aciklama, secenekler, sira)
             VALUES (:anahtar, :deger, :grup, :tip, :etiket, :aciklama, NULL, :sira)'
        )->execute([
            ':anahtar'  => 'sistem_kayit_dogrulama',
            ':deger'    => '1',
            ':grup'     => 'sistem',
            ':tip'      => 'onay',
            ':etiket'   => 'Kayıtta E-posta Doğrulaması',
            ':aciklama' => 'Yeni hesap, e-postadaki bağlantıya tıklanana kadar giriş yapamaz. E-posta ayarları eksikse kayıt formu kapanır.',
            ':sira'     => 21,
        ]);
    }

    public function down(): void
    {
        // Onay bekleyen hesaplar geri alınan şemada tutulamaz; pasife alınır.
        $this->execute("UPDATE `kullanicilar` SET `durum` = 'pasif' WHERE `durum` = 'onay_bekliyor'");

        $this->execute(
            "ALTER TABLE `kullanicilar`
               MODIFY `durum` ENUM('aktif','pasif','askida') NOT NULL DEFAULT 'aktif'"
        );

        $this->execute("DELETE FROM ayarlar WHERE anahtar = 'sistem_kayit_dogrulama'");
    }
};
