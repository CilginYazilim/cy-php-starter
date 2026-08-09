<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – ORTAK ALT ŞABLON
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Sayfayı kapatır, JavaScript'leri yükler ve panel genelinde
 *  kullanılan CY yardımcı nesnesini tanımlar.
 * =====================================================================
 */

declare(strict_types=1);
?>
</main><!-- /.adm-content -->

<footer class="adm-content pt-0 pb-4">
    <div class="d-flex flex-wrap justify-content-between gap-2 small cy-muted">
        <span>
            <strong><?= e((string) setting('site_adi', APP_NAME)) ?></strong> yönetim paneli
            &middot; Çılgın Yazılım PHP Başlangıç Şablonu
        </span>
        <span>
            Sürüm <?= e((string) setting('sistem_surum', '1.0.0')) ?>
            &middot; PHP <?= e(PHP_VERSION) ?>
        </span>
    </div>
</footer>

<!-- Toast (bildirim) balonlarının ekleneceği kapsayıcı -->
<div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="toast_container"></div>

<!-- ==================================================================
     ORTAK ONAY PENCERESİ
     ------------------------------------------------------------------
     Her sayfada ayrı bir "silmek istediğinize emin misiniz?" modalı
     yazmak yerine tek bir tane burada duruyor. Sayfalar şöyle kullanır:

         CY.onay({
             baslik: 'Kaydı Sil',
             metin:  'Bu kayıt kalıcı olarak silinecek.',
             buton:  'Evet, Sil'
         }, function () { ...silme isteğini gönder... });
     ================================================================== -->
<div class="modal fade cy-modal" id="cy_onay_modal" tabindex="-1" aria-hidden="true"
     aria-labelledby="cy_onay_baslik">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 mb-0" id="cy_onay_baslik">Onay</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-0" id="cy_onay_metin"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary cy-btn btn-sm"
                        data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-danger cy-btn btn-sm" id="cy_onay_evet">Onayla</button>
            </div>
        </div>
    </div>
</div>

<!--
    JAVASCRIPT YÜKLEME SIRASI ÖNEMLİDİR:
    1) jQuery            → diğerleri buna bağımlı
    2) bootstrap.bundle  → Modal, Toast, Dropdown (Popper dahil)
    3) dataTables        → tablo motoru
-->
<script src="../assets/js/jquery-3.7.0.js"></script>
<script src="../assets/js/bootstrap.bundle.js"></script>
<script src="../assets/js/jquery.dataTables.min.js"></script>
<script src="../assets/js/dataTables.bootstrap5.min.js"></script>

<script>
/* =====================================================================
 *  PANEL ORTAK JAVASCRIPT'İ
 *  Her panel sayfasında kullanılabilecek yardımcılar.
 * ================================================================== */
