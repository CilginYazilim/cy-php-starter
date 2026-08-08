<?php
/**
 * =====================================================================
 *  BAŞLANGIÇ SAYFASI – Çılgın Yazılım PHP Başlangıç Şablonu
 *  cilginyazilim.com
 * ---------------------------------------------------------------------
 *  Bu sayfa iki iş yapar:
 *    1. Kurulumun doğru olduğunu test eder ("Bağlantıyı Test Et")
 *    2. Tasarım kalıbındaki hazır bileşenleri gösterir
 *
 *  ► Yeni projeye başlarken: Aşağıdaki "BİLEŞEN GALERİSİ" bölümünü
 *    silip yerine kendi içeriğinizi yazın. İskelet (başlık, kart,
 *    modal, toast, CSRF) olduğu gibi kalsın.
 * =====================================================================
 */

declare(strict_types=1);

// __DIR__ : Bu dosyanın bulunduğu klasörün TAM yolu. Göreli yol yerine
// bunu kullanmak, dosya nereden çağrılırsa çağrılsın doğru çalışır.
require __DIR__ . '/system/config.php';
require __DIR__ . '/system/function.php';

// CSRF anahtarı: sahte istekleri engeller. Hem <meta> etiketine hem
// formlara gömülür, JavaScript her istekte gönderir.
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Çılgın Yazılım - cilginyazilim.com">
    <meta name="description" content="<?= e(APP_DESCRIPTION) ?>">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">

    <title><?= e(APP_NAME) ?> | Çılgın Yazılım</title>

    <link rel="icon" type="image/png" href="assets/images/logo.png">

    <!--
        CSS YÜKLEME SIRASI ÖNEMLİDİR:
        1) bootstrap      → temel çatı
        2) dataTables     → tablo eklentisi (kullanmıyorsanız silin)
        3) cilginyazilim  → MARKA TASARIM KALIBI (Bootstrap'i ezer)
        4) style          → sadece bu projeye özel eklemeler
        Sonra yüklenen dosya, öncekini geçersiz kılabilir.
    -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/cilginyazilim.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<!-- "cy-app" sınıfı marka tasarım kalıbını sayfaya uygular. -->
