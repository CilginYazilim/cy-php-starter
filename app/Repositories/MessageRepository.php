<?php
/**
 * =====================================================================
 *  MessageRepository – "mesajlar" tablosuna erişimin TEK kapısı
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Message;
use PDO;

final class MessageRepository
{
    /** @var array<int,string> */
    private const SORTABLE = [
        1 => 'ad',
        2 => 'konu',
        3 => 'okundu',
        4 => 'created_at',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function find(int $id): ?Message
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, k.kullanici_adi
               FROM mesajlar m
          LEFT JOIN kullanicilar k ON k.id = m.kullanici_id
              WHERE m.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? Message::fromRow($row) : null;
    }

    public function markRead(int $id, bool $read): void
    {
        $stmt = $this->db->prepare('UPDATE mesajlar SET okundu = :okundu WHERE id = :id');
        $stmt->execute([':okundu' => $read ? 1 : 0, ':id' => $id]);
    }

    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM mesajlar WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM mesajlar WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<int,int> $ids
     * @return int Etkilenen kayıt sayısı
     */
    public function bulk(array $ids, string $action): int
    {
        $placeholders = [];
        $params       = [];

        foreach ($ids as $i => $id) {
            $placeholders[]        = ':id' . $i;
            $params[':id' . $i]    = $id;
        }

        $list = implode(', ', $placeholders);

        if ($action === 'sil') {
            $stmt = $this->db->prepare('DELETE FROM mesajlar WHERE id IN (' . $list . ')');
            $stmt->execute($params);

            return $stmt->rowCount();
        }

        $params[':okundu'] = $action === 'okundu' ? 1 : 0;
        $stmt = $this->db->prepare('UPDATE mesajlar SET okundu = :okundu WHERE id IN (' . $list . ')');
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /** @return array{rows:array<int,Message>,total:int,filtered:int,unread:int} */
    public function paginate(array $options): array
    {
        [$where, $params] = $this->buildFilter($options);

        $orderBy  = self::SORTABLE[(int) ($options['order_column'] ?? 4)] ?? 'created_at';
        $orderDir = strtolower((string) ($options['order_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $start  = max(0, (int) ($options['start'] ?? 0));
        $length = (int) ($options['length'] ?? 10);
        $limit  = $length <= 0 ? 500 : min($length, 500);

        $sql = 'SELECT id, ad, eposta, konu, mesaj, okundu, created_at FROM mesajlar'
             . $where
             . sprintf(' ORDER BY `%s` %s, id DESC', $orderBy, $orderDir)
             . sprintf(' LIMIT %d OFFSET %d', $limit, $start);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = array_map(static fn (array $row): Message => Message::fromRow($row), $stmt->fetchAll());

        return [
            'rows'     => $rows,
            'total'    => $this->countAll(),
            'filtered' => $this->countFiltered($options),
            'unread'   => $this->countUnread(),
        ];
    }

    public function countFiltered(array $options): int
    {
        [$where, $params] = $this->buildFilter($options);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM mesajlar' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildFilter(array $options): array
    {
        $conditions = [];
        $params     = [];

        $filter = (string) ($options['filter'] ?? '');

        if ($filter === 'okunmamis') {
            $conditions[] = 'okundu = 0';
        } elseif ($filter === 'okunmus') {
            $conditions[] = 'okundu = 1';
        }

        $search = trim((string) ($options['search'] ?? ''));

        if ($search !== '') {
            $conditions[] = '(ad LIKE :s_ad OR eposta LIKE :s_eposta OR konu LIKE :s_konu OR mesaj LIKE :s_mesaj)';
            $pattern = '%' . $this->escapeLike($search) . '%';

            $params[':s_ad']     = $pattern;
            $params[':s_eposta'] = $pattern;
            $params[':s_konu']   = $pattern;
            $params[':s_mesaj']  = $pattern;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM mesajlar')->fetchColumn();
    }

    public function countUnread(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM mesajlar WHERE okundu = 0')->fetchColumn();
    }

    public function countToday(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM mesajlar WHERE DATE(created_at) = CURDATE()')->fetchColumn();
    }

    /** @return array<int,Message> */
    public function latest(int $limit = 5): array
    {
        $limit = max(1, min($limit, 50));

        $stmt = $this->db->query(
            'SELECT id, ad, eposta, konu, mesaj, okundu, created_at
               FROM mesajlar ORDER BY created_at DESC, id DESC LIMIT ' . $limit
        );

        return array_map(static fn (array $row): Message => Message::fromRow($row), $stmt->fetchAll());
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO mesajlar (ad, eposta, konu, mesaj, kullanici_id, ip, tarayici)
             VALUES (:ad, :eposta, :konu, :mesaj, :kullanici_id, :ip, :tarayici)'
        );
        $stmt->execute([
            ':ad'           => $data['ad'],
            ':eposta'       => $data['eposta'],
            ':konu'         => $data['konu'] ?? '',
            ':mesaj'        => $data['mesaj'],
            ':kullanici_id' => $data['kullanici_id'] ?? null,
            ':ip'           => $data['ip'] ?? '',
            ':tarayici'     => $data['tarayici'] ?? '',
        ]);

        return (int) $this->db->lastInsertId();
    }
}
