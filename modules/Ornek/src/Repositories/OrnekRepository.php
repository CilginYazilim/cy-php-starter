<?php
/**
 * =====================================================================
 *  OrnekRepository – "ornek" tablosuna erişimin TEK kapısı
 * ---------------------------------------------------------------------
 *  SQL yalnızca burada yazılır ve HER SORGU HAZIRLIKLIDIR (prepared).
 *  Değişkeni SQL metnine yapıştırmak enjeksiyona açık kapı bırakır.
 * =====================================================================
 */

declare(strict_types=1);

namespace Modules\Ornek\Repositories;

use PDO;

final class OrnekRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(int $limit = 200): array
    {
        $limit = max(1, min($limit, 1000));

        return $this->db->query(
            'SELECT * FROM `ornek` ORDER BY id DESC LIMIT ' . $limit
        )->fetchAll();
    }

    public function create(string $baslik): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO `ornek` (baslik) VALUES (:baslik)'
        );
        $statement->execute([':baslik' => $baslik]);

        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM `ornek` WHERE id = :id');
        $statement->execute([':id' => $id]);

        return $statement->rowCount() > 0;
    }
}
