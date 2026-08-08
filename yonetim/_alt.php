            </div><!-- /.cy-card__body -->

            <div class="cy-card__footer d-flex flex-wrap justify-content-between gap-2">
                <span>Çılgın Yazılım PHP Başlangıç Şablonu</span>
                <span>Sürüm <?= e((string) setting('sistem_surum', '1.0.0')) ?> &middot; PHP <?= e(PHP_VERSION) ?></span>
            </div>
        </div>

        <p class="cy-footer-note mt-4 mb-0">
            <a href="../index.php">← Siteyi görüntüle</a>
        </p>
    </div>

    <!-- Toast (bildirim) balonlarının ekleneceği kapsayıcı -->
    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="toast_container"></div>

    <!--
        JAVASCRIPT YÜKLEME SIRASI ÖNEMLİDİR:
        1) jQuery            → diğerleri buna bağımlı
        2) bootstrap.bundle  → Modal, Toast (Popper dahil)
        3) dataTables        → tablo motoru
    -->
    <script src="../assets/js/jquery-3.7.0.js"></script>
    <script src="../assets/js/bootstrap.bundle.js"></script>
    <script src="../assets/js/jquery.dataTables.min.js"></script>
    <script src="../assets/js/dataTables.bootstrap5.min.js"></script>

    <script>
    /* =================================================================
     *  PANEL ORTAK JAVASCRIPT'İ
     *  Her panel sayfasında kullanılabilecek yardımcılar.
     * ================================================================= */
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

        return {
            endpoint: ENDPOINT,
            token: CSRF_TOKEN,
            notify: notify,
            clearErrors: clearErrors,
            showErrors: showErrors,
            post: post
        };
    })();
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
