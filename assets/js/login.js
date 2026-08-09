/* ==================================================================
 *  GİRİŞ EKRANI
 *  cilginyazilim.com
 * ------------------------------------------------------------------
 *  Çift gönderim koruması: yavaş bağlantıda kullanıcı butona iki kez
 *  basabilir; bu da iki giriş denemesi demektir ve kaba kuvvet
 *  sayacını boş yere artırır.
 *
 *  Parolayı göster/gizle davranışı app.js içinde ortak tanımlıdır.
 * ================================================================== */

/* global jQuery */
jQuery(function ($) {
    'use strict';

    var $form = $('#cy_login_form');

    if (!$form.length) { return; }

    $form.on('submit', function () {
        var $button = $form.find('button[type="submit"]');

        $button.prop('disabled', true).addClass('disabled');

        // Sunucu hata döndürüp sayfa yeniden yüklenirse buton zaten
        // sıfırlanır. Tarayıcı geri tuşu senaryosu için kısa bir süre
        // sonra tekrar açıyoruz.
        window.setTimeout(function () {
            $button.prop('disabled', false).removeClass('disabled');
        }, 6000);
    });
});