<body class="cy-app">

    <!-- Sayfanın en üstündeki ince marka şeridi -->
    <div class="cy-topbar"></div>

    <div class="container py-4 py-lg-5">

        <div class="cy-card">

            <!-- ---------- Kart Başlığı (marka gradyanlı) ---------- -->
            <div class="cy-card__header">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                    <a class="cy-brand" href="https://cilginyazilim.com" target="_blank" rel="noopener">
                        <span class="cy-brand__mark">
                            <img src="assets/images/logo.png" alt="Çılgın Yazılım logosu">
                        </span>
                        <div>
                            <h1 class="cy-brand__title"><?= e(APP_NAME) ?></h1>
                            <p class="cy-brand__subtitle"><?= e(APP_DESCRIPTION) ?></p>
                        </div>
                    </a>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="cy-badge cy-badge--glass">PHP <?= e(PHP_VERSION) ?></span>
                        <button type="button" id="ping_button" class="btn cy-btn cy-btn--onbrand">
                            Bağlantıyı Test Et
                        </button>
                    </div>
                </div>
            </div>

            <!-- ---------- Kart Gövdesi ---------- -->
            <div class="cy-card__body">

                <!-- =======================================================
                     BİLEŞEN GALERİSİ
                     Yeni projeye başlarken bu bölümü silin.
                     ======================================================= -->

                <div class="alert alert-primary" role="alert">
                    <strong>Şablon hazır.</strong> Başlamak için:
                    <code>system/config.php</code> içindeki <code>APP_NAME</code> ve
                    <code>DB_NAME</code> değerlerini değiştirin, sonra bu galeriyi silip
                    kendi içeriğinizi yazın.
                </div>

                <h2 class="h6 text-uppercase cy-muted mt-4 mb-3">Butonlar</h2>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button class="btn cy-btn cy-btn--primary">Ana Eylem</button>
                    <button class="btn btn-outline-secondary cy-btn">İkincil</button>
                    <button class="btn btn-danger cy-btn">Tehlikeli</button>
                    <span class="cy-actions ms-2">
                        <button class="cy-btn-icon cy-btn-icon--view" title="Görüntüle">&#128065;</button>
                        <button class="cy-btn-icon cy-btn-icon--edit" title="Düzenle">&#9998;</button>
                        <button class="cy-btn-icon cy-btn-icon--delete" title="Sil">&#128465;</button>
                    </span>
                </div>

                <h2 class="h6 text-uppercase cy-muted mb-3">Rozetler ve Avatarlar</h2>
                <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                    <span class="cy-badge cy-badge--soft">Yumuşak Rozet</span>
                    <span class="cy-avatar cy-avatar--initial">Ç</span>
                    <img src="assets/images/logo.png" class="cy-avatar" alt="Örnek avatar">
                </div>

                <h2 class="h6 text-uppercase cy-muted mb-3">Tablo</h2>
                <div class="table-responsive mb-4">
                    <table class="table cy-table w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Başlık</th>
                                <th>Tarih</th>
                                <th class="text-center">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="cy-id">1</td>
                                <td class="cy-name">Örnek kayıt</td>
                                <td class="cy-nowrap"><?= e(format_date(date('Y-m-d H:i:s'))) ?></td>
                                <td class="text-center">
                                    <span class="cy-actions">
                                        <button class="cy-btn-icon cy-btn-icon--edit">&#9998;</button>
                                        <button class="cy-btn-icon cy-btn-icon--delete">&#128465;</button>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h2 class="h6 text-uppercase cy-muted mb-3">Modal ve Bildirim</h2>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn cy-btn cy-btn--primary" data-bs-toggle="modal" data-bs-target="#demoModal">
                        Modal Aç
                    </button>
                    <button class="btn btn-outline-secondary cy-btn js-toast" data-type="success">Başarı Bildirimi</button>
                    <button class="btn btn-outline-secondary cy-btn js-toast" data-type="danger">Hata Bildirimi</button>
                </div>

                <!-- ================= GALERİ SONU ================= -->
            </div>

            <div class="cy-card__footer d-flex flex-wrap justify-content-between gap-2">
                <span>Çılgın Yazılım PHP Başlangıç Şablonu</span>
                <span>cilginyazilim.com</span>
            </div>
        </div>

        <p class="cy-footer-note mt-4 mb-0">
            <a href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a>
        </p>
    </div>


    <!-- ================================================================
         ÖRNEK MODAL
         Kendi modallarınız için bu yapıyı kopyalayın.
         ================================================================ -->
    <div class="modal fade cy-modal" id="demoModal" tabindex="-1" aria-labelledby="demoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6 mb-0" id="demoModalLabel">Örnek Modal</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <dl class="cy-detail">
                        <dt>Uygulama</dt>
                        <dd><?= e(APP_NAME) ?></dd>
                        <dt>Veritabanı</dt>
                        <dd><?= e(DB_NAME) ?></dd>
                        <dt>PHP</dt>
                        <dd><?= e(PHP_VERSION) ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary cy-btn" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn cy-btn cy-btn--primary" data-bs-dismiss="modal">Tamam</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast (bildirim) balonlarının ekleneceği kapsayıcı -->
    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="toast_container"></div>


    <!--
        JAVASCRIPT YÜKLEME SIRASI ÇOK ÖNEMLİDİR:
        1) jQuery            → diğerleri buna bağımlı
        2) bootstrap.bundle  → Modal, Toast (Popper dahil)
        3) dataTables        → tablo motoru (kullanmıyorsanız silin)
        Sıra bozulursa "$ is not defined" hatası alırsınız.
    -->
    <script src="assets/js/jquery-3.7.0.js"></script>
    <script src="assets/js/bootstrap.bundle.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js"></script>

    <script>
    /* =================================================================
     *  UYGULAMA JAVASCRIPT'İ
     * -----------------------------------------------------------------
     *  $(function () { ... }) = "Sayfa hazır olunca çalıştır".
     *  Bu sarmalayıcı olmadan HTML henüz yüklenmemişken elemanları
     *  bulmaya çalışır ve kod sessizce çalışmaz.
     * ================================================================= */
    $(function () {
        'use strict';

        var ENDPOINT   = 'system/ajax.php';
        var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

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

            // ÖNEMLİ: .html() değil .text() kullanıyoruz.
            // .html() olsaydı mesaj içindeki HTML çalışır ve XSS açığı oluşurdu.
            $toast.find('.toast-body').text(message);
            $('#toast_container').append($toast);

            var toast = new bootstrap.Toast($toast[0], { delay: 4000 });
            // Kapanınca DOM'dan kaldır (bellek sızıntısını önler).
            $toast.on('hidden.bs.toast', function () { $toast.remove(); });
            toast.show();
        }

        /* -------------------------------------------------------------
         *  BAĞLANTI TESTİ
         *  Şablonun uçtan uca çalıştığını doğrular:
         *  AJAX → CSRF → veritabanı → JSON yanıt
         * ----------------------------------------------------------- */
        $('#ping_button').on('click', function () {
            var $btn = $(this).prop('disabled', true);

            $.ajax({
                url: ENDPOINT,
                method: 'POST',
                dataType: 'json',
                data: { action: 'ping', csrf_token: CSRF_TOKEN }
            })
            .done(function (res) {
                notify(res.description + ' (' + res.database + ' · ' + res.server_at + ')', 'success');
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};
                notify(res.description || 'Bağlantı kurulamadı.', 'danger');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
        });

        // Galeri: bildirim örneği (kendi projenizde silin)
        $('.js-toast').on('click', function () {
            var type = $(this).data('type');
            notify(type === 'danger' ? 'Bir şeyler ters gitti.' : 'İşlem başarıyla tamamlandı.', type);
        });
    });
    </script>
</body>
</html>
