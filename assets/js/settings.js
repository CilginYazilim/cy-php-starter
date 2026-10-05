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
 *  KAYDEDİLMEMİŞ DEĞİŞİKLİK UYARISI
 * ------------------------------------------------------------------
 *  Bu blok eskiden "#ayar_ara" arama kutusunun varlığına bağlıydı.
 *  Ayarlar sekmelerden ayrı sayfalara bölününce o kutu kaldırıldı ve
 *  arama kodu ile birlikte bu uyarı da hiç çalışmaz oldu (ölü kod).
 *  Arama artık yok — her sayfa tek bir grubu gösteriyor — ama uyarı
 *  hâlâ işe yarar; doğrudan forma bağlandı.
 * ================================================================== */
jQuery(function ($) {
    'use strict';

    var $form = $('#ayarlar_formu');

    if (!$form.length) { return; }

    var kirli = false;

    $form.on('change input', ':input', function () { kirli = true; });
    $form.on('submit', function () { kirli = false; });

    $(window).on('beforeunload', function () {
        if (kirli) { return 'Kaydedilmemiş ayar değişiklikleriniz var.'; }
    });
});
