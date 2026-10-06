<?php
/**
 * =====================================================================
 *  SEEDER: Örnek veri (demo) — ÇILGIN Yazılım markalı vitrin
 * ---------------------------------------------------------------------
 *      php cy db:seed                    Bütün tohumlayıcılar (bu dahil)
 *      php cy db:seed --class=DemoSeeder Yalnızca bu
 *
 *  Verinin kendisi App\Core\DemoData'dadır (TEK KAYNAK): kurulum
 *  sihirbazı, bu seeder ve canlı demonun sıfırlanması (php cy
 *  demo:reset) aynı veriyi üretir. Tekrar çalıştırmak çoğaltmaz.
 *
 *  Kaldırmak için: php cy demo:temizle ya da Panel → Sistem Bilgisi →
 *  "Demo verisini kaldır".
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Seeder
{
    public function run(): void
    {
        (new App\Core\DemoData($this->db, fn (string $mesaj) => $this->say($mesaj)))->seed();
    }
};
