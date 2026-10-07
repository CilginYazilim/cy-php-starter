<?php
/**
 * =====================================================================
 *  MIGRATION: 1.6.2 — parola bağlantısının türü
 * ---------------------------------------------------------------------
 *  parola_sifirlama.tur: "sifirlama" ya da "acilis". Yöneticinin açtığı
 *  hesaba giden "Parolanızı belirleyin" bağlantısı, sıfırlama sayfasını
 *  "Yeni parola belirleyin · diğer cihazlardaki oturumlar kapatılır"
 *  diye açıyor, ardından "Parolanız değiştirildi" mektubu gidiyordu.
 *
 *  Var olan satırlar "sifirlama" sayılır (varsayılan). Birden fazla kez
 *  çalıştırılabilir; taze kurulumda da çalışır (tablo 1.6 migration'ında
 *  kurulur). Tek kaynak: App\Support\Surum162.
 * =====================================================================
 */

declare(strict_types=1);

use App\Support\Surum162;

return new class extends App\Core\Database\Migration
{
    /** Çekirdek migration: temel partiye (0) yazılır, geri alınmaz (bkz. Migration::baseline). */
    public function baseline(): bool
    {
        return true;
    }

    public function up(): void
    {
        if ($this->tableExists('parola_sifirlama') && !$this->columnExists('parola_sifirlama', 'tur')) {
            $this->execute('ALTER TABLE parola_sifirlama ' . Surum162::PAROLA_TUR_SUTUNU);
        }
    }

    public function down(): void
    {
        if ($this->tableExists('parola_sifirlama') && $this->columnExists('parola_sifirlama', 'tur')) {
            $this->execute('ALTER TABLE parola_sifirlama DROP COLUMN tur');
        }
    }
};