var CY = (function () {
    'use strict';

    var ENDPOINT   = '../system/ajax.php';
    var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    /**
     * Sağ üstte geçici bildirim (toast) gösterir.
     * @param {string} message Gösterilecek metin
     * @param {string} type    'success' | 'danger' | 'info'
     */
    function notify(message, type) {
        type = type || 'success';

        var $toast = $(
            '<div class="toast cy-toast cy-toast--' + type + '" role="alert" aria-live="assertive" aria-atomic="true">' +
                '<div class="d-flex">' +
                    '<div class="toast-body"></div>' +
                    '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Kapat"></button>' +
                '</div>' +
            '</div>'
        );

        // .html() değil .text() — mesajdaki HTML çalıştırılmasın (XSS).
        $toast.find('.toast-body').text(message);
        $('#toast_container').append($toast);

        var toast = new bootstrap.Toast($toast[0], { delay: 4000 });
        $toast.on('hidden.bs.toast', function () { $toast.remove(); });
        toast.show();
    }

    /** Formdaki hata işaretlerini temizler. */
    function clearErrors($form) {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('[data-error-for]').text('');
    }

    /** Sunucudan gelen alan hatalarını ilgili girdilerin altına yazar. */
    function showErrors($form, errors) {
        clearErrors($form);
        $.each(errors || {}, function (field, message) {
            $form.find('[name="' + field + '"]').addClass('is-invalid');
            $form.find('[data-error-for="' + field + '"]').text(message);
        });
    }

    /** AJAX uç noktasına POST atar; CSRF anahtarını otomatik ekler. */
    function post(data) {
        data = data || {};
        data.csrf_token = CSRF_TOKEN;

        return $.ajax({
            url: ENDPOINT,
            method: 'POST',
            dataType: 'json',
            data: data
        });
    }

    /**
     * Dosya (FormData) gönderir. Normal post()'tan farkı: jQuery'nin
     * veriyi kendiliğinden dizgeye çevirmesini kapatmamız gerekir,
     * aksi halde dosya "[object File]" olarak gider.
     */
    function upload(formData) {
        formData.append('csrf_token', CSRF_TOKEN);

        return $.ajax({
            url: ENDPOINT,
            method: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,   // veriyi query string'e çevirme
            contentType: false    // sınır (boundary) başlığını tarayıcı koysun
        });
    }

    /**
     * Ortak onay penceresini açar; kullanıcı onaylarsa geri çağırımı çalıştırır.
     *
     * @param {Object}   ayar {baslik, metin, buton, tur}
     * @param {Function} evetIse Onaylanınca çalışacak fonksiyon
     */
    function onay(ayar, evetIse) {
        ayar = ayar || {};

        var el     = document.getElementById('cy_onay_modal');
        var modal  = bootstrap.Modal.getOrCreateInstance(el);
        var $evet  = $('#cy_onay_evet');

        $('#cy_onay_baslik').text(ayar.baslik || 'Onay');
        $('#cy_onay_metin').text(ayar.metin || 'Bu işlemi onaylıyor musunuz?');
        $evet.text(ayar.buton || 'Onayla')
             .removeClass('btn-danger btn-primary')
             .addClass(ayar.tur === 'primary' ? 'btn-primary' : 'btn-danger');

        /* .off('click') ŞART: aynı düğmeye her açılışta yeni bir olay
         * bağlanırsa, üçüncü açılışta işlem üç kez çalışır. */
        $evet.off('click').on('click', function () {
            modal.hide();
            if (typeof evetIse === 'function') { evetIse(); }
        });

        modal.show();
    }

    /** Hata yanıtından okunabilir bir mesaj çıkarır. */
    function hataMesaji(xhr, varsayilan) {
        var res = (xhr && xhr.responseJSON) || {};
        return res.description || varsayilan || 'İşlem tamamlanamadı.';
    }

    /* DataTables Türkçe metinleri: her sayfada tekrar yazmamak için.
     * Kullanımı:  language: CY.dtDil('Kullanıcı')
     * $birim sadece "yok / bulunamadı" cümlelerinde geçer; sayı içeren
     * satırlarda nötr "kayıt" sözcüğü kullanılır (Türkçe ekler
     * kelimeden kelimeye değiştiği için birleştirme yapmıyoruz). */
    function dtDil(birim) {
        birim = birim || 'Kayıt';

        return {
            emptyTable:     birim + ' bulunmuyor.',
            info:           'Toplam _TOTAL_ kayıttan _START_ – _END_ arası',
            infoEmpty:      'Gösterilecek kayıt yok',
            infoFiltered:   '(toplam _MAX_ kayıt içinden filtrelendi)',
            lengthMenu:     'Sayfada _MENU_ kayıt',
            loadingRecords: 'Yükleniyor…',
            processing:     'İşleniyor…',
            search:         '',
            searchPlaceholder: 'Ara…',
            zeroRecords:    'Aramanızla eşleşen kayıt bulunamadı.',
            paginate:       { first: 'İlk', last: 'Son', next: 'Sonraki', previous: 'Önceki' }
        };
    }

    return {
        endpoint:    ENDPOINT,
        token:       CSRF_TOKEN,
        notify:      notify,
        clearErrors: clearErrors,
        showErrors:  showErrors,
        post:        post,
        upload:      upload,
        onay:        onay,
        hataMesaji:  hataMesaji,
        dtDil:       dtDil
    };
})();


