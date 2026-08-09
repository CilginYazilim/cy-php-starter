/* ==================================================================
 *  KAYIT EKRANI – çift gönderim koruması
 * ================================================================== */

/* global jQuery */
jQuery(function ($) {
    'use strict';

    $('form[action*="kayit"]').on('submit', function () {
        $(this).find('button[type="submit"]').prop('disabled', true).addClass('disabled');
    });
});
