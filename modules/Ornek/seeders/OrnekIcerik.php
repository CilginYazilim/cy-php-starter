<?php
/**
 * =====================================================================
 *  SEEDER: Ornek modülüne rastgele deneme kayıtları
 * ---------------------------------------------------------------------
 *  Kurulum sihirbazında "Örnek verileri de yükle" seçilirse ve modül
 *  açıksa çalışır; elle: php cy db:seed --class=OrnekIcerik
 *
 *  Kayıtlar aktif yönetici ve editörler arasında dağıtılır, üçte biri
 *  taslaktır: her rol RBAC ekranında farklı bir liste görür. Tablo
 *  doluysa hiçbir şey yapmaz (tekrar çalıştırmak çoğaltmaz).
 * =====================================================================
 */

declare(strict_types=1);

use Modules\Ornek\Repositories\OrnekRepository;

return new class extends App\Core\Database\Seeder
{
    public function run(): void
    {
        $kayitlar = new OrnekRepository($this->db);

        if ($kayitlar->count() > 0) {
            $this->say('ornek tablosu dolu; atlandı.');

            return;
        }

        $this->say($kayitlar->seedSamples(12, $kayitlar->ownerCandidates()) . ' örnek kayıt eklendi.');
    }
};
