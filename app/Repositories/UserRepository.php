<?php
/**
 * =====================================================================
 *  UserRepository – "kullanicilar" tablosuna erişimin TEK kapısı
 * ---------------------------------------------------------------------
 *  Tüm sorgular prepared statement kullanır. Sıralama sütunu beyaz
 *  listeden geçer (sütun adı bind edilemez).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Role;
use App\Models\User;
use PDO;

final class UserRepository
{
    private const COLUMNS = 'id, ad, soyad, kullanici_adi, eposta, rol, durum, tema, avatar, telefon,
                             hakkinda, son_giris, son_giris_ip, giris_sayisi, created_at, updated_at';

    /**
     * DataTables sütun sırasına göre sıralanabilir sütunlar.
     * Anahtar, JS tarafındaki GÖRÜNÜR sütun indeksiyle birebir eşleşmeli
     * (0:#, 1:görsel, 2:Kullanıcı, 3:E-posta, 4:Rol, 5:Durum, 6:İşlemler).
     *
     * @var array<int,string>
     */
    private const SORTABLE = [
        0 => 'id',
        2 => 'ad',
        3 => 'eposta',
        4 => 'rol',
        5 => 'durum',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function find(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM kullanicilar WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    /** Giriş için: e-posta VEYA kullanıcı adıyla arar, parola özetini de çeker. */
    public function findForLogin(string $identifier): ?User
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::COLUMNS . ', sifre FROM kullanicilar
              WHERE eposta = :id OR kullanici_adi = :id2 LIMIT 1'
        );
        $stmt->execute([':id' => $identifier, ':id2' => $identifier]);

        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function fieldTaken(string $field, string $value, ?int $exceptId = null): bool
    {
        if (!in_array($field, ['eposta', 'kullanici_adi'], true)) {
            throw new \InvalidArgumentException('Geçersiz alan: ' . $field);
        }

        $sql    = 'SELECT COUNT(*) FROM kullanicilar WHERE `' . $field . '` = :deger';
        $params = [':deger' => $value];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * DataTables için sayfalanmış liste.
     *
     * @return array{rows:array<int,User>,total:int,filtered:int}
     */
    public function paginate(array $options): array
    {
        [$where, $params] = $this->buildFilter($options);

        $orderBy  = self::SORTABLE[(int) ($options['order_column'] ?? 0)] ?? 'id';
        $orderDir = strtolower((string) ($options['order_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $start  = max(0, (int) ($options['start'] ?? 0));
        $length = (int) ($options['length'] ?? 10);
        $limit  = $length <= 0 ? 500 : min($length, 500);

        $sql = 'SELECT ' . self::COLUMNS . ' FROM kullanicilar'
             . $where
             . sprintf(' ORDER BY `%s` %s', $orderBy, $orderDir)
             . sprintf(' LIMIT %d OFFSET %d', $limit, $start);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = array_map(static fn (array $row): User => User::fromRow($row), $stmt->fetchAll());

        return [
            'rows'     => $rows,
            'total'    => $this->countAll(),
            'filtered' => $this->countFiltered($options),
        ];
    }

    public function countFiltered(array $options): int
    {
        [$where, $params] = $this->buildFilter($options);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM kullanicilar' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * TOPLU E-POSTA ALICILARI.
     *
     * Rol ve duruma göre süzülmüş kullanıcıların yalnızca gönderim
     * için gereken alanlarını döndürür — bütün User nesnelerini
     * belleğe almak binlerce kayıtta gereksiz yük olurdu.
     *
     * @param string $role   '' → rol farketmez
     * @param string $status Varsayılan 'aktif'; '' → durum farketmez
     * @return array<int,array{id:int,ad:string,soyad:string,eposta:string}>
     */
    public function mailRecipients(string $role = '', string $status = 'aktif'): array
    {
        $conditions = ["eposta <> ''"];
        $params     = [];

        if ($role !== '' && Role::exists($role)) {
            $conditions[]   = 'rol = :rol';
            $params[':rol'] = $role;
        }

        if (in_array($status, ['aktif', 'pasif', 'askida'], true)) {
            $conditions[]     = 'durum = :durum';
            $params[':durum'] = $status;
        }

        $stmt = $this->db->prepare(
            'SELECT id, ad, soyad, eposta FROM kullanicilar
              WHERE ' . implode(' AND ', $conditions) . '
           ORDER BY id ASC'
        );
        $stmt->execute($params);

        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id'     => (int) $row['id'],
                'ad'     => (string) $row['ad'],
                'soyad'  => (string) $row['soyad'],
                'eposta' => (string) $row['eposta'],
            ];
        }

        return $rows;
    }

    /**
     * Alıcı seçme kutusunu doldurmak için hafif kullanıcı listesi.
     *
     * @return array<int,array{id:int,etiket:string,eposta:string}>
     */
    public function pickList(int $limit = 500): array
    {
        $limit = max(1, min($limit, 2000));

        $rows = $this->db->query(
            "SELECT id, ad, soyad, kullanici_adi, eposta FROM kullanicilar
              WHERE eposta <> ''
           ORDER BY ad ASC, soyad ASC
              LIMIT " . $limit
        )->fetchAll();

        $list = [];

        foreach ($rows as $row) {
            $name = trim((string) $row['ad'] . ' ' . (string) $row['soyad']);

            $list[] = [
                'id'     => (int) $row['id'],
                'etiket' => ($name !== '' ? $name : (string) $row['kullanici_adi']) . ' (' . (string) $row['eposta'] . ')',
                'eposta' => (string) $row['eposta'],
            ];
        }

        return $list;
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildFilter(array $options): array
    {
        $conditions = [];
        $params     = [];

        $search = trim((string) ($options['search'] ?? ''));

        if ($search !== '') {
            $conditions[] = '(ad LIKE :s_ad OR soyad LIKE :s_soyad OR eposta LIKE :s_eposta OR kullanici_adi LIKE :s_kadi)';
            $pattern = '%' . $this->escapeLike($search) . '%';

            $params[':s_ad']     = $pattern;
            $params[':s_soyad']  = $pattern;
            $params[':s_eposta'] = $pattern;
            $params[':s_kadi']   = $pattern;
        }

        $role = (string) ($options['role'] ?? '');

        if ($role !== '' && Role::exists($role)) {
            $conditions[] = 'rol = :rol';
            $params[':rol'] = $role;
        }

        $status = (string) ($options['status'] ?? '');

        if (in_array($status, ['aktif', 'pasif', 'askida'], true)) {
            $conditions[] = 'durum = :durum';
            $params[':durum'] = $status;
        }

        $dateFrom = $this->validDate((string) ($options['date_from'] ?? ''));
        $dateTo   = $this->validDate((string) ($options['date_to'] ?? ''));

        if ($dateFrom !== null) {
            $conditions[] = 'created_at >= :tarih_bas';
            $params[':tarih_bas'] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== null) {
            $conditions[] = 'created_at <= :tarih_bit';
            $params[':tarih_bit'] = $dateTo . ' 23:59:59';
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    private function validDate(string $value): ?string
    {
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date->format('Y-m-d') : null;
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM kullanicilar')->fetchColumn();
    }

    public function countByRole(string $role): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM kullanicilar WHERE rol = :rol');
        $stmt->execute([':rol' => $role]);

        return (int) $stmt->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM kullanicilar WHERE durum = :durum');
        $stmt->execute([':durum' => $status]);

        return (int) $stmt->fetchColumn();
    }

    public function countSince(int $days): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM kullanicilar WHERE created_at >= (NOW() - INTERVAL :days DAY)');
        $stmt->execute([':days' => $days]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,int> "2026-08-09" => 3 */
    public function dailyCounts(int $days = 14): array
    {
        $stmt = $this->db->prepare(
            'SELECT DATE(created_at) AS gun, COUNT(*) AS adet
               FROM kullanicilar
              WHERE created_at >= (NOW() - INTERVAL :days DAY)
              GROUP BY DATE(created_at) ORDER BY gun ASC'
        );
        $stmt->execute([':days' => $days]);

        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['gun']] = (int) $row['adet'];
        }

        return $result;
    }

    /** @return array<int,User> */
    public function latest(int $limit = 5): array
    {
        $limit = max(1, min($limit, 50));

        $stmt = $this->db->query(
            'SELECT ' . self::COLUMNS . ' FROM kullanicilar ORDER BY created_at DESC, id DESC LIMIT ' . $limit
        );

        return array_map(static fn (array $row): User => User::fromRow($row), $stmt->fetchAll());
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO kullanicilar (ad, soyad, kullanici_adi, eposta, sifre, rol, durum, avatar, telefon, hakkinda)
             VALUES (:ad, :soyad, :kadi, :eposta, :sifre, :rol, :durum, :avatar, :telefon, :hakkinda)'
        );

        $stmt->execute([
            ':ad'       => $data['ad'],
            ':soyad'    => $data['soyad'],
            ':kadi'     => $data['kullanici_adi'],
            ':eposta'   => mb_strtolower($data['eposta']),
            ':sifre'    => password_hash($data['sifre'], PASSWORD_DEFAULT),
            ':rol'      => $data['rol'] ?? 'uye',
            ':durum'    => $data['durum'] ?? 'aktif',
            ':avatar'   => $data['avatar'] ?? '',
            ':telefon'  => $data['telefon'] ?? '',
            ':hakkinda' => $data['hakkinda'] ?? '',
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): bool
    {
        /* Güncellenmesine izin verilen sütunların BEYAZ LİSTESİ.
         * Bu olmadan forma "rol" ekleyen bir üye kendini yönetici
         * yapabilirdi (mass assignment açığı). */
        $allowed = ['ad', 'soyad', 'kullanici_adi', 'eposta', 'rol', 'durum', 'avatar', 'telefon', 'hakkinda'];

        $sets   = [];
        $params = [':id' => $id];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $sets[] = sprintf('`%s` = :%s', $column, $column);
            $params[':' . $column] = $column === 'eposta' ? mb_strtolower((string) $data['eposta']) : $data[$column];
        }

        if (!empty($data['sifre'])) {
            $sets[] = '`sifre` = :sifre';
            $params[':sifre'] = password_hash((string) $data['sifre'], PASSWORD_DEFAULT);
        }

        if ($sets === []) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE kullanicilar SET ' . implode(', ', $sets) . ' WHERE id = :id');

        return $stmt->execute($params);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE kullanicilar SET sifre = :sifre WHERE id = :id');
        $stmt->execute([':sifre' => $hash, ':id' => $id]);
    }

    public function updateTheme(int $id, string $tema): void
    {
        $stmt = $this->db->prepare('UPDATE kullanicilar SET tema = :tema WHERE id = :id');
        $stmt->execute([':tema' => $tema, ':id' => $id]);
    }

    public function touchLogin(int $id, string $ip): void
    {
        $stmt = $this->db->prepare(
            'UPDATE kullanicilar SET son_giris = NOW(), son_giris_ip = :ip, giris_sayisi = giris_sayisi + 1 WHERE id = :id'
        );
        $stmt->execute([':ip' => $ip, ':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM kullanicilar WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function otherActiveAdminExists(int $exceptId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM kullanicilar WHERE rol = 'admin' AND durum = 'aktif' AND id <> :id"
        );
        $stmt->execute([':id' => $exceptId]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
