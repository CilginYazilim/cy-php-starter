/* ==================================================================
 *  E-POSTA MERKEZİ – Gönderim + kuyruk ilerlemesi + geçmiş tablosu
 *  cilginyazilim.com
 * ------------------------------------------------------------------
 *  TOPLU GÖNDERİM İKİ ADIMDIR (bkz. MailApiController):
 *
 *    1) api/eposta/gonder → mektupları KUYRUĞA yazar, hemen döner.
 *    2) api/eposta/isle   → her çağrıda bir parti gönderir.
 *
 *  Bu dosya 2. adımı "kalan sıfırlanana kadar" tekrar tekrar çağırır
 *  ve ilerleme çubuğunu besler. Tarayıcı kapanırsa mektuplar kuyrukta
 *  bekler; kullanıcı "Kuyruğu Gönder" ile kaldığı yerden devam eder.
 * ================================================================== */

/* global bootstrap, jQuery, CY */
jQuery(function ($) {
    'use strict';

    if (!document.getElementById('mail_table')) { return; }

    var API = {
        list:     CY.url('api/eposta/list'),
        fetch:    CY.url('api/eposta/fetch'),
        requeue:  CY.url('api/eposta/tekrar'),
        remove:   CY.url('api/eposta/sil'),
        audience: CY.url('api/eposta/alicilar'),
        preview:  CY.url('api/eposta/onizle'),
        send:     CY.url('api/eposta/gonder'),
        process:  CY.url('api/eposta/isle')
    };

    var previewModal = new bootstrap.Modal(document.getElementById('mailPreviewModal'));
    var deleteModal  = new bootstrap.Modal(document.getElementById('mailDeleteModal'));

    var pendingDeleteId = null;
    var searchTimer     = null;
    var audienceTimer   = null;
    var busy            = false;

    /* =============================================================
     *  GEÇMİŞ TABLOSU
     * ============================================================= */

    var table = CY.table('#mail_table', {
        isim:  'kayıt',
        order: [[4, 'desc']],

        ajax: {
            url: API.list,
            data: function (d) {
                d.durum = $('#filter_durum').val() || '';
                d.tur   = $('#filter_tur').val()   || '';
            },
            error: function (xhr) { CY.ajaxError(xhr, 'E-posta geçmişi yüklenemedi.'); }
        },

        columnDefs: [
            { targets: 5, orderable: false, searchable: false, className: 'text-center' }
        ],

        drawCallback: function (settings) {
            // Kuyruk sayacı listeyle birlikte tazelenir; başka bir
            // sekmede kuyruk işlendiyse burada da güncel görünür.
            if (settings.json && typeof settings.json.bekleyen !== 'undefined') {
                setPending(settings.json.bekleyen);
            }
        },

        language: { emptyTable: 'Henüz e-posta gönderilmemiş.' }
    });

    function reload(resetPaging) { table.ajax.reload(null, resetPaging === true); }

    function setPending(count) { $('#stat_kuyrukta').text(count); }

    $('#mail_search').on('input', function () {
        var value = this.value;
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () { table.search(value).draw(); }, 350);
    });

    $('#filter_durum, #filter_tur').on('change', function () { reload(true); });

    /* =============================================================
     *  ÖNİZLEME (hem geçmişteki kayıt hem de yazılmakta olan taslak)
     * ============================================================= */

    /**
     * Mektubu iframe'e yerleştirir.
     * srcdoc ATANIR, .html() ile GÖMÜLMEZ: iframe'in sandbox
     * özniteliği sayesinde içerideki hiçbir script çalışmaz ve
     * mektubun stilleri panele sızmaz.
     */
    function showPreview(meta, html) {
        $('#preview_alici').text(meta.alici || '—');
        $('#preview_konu').text(meta.konu || '—');
        $('#preview_durum').text(meta.durum || '—');
        $('#preview_tarih').text(meta.tarih || '—');

        $('#preview_meta').toggleClass('d-none', meta.hideMeta === true);

        var $error = $('#preview_hata');
        if (meta.hata) {
            $error.removeClass('d-none').text('Hata: ' + meta.hata + (meta.deneme ? ' (deneme: ' + meta.deneme + ')' : ''));
        } else {
            $error.addClass('d-none').text('');
        }

        document.getElementById('preview_frame').srcdoc = html || '';
        previewModal.show();
    }

    $('#mail_table').on('click', '.js-preview', function () {
        var $button = $(this).prop('disabled', true);

        $.ajax({
            url: API.fetch, method: 'POST', dataType: 'json',
            data: { id: $button.data('id'), csrf_token: CY.token() }
        })
        .done(function (res) {
            showPreview({
                alici:  res.alici,
                konu:   res.konu,
                durum:  res.durum + ' · ' + res.tur,
                tarih:  res.tarih,
                hata:   res.hata,
                deneme: res.deneme
            }, res.govde);
        })
        .fail(function (xhr) { CY.ajaxError(xhr, 'Mektup getirilemedi.'); })
        .always(function () { $button.prop('disabled', false); });
    });

    /* =============================================================
     *  GEÇMİŞ İŞLEMLERİ
     * ============================================================= */

    $('#mail_table').on('click', '.js-requeue', function () {
        var $button = $(this).prop('disabled', true);

        $.ajax({
            url: API.requeue, method: 'POST', dataType: 'json',
            data: { id: $button.data('id'), csrf_token: CY.token() }
        })
        .done(function (res) {
            CY.notify(res.description, 'success');
            setPending(res.bekleyen);
            reload(false);
        })
        .fail(function (xhr) { CY.ajaxError(xhr, 'Yeniden kuyruğa alınamadı.'); $button.prop('disabled', false); });
    });

    $('#mail_table').on('click', '.js-delete', function () {
        pendingDeleteId = $(this).data('id');
        $('#mail_delete_label').text($(this).data('label') || ('#' + pendingDeleteId));
        deleteModal.show();
    });

    $('#mail_delete_confirm').on('click', function () {
        if (pendingDeleteId === null) { return; }
        var $button = $(this).prop('disabled', true);

        $.ajax({
            url: API.remove, method: 'POST', dataType: 'json',
            data: { id: pendingDeleteId, csrf_token: CY.token() }
        })
        .done(function (res) { CY.notify(res.description, 'success'); reload(false); })
        .fail(function (xhr) { CY.ajaxError(xhr, 'Kayıt silinemedi.'); })
        .always(function () { $button.prop('disabled', false); deleteModal.hide(); pendingDeleteId = null; });
    });

    /* =============================================================
     *  KUYRUĞU İŞLEME
     * ------------------------------------------------------------
     *  "isle" ucu KUYRUĞUN TAMAMINDAN bir parti gönderir; yalnızca
     *  az önce yazdığımız partiden değil. Bu yüzden ilerlemeyi
     *  "başlangıçtaki bekleyen sayısı" üzerinden hesaplıyoruz.
     * ============================================================= */

    function showProgress(show) {
        $('#gonderim_ilerleme').toggleClass('d-none', !show);
    }

    function updateProgress(done, total, note) {
        var percent = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 100;

        $('#ilerleme_cubuk').css('width', percent + '%');
        $('#ilerleme_yuzde').text('%' + percent);
        $('#ilerleme_metin').text(note || (done + ' / ' + total + ' mektup işlendi'));
    }

    /**
     * Kuyruğu boşalana kadar parti parti gönderir.
     *
     * @param {number}   total    Başlangıçtaki bekleyen mektup sayısı
     * @param {function} onFinish İşlem bitince çağrılır (sonuç özeti ile)
     */
    function drainQueue(total, onFinish) {
        var sent    = 0;
        var failed  = 0;
        var lastError = '';

        function step() {
            $.ajax({
                url: API.process, method: 'POST', dataType: 'json',
                data: { csrf_token: CY.token() }
            })
            .done(function (res) {
                sent   += res.gonderildi;
                failed += res.basarisiz;

                if (res.hata) { lastError = res.hata; }

                setPending(res.kalan);
                updateProgress(sent + failed, total);

                // GÜVENLİK SUBABI: bir turda hiçbir şey işlenmediyse
                // kuyruk ilerlemiyor demektir (ör. veritabanı hatası).
                // Sonsuz döngüye girmemek için dururuz.
                if (res.kalan > 0 && (res.gonderildi + res.basarisiz) > 0) {
                    window.setTimeout(step, 300);
                    return;
                }

                onFinish({ sent: sent, failed: failed, kalan: res.kalan, error: lastError });
            })
            .fail(function (xhr) {
                CY.ajaxError(xhr, 'Kuyruk işlenirken bir hata oluştu.');
                onFinish({ sent: sent, failed: failed, kalan: -1, error: lastError });
            });
        }

        step();
    }

    function finishMessage(result) {
        if (result.kalan === -1) {
            return 'İşlem yarıda kesildi. ' + result.sent + ' mektup gönderildi; kalanlar kuyrukta.';
        }

        if (result.failed > 0) {
            return result.sent + ' mektup gönderildi, ' + result.failed + ' tanesi başarısız oldu'
                 + (result.error ? ' (' + result.error + ')' : '') + '.';
        }

        return result.sent + ' mektup başarıyla gönderildi.';
    }

    $('#kuyrugu_isle').on('click', function () {
        if (busy) { return; }

        var total = parseInt($('#stat_kuyrukta').text(), 10) || 0;

        if (total === 0) {
            CY.notify('Kuyrukta bekleyen mektup yok.', 'info');
            return;
        }

        busy = true;
        var $button = $(this).prop('disabled', true);

        showProgress(true);
        updateProgress(0, total, 'Kuyruk gönderiliyor…');

        drainQueue(total, function (result) {
            busy = false;
            $button.prop('disabled', false);
            showProgress(false);

            CY.notify(finishMessage(result), result.failed > 0 ? 'warning' : 'success');
            reload(false);
        });
    });

    /* =============================================================
     *  GÖNDERME FORMU (yalnızca "mail.send" yetkisi varsa basılır)
     * ============================================================= */

    var $form = $('#mail_form');

    if (!$form.length) { return; }

    function clearErrors() {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('[data-error-for]').text('');
    }

    function showErrors(errors, general) {
        clearErrors();

        $.each(errors || {}, function (field, message) {
            $('#' + field).addClass('is-invalid');
            $('[data-error-for="' + field + '"]').text(message);
        });

        if (general) { CY.notify(general, 'danger'); }

        var $first = $form.find('.is-invalid').first();
        if ($first.length) { $first.trigger('focus'); }
    }

    function setSending(isSending, label) {
        busy = isSending;
        $('#mail_gonder, #mail_onizle').prop('disabled', isSending);
        $('#mail_spinner').toggleClass('d-none', !isSending);
        $('#mail_gonder_metin').text(label || 'Gönder');
    }

    /** Taslak + hedef kitle alanlarını tek bir POST gövdesine toplar. */
    function formData() {
        return {
            csrf_token:   CY.token(),
            hedef:        $('#hedef').val(),
            adresler:     $('#adresler').val(),
            kullanici_id: $('#kullanici_id').val(),
            konu:         $('#konu').val(),
            baslik:       $('#baslik').val(),
            govde:        $('#govde').val(),
            dugme_metni:  $('#dugme_metni').val(),
            dugme_url:    $('#dugme_url').val()
        };
    }

    /* ---- Hedef kitle seçimi ---- */

    function syncAudienceFields() {
        var hedef = $('#hedef').val();

        $('#alan_adresler').toggleClass('d-none', hedef !== 'elle');
        $('#alan_kullanici').toggleClass('d-none', hedef !== 'kullanici');
    }

    function refreshAudience() {
        var $summary = $('#alici_ozet').removeClass('cy-alert--danger').addClass('cy-alert--info');

        $.ajax({
            url: API.audience, method: 'POST', dataType: 'json', data: formData()
        })
        .done(function (res) {
            var text = res.sayi + ' alıcıya gönderilecek.';

            if (res.ornek && res.ornek.length) {
                text += ' Örnek: ' + res.ornek.join(', ') + (res.sayi > res.ornek.length ? ' …' : '');
            }

            $summary.text(text);
        })
        .fail(function (xhr) {
            var res = xhr.responseJSON || {};

            $summary
                .removeClass('cy-alert--info').addClass('cy-alert--danger')
                .text(res.description || 'Alıcı listesi hesaplanamadı.');
        });
    }

    function scheduleAudience() {
        window.clearTimeout(audienceTimer);
        audienceTimer = window.setTimeout(refreshAudience, 400);
    }

    $('#hedef').on('change', function () {
        syncAudienceFields();
        refreshAudience();
    });

    $('#adresler').on('input', scheduleAudience);
    $('#kullanici_id').on('change', refreshAudience);

    syncAudienceFields();
    refreshAudience();

    /* ---- Önizleme ---- */

    $('#mail_onizle').on('click', function () {
        clearErrors();

        var $button = $(this).prop('disabled', true);

        $.ajax({ url: API.preview, method: 'POST', dataType: 'json', data: formData() })
            .done(function (res) {
                showPreview({ konu: res.konu, hideMeta: true }, res.govde);
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};
                if (xhr.status === 422) { showErrors(res.errors, res.description); return; }
                CY.ajaxError(xhr, 'Önizleme oluşturulamadı.');
            })
            .always(function () { $button.prop('disabled', false); });
    });

    /* ---- Gönder ---- */

    $form.on('submit', function (event) {
        event.preventDefault();

        if (busy) { return; }

        clearErrors();
        setSending(true, 'Kuyruğa alınıyor…');

        $.ajax({ url: API.send, method: 'POST', dataType: 'json', data: formData() })
            .done(function (res) {
                setPending(res.bekleyen);

                // Kuyruğa yazıldı; şimdi parti parti gönderiyoruz.
                setSending(true, 'Gönderiliyor…');
                showProgress(true);
                updateProgress(0, res.bekleyen, res.toplam + ' mektup kuyruğa alındı, gönderim başlıyor…');

                drainQueue(res.bekleyen, function (result) {
                    setSending(false);
                    showProgress(false);

                    CY.notify(finishMessage(result), result.failed > 0 ? 'warning' : 'success');

                    if (result.failed === 0 && result.kalan === 0) {
                        $('#konu, #baslik, #govde, #dugme_metni, #dugme_url').val('');
                        refreshAudience();
                    }

                    reload(false);
                });
            })
            .fail(function (xhr) {
                setSending(false);

                var res = xhr.responseJSON || {};
                if (xhr.status === 422) { showErrors(res.errors, res.description); return; }
                CY.ajaxError(xhr, 'Mektuplar kuyruğa alınamadı.');
            });
    });

    /* Sayfadan ayrılırken gönderim sürüyorsa uyar. */
    $(window).on('beforeunload', function () {
        if (busy) { return 'Gönderim sürüyor. Sayfadan ayrılırsanız kalan mektuplar kuyrukta bekler.'; }
    });
});
