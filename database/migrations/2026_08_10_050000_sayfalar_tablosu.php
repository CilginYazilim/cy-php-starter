<?php
/**
 * =====================================================================
 *  MIGRATION: sayfalar tablosu
 * ---------------------------------------------------------------------
 *  "Hakkımızda" metni eskiden tek bir ayar satırıydı (site_hakkinda).
 *  İkinci bir durağan sayfa (Gizlilik, KVKK, SSS…) isteyen herkes
 *  kod yazmak zorundaydı. Artık sayfalar veritabanında durur ve
 *  panelden zengin metin editörüyle yazılır.
 *
 *  Bu migration TAZE kurulumda da çalışır: kurulum/database.sql
 *  tabloyu zaten oluşturduğu için tableExists() ile erken çıkar.
 *  Var olan bir kurulumda ise tabloyu kurar ve eski site_hakkinda
 *  metnini kaybetmemek için Hakkımızda sayfasına taşır.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        if ($this->tableExists('sayfalar')) {
            return;
        }

        $this->execute(
            "CREATE TABLE `sayfalar` (
              `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `baslik`        VARCHAR(190) NOT NULL,
              `slug`          VARCHAR(190) NOT NULL COLLATE utf8mb4_unicode_ci,
              `ozet`          VARCHAR(255) NOT NULL DEFAULT '',
              `icerik`        MEDIUMTEXT NULL,
              `kapak`         VARCHAR(191) NOT NULL DEFAULT '',
              `durum`         ENUM('taslak','yayin') NOT NULL DEFAULT 'taslak',
              `menude`        TINYINT(1) NOT NULL DEFAULT 0,
              `korumali`      TINYINT(1) NOT NULL DEFAULT 0,
              `sira`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              `seo_baslik`    VARCHAR(190) NOT NULL DEFAULT '',
              `seo_aciklama`  VARCHAR(255) NOT NULL DEFAULT '',
              `yazar_id`      INT UNSIGNED NULL DEFAULT NULL,
              `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_sayfalar_slug` (`slug`),
              KEY `idx_sayfalar_durum` (`durum`, `sira`),
              CONSTRAINT `fk_sayfalar_yazar`
                FOREIGN KEY (`yazar_id`) REFERENCES `kullanicilar` (`id`)
                ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci"
        );

        /* Eski "site_hakkinda" ayarı düz metindi; satır sonlarını
         * paragrafa çevirerek taşıyoruz. Ayar satırını SİLMİYORUZ,
         * yalnızca "dahili" gruba alıyoruz: geri alma (down) o metne
         * hâlâ ihtiyaç duyabilir. */
        $eski = $this->db->query("SELECT deger FROM ayarlar WHERE anahtar = 'site_hakkinda'")->fetchColumn();
        $metin = is_string($eski) ? trim($eski) : '';

        $icerik = $metin !== ''
            ? '<p>' . implode('</p><p>', array_map(
                static fn (string $p): string => nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')),
                preg_split('/\n{2,}/', $metin) ?: [$metin]
            )) . '</p>'
            : '<h2>Biz kimiz?</h2><p>Bu metni <strong>Panel → Sayfalar</strong> ekranından değiştirebilirsiniz.</p>';

        $this->execute(
            'INSERT INTO sayfalar (baslik, slug, ozet, icerik, durum, menude, korumali, sira)
             VALUES (:b1, :s1, :o1, :i1, :d1, 1, 1, 10),
                    (:b2, :s2, :o2, :i2, :d2, 1, 1, 20)',
            [
                ':b1' => 'Hakkımızda',
                ':s1' => 'hakkimizda',
                ':o1' => 'Kim olduğumuzu ve nasıl çalıştığımızı anlatan kısa bir tanıtım.',
                ':i1' => $icerik,
                ':d1' => 'yayin',
                ':b2' => 'İletişim',
                ':s2' => 'iletisim',
                ':o2' => 'Bize ulaşmanın tüm yolları: telefon, e-posta, adres ve iletişim formu.',
                ':i2' => '<p>Sorularınız ve teklif talepleriniz için aşağıdaki formu doldurabilirsiniz.</p>',
                ':d2' => 'yayin',
            ]
        );

        $this->execute("UPDATE ayarlar SET grup = 'dahili', duzenlenebilir = 0 WHERE anahtar = 'site_hakkinda'");
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sayfalar`');
        $this->execute("UPDATE ayarlar SET grup = 'genel', duzenlenebilir = 1 WHERE anahtar = 'site_hakkinda'");
    }
};
