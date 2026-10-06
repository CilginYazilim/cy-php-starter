<?php
/**
 * =====================================================================
 *  MIGRATION: Çekirdek migration'ları TEMEL PARTİ'ye (0) taşı
 * ---------------------------------------------------------------------
 *  Parti 0'daki kayıtlar "php cy migrate:rollback" / "migrate:fresh"
 *  ile GERİ ALINMAZ (bkz. Migrator::BASELINE).
 *
 *  NEDEN GEREKLİ? 1.3.0 temel partiyi getirdi ama yalnızca YENİ
 *  kurulumlar için:
 *
 *    · 1.2.1'den yükseltilen kurulumda sihirbazın çalıştırdığı
 *      migration'lar 1. partide kaldı. Güncellemeden sonraki ilk
 *      "migrate:rollback" kurulum tablolarını (onbellek, isler,
 *      api_anahtarlari…) siliyordu. Bunu önleyen tek adım CHANGELOG'da
 *      elle yazılacak bir SQL'di; README'deki güncelleme kutusunda
 *      bile yoktu.
 *    · Elle kurulumda (kurulum/database.sql + php cy migrate) da
 *      database.sql'in oluşturmadığı tablolar 1. partiye yazılıyordu.
 *
 *  Bu migration ÇEKİRDEKLE GELEN migration'ları (listede adı olanlar)
 *  partilerinden bağımsız olarak 0'a taşır. Sizin eklediğiniz ve
 *  modüllerden gelen migration'lara DOKUNMAZ.
 *
 *  Bu liste yalnızca 1.4.0'dan ÖNCE yazılmış kayıtlar içindir. 1.4.0'dan
 *  itibaren çekirdek migration'lar baseline() ile kendilerini temel
 *  partiye yazar (bkz. App\Core\Database\Migration::baseline); yeni bir
 *  çekirdek migration'ı buraya eklemek gerekmez. Şemaya giren bir
 *  değişikliği getiriyorsa adını kurulum/database.sql'in sonundaki
 *  listeye yazmak yeterlidir.
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

    /** Çekirdekle gelen migration'lar (bu dosya hariç; kendi kaydını Migrator yazar). */
    private const CEKIRDEK = [
        '2026_08_10_010000_onbellek_tablosu',
        '2026_08_10_020000_isler_tablosu',
        '2026_08_10_030000_api_anahtarlari_tablosu',
        '2026_08_10_040000_mesajlar_ip_indeksi',
        '2026_08_10_050000_sayfalar_tablosu',
        '2026_08_10_060000_beni_hatirla_sutunlari',
        '2026_08_10_070000_yeni_ayarlar',
        '2026_08_10_080000_pwa_ayarlari',
        '2026_08_30_010000_surum_ayarini_kaldir',
        '2026_10_06_010000_oturum_surumu',
        '2026_10_06_020000_mail_kuyrugu_kilidi',
        '2026_10_06_030000_giris_denemeleri_ip_indeksi',
        '2026_10_06_040000_eposta_dogrulama',
    ];

    public function up(): void
    {
        $isaretler = implode(',', array_fill(0, count(self::CEKIRDEK), '?'));

        $this->db
            ->prepare("UPDATE `migrasyonlar` SET `parti` = 0 WHERE `dosya` IN ($isaretler)")
            ->execute(self::CEKIRDEK);
    }

    /**
     * Geri alınırken hiçbir şey yapılmaz: hangi migration'ın daha önce
     * hangi partide olduğu bilinmiyor; temel partide kalmaları zararsız.
     */
    public function down(): void
    {
    }
};