$(function () {
    'use strict';

    var govde = document.body;

    /* -----------------------------------------------------------------
     *  KENAR ÇUBUĞU AÇ / KAPA
     * -----------------------------------------------------------------
     *  Masaüstünde menü daralır (sadece ikonlar), mobilde üste kayarak
     *  açılır. Tercih ÇEREZE yazılır: sunucu bir sonraki sayfayı doğru
     *  genişlikle basar, ekran sıçraması olmaz.
     * -------------------------------------------------------------- */
    function mobilMi() { return window.matchMedia('(max-width: 991.98px)').matches; }

    $('#adm_menu_toggle').on('click', function () {
        if (mobilMi()) {
            govde.classList.toggle('adm--acik');
            return;
        }

        var dar = govde.classList.toggle('adm--mini');
        // 1 yıl geçerli, sadece bu site (SameSite=Lax) — hassas veri değil.
        document.cookie = 'cy_menu=' + (dar ? 'dar' : 'genis') +
            ';path=/;max-age=31536000;samesite=Lax';
    });

    // Perdeye veya bir menü bağlantısına tıklayınca mobil menü kapansın.
    $('#adm_backdrop').on('click', function () {
        govde.classList.remove('adm--acik');
    });
    $('.adm-menu__link').on('click', function () {
        if (mobilMi()) { govde.classList.remove('adm--acik'); }
    });

    // Esc tuşu mobil menüyü kapatır.
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') { govde.classList.remove('adm--acik'); }
    });

    /* -----------------------------------------------------------------
     *  AÇIK / KOYU TEMA
     * -------------------------------------------------------------- */
    $('#adm_tema').on('click', function () {
        var kok    = document.documentElement;
        var suanki = kok.getAttribute('data-cy-theme');

        // Kullanıcı henüz seçim yapmadıysa işletim sistemi ayarının
        // TERSİNE geçiyoruz — böylece ilk tık her zaman iş görür.
        if (!suanki) {
            suanki = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        var yeni = suanki === 'dark' ? 'light' : 'dark';
        kok.setAttribute('data-cy-theme', yeni);

        try { localStorage.setItem('cy-tema', yeni); } catch (e) {}
    });

    /* -----------------------------------------------------------------
     *  OTURUM SÜRESİ UYARISI
     * -----------------------------------------------------------------
     *  Uzun süre açık kalan panelde oturum sunucuda düşmüş olabilir.
     *  Kullanıcı "Kaydet"e basınca formun sessizce kaybolmasındansa,
     *  15 dakikada bir hafif bir istekle oturumu hem canlı tutuyor hem
     *  de düştüyse haber veriyoruz.
     * -------------------------------------------------------------- */
    setInterval(function () {
        CY.post({ action: 'ping' }).fail(function (xhr) {
            if (xhr.status === 401 || xhr.status === 419) {
                CY.notify('Oturumunuz sona erdi. Sayfayı yenileyip tekrar giriş yapın.', 'danger');
            }
        });
    }, 15 * 60 * 1000);
});
</script>

<?php
/* SAYFAYA ÖZEL SCRIPT YUVASI
 * -----------------------------------------------------------------
 * Panel sayfaları kendi JavaScript'ini burada çalıştırır. Neden
 * doğrudan _alt.php'den SONRA yazmıyoruz? Çünkü _alt.php sayfayı
 * </body></html> ile kapatır; ondan sonra gelen her şey geçersiz
 * HTML olur. Ayrıca sayfa script'i yukarıdaki CY nesnesine
 * bağımlıdır, yani ondan sonra çalışmalıdır.
 *
 * KULLANIMI (panel sayfasının sonunda):
 *     ob_start();
 *     ?><script> ... </script><?php
 *     $sayfaScript = ob_get_clean();
 *     require __DIR__ . '/_alt.php';
 */
echo $sayfaScript ?? '';
?>
</body>
</html>
