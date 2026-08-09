/* ==================================================================
 *  KULLANICI YÖNETİMİ – DataTables + AJAX CRUD
 *  cilginyazilim.com
 * ================================================================== */

/* global bootstrap, jQuery, CY */
jQuery(function ($) {
    'use strict';

    if (!document.getElementById('user_table')) { return; }

    var API = {
        list:   CY.url('api/kullanicilar/list'),
        fetch:  CY.url('api/kullanicilar/fetch'),
        save:   CY.url('api/kullanicilar/save'),
        remove: CY.url('api/kullanicilar/delete'),
        status: CY.url('api/kullanicilar/status')
    };

    var MAX_BYTES = 2 * 1024 * 1024;

    var userModal   = new bootstrap.Modal(document.getElementById('userModal'));
    var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    var pendingDeleteId = null;
    var currentDetailId = null;
    var searchTimer     = null;

    function clearErrors() {
        var $form = $('#user_form');
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.is-invalid-group').removeClass('is-invalid-group');
        $form.find('[data-error-for]').text('');
        $('#form_alert').addClass('d-none').text('');
    }

    function showErrors(errors, general) {
        clearErrors();

        if (general) { $('#form_alert').removeClass('d-none').text(general); }

        $.each(errors || {}, function (field, message) {
            // "rol"/"durum" artık radyo grubu; grubun kendi id'si yoktur,
            // ama "<alan>_group" sarmalayıcısı vardır (bkz. modals.php).
            $('#' + field).addClass('is-invalid');
            $('#' + field + '_group').addClass('is-invalid-group');
            $('[data-error-for="' + field + '"]').text(message);
        });

        var $first = $('#user_form').find('.is-invalid, .is-invalid-group').first();
        if ($first.length) { $first.trigger('focus'); }
    }

    function setLoading(isLoading) {
        $('#submit_button').prop('disabled', isLoading);
        $('#submit_spinner').toggleClass('d-none', !isLoading);
        $('#submit_label').text(isLoading ? 'Kaydediliyor…' : 'Kaydet');
    }

    function resetForm() {
        document.getElementById('user_form').reset();
        $('#user_id').val('');
        $('#avatar_preview').addClass('d-none').attr('src', '');
        $('#avatar_placeholder').removeClass('d-none');
        $('#durum_aktif').prop('checked', true);
        clearErrors();
    }

    function fetchUser(id, onDone) {
        $.ajax({
            url: API.fetch, method: 'POST', dataType: 'json',
            data: { id: id, csrf_token: CY.token() }
        })
        .done(function (response) { onDone(response.data); })
        .fail(function (xhr) { CY.ajaxError(xhr, 'Kayıt getirilemedi.'); });
    }

    function openEditModal(data) {
        resetForm();

        $('#form_action').val('edit');
        $('#user_id').val(data.id);
        $('#ad').val(data.ad);
        $('#soyad').val(data.soyad);
        $('#kullanici_adi').val(data.kullanici_adi);
        $('#eposta').val(data.eposta);
        $('#telefon').val(data.telefon);
        $('#hakkinda').val(data.hakkinda);
        $('#rol_' + data.rol).prop('checked', true);
        $('#durum_' + data.durum).prop('checked', true);

        $('#userModalLabel').text('Kullanıcıyı Düzenle');
        $('#userModalSubtitle').text('#' + data.id + ' numaralı hesabın bilgilerini güncelleyin.');
        $('#password_required').addClass('d-none');
        $('#password_hint').text('Değiştirmek istemiyorsanız boş bırakın.');
        $('#sifre').attr('placeholder', 'Boş bırakılırsa değişmez');

        if (data.avatar_url) {
            $('#avatar_preview').attr('src', data.avatar_url).removeClass('d-none');
            $('#avatar_placeholder').addClass('d-none');
        }

        userModal.show();
    }

    var table = $('#user_table').DataTable({
        processing: true,
        serverSide: true,
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: 'rt<"cy-dt-bottom"<"cy-dt-bottom__left"li>p>',

        ajax: {
            url: API.list,
            type: 'POST',
            data: function (d) {
                d.csrf_token      = CY.token();
                d.filter_role     = $('#filter_role').val() || '';
                d.filter_status   = $('#filter_status').val() || '';
                d.filter_date_from = $('#filter_date_from').val() || '';
                d.filter_date_to   = $('#filter_date_to').val() || '';
            },
            error: function (xhr) { CY.ajaxError(xhr, 'Kayıtlar yüklenirken bir hata oluştu.'); }
        },

        columnDefs: [
            { targets: 0, className: 'cy-id' },
            { targets: 1, orderable: false, searchable: false },
            { targets: 3, className: 'cy-hide-sm' },
            { targets: 5, className: 'cy-hide-xs' },
            { targets: 6, orderable: false, searchable: false, className: 'text-center' }
        ],

        drawCallback: function (settings) {
            $('#total_records').text(settings.json ? settings.json.recordsTotal : 0);
        },

        language: {
            emptyTable: 'Henüz kullanıcı bulunmuyor.',
            info: '_TOTAL_ kayıttan _START_ – _END_ arası',
            infoEmpty: 'Gösterilecek kayıt yok',
            infoFiltered: '(toplam _MAX_ kayıt içinden filtrelendi)',
            lengthMenu: 'Sayfada _MENU_ kayıt',
            loadingRecords: 'Yükleniyor…',
            processing: 'İşleniyor…',
            zeroRecords: 'Aramanızla eşleşen kullanıcı bulunamadı.',
            paginate: { first: 'İlk', last: 'Son', next: 'Sonraki', previous: 'Önceki' },
            aria: {
                sortAscending: ': artan sırada sıralamak için etkinleştir',
                sortDescending: ': azalan sırada sıralamak için etkinleştir'
            }
        }
    });

    function reload(resetPaging) { table.ajax.reload(null, resetPaging === true); }

    function updateFilterState() {
        var count = 0;
        if ($('#table_search').val()) { count++; }
        if ($('#filter_role').val()) { count++; }
        if ($('#filter_status').val()) { count++; }
        if ($('#filter_date_from').val()) { count++; }
        if ($('#filter_date_to').val()) { count++; }

        $('#reset_filters').prop('disabled', count === 0);
        $('#active_filter_count').text(count).toggleClass('d-none', count === 0);
    }

    $('#table_search').on('input', function () {
        var value = this.value;
        updateFilterState();
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () { table.search(value).draw(); }, 350);
    });

    $('#filter_role, #filter_status').on('change', function () {
        updateFilterState();
        reload(true);
    });

    $('#filter_date_from, #filter_date_to').on('change', function () {
        // Bitiş tarihi başlangıçtan küçükse ikisini de aynı güne çek.
        var from = $('#filter_date_from').val();
        var to   = $('#filter_date_to').val();
        if (from && to && to < from) {
            if (this.id === 'filter_date_from') { $('#filter_date_to').val(from); }
            else { $('#filter_date_from').val(to); }
        }
        updateFilterState();
        reload(true);
    });

    $('#reset_filters').on('click', function () {
        $('#table_search').val('');
        $('#filter_role').val('');
        $('#filter_status').val('');
        $('#filter_date_from').val('');
        $('#filter_date_to').val('');
        updateFilterState();
        table.search('').draw();
    });

    $('#add_button').on('click', function () {
        resetForm();
        $('#form_action').val('add');
        $('#userModalLabel').text('Yeni Kullanıcı');
        $('#userModalSubtitle').text('Formu doldurup hesabı oluşturun.');
        $('#password_required').removeClass('d-none');
        $('#password_hint').text('En az 8 karakter; harf ve rakam içermelidir.');
        $('#sifre').attr('placeholder', 'En az 8 karakter, harf ve rakam');
        userModal.show();
    });

    $('#avatar').on('change', function () {
        var file = this.files && this.files[0];

        $(this).removeClass('is-invalid');
        $('[data-error-for="avatar"]').text('');

        if (!file) {
            $('#avatar_preview').addClass('d-none').attr('src', '');
            $('#avatar_placeholder').removeClass('d-none');
            return;
        }
        if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
            $(this).val('').addClass('is-invalid');
            $('[data-error-for="avatar"]').text('Yalnızca JPG, PNG, GIF ve WEBP dosyaları yükleyebilirsiniz.');
            return;
        }
        if (file.size > MAX_BYTES) {
            $(this).val('').addClass('is-invalid');
            $('[data-error-for="avatar"]').text('Görsel boyutu en fazla 2 MB olabilir.');
            return;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            $('#avatar_preview').attr('src', event.target.result).removeClass('d-none');
            $('#avatar_placeholder').addClass('d-none');
        };
        reader.readAsDataURL(file);
    });

    $('#user_form').on('submit', function (event) {
        event.preventDefault();
        clearErrors();
        setLoading(true);

        $.ajax({
            url: API.save, method: 'POST',
            data: new FormData(this), contentType: false, processData: false, dataType: 'json'
        })
        .done(function (response) {
            userModal.hide();
            CY.notify(response.description, 'success');
            reload(false);
        })
        .fail(function (xhr) {
            var res = xhr.responseJSON || {};
            if (xhr.status === 422) { showErrors(res.errors, res.description); return; }
            CY.ajaxError(xhr, 'İşlem tamamlanamadı.');
        })
        .always(function () { setLoading(false); });
    });

    $('#user_table').on('click', '.js-view', function () {
        fetchUser($(this).data('id'), function (data) {
            currentDetailId = data.id;

            $('#detail_id').text('#' + data.id);
            $('#detail_fullname').text(data.ad_soyad);
            $('#detail_kadi').text('@' + data.kullanici_adi);
            $('#detail_email').text(data.eposta);
            $('#detail_phone').text(data.telefon || '—');
            $('#detail_bio').text(data.hakkinda || '—');
            $('#detail_login').text(data.son_giris);
            $('#detail_created').text(data.created_at);

            var roleClass = data.rol === 'admin' ? 'admin' : (data.rol === 'editor' ? 'editor' : 'member');
            $('#detail_role').attr('class', 'cy-role cy-role--' + roleClass).text(data.rol_etiket);

            var statusClass = data.durum === 'aktif' ? 'is-active' : (data.durum === 'askida' ? 'is-hold' : 'is-passive');
            $('#detail_status').attr('class', 'cy-status ' + statusClass).text(data.durum_etiket);

            if (data.avatar_url) {
                $('#detail_image').attr('src', data.avatar_url).attr('alt', data.ad_soyad).removeClass('d-none');
                $('#detail_initial').addClass('d-none');
            } else {
                $('#detail_image').addClass('d-none').attr('src', '');
                $('#detail_initial').text(data.ad.charAt(0).toLocaleUpperCase('tr-TR')).removeClass('d-none');
            }

            $('#detail_edit_button').toggleClass('d-none', !data.can_edit);
            detailModal.show();
        });
    });

    $('#detail_edit_button').on('click', function () {
        if (currentDetailId === null) { return; }
        var id = currentDetailId;
        detailModal.hide();
        $('#detailModal').one('hidden.bs.modal', function () { fetchUser(id, openEditModal); });
    });

    $('#user_table').on('click', '.js-edit', function () {
        fetchUser($(this).data('id'), openEditModal);
    });

    $('#user_table').on('click', '.js-toggle-status', function () {
        var $button = $(this).prop('disabled', true);

        $.ajax({
            url: API.status, method: 'POST', dataType: 'json',
            data: { id: $button.data('id'), csrf_token: CY.token() }
        })
        .done(function (response) { CY.notify(response.description, 'success'); reload(false); })
        .fail(function (xhr) { CY.ajaxError(xhr, 'Durum değiştirilemedi.'); $button.prop('disabled', false); });
    });

    $('#user_table').on('click', '.js-delete', function () {
        pendingDeleteId = $(this).data('id');
        $('#delete_label').text($(this).data('label') || ('#' + pendingDeleteId));
        deleteModal.show();
    });

    $('#confirm_delete').on('click', function () {
        if (pendingDeleteId === null) { return; }
        var $button = $(this).prop('disabled', true);

        $.ajax({
            url: API.remove, method: 'POST', dataType: 'json',
            data: { id: pendingDeleteId, csrf_token: CY.token() }
        })
        .done(function (response) { CY.notify(response.description, 'success'); reload(false); })
        .fail(function (xhr) { CY.ajaxError(xhr, 'Silme işlemi başarısız oldu.'); })
        .always(function () { $button.prop('disabled', false); deleteModal.hide(); pendingDeleteId = null; });
    });

    $('#userModal').on('hidden.bs.modal', resetForm);
    $('#detailModal').on('hidden.bs.modal', function () { currentDetailId = null; });
});
