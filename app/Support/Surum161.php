<?php
/**
 * =====================================================================
 *  Surum161 – 1.6.1 ile gelen ayar satırları ve sütunlar (TEK KAYNAK)
 * ---------------------------------------------------------------------
 *  database/migrations/2026_10_08_020000_surum_1_6_1.php bunları yazar.
 *  Taze kurulumda da sihirbaz migration'ları çalıştırır; ayar satırları
 *  kurulum/database.sql'e AYRICA kopyalanmaz (bkz. Surum16).
 *
 *  kullanicilar.bildirim_tercihleri ise taze kurulumda database.sql'deki
 *  CREATE TABLE ile gelir; migration yalnızca eksikse ekler.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Support;

final class Surum161
{
    /**
     * anahtar, varsayılan değer, grup, tip, etiket, açıklama, seçenekler, sıra
     * (Surum16::AYARLAR ile aynı biçim)
     *
     * @var array<int,array{0:string,1:string,2:string,3:string,4:string,5:?string,6:?string,7:int}>
     */
    public const AYARLAR = [
        ['mail_bildirim_yeni_uye', '0', 'eposta', 'onay', 'Yeni Üye Bildirimi',
            'Biri kayıt olunca iletişim e-postasına haber verilsin mi? Saatte en fazla 10 mektup gider.', null, 95],
    ];

    /** Bildirim tercihleri sütunu (NULL = varsayılan: hepsi açık). */
    public const TERCIH_SUTUNU = 'ADD COLUMN bildirim_tercihleri JSON NULL DEFAULT NULL';
}
