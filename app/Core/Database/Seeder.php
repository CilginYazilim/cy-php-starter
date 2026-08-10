<?php
/**
 * =====================================================================
 *  Seeder – Veritabanına başlangıç/örnek veri yazar
 * ---------------------------------------------------------------------
 *      database/seeders/OrnekSeeder.php
 *
 *      <?php
 *      return new class extends App\Core\Database\Seeder
 *      {
 *          public function run(): void
 *          {
 *              $this->execute("INSERT INTO ... ");
 *              $this->say('12 örnek kayıt eklendi.');
 *          }
 *      };
 *
 *  İKİ TÜR TOHUMLAMA VARDIR — karıştırmayın:
 *
 *    1) ZORUNLU VERİ (roller, varsayılan ayarlar): uygulama bunlar
 *       olmadan çalışmaz. Bunlar MIGRATION içinde olmalıdır ki her
 *       ortamda kesinlikle bulunsunlar.
 *
 *    2) ÖRNEK VERİ (demo kullanıcılar, sahte siparişler): yalnızca
 *       geliştirmede işe yarar. Seeder budur.
 *
 *  Bu ayrım yüzünden seeder'lar YAYINDA çalıştırılmaz; db:seed komutu
 *  production ortamında --force ister.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Database;

use PDO;

abstract class Seeder
{
    protected PDO $db;

    /** @var callable(string):void|null */
    private $reporter = null;

    public function setConnection(PDO $db): void
    {
        $this->db = $db;
    }

    /** Komut, ilerlemeyi terminale basabilmek için bunu verir. */
    public function setReporter(?callable $reporter): void
    {
        $this->reporter = $reporter;
    }

    abstract public function run(): void;

    /** Terminale bilgi satırı yazar (seeder'ın kendi çıktısı). */
    protected function say(string $message): void
    {
        if ($this->reporter !== null) {
            ($this->reporter)($message);
        }
    }

    /** @param array<string,mixed> $params */
    protected function execute(string $sql, array $params = []): void
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
    }

    protected function count(string $table): int
    {
        // Tablo adı asla kullanıcıdan gelmez; yine de savunma amaçlı
        // yalnızca harf/rakam/alt çizgiye izin veriyoruz.
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';

        return (int) $this->db->query('SELECT COUNT(*) FROM `' . $safe . '`')->fetchColumn();
    }
}
