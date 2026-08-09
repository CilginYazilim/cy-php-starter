/* ==================================================================
 *  İLETİŞİM FORMU – AJAX gönderim
 *  cilginyazilim.com
 * ------------------------------------------------------------------
 *  Sayfa yenilenmeden mesaj gönderir; başarı/hata CY.notify() ile
 *  bildirilir, alan bazlı hatalar formun altına yazılır.
 * ================================================================== */

/* global jQuery, CY */
jQuery(function ($) {
    'use strict';

    var $form = $('#contact_form');

    if (!$form.length) { return; }

    function clearErrors() {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('[data-error-for]').text('');
        $('#contact_alert').addClass('d-none').text('');
        $('#contact_success').addClass('d-none').text('');
    }

    function setLoading(isLoading) {
        $('#contact_submit').prop('disabled', isLoading);
        $('#contact_spinner').toggleClass('d-none', !isLoading);
        $('#contact_submit_label').text(isLoading ? 'Gönderiliyor…' : 'Gönder');
    }

    $form.on('submit', function (event) {
        event.preventDefault();
        clearErrors();
        setLoading(true);

        $.ajax({
            url: CY.url('api/iletisim/gonder'),
            method: 'POST',
            dataType: 'json',
            data: $form.serialize()
        })
        .done(function (response) {
            $('#contact_success').removeClass('d-none').text(response.description);
            $form[0].reset();
        })
        .fail(function (xhr) {
            var res = xhr.responseJSON || {};

            if (xhr.status === 422 && res.errors) {
                $.each(res.errors, function (field, message) {
                    $('#' + field).addClass('is-invalid');
                    $('[data-error-for="' + field + '"]').text(message);
                });
                $('#contact_alert').removeClass('d-none').text(res.description || 'Lütfen formdaki hataları düzeltin.');
                return;
            }

            CY.ajaxError(xhr, 'Mesajınız gönderilemedi.');
        })
        .always(function () {
            setLoading(false);
        });
    });
});
