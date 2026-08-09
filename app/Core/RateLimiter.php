<?php
/**
 * =====================================================================
 *  RateLimiter – Kaba kuvvet (brute force) saldırısı koruması
 * ---------------------------------------------------------------------
 *  Sayaç VERİTABANINDA tutulur (oturumda değil); aksi halde saldırgan
 *  çerezini silerek sayacı sıfırlayabilirdi.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use PDO;

final class RateLimiter
{
    public function __construct(private PDO $db)
    {
    }

    public function lockedFor(string $key): int
    {
        $max    = (int) Config::get('security.login_max_attempts', 5);
        $window = (int) Config::get('security.login_window', 900);
        $lock   = (int) Config::get('security.login_lockout', 900);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS adet, MAX(attempted_at) AS son
               FROM login_attempts
              WHERE identifier = :key
                AND attempted_at >= (NOW() - INTERVAL :window SECOND)'
        );
        $stmt->execute([':key' => $this->hash($key), ':window' => $window]);

        $row = $stmt->fetch() ?: [];

        if ((int) ($row['adet'] ?? 0) < $max) {
            return 0;
        }

        $last      = strtotime((string) ($row['son'] ?? 'now')) ?: time();
        $remaining = ($last + $lock) - time();

        return max(0, $remaining);
    }

    public function hit(string $key, string $ip): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (identifier, ip, attempted_at) VALUES (:key, :ip, NOW())'
        );
        $stmt->execute([':key' => $this->hash($key), ':ip' => $ip]);

        if (random_int(1, 20) === 1) {
            $this->prune();
        }
    }

    public function clear(string $key): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE identifier = :key');
        $stmt->execute([':key' => $this->hash($key)]);
    }

    public function remaining(string $key): int
    {
        $max    = (int) Config::get('security.login_max_attempts', 5);
        $window = (int) Config::get('security.login_window', 900);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM login_attempts
              WHERE identifier = :key AND attempted_at >= (NOW() - INTERVAL :window SECOND)'
        );
        $stmt->execute([':key' => $this->hash($key), ':window' => $window]);

        return max(0, $max - (int) $stmt->fetchColumn());
    }

    public function prune(): void
    {
        $this->db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }

    private function hash(string $key): string
    {
        return hash('sha256', mb_strtolower(trim($key)));
    }
}
