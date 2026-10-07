<?php
/**
 * =====================================================================
 *  MIGRATION: 1.6.1 — ayar açıklamaları
 * ---------------------------------------------------------------------
 *  Ayarlar ekranında her alanın altında görünen açıklamalar boştu ya
 *  da yanlıştı (ör. bakım modu "sadece yöneticiler" diyordu; editörler
 *  de gezebilir). Bu migration YALNIZCA "aciklama" sütununu günceller:
 *  değerlere, etiketlere ve sıralara dokunmaz.
 *
 *  TEK KAYNAK
 *    · Çekirdek ayarlar: aşağıdaki ACIKLAMALAR; taze kurulumda aynı
 *      metinler kurulum/database.sql'den gelir.
 *    · 1.6 ayarları: App\Support\Surum16::AYARLAR (buradan okunur).
 *  tests/unit.php üçünün birebir aynı olduğunu denetler.
 *
 *  Birden fazla kez çalıştırılabilir; taze kurulumda da zararsızdır.
 * =====================================================================
 */

declare(strict_types=1);

use App\Support\Surum16;

return new class extends App\Core\Database\Migration
{
    /** @var array<string,string> anahtar => açıklama (kurulum/database.sql ile aynı) */
    public const ACIKLAMALAR = [
        'site_aciklama'         => 'Arama sonuçlarında ve sosyal medya paylaşımlarında görünen tanıtım. 120–160 karakter idealdir.',
        'site_slogan'           => 'Ana sayfada başlığın altında ve alt bilgide görünen kısa cümle.',
        'site_dil'              => 'Sayfanın dil etiketi (lang). Arayüz metinlerini çevirmez; arama motorları ve ekran okuyucular için.',
        'mail_surucu'           => 'kayit: mektuplar gönderilmez, storage/mail/ klasörüne yazılır (geliştirme). smtp: gerçek gönderim (yayında bunu seçin). php: sunucunun mail() işlevi.',
        'seo_anahtar_kelimeler' => 'Virgülle ayırın. Google bu alanı sıralamada kullanmaz; yalnızca bazı arama motorları ve site içi araçlar okur.',
        'sistem_bakim_modu'     => 'Açıkken ziyaretçiler bakım sayfasını görür; yalnızca yöneticiler ve editörler siteyi gezebilir. Panelde uyarı şeridi çıkar.',
        'sistem_sayfa_basina'   => 'Paneldeki listelerin (kullanıcılar, mesajlar…) ilk açılıştaki satır sayısı. 5–500 arası.',
        'sistem_zaman_dilimi'   => 'Tarih ve saatler bu bölgeye göre gösterilir. IANA biçiminde yazın, örn. Europe/Istanbul, Europe/Berlin.',
    ];

    /** Çekirdek migration: temel partiye (0) yazılır, geri alınmaz (bkz. Migration::baseline). */
    public function baseline(): bool
    {
        return true;
    }

    /** Yalnızca UPDATE: yarıda kalırsa hiçbiri yazılmamış olsun. */
    public function useTransaction(): bool
    {
        return true;
    }

    /**
     * Yazılacak bütün açıklamalar: çekirdek ayarlar + 1.6 ayarları
     * ("dahili" grup ekranda görünmez, atlanır).
     *
     * @return array<string,string>
     */
    public function aciklamalar(): array
    {
        $liste = self::ACIKLAMALAR;

        foreach (Surum16::AYARLAR as [$anahtar, , $grup, , , $aciklama]) {
            if ($grup !== 'dahili' && $aciklama !== null) {
                $liste[$anahtar] = $aciklama;
            }
        }

        return $liste;
    }

    public function up(): void
    {
        $stmt = $this->db->prepare('UPDATE ayarlar SET aciklama = :aciklama WHERE anahtar = :anahtar');

        foreach ($this->aciklamalar() as $anahtar => $aciklama) {
            $stmt->execute([':aciklama' => $aciklama, ':anahtar' => $anahtar]);
        }
    }

    public function down(): void
    {
        // Eski metinlere dönmenin bir faydası yok; yapı değişmedi.
    }
};
