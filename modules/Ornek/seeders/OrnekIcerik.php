<?php
/**
 * =====================================================================
 *  SEEDER: Ornek modülüne rastgele deneme kayıtları
 * ---------------------------------------------------------------------
 *  Kurulum sihirbazında "Örnek verileri de yükle" seçilirse ve modül
 *  açıksa çalışır; elle: php cy db:seed --class=OrnekIcerik
 *
 *  18 kayıt: her rolden (yönetici, editör, üye) her durumda (yayında,
 *  onay bekliyor, taslak) ikişer tane. Böylece her rol RBAC ekranında
 *  farklı bir liste görür, editörün onaylayacağı üye kayıtları hazırdır.
 *  Tablo doluysa hiçbir şey yapmaz (tekrar çalıştırmak çoğaltmaz).
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

        $this->say($kayitlar->seedDemo() . ' örnek kayıt eklendi.');
    }
};
