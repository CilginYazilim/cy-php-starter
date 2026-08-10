/* ==================================================================
 *  SİTE AYARLARI – E-posta sınama düğmeleri
 *  cilginyazilim.com
 * ------------------------------------------------------------------
 *  İki uç nokta kullanılır:
 *    api/eposta/baglanti → yalnızca SMTP'ye bağlanır, mektup GÖNDERMEZ
 *    api/eposta/sinama   → gerçek bir örnek mektup gönderir
 *
 *  Hata metinleri SMTP sunucusundan geldiği gibi gösterilir; "535
 *  Authentication failed" gibi bir yanıt sorunun parolada olduğunu
 *  tahmin etmekten çok daha hızlı anlatır.
 * ================================================================== */

/* global jQuery, CY */
jQuery(function ($) {
    'use strict';

    var $card = $('#mail_test_card');

    if (!$card.length) { return; }

    var $result = $('#mail_test_sonuc');

    function busy(isBusy) {
        $('#mail_test_gonder, #mail_test_baglanti').prop('disabled', isBusy);
        $('#mail_test_spinner').toggleClass('d-none', !isBusy);
    }

    function showResult(message, isError) {
        $result
            .removeClass('d-none cy-alert--danger cy-alert--success')
            .addClass(isError ? 'cy-alert--danger' : 'cy-alert--success')
            .text(message);
    }

    function call(url, data, fallback) {
        busy(true);
        $result.addClass('d-none');

        $.ajax({ url: CY.url(url), method: 'POST', dataType: 'json', data: data })
            .done(function (response) {
                showResult(response.description, false);
                CY.notify(response.description, 'success');
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};
                showResult(res.description || fallback, true);
            })
            .always(function () { busy(false); });
    }

    $('#mail_test_gonder').on('click', function () {
        call('api/eposta/sinama', {
            eposta: $('#mail_test_adres').val(),
            csrf_token: CY.token()
        }, 'Sınama e-postası gönderilemedi.');
    });

    $('#mail_test_baglanti').on('click', function () {
        call('api/eposta/baglanti', { csrf_token: CY.token() }, 'Bağlantı kurulamadı.');
    });
});

/* ==================================================================
 *  AYAR ARAMA + YAPIŞKAN KAYDET
 * ------------------------------------------------------------------
 *  Arama, TÜM sekmelerde birden arar: aradığınız ayarın hangi grupta
 *  olduğunu bilmek zorunda kalmazsınız. Eşleşme bulunan sekmeler
 *  otomatik görünür kalır, boş kalanlar gizlenir.
 * ================================================================== */
jQuery(function ($) {
    'use strict';

    var $arama = $('#ayar_ara');

    if (!$arama.length) { return; }

    var $ayarlar = $('.cy-ayar');
    var $bos     = $('#ayar_bos');
    var $tablar  = $('.cy-tabnav .nav-item');
    var $icerik  = $('.tab-content');

    function normalize(text) {
        // Türkçe büyük/küçük harf farkı aramayı bozmasın.
        return (text || '').toLocaleLowerCase('tr-TR').trim();
    }

    $arama.on('input', function () {
        var terim = normalize(this.value);

        if (terim === '') {
            $ayarlar.removeClass('d-none');
            $('.tab-pane').removeClass('cy-arama-modu');
            $tablar.removeClass('d-none');
            $icerik.removeClass('cy-arama-acik');
            $bos.addClass('d-none');
            return;
        }

        var toplam = 0;

        $('.tab-pane').each(function () {
            var $pane   = $(this);
            var eslesen = 0;

            $pane.find('.cy-ayar').each(function () {
                var uygun = normalize($(this).data('ayar')).indexOf(terim) !== -1;

                $(this).toggleClass('d-none', !uygun);

                if (uygun) { eslesen++; }
            });

            // Arama modunda tüm paneller aynı anda görünür; kullanıcı
            // sekme sekme gezmek zorunda kalmasın.
            $pane.toggleClass('cy-arama-modu', eslesen > 0);
            toplam += eslesen;
        });

        $icerik.addClass('cy-arama-acik');
        $bos.toggleClass('d-none', toplam > 0);
    });

    /* Kaydedilmemiş değişiklik varsa sayfadan ayrılırken uyar. */
    var kirli = false;

    $('#ayarlar_formu').on('change input', ':input', function () { kirli = true; });
    $('#ayarlar_formu').on('submit', function () { kirli = false; });

    $(window).on('beforeunload', function () {
        if (kirli) { return 'Kaydedilmemiş ayar değişiklikleriniz var.'; }
    });
});
