<?php
/**
 * =====================================================================
 *  SİTE (ÖN YÜZ) – ORTAK ALT ŞABLON
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  _ust.php ile açılan etiketleri kapatır, alt bilgiyi ve tüm sayfalarda
 *  ortak olan JavaScript'i basar.
 *
 *  SAYFAYA ÖZEL JAVASCRIPT NASIL EKLENİR?
 *  Sayfanızın içinde çıktı tamponu (output buffer) kullanın:
 *
 *      <?php ob_start(); ?>
 *      <script> ... sadece bu sayfaya ait kod ... </script>
 *      <?php $sayfaScript = ob_get_clean(); ?>
 *      <?php require __DIR__ . '/_alt.php'; ?>
 *
 *  Böylece kodunuz jQuery ve Bootstrap YÜKLENDİKTEN SONRA, </body>
 *  etiketinden hemen önce basılır. Sayfanın ortasına <script> yazarsanız
 *  jQuery henüz yüklenmemiş olabilir ve "$ is not defined" hatası alırsınız.
 * =====================================================================
 */

// Alt bilgide gösterilecek iletişim ve sosyal medya bilgileri.
// Boş olanlar otomatik gizlenir: array_filter boş dizeleri atar.
$sosyal = array_filter([
    'Facebook'  => (string) setting('sosyal_facebook', ''),
    'X'         => (string) setting('sosyal_x', ''),
    'Instagram' => (string) setting('sosyal_instagram', ''),
    'LinkedIn'  => (string) setting('sosyal_linkedin', ''),
    'YouTube'   => (string) setting('sosyal_youtube', ''),
    'GitHub'    => (string) setting('sosyal_github', ''),
]);

// Her ağ için kısa bir simge (harici ikon kütüphanesi yüklememek için).
$sosyalSimge = [
    'Facebook'  => 'f',
    'X'         => '𝕏',
    'Instagram' => '◎',
    'LinkedIn'  => 'in',
    'YouTube'   => '▶',
    'GitHub'    => '⌥',
];

