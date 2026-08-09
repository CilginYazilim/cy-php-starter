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

    var table = $('#message_table').DataTable({
        processing: true,
        serverSide: true,
        order: [[4, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: '<"cy-dt-top"l>rt<"cy-dt-bottom"ip>',

        ajax: {
            url: API.list,
            type: 'POST',
            data: function (d) {
                d.csrf_token = CY.token();
                d.filter     = $('#filter_status').val() || '';
            },
            dataSrc: function (json) {
                updateUnread(json.unread);
                return json.data;
            },
            error: function (xhr) { CY.ajaxError(xhr, 'Mesajlar yüklenirken bir hata oluştu.'); }
        },

        columnDefs: [
            { targets: 0, orderable: false, searchable: false },
            { targets: 5, orderable: false, searchable: false, className: 'text-center' }
        ],

        drawCallback: function (settings) {
            $('#total_records').text(settings.json ? settings.json.recordsTotal : 0);
            updateBulkButtons();
        },

        language: {
            emptyTable: 'Henüz mesaj bulunmuyor.',
            info: '_TOTAL_ kayıttan _START_ – _END_ arası',
            infoEmpty: 'Gösterilecek kayıt yok',
            infoFiltered: '(toplam _MAX_ kayıt içinden filtrelendi)',
            lengthMenu: 'Sayfada _MENU_ kayıt',
            loadingRecords: 'Yükleniyor…',
            processing: 'İşleniyor…',
            zeroRecords: 'Aramanızla eşleşen mesaj bulunamadı.',
            paginate: { first: 'İlk', last: 'Son', next: 'Sonraki', previous: 'Önceki' }
        }
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
                $('#msg_ad').text(response.ad);
                $('#msg_eposta').text(response.eposta);
                $('#msg_konu').text(response.konu || '(konusuz)');
                $('#msg_tarih').text(response.tarih);
                $('#msg_uye').text(response.uye || '—');
                $('#msg_ip').text(response.ip || '—');
                $('#msg_metin').text(response.mesaj);
                $('#msg_reply').attr('href', 'mailto:' + response.eposta);
                updateUnread(response.unread);
                messageModal.show();
                reload(false);
            })
            .fail(function (xhr) { CY.ajaxError(xhr, 'Mesaj getirilemedi.'); });
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
