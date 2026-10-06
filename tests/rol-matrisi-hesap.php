<?php
/**
 * =====================================================================
 *  Rota ara katmanlarından "kim erişebilir?" hesabı
 * ---------------------------------------------------------------------
 *  tests/unit.php → rol matrisi testi kullanır. Dönen dize, rotaya
 *  erişebilenlerin kısaltmalarıdır (sıra sabit):
 *
 *      M  misafir (giriş yapmamış)     U  üye
 *      E  editör                       Y  yönetici
 *
 *  Örnek: "EY" → yalnızca editör ve yönetici. "MUEY" → herkes.
 *  "M" → yalnızca misafir ("guest": giriş yapmış kişi yönlendirilir).
 *
 *  API rotalarında (api, api.can) "giriş" Bearer anahtarı ya da panel
 *  oturumudur; misafir 401 alır.
 * =====================================================================
 */

declare(strict_types=1);

use App\Models\Role;

if (!function_exists('cy_rota_erisimi')) {
    /** @param array<int,string> $middleware */
    function cy_rota_erisimi(array $middleware): string
    {
        $roller = ['U' => Role::MEMBER, 'E' => 'editor', 'Y' => Role::ADMIN];

        $girisGerekir = false;
        $yalnizMisafir = false;
        $yetkiler = [];

        foreach ($middleware as $kural) {
            [$ad, $parametre] = array_pad(explode(':', $kural, 2), 2, '');

            match ($ad) {
                'auth', 'api'      => $girisGerekir = true,
                'guest'            => $yalnizMisafir = true,
                'can', 'api.can'   => $yetkiler[] = explode('|', $parametre),
                default            => null,
            };

            if ($ad === 'api.can') {
                $girisGerekir = true;
            }
        }

        if ($yalnizMisafir) {
            return 'M';
        }

        $sonuc = $girisGerekir ? '' : 'M';

        foreach ($roller as $kisa => $rol) {
            $izin = true;

            // Her "can:" kuralı ayrı ayrı sağlanmalı; kural içindeki "|" VEYA'dır.
            foreach ($yetkiler as $secenekler) {
                $herhangi = false;

                foreach ($secenekler as $yetki) {
                    if (Role::can($rol, $yetki)) {
                        $herhangi = true;
                        break;
                    }
                }

                $izin = $izin && $herhangi;
            }

            if ($izin) {
                $sonuc .= $kisa;
            }
        }

        return $sonuc;
    }
}
