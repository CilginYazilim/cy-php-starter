<?php
/**
 * =====================================================================
 *  Migration – Tek bir şema değişikliği
 * ---------------------------------------------------------------------
 *  Her migration dosyası ADSIZ (anonim) bir sınıf döndürür:
 *
 *      <?php
 *      return new class extends App\Core\Database\Migration
 *      {
 *          public function up(): void
 *          {
 *              $this->execute("CREATE TABLE `urunler` ( ... )");
 *          }
 *
 *          public function down(): void
 *          {
 *              $this->execute('DROP TABLE IF EXISTS `urunler`');
 *          }
 *      };
 *
 *  NEDEN "SCHEMA BUILDER" YOK?
 *  Laravel'deki gibi $table->string('ad')->nullable() zinciri güzeldir
 *  ama arkasında binlerce satırlık bir SQL üreteci vardır ve yine de
 *  her veritabanı özelliğini karşılamaz. Bu starter sıfır bağımlılık
 *  sözü verir; ham SQL hem daha küçük hem de daha dürüsttür:
 *  yazdığınız şey aynen çalışır. Zaten MySQL kullanıyorsanız
 *  CREATE TABLE sözdizimini zaten biliyorsunuzdur.
 *
 *  GERİ ALMA (down) YAZMAYI İHMAL ETMEYİN: rollback'i olmayan bir
 *  migration, hatalı bir dağıtımı geri sarmayı imkânsız kılar.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Database;

use PDO;

abstract class Migration
{
    protected PDO $db;

    /** Migrator tarafından çalıştırmadan hemen önce verilir. */
    public function setConnection(PDO $db): void
    {
        $this->db = $db;
    }

    abstract public function up(): void;

    abstract public function down(): void;

    /**
     * Bu migration işlem (transaction) içinde çalışsın mı?
     *
     * MySQL'de CREATE/ALTER TABLE gibi DDL komutları örtük commit
     * yapar; işlem başlatmak yanıltıcı bir güven verir. Yalnızca
     * veri taşıyan (INSERT/UPDATE) migration'larda true döndürün.
     */
    public function useTransaction(): bool
    {
        return false;
    }

    /* =================================================================
     *  YARDIMCILAR
     * ============================================================== */

    /** @param array<string,mixed> $params */
    protected function execute(string $sql, array $params = []): void
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
    }

    protected function tableExists(string $table): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tablo'
        );
        $statement->execute([':tablo' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }

    protected function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tablo AND COLUMN_NAME = :sutun'
        );
        $statement->execute([':tablo' => $table, ':sutun' => $column]);

        return (int) $statement->fetchColumn() > 0;
    }
}
