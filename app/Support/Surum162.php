<?php
/**
 * =====================================================================
 *  Surum162 – 1.6.2 ile gelen şema değişiklikleri (TEK KAYNAK)
 * ---------------------------------------------------------------------
 *  database/migrations/2026_10_09_010000_surum_1_6_2.php bunları yazar.
 *  Taze kurulumda da sihirbaz migration'ları çalıştırır; parola_sifirlama
 *  tablosunun kendisi 1.6 migration'ında (Surum16) kurulur, sütun burada
 *  eklenir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Support;

final class Surum162
{
    /**
     * Jetonun türü: "sifirlama" (Parolamı unuttum, 60 dk) ya da "acilis"
     * (yöneticinin açtığı hesap, 48 saat). Açılış bağlantısında sayfa
     * "Hesabınızı etkinleştirin" der ve "Parolanız değiştirildi" mektubu
     * gitmez (bkz. PasswordReset).
     */
    public const PAROLA_TUR_SUTUNU = "ADD COLUMN `tur` ENUM('sifirlama','acilis') NOT NULL DEFAULT 'sifirlama' AFTER `ozet`";
}
