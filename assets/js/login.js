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

    // Demo hesap butonları: alanları doldurup formu otomatik gönderir.
    // "Pasif" / "Askıda" hesaplar sunucu tarafında reddedilir; bu da
    // durum kontrolünün gerçekten çalıştığını gösterir.
    $('.js-quick-login').on('click', function () {
        var $button = $(this);

        $('#identifier').val($button.data('identifier'));
        $('#password').val($button.data('password'));

        $('.js-quick-login').prop('disabled', true);
        $form.trigger('submit');
    });

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