$iletisimEposta  = (string) setting('iletisim_eposta', '');
$iletisimTelefon = (string) setting('iletisim_telefon', '');
$iletisimAdres   = (string) setting('iletisim_adres', '');
?>
    </main><!-- /#icerik -->

    <!-- ================= ALT BİLGİ ================= -->
    <footer class="cy-site-footer">
        <div class="container">
            <div class="row g-4">

                <!-- Marka -->
                <div class="col-lg-4">
                    <div class="cy-brand mb-3">
                        <span class="cy-brand__mark">
                            <img src="<?= e(site_logo_url()) ?>" alt="">
                        </span>
                        <div>
                            <span class="cy-brand__title d-block"><?= e($siteAdi) ?></span>
                            <span class="cy-brand__subtitle">
                                <?= e((string) setting('site_slogan', 'Çılgın Yazılım PHP Şablonu')) ?>
                            </span>
                        </div>
                    </div>

                    <p style="font-size:.92rem; max-width:38ch;">
                        <?= e((string) setting('site_aciklama', APP_DESCRIPTION)) ?>
                    </p>

                    <?php if ($sosyal !== []): ?>
                        <div class="cy-social mt-3">
                            <?php foreach ($sosyal as $ad => $url): ?>
                                <!-- rel="noopener": yeni sekmede açılan sayfa,
                                     window.opener ile sizin sayfanızı yönlendiremesin. -->
                                <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"
                                   title="<?= e($ad) ?>" aria-label="<?= e($ad) ?>">
                                    <span aria-hidden="true"><?= e($sosyalSimge[$ad] ?? '•') ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Site haritası -->
                <div class="col-6 col-lg-2">
                    <h2>Site</h2>
                    <ul>
                        <li><a href="index.php">Ana Sayfa</a></li>
                        <li><a href="hakkimizda.php">Hakkımızda</a></li>
                        <li><a href="iletisim.php">İletişim</a></li>
                    </ul>
                </div>

                <!-- Hesap -->
                <div class="col-6 col-lg-2">
                    <h2>Hesap</h2>
                    <ul>
                        <?php if ($aktifKullanici !== null): ?>
                            <li><a href="hesabim.php">Hesabım</a></li>
                            <?php if (auth_at_least('editor')): ?>
                                <li><a href="yonetim/index.php">Yönetim Paneli</a></li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li><a href="giris.php">Giriş Yap</a></li>
                            <?php if ($kayitAcik): ?>
                                <li><a href="kayit.php">Kayıt Ol</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- İletişim -->
                <div class="col-lg-4">
                    <h2>İletişim</h2>
                    <ul>
                        <?php if ($iletisimEposta !== ''): ?>
                            <li>✉️ <a href="mailto:<?= e($iletisimEposta) ?>"><?= e($iletisimEposta) ?></a></li>
                        <?php endif; ?>
                        <?php if ($iletisimTelefon !== ''): ?>
                            <li>📞 <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $iletisimTelefon) ?? '') ?>"><?= e($iletisimTelefon) ?></a></li>
                        <?php endif; ?>
                        <?php if ($iletisimAdres !== ''): ?>
                            <li>📍 <?= nl2br(e($iletisimAdres)) ?></li>
                        <?php endif; ?>
                        <?php if ($iletisimEposta === '' && $iletisimTelefon === '' && $iletisimAdres === ''): ?>
                            <li class="cy-muted">
                                İletişim bilgileri henüz girilmedi.
                                <?php if (is_admin()): ?>
                                    <a href="yonetim/ayarlar.php">Ayarlardan ekleyin →</a>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="cy-site-footer__bottom">
                <span>&copy; <?= date('Y') ?> <?= e($siteAdi) ?>. Tüm hakları saklıdır.</span>
                <span>
                    <a href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a>
                    &middot;
                    <a href="https://github.com/CilginYazilim/cy-php-starter" target="_blank" rel="noopener">GitHub</a>
                </span>
            </div>
        </div>
    </footer>

    <!-- Bildirimlerin (toast) çıkacağı köşe -->
    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="toast_container"></div>

    <script src="assets/js/jquery-3.7.0.js"></script>
    <script src="assets/js/bootstrap.bundle.js"></script>

    <script>
    /* =================================================================
     *  ORTAK SİTE JAVASCRIPT'İ
     * ---------------------------------------------------------------
     *  CY nesnesi tüm sayfaların kullanabileceği küçük bir yardımcıdır.
     *  'use strict': yazım hatalarını sessizce yutmak yerine hata
     *  verdirir; hataları erken yakalarsınız.
     * ============================================================== */
    var CY = (function () {
        'use strict';

        // CSRF anahtarını <meta> etiketinden bir kez okuyoruz.
        var token = document.querySelector('meta[name="csrf-token"]');
        token = token ? token.getAttribute('content') : '';

        /** Sağ üstte geçici bildirim gösterir. */
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

            // .html() DEĞİL .text(): mesajın içindeki HTML çalıştırılmasın (XSS).
            $toast.find('.toast-body').text(message);
            $('#toast_container').append($toast);

            var toast = new bootstrap.Toast($toast[0], { delay: 4500 });
            $toast.on('hidden.bs.toast', function () { $toast.remove(); });
            toast.show();
        }

        /** Sunucuya AJAX isteği gönderir; CSRF anahtarını otomatik ekler. */
        function post(action, data) {
            return $.post('system/ajax.php', $.extend({ action: action, csrf_token: token }, data || {}), null, 'json');
        }

        /** Form alanlarındaki hata işaretlerini temizler. */
        function clearErrors($form) {
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('');
        }

        /** Sunucudan gelen {alan: 'mesaj'} listesini forma işler. */
        function showErrors($form, errors) {
            $.each(errors || {}, function (field, message) {
                var $input = $form.find('[name="' + field + '"]');
                $input.addClass('is-invalid');
                $input.siblings('.invalid-feedback').text(message);
            });
        }

        return {
            token: token,
            notify: notify,
            post: post,
            clearErrors: clearErrors,
            showErrors: showErrors
        };
    })();

    $(function () {
        'use strict';

        /* ---------- Mobil menü ---------- */
        $('#menu_toggle').on('click', function () {
            var $menu = $('#site_menu').toggleClass('acik');
            // aria-expanded: ekran okuyucu menünün açık mı kapalı mı
            // olduğunu ancak bu öznitelikten anlayabilir.
            $(this).attr('aria-expanded', $menu.hasClass('acik'));
        });

        /* ---------- Açık / koyu tema ---------- */
        $('#tema_dugmesi').on('click', function () {
            var kok    = document.documentElement;
            var suanki = kok.getAttribute('data-cy-theme');

            // Kullanıcı henüz seçim yapmadıysa işletim sistemi ayarına bakıp
            // onun TERSİNE geçiyoruz — böylece ilk tık her zaman iş görür.
            if (!suanki) {
                suanki = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            var yeni = suanki === 'dark' ? 'light' : 'dark';
            kok.setAttribute('data-cy-theme', yeni);

            try { localStorage.setItem('cy-tema', yeni); } catch (e) {}
        });
    });
    </script>

    <?php
    /* Sayfaya özel JavaScript (varsa) burada basılır — jQuery ve
     * Bootstrap yüklendikten SONRA, </body> etiketinden hemen önce. */
    echo $sayfaScript ?? '';

    /* SEO: Analytics kodu ayarlardan gelir.
     * Bu alan yöneticiye ait olduğu için BİLEREK kaçışlanmadan basılır;
     * panele sadece güvendiğiniz kişilere admin yetkisi verin. */
    $analytics = (string) setting('seo_analytics', '');
    if ($analytics !== '') {
        echo $analytics;
    }
    ?>
</body>
</html>
