<?php
/**
 * =====================================================================
 *  PageRepository – "sayfalar" tablosuna erişimin TEK kapısı
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Html;
use App\Models\Page;
use PDO;

final class PageRepository
{
    /**
     * SLUG OLARAK KULLANILAMAYACAK ADRESLER.
     *
     * Router önce SABİT rotalara baktığı için "panel" adlı bir sayfa
     * paneli ele geçiremez — ama sayfa da hiçbir zaman açılamaz ve
     * yönetici sebebini anlayamaz. Kaydederken uyarmak, sessizce
     * erişilemez bir sayfa üretmekten iyidir.
     *
     * @var array<int,string>
     */
    private const AYRILMIS = [
        'panel', 'giris', 'kayit', 'cikis', 'api', 'kurulum', 'upload', 'assets',
        'sitemap', 'robots', 'manifest', 'cevrimdisi', 'storage', 'modules',
    ];

    /**
     * Menü sorgusunun istek içi belleği.
     *
     * Statiktir çünkü depo her çağrıldığında yeniden kurulur
     * (nav bir örnek, alt bilgi başka bir örnek yaratır); önbelleği
     * örnekte tutmak hiçbir işe yaramazdı.
     *
     * @var array<int,Page>|null
     */
    private static ?array $menuCache = null;

    public function __construct(private PDO $db)
    {
    }

    /** Sayfa yazıldığında menü belleği geçersizleşir. */
    public static function flushMenu(): void
    {
        self::$menuCache = null;
    }

    public function find(int $id): ?Page
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, CONCAT(k.ad, ' ', k.soyad) AS yazar_adi
               FROM sayfalar s
          LEFT JOIN kullanicilar k ON k.id = s.yazar_id
              WHERE s.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? Page::fromRow($row) : null;
    }

    /** Ön yüz için: YALNIZCA yayındaki sayfa döner. */
    public function findPublished(string $slug): ?Page
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, CONCAT(k.ad, ' ', k.soyad) AS yazar_adi
               FROM sayfalar s
          LEFT JOIN kullanicilar k ON k.id = s.yazar_id
              WHERE s.slug = :slug AND s.durum = 'yayin' LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);

        $row = $stmt->fetch();

        return $row ? Page::fromRow($row) : null;
    }

    public function findBySlug(string $slug): ?Page
    {
        $stmt = $this->db->prepare('SELECT * FROM sayfalar WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);

        $row = $stmt->fetch();

        return $row ? Page::fromRow($row) : null;
    }

    /**
     * Panel listesi — sayfa sayısı azdır, sayfalama gerekmez.
     *
     * @return array<int,Page>
     */
    public function all(): array
    {
        $rows = $this->db->query(
            "SELECT s.*, CONCAT(k.ad, ' ', k.soyad) AS yazar_adi
               FROM sayfalar s
          LEFT JOIN kullanicilar k ON k.id = s.yazar_id
           ORDER BY s.sira ASC, s.baslik ASC"
        )->fetchAll();

        return array_map(static fn (array $row): Page => Page::fromRow($row), $rows);
    }

    /**
     * Üst menüye girecek yayındaki sayfalar.
     *
     * İSTEK BOYUNCA BİR KEZ okunur. Menü hem üst çubukta hem alt
     * bilgide çiziliyor; belleğe almasaydık her sayfa görüntülemesi
     * aynı sorguyu iki kez çalıştırırdı.
     *
     * @return array<int,Page>
     */
    public function menu(): array
    {
        if (self::$menuCache !== null) {
            return self::$menuCache;
        }

        $rows = $this->db->query(
            "SELECT * FROM sayfalar
              WHERE durum = 'yayin' AND menude = 1
           ORDER BY sira ASC, baslik ASC"
        )->fetchAll();

        return self::$menuCache = array_map(static fn (array $row): Page => Page::fromRow($row), $rows);
    }

    /**
     * Site haritasına girecek sayfalar.
     *
     * @return array<int,array{slug:string,updated:string}>
     */
    public function sitemap(): array
    {
        $rows = $this->db->query(
            "SELECT slug, updated_at FROM sayfalar WHERE durum = 'yayin' ORDER BY sira ASC"
        )->fetchAll();

        return array_map(
            static fn (array $row): array => [
                'slug'    => (string) $row['slug'],
                'updated' => (string) ($row['updated_at'] ?? ''),
            ],
            $rows
        );
    }

    /** @return array{toplam:int,yayin:int,taslak:int,menude:int} */
    public function stats(): array
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS toplam,
                    SUM(durum = 'yayin')  AS yayin,
                    SUM(durum = 'taslak') AS taslak,
                    SUM(menude = 1)       AS menude
               FROM sayfalar"
        )->fetch() ?: [];

        return [
            'toplam' => (int) ($row['toplam'] ?? 0),
            'yayin'  => (int) ($row['yayin'] ?? 0),
            'taslak' => (int) ($row['taslak'] ?? 0),
            'menude' => (int) ($row['menude'] ?? 0),
        ];
    }

    /**
     * Yeni sayfa. Dönen değer yeni kaydın id'sidir.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sayfalar
                (baslik, slug, ozet, icerik, durum, menude, sira, seo_baslik, seo_aciklama, yazar_id)
             VALUES
                (:baslik, :slug, :ozet, :icerik, :durum, :menude, :sira, :seo_baslik, :seo_aciklama, :yazar_id)'
        );

        $stmt->execute($this->bind($data));

        self::flushMenu();

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sayfalar SET
                baslik = :baslik, slug = :slug, ozet = :ozet, icerik = :icerik,
                durum = :durum, menude = :menude, sira = :sira,
                seo_baslik = :seo_baslik, seo_aciklama = :seo_aciklama
              WHERE id = :id'
        );

        $params = $this->bind($data);

        /* SORGUDA OLMAYAN PARAMETRE GÖNDERİLEMEZ: PDO "Invalid
         * parameter number" der ve kaydetme çöker. Yazar alanı
         * yalnızca INSERT'te vardır — sayfayı ilk yazan kişi
         * kayıtlıdır, her düzenlemede değişmesi istenmez. */
        unset($params[':yazar_id']);

        $stmt->execute($params + [':id' => $id]);

        self::flushMenu();
    }

    /**
     * Ortak bağlama.
     *
     * İçerik TAM BURADA süzülür. Denetleyicinin unutma ihtimali olan
     * bir adımı depoya taşımak, "bir yerde unutuldu" hatasını
     * yapısal olarak imkânsız kılar.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function bind(array $data): array
    {
        return [
            ':baslik'       => (string) ($data['baslik'] ?? ''),
            ':slug'         => (string) ($data['slug'] ?? ''),
            ':ozet'         => mb_substr((string) ($data['ozet'] ?? ''), 0, 255),
            ':icerik'       => Html::sanitize((string) ($data['icerik'] ?? '')),
            ':durum'        => ($data['durum'] ?? 'taslak') === 'yayin' ? 'yayin' : 'taslak',
            ':menude'       => !empty($data['menude']) ? 1 : 0,
            ':sira'         => (int) ($data['sira'] ?? 0),
            ':seo_baslik'   => mb_substr((string) ($data['seo_baslik'] ?? ''), 0, 190),
            ':seo_aciklama' => mb_substr((string) ($data['seo_aciklama'] ?? ''), 0, 255),
            ':yazar_id'     => $data['yazar_id'] ?? null,
        ];
    }

    /** Korumalı sayfalar silinemez; false dönerse hiçbir şey silinmemiştir. */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM sayfalar WHERE id = :id AND korumali = 0');
        $stmt->execute([':id' => $id]);

        self::flushMenu();

        return $stmt->rowCount() > 0;
    }

    public function setStatus(int $id, string $durum): void
    {
        $stmt = $this->db->prepare('UPDATE sayfalar SET durum = :durum WHERE id = :id');
        $stmt->execute([':durum' => $durum === 'yayin' ? 'yayin' : 'taslak', ':id' => $id]);

        self::flushMenu();
    }

    /**
     * Slug benzersiz mi? ($ignoreId düzenlenen sayfanın kendisidir.)
     */
    public function slugTaken(string $slug, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT 1 FROM sayfalar WHERE slug = :slug';
        $params = [':slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public static function reserved(string $slug): bool
    {
        return in_array(mb_strtolower($slug), self::AYRILMIS, true);
    }

    /**
     * Başlıktan çakışmayan bir slug üretir: "hakkimizda", "hakkimizda-2"…
     */
    public function uniqueSlug(string $kaynak, ?int $ignoreId = null): string
    {
        $temel = Html::slug($kaynak);
        $temel = $temel !== '' ? $temel : 'sayfa';

        $aday = $temel;
        $ek   = 2;

        while ($this->slugTaken($aday, $ignoreId) || self::reserved($aday)) {
            $aday = $temel . '-' . $ek++;
        }

        return $aday;
    }
}
