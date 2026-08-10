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

    /**
     * Bir kullanıcının e-posta VEYA kullanıcı adıyla yapılmış son
     * başarısız giriş denemeleri (panelde göstermek için).
     *
     * Kayıtlar başarılı girişte silindiği (bkz. clear()) ve bir günden
     * eskisi budandığı (bkz. prune()) için bu yalnızca YAKIN ZAMANDAKİ
     * başarısız denemeleri gösterir — kalıcı bir denetim günlüğü değildir.
     *
     * @return array{adet:int,son_ip:string,son_tarih:?string}
     */
    public function recentFailures(string $eposta, string $kullaniciAdi): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS adet, MAX(attempted_at) AS son, SUBSTRING_INDEX(GROUP_CONCAT(ip ORDER BY attempted_at DESC), \',\', 1) AS son_ip
               FROM login_attempts
              WHERE identifier IN (:eposta, :kadi)'
        );
        $stmt->execute([':eposta' => $this->hash($eposta), ':kadi' => $this->hash($kullaniciAdi)]);

        $row = $stmt->fetch() ?: [];

        return [
            'adet'      => (int) ($row['adet'] ?? 0),
            'son_ip'    => (string) ($row['son_ip'] ?? ''),
            'son_tarih' => $row['son'] !== null ? (string) $row['son'] : null,
        ];
    }

    private function hash(string $key): string
    {
        return hash('sha256', mb_strtolower(trim($key)));
    }
}
