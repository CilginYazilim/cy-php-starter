<?php
/**
 * =====================================================================
 *  OrnekRepository – "ornek" tablosuna erişimin TEK kapısı
 * ---------------------------------------------------------------------
 *  SQL yalnızca burada yazılır ve HER SORGU HAZIRLIKLIDIR (prepared).
 *  Değişkeni SQL metnine yapıştırmak enjeksiyona açık kapı bırakır.
 *
 *  Satır düzeyi süzgeç (kim hangi kaydı görür) SQL'e burada çevrilir;
 *  kuralın kendisi OrnekPolicy'dedir.
 * =====================================================================
 */

declare(strict_types=1);

namespace Modules\Ornek\Repositories;

use Modules\Ornek\OrnekPolicy;
use PDO;

final class OrnekRepository
{
    /** Rastgele örnek üretmenin üst sınırı: herkese açık demoda tablo şişmesin. */
    public const UST_SINIR = 200;

    public const DURUMLAR = ['yayinda' => 'Yayında', 'taslak' => 'Taslak'];

    public function __construct(private PDO $db)
    {
    }

    /**
     * Kapsama göre kayıtlar, sahibinin adı ve rolüyle.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listFor(string $kapsam, int $kullaniciId, int $limit = 200): array
    {
        $limit  = max(1, min($limit, 1000));
        $kosul  = match ($kapsam) {
            OrnekPolicy::KAPSAM_HEPSI => '1 = 1',
            OrnekPolicy::KAPSAM_KENDI => "(o.durum = 'yayinda' OR o.kullanici_id = :kullanici)",
            default                   => "o.durum = 'yayinda'",
        };

        $stmt = $this->db->prepare(
            'SELECT o.*, k.ad AS sahip_ad, k.soyad AS sahip_soyad, k.rol AS sahip_rol
               FROM `ornek` o
               LEFT JOIN `kullanicilar` k ON k.id = o.kullanici_id
              WHERE ' . $kosul . '
              ORDER BY o.id DESC
              LIMIT ' . $limit
        );

        if ($kapsam === OrnekPolicy::KAPSAM_KENDI) {
            $stmt->bindValue(':kullanici', $kullaniciId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `ornek` WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $kayit = $stmt->fetch();

        return is_array($kayit) ? $kayit : null;
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM `ornek`')->fetchColumn();
    }

    public function create(string $baslik, string $aciklama, string $durum, ?int $kullaniciId): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO `ornek` (baslik, aciklama, durum, kullanici_id)
             VALUES (:baslik, :aciklama, :durum, :kullanici)'
        );
        $statement->execute([
            ':baslik'    => $baslik,
            ':aciklama'  => $aciklama,
            ':durum'     => array_key_exists($durum, self::DURUMLAR) ? $durum : 'taslak',
            ':kullanici' => $kullaniciId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function setStatus(int $id, string $durum): bool
    {
        if (!array_key_exists($durum, self::DURUMLAR)) {
            return false;
        }

        $statement = $this->db->prepare('UPDATE `ornek` SET durum = :durum WHERE id = :id');
        $statement->execute([':durum' => $durum, ':id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM `ornek` WHERE id = :id');
        $statement->execute([':id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * Kayıt sahibi olabilecek kullanıcılar: aktif yönetici ve editörler.
     * Rastgele örnekler bunlar arasında dağıtılır; böylece editör
     * listede hem kendi kaydını (yönetebilir) hem başkasınınkini
     * (kilitli) görür.
     *
     * @return array<int,int>
     */
    public function ownerCandidates(): array
    {
        return array_map('intval', $this->db->query(
            "SELECT id FROM `kullanicilar` WHERE durum = 'aktif' AND rol IN ('admin', 'editor') ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Rastgele örnek kayıtlar üretir; üretilen sayıyı döndürür.
     * Üst sınır aşılmaz (bkz. UST_SINIR).
     *
     * @param array<int,int> $sahipler kayıtların dağıtılacağı kullanıcılar
     */
    public function seedSamples(int $adet, array $sahipler): int
    {
        $adet = max(0, min($adet, self::UST_SINIR - $this->count()));

        if ($adet === 0) {
            return 0;
        }

        $basliklar = [
            'Haftalık stok sayımı', 'Müşteri geri bildirim raporu', 'Kampanya görseli taslağı',
            'Sunucu bakım notları', 'Yeni ürün açıklaması', 'Teklif şablonu güncellemesi',
            'SSS sayfası için sorular', 'Mobil uygulama test listesi', 'Fatura hatırlatma metni',
            'Bülten konu başlıkları', 'Destek talebi özeti', 'Toplantı karar notları',
            'Fiyat listesi revizyonu', 'Blog yazısı fikirleri', 'Sosyal medya takvimi',
            'Kargo süreç akışı', 'Personel eğitim planı', 'Yedekleme kontrol listesi',
        ];
        $aciklamalar = [
            'Cuma gününe kadar gözden geçirilecek.',
            'İlk sürüm hazır; ekipten yorum bekleniyor.',
            'Yönetim onayından sonra yayına alınacak.',
            'Geçen ayın verileriyle karşılaştırıldı.',
            'Eksik maddeler işaretlendi, tamamlanacak.',
            'Müşteri temsilcisiyle birlikte hazırlandı.',
            'Bir sonraki toplantıda görüşülecek.',
            'Test ortamında denendi, sorun görülmedi.',
        ];

        $statement = $this->db->prepare(
            'INSERT INTO `ornek` (baslik, aciklama, durum, kullanici_id, created_at)
             VALUES (:baslik, :aciklama, :durum, :kullanici, :tarih)'
        );

        for ($i = 0; $i < $adet; $i++) {
            $statement->execute([
                ':baslik'    => $basliklar[random_int(0, count($basliklar) - 1)],
                ':aciklama'  => $aciklamalar[random_int(0, count($aciklamalar) - 1)],
                // Kabaca üçte biri taslak: üye görünümünde fark görünsün.
                ':durum'     => random_int(1, 3) === 1 ? 'taslak' : 'yayinda',
                ':kullanici' => $sahipler === [] ? null : $sahipler[random_int(0, count($sahipler) - 1)],
                ':tarih'     => date('Y-m-d H:i:s', time() - random_int(0, 30 * 86400)),
            ]);
        }

        return $adet;
    }
}
