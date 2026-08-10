<?php
/**
 * =====================================================================
 *  DEPOLAMA DİSKLERİ            →  Config::get('storage.*')
 * ---------------------------------------------------------------------
 *  Bir "disk", kök klasörü ve (varsa) web adresi olan bir alandır.
 *
 *      Storage::disk('private')->put('fatura/2026-03.pdf', $icerik);
 *
 *  HANGİSİNİ SEÇMELİ?
 *    public  → herkesin görebileceği şeyler (avatar, logo, ürün görseli)
 *    private → görmemesi gerekenler (fatura, sözleşme, dışa aktarım)
 *
 *  Şüphedeyseniz private seçin: bir dosyayı sonradan herkese açmak
 *  kolaydır, yanlışlıkla açılmış bir belgeyi geri almak imkânsızdır.
 *
 *  "url" boş olan disk web'den ERİŞİLEMEZ; url() boş string döner.
 *  O dosyalar bir denetleyici üzerinden, yetki denetiminden geçerek
 *  indirilir (bkz. Response::download).
 * =====================================================================
 */

declare(strict_types=1);

return [
    'default' => 'public',

    'disks' => [
        /* Web kökünün İÇİNDE. upload/.htaccess PHP çalıştırmayı
         * kapatır — yüklenmiş bir dosya asla kod olarak çalışmaz. */
        'public' => [
            'root' => CY_BASE . DIRECTORY_SEPARATOR . 'upload',
            'url'  => 'upload/',
        ],

        /* Web kökünün DIŞINDA sayılır: storage/ klasörünün tamamı
         * .htaccess ile kapalıdır. Adres verilmediği için url()
         * bilerek boş döner. */
        'private' => [
            'root' => CY_BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'files',
            'url'  => '',
        ],
    ],
];
