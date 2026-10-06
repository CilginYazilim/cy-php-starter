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

/* ==================================================================
 *  LİSTE AYARLARI (Ayarlar → Ana Sayfa → Özellikler, SSS…)
 * ------------------------------------------------------------------
 *  Satır ekle / sil / yukarı / aşağı. Alan adları "anahtar[3][baslik]"
 *  biçimindedir; PHP diziyi formdaki SIRAYLA kurar, bu yüzden taşımak
 *  için adları yeniden numaralamak gerekmez. Yeni satır <template>'ten
 *  kopyalanır ve benzersiz bir numara alır.
 *
 *  JavaScript yoksa sunucu kayıtlı satırların altına bir boş satır
 *  basar; form yine çalışır.
 * ================================================================== */
(function () {
    'use strict';

    var sayac = 1000;

    function guncelle(kutu) {
        var max   = parseInt(kutu.getAttribute('data-max'), 10) || 20;
        var adet  = kutu.querySelectorAll('[data-repeater-list] > [data-repeater-row]').length;
        var ekle  = kutu.querySelector('[data-repeater-add]');

        if (ekle) { ekle.disabled = adet >= max; }
    }

    document.addEventListener('click', function (olay) {
        var dugme = olay.target.closest('[data-repeater-add], [data-repeater-remove], [data-repeater-up], [data-repeater-down]');
        if (!dugme) { return; }

        var kutu  = dugme.closest('[data-repeater]');
        var liste = kutu && kutu.querySelector('[data-repeater-list]');
        if (!liste) { return; }

        if (dugme.hasAttribute('data-repeater-add')) {
            var sablon = kutu.querySelector('template[data-repeater-template]');
            var html   = sablon.innerHTML.replace(/__i__/g, String(sayac++));
            liste.insertAdjacentHTML('beforeend', html);

            var ilk = liste.lastElementChild.querySelector('input, textarea, select');
            if (ilk) { ilk.focus(); }
        } else {
            var satir = dugme.closest('[data-repeater-row]');

            if (dugme.hasAttribute('data-repeater-remove')) {
                satir.remove();
            } else if (dugme.hasAttribute('data-repeater-up') && satir.previousElementSibling) {
                liste.insertBefore(satir, satir.previousElementSibling);
                dugme.focus();
            } else if (dugme.hasAttribute('data-repeater-down') && satir.nextElementSibling) {
                liste.insertBefore(satir.nextElementSibling, satir);
                dugme.focus();
            }
        }

        guncelle(kutu);
    });

    document.querySelectorAll('[data-repeater]').forEach(guncelle);
}());

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
