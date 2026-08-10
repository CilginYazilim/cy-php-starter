/* ==================================================================
 *  MESAJ YÖNETİMİ – DataTables + AJAX
 *  cilginyazilim.com
 * ================================================================== */

/* global bootstrap, jQuery, CY */
jQuery(function ($) {
    'use strict';

    if (!document.getElementById('message_table')) { return; }

    var API = {
        list:   CY.url('api/mesajlar/list'),
        fetch:  CY.url('api/mesajlar/fetch'),
        read:   CY.url('api/mesajlar/okundu'),
        remove: CY.url('api/mesajlar/delete'),
        bulk:   CY.url('api/mesajlar/toplu')
    };

    var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
    var deleteModal  = new bootstrap.Modal(document.getElementById('deleteMessageModal'));

    var pendingDeleteId = null;
    var searchTimer     = null;

    function updateUnread(count) {
        if (typeof count === 'number') { $('#unread_count').text(count); }
    }

    var table = CY.table('#message_table', {
        isim:  'mesaj',
        order: [[4, 'desc']],

        ajax: {
            url: API.list,
            data: function (d) {
                d.filter = $('#filter_status').val() || '';
            },
            // Sunucu okunmamış sayısını her yanıtta gönderir; rozeti
            // satırları basmadan önce tazeliyoruz.
            dataSrc: function (json) {
                updateUnread(json.unread);
                return json.data;
            }
        },

        columnDefs: [
            { targets: 0, orderable: false, searchable: false },
            { targets: 5, orderable: false, searchable: false, className: 'text-center' }
        ],

        drawCallback: function () { updateBulkButtons(); }
    });

    function reload(resetPaging) { table.ajax.reload(null, resetPaging === true); }

    function selectedIds() {
        return $('.js-select-row:checked').map(function () { return $(this).val(); }).get();
    }

    function updateBulkButtons() {
        var count = selectedIds().length;
        $('#bulk_read, #bulk_delete').prop('disabled', count === 0);
    }

    $('#table_search').on('input', function () {
        var value = this.value;
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () { table.search(value).draw(); }, 350);
    });

    $('#filter_status').on('change', function () { reload(true); });

    $('#select_all').on('change', function () {
        $('.js-select-row').prop('checked', this.checked);
        updateBulkButtons();
    });

    $('#message_table').on('change', '.js-select-row', updateBulkButtons);

    $('#message_table').on('click', '.js-view', function () {
        var id = $(this).data('id');

        $.ajax({ url: API.fetch, method: 'POST', dataType: 'json', data: { id: id, csrf_token: CY.token() } })
            .done(function (response) {
                var konu = response.konu || '(konusuz)';

                $('#msg_ad').text(response.ad);
                $('#msg_eposta').text(response.eposta);
                $('#msg_eposta_link').attr('href', 'mailto:' + response.eposta);
                $('#msg_konu').text(konu);
                $('#msg_tarih').text(response.tarih);
                $('#msg_uye').text(response.uye || '—');
                $('#msg_ip').text(response.ip || '—');
                $('#msg_metin').text(response.mesaj);

                // Baş harf: gönderen adının ilk karakteri.
                $('#msg_bashari').text((response.ad || '?').charAt(0).toLocaleUpperCase('tr-TR'));

                // Mesaj artık okundu sayılır; rozet bunu yansıtsın.
                $('#msg_durum').text('Okundu').removeClass('cy-badge--warning').addClass('cy-badge--success');

                /* POSTA PROGRAMI BAĞLANTISI
                 * Konu ve alıntılanan mesaj da taşınır: kullanıcı boş bir
                 * pencereye "neydi bu?" diye bakmasın. mailto: yalnızca
                 * işletim sisteminde kayıtlı bir posta istemcisi varsa
                 * çalışır; olmadığında yandaki "Adresi Kopyala" devreye
                 * girer (bkz. modal). */
                var govde = '\n\n----- Gelen mesaj -----\n' + (response.mesaj || '');

                /* Adres KAÇIŞLANMAZ: "@" karakterini %40'a çevirmek
                 * bazı posta istemcilerinde alıcı alanını boş bırakıyor.
                 * Konu ve gövde ise mutlaka kaçışlanmalı. */
                $('#msg_reply').attr(
                    'href',
                    'mailto:' + response.eposta
                        + '?subject=' + encodeURIComponent('Re: ' + konu)
                        + '&body=' + encodeURIComponent(govde)
                );

                $('#msg_copy').data('eposta', response.eposta);

                // Panelden yanıtlama: alıcı ve konu, e-posta merkezinin
                // formuna adres satırından taşınır (bkz. MailController).
                var $panelReply = $('#msg_reply_panel');

                if ($panelReply.length) {
                    var yanitKonusu = response.konu ? 'Re: ' + response.konu : 'Mesajınız hakkında';
                    var base = CY.url('panel/eposta');

                    // "Temiz adres" ayarı açıkken adreste henüz "?" yoktur,
                    // kapalıyken "index.php?r=..." zaten sorgu taşır.
                    var sep = base.indexOf('?') === -1 ? '?' : '&';

                    $panelReply.attr('href',
                        base + sep
                        + 'adres=' + encodeURIComponent(response.eposta)
                        + '&konu=' + encodeURIComponent(konu));
                }
                updateUnread(response.unread);
                messageModal.show();
                reload(false);
            })
            .fail(function (xhr) { CY.ajaxError(xhr, 'Mesaj getirilemedi.'); });
    });

    /* ADRESİ KOPYALA
     *
     * "Posta Programım" düğmesi mailto: kullanır ve işletim sisteminde
     * kayıtlı bir posta istemcisi yoksa HİÇBİR ŞEY YAPMAZ — kullanıcı
     * da düğmenin bozuk olduğunu sanır. Bu düğme her koşulda çalışan
     * alternatiftir: adresi panoya alır, kişi kendi webmail'ine
     * yapıştırır.
     *
     * navigator.clipboard yalnızca güvenli bağlamda (https ya da
     * localhost) tanımlıdır; olmadığı yerde eski execCommand yoluna
     * düşüyoruz. */
    $('#msg_copy').on('click', function () {
        var adres = $(this).data('eposta');

        if (!adres) { return; }

        function bildir() { CY.notify(adres + ' panoya kopyalandı.', 'success'); }

        if (window.navigator.clipboard && window.isSecureContext) {
            window.navigator.clipboard.writeText(adres).then(bildir, yedek);
            return;
        }

        yedek();

        function yedek() {
            var $gecici = $('<textarea>').val(adres).css({ position: 'fixed', opacity: 0 }).appendTo('body');

            $gecici[0].select();

            try {
                document.execCommand('copy');
                bildir();
            } catch (e) {
                CY.notify('Kopyalanamadı: ' + adres, 'warning');
            }

            $gecici.remove();
        }
    });

    $('#message_table').on('click', '.js-toggle-read', function () {
        var $button = $(this).prop('disabled', true);

        $.ajax({
            url: API.read, method: 'POST', dataType: 'json',
            data: { id: $button.data('id'), deger: $button.data('next'), csrf_token: CY.token() }
        })
        .done(function (response) { CY.notify(response.description, 'success'); updateUnread(response.unread); reload(false); })
        .fail(function (xhr) { CY.ajaxError(xhr, 'Durum değiştirilemedi.'); $button.prop('disabled', false); });
    });

    $('#message_table').on('click', '.js-delete', function () {
        pendingDeleteId = $(this).data('id');
        $('#delete_message_label').text($(this).data('label') || ('#' + pendingDeleteId));
        deleteModal.show();
    });

    $('#confirm_delete_message').on('click', function () {
        if (pendingDeleteId === null) { return; }
        var $button = $(this).prop('disabled', true);

        $.ajax({
            url: API.remove, method: 'POST', dataType: 'json',
            data: { id: pendingDeleteId, csrf_token: CY.token() }
        })
        .done(function (response) { CY.notify(response.description, 'success'); updateUnread(response.unread); reload(false); })
        .fail(function (xhr) { CY.ajaxError(xhr, 'Silme işlemi başarısız oldu.'); })
        .always(function () { $button.prop('disabled', false); deleteModal.hide(); pendingDeleteId = null; });
    });

    function bulkAction(action, confirmMessage) {
        var ids = selectedIds();
        if (ids.length === 0) { return; }
        if (confirmMessage && !window.confirm(confirmMessage)) { return; }

        $.ajax({
            url: API.bulk, method: 'POST', dataType: 'json',
            data: { islem: action, ids: ids, csrf_token: CY.token() }
        })
        .done(function (response) { CY.notify(response.description, 'success'); updateUnread(response.unread); reload(false); })
        .fail(function (xhr) { CY.ajaxError(xhr, 'Toplu işlem başarısız oldu.'); });
    }

    $('#bulk_read').on('click', function () { bulkAction('okundu'); });
    $('#bulk_delete').on('click', function () { bulkAction('sil', 'Seçili mesajlar kalıcı olarak silinecek. Emin misiniz?'); });
});
