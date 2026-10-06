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
    /**
     * Tablonun üst sınırı: herkese açık demoda üyeler de kayıt ekler;
     * ne elle eklenen ne rastgele üretilen kayıt bu sayıyı aşar.
     */
    public const UST_SINIR = 300;

    /** Onay akışının durumları (bkz. OrnekPolicy). */
    public const DURUMLAR = ['yayinda' => 'Yayında', 'onay' => 'Onay bekliyor', 'taslak' => 'Taslak'];

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
            OrnekPolicy::KAPSAM_HEPSI  => '1 = 1',
            OrnekPolicy::KAPSAM_EDITOR => "(o.durum IN ('yayinda', 'onay') OR o.kullanici_id = :kullanici)",
            OrnekPolicy::KAPSAM_KENDI  => "(o.durum = 'yayinda' OR o.kullanici_id = :kullanici)",
            default                    => "o.durum = 'yayinda'",
        };

        // Onay bekleyenler en üstte: editörün işi gözden kaçmasın.
        $sira = "(o.durum = 'onay') DESC, o.id DESC";

        $stmt = $this->db->prepare(
            'SELECT o.*, k.ad AS sahip_ad, k.soyad AS sahip_soyad, k.rol AS sahip_rol
               FROM `ornek` o
               LEFT JOIN `kullanicilar` k ON k.id = o.kullanici_id
              WHERE ' . $kosul . '
              ORDER BY ' . $sira . '
              LIMIT ' . $limit
        );

        if (in_array($kapsam, [OrnekPolicy::KAPSAM_EDITOR, OrnekPolicy::KAPSAM_KENDI], true)) {
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

    public function countByStatus(string $durum): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `ornek` WHERE durum = :durum');
        $stmt->execute([':durum' => $durum]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Bir kullanıcının kayıtları, duruma göre.
     *
     * @return array{yayinda:int,onay:int,taslak:int}
     */
    public function statusCountsFor(int $kullaniciId): array
    {
        $sayilar = ['yayinda' => 0, 'onay' => 0, 'taslak' => 0];

        $stmt = $this->db->prepare('SELECT durum, COUNT(*) AS adet FROM `ornek` WHERE kullanici_id = :id GROUP BY durum');
        $stmt->execute([':id' => $kullaniciId]);

        foreach ($stmt->fetchAll() as $satir) {
            if (array_key_exists((string) $satir['durum'], $sayilar)) {
                $sayilar[(string) $satir['durum']] = (int) $satir['adet'];
            }
        }

        return $sayilar;
    }

    public function isFull(): bool
    {
        return $this->count() >= self::UST_SINIR;
    }

    public function update(int $id, string $baslik, string $aciklama, string $durum): bool
    {
        $statement = $this->db->prepare(
            'UPDATE `ornek` SET baslik = :baslik, aciklama = :aciklama, durum = :durum WHERE id = :id'
        );
        $statement->execute([
            ':baslik'   => $baslik,
            ':aciklama' => $aciklama,
            ':durum'    => array_key_exists($durum, self::DURUMLAR) ? $durum : 'taslak',
            ':id'       => $id,
        ]);

        return $statement->rowCount() > 0;
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
     * Kayıt sahibi olabilecek kullanıcılar: aktif yönetici, editör ve
     * üyeler. Rastgele örnekler bunlar arasında dağıtılır; böylece her
     * rol listede hem kendi kaydını (düzenleyebilir) hem başkasınınkini
     * (kilitli) görür, editör de onay bekleyen üye kayıtlarını bulur.
     *
     * @return array<int,int>
     */
    public function ownerCandidates(): array
    {
        return array_map('intval', $this->db->query(
            "SELECT id FROM `kullanicilar` WHERE durum = 'aktif' AND rol IN ('admin', 'editor', 'uye') ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Demo verisi: her rolden bir sahip × üç durum × iki kayıt = 18.
     * Sahipler önce demo hesaplarından (ali.yonetici, elif.editor,
     * mehmet.uye) seçilir; yoksa o roldeki ilk aktif hesap.
     */
    public function seedDemo(): int
    {
        $sahipler = [];

        foreach ($this->db->query(
            "SELECT id, rol FROM `kullanicilar`
              WHERE durum = 'aktif' AND rol IN ('admin', 'editor', 'uye')
              ORDER BY (kullanici_adi IN ('ali.yonetici', 'elif.editor', 'mehmet.uye')) DESC, id"
        )->fetchAll() as $satir) {
            $sahipler[(string) $satir['rol']] ??= (int) $satir['id'];
        }

        if ($sahipler === []) {
            return 0;
        }

        $kayitlar = [
            ['Haftalık stok sayımı', 'Cuma gününe kadar gözden geçirilecek.'],
            ['Müşteri geri bildirim raporu', 'Geçen ayın verileriyle karşılaştırıldı.'],
            ['Kampanya görseli taslağı', 'İlk sürüm hazır; ekipten yorum bekleniyor.'],
            ['Sunucu bakım notları', 'Pazar gecesi 02:00–03:00 arası planlandı.'],
            ['Yeni ürün açıklaması', 'Müşteri temsilcisiyle birlikte hazırlandı.'],
            ['Teklif şablonu güncellemesi', 'Fiyat tablosu yeni KDV oranına göre düzenlendi.'],
            ['SSS sayfası için sorular', 'Destek taleplerinden en sık gelen on soru.'],
            ['Mobil uygulama test listesi', 'Giriş, çıkış ve oturum listesi senaryoları.'],
            ['Fatura hatırlatma metni', 'Eksik maddeler işaretlendi, tamamlanacak.'],
            ['Bülten konu başlıkları', 'Ekim bülteni için beş öneri.'],
            ['Destek talebi özeti', 'Haftalık en çok sorulan konular.'],
            ['Toplantı karar notları', 'Bir sonraki toplantıda görüşülecek.'],
            ['Fiyat listesi revizyonu', 'Yönetim onayından sonra yayına alınacak.'],
            ['Blog yazısı fikirleri', 'Modül geliştirme üzerine üç yazı.'],
            ['Sosyal medya takvimi', 'Kasım ayının paylaşım planı.'],
            ['Kargo süreç akışı', 'Depo ve müşteri hizmetleriyle paylaşılacak.'],
            ['Personel eğitim planı', 'Yeni panel ekranları için kısa eğitim.'],
            ['Yedekleme kontrol listesi', 'Veritabanı ve upload klasörü haftalık.'],
        ];

        $statement = $this->db->prepare(
            'INSERT INTO `ornek` (baslik, aciklama, durum, kullanici_id, created_at)
             VALUES (:baslik, :aciklama, :durum, :kullanici, NOW() - INTERVAL :saat HOUR)'
        );

        $roller    = array_keys($sahipler);
        $durumlar  = ['yayinda', 'onay', 'taslak'];
        $adet      = min(count($kayitlar), self::UST_SINIR - $this->count());

        for ($i = 0; $i < $adet; $i++) {
            $statement->execute([
                ':baslik'    => $kayitlar[$i][0],
                ':aciklama'  => $kayitlar[$i][1],
                ':durum'     => $durumlar[intdiv($i, count($roller)) % 3],
                ':kullanici' => $sahipler[$roller[$i % count($roller)]],
                ':saat'      => 2 + $i * 17,
            ]);
        }

        return max(0, $adet);
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
                // Yaklaşık %60 yayında, %20 onay bekliyor, %20 taslak:
                // her rolün listesi gözle görülür biçimde farklı olsun.
                ':durum'     => ['yayinda', 'yayinda', 'yayinda', 'onay', 'taslak'][random_int(0, 4)],
                ':kullanici' => $sahipler === [] ? null : $sahipler[random_int(0, count($sahipler) - 1)],
                ':tarih'     => date('Y-m-d H:i:s', time() - random_int(0, 30 * 86400)),
            ]);
        }

        return $adet;
    }
}
