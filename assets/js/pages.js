/* ==================================================================
 *  SAYFA FORMU – başlıktan adres (slug) üretimi
 *  cilginyazilim.com
 * ------------------------------------------------------------------
 *  Adres alanı boşken başlığı yazdıkça canlı olarak dolar. Kullanıcı
 *  adrese BİR KEZ dokunduğunda otomatik üretim durur: kendi yazdığı
 *  adresin, başlığı düzelttiği anda silinmesi can sıkıcıdır.
 *
 *  Sunucu aynı dönüşümü Html::slug() ile TEKRAR yapar; buradaki
 *  yalnızca önizlemedir.
 * ================================================================== */

/* global jQuery */
jQuery(function ($) {
    'use strict';

    var $form = $('#cy_page_form');

    if (!$form.length) { return; }

    var $baslik = $('#baslik');
    var $slug   = $('#slug');

    if (!$baslik.length || !$slug.length || $slug.prop('readonly')) { return; }

    // Adres zaten doluysa (düzenleme) otomatik üretim kapalı başlar.
    var otomatik = $.trim($slug.val()) === '';

    function slugify(value) {
        var tr = { 'ş': 's', 'Ş': 's', 'ı': 'i', 'İ': 'i', 'ğ': 'g', 'Ğ': 'g',
                   'ü': 'u', 'Ü': 'u', 'ö': 'o', 'Ö': 'o', 'ç': 'c', 'Ç': 'c' };

        return value
            .replace(/[şŞıİğĞüÜöÖçÇ]/g, function (ch) { return tr[ch]; })
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    $baslik.on('input', function () {
        if (!otomatik) { return; }

        $slug.val(slugify(this.value));
    });

    $slug.on('input', function () { otomatik = false; });
});
