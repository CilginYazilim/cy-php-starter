<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – GELEN MESAJLAR
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  iletisim.php sayfasındaki formdan gelen mesajlar burada okunur.
 *  Listeleme, okundu işaretleme, silme ve toplu işlemler AJAX ile
 *  system/ajax.php üzerinden yapılır (action=mesaj_*).
 *
 *  DERİN BAĞLANTI: mesajlar.php?id=12 adresi açıldığında ilgili mesaj
 *  kendiliğinden açılır. Bildirim zilindeki bağlantılar bunu kullanır.
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik   = 'Mesajlar';
$sayfaAciklama = 'İletişim formundan gelen mesajlar.';
$aktifMenu     = 'mesajlar';
$gerekenRol    = 'admin';
$kirintilar    = ['Mesajlar'];

require __DIR__ . '/_ust.php';

/* Tablo yoksa (şablonu sadeleştirmiş olabilirsiniz) sayfayı çökertmek
 * yerine ne yapılması gerektiğini anlatıyoruz. */
$tabloVar = table_exists($db, 'mesajlar');

// Zil menüsünden gelen ?id=12 bağlantısı için.
$acilacakId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
?>

<?php if (!$tabloVar): ?>

    <div class="adm-card adm-card--warn">
        <div class="adm-card__body">
            <h3 class="h6"><?= panel_icon('alert') ?> "mesajlar" tablosu bulunamadı</h3>
            <p class="cy-muted mb-0">
                Bu sayfa <code>mesajlar</code> tablosuna ihtiyaç duyar. Tabloyu oluşturmak için
                <code>kurulum/database.sql</code> dosyasındaki ilgili <code>CREATE TABLE</code>
                bloğunu çalıştırın.
            </p>
        </div>
    </div>

<?php else: ?>

<div class="adm-card adm-card--brand">
    <div class="adm-card__header">
        <h3 class="adm-card__title"><?= panel_icon('mail') ?> Gelen Kutusu</h3>

        <div class="adm-filters">
            <!-- Durum süzgeci: DataTables'a ekstra parametre olarak gider -->
            <div class="btn-group btn-group-sm" role="group" aria-label="Durum süzgeci">
                <button type="button" class="btn btn-outline-secondary cy-btn active js-filtre" data-filtre="">Tümü</button>
                <button type="button" class="btn btn-outline-secondary cy-btn js-filtre" data-filtre="okunmamis">Okunmamış</button>
                <button type="button" class="btn btn-outline-secondary cy-btn js-filtre" data-filtre="okunmus">Okunmuş</button>
            </div>

            <button type="button" class="btn btn-outline-secondary cy-btn btn-sm" id="yenile" title="Listeyi yenile">
                <?= panel_icon('refresh') ?>
            </button>
        </div>
    </div>

    <!-- ---------- Toplu işlem çubuğu ----------
         Hiç kayıt seçilmediğinde gizli durur; seçim yapılınca belirir. -->
    <div class="adm-card__footer d-none" id="toplu_cubuk">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <strong id="secim_sayisi">0</strong>
            <span>mesaj seçildi:</span>

            <button type="button" class="btn btn-outline-secondary cy-btn btn-sm js-toplu" data-islem="okundu">
                Okundu işaretle
            </button>
            <button type="button" class="btn btn-outline-secondary cy-btn btn-sm js-toplu" data-islem="okunmadi">
                Okunmadı işaretle
            </button>
            <button type="button" class="btn btn-danger cy-btn btn-sm js-toplu" data-islem="sil">
                Sil
            </button>
            <button type="button" class="btn btn-link btn-sm" id="secimi_temizle">Seçimi temizle</button>
        </div>
    </div>

    <div class="adm-card__body adm-card__body--flush">
        <div class="table-responsive">
            <!--
                <th> SAYISI, system/ajax.php içindeki handle_mesaj_list()
                fonksiyonundan dönen dizi uzunluğuyla AYNI olmalıdır (6).
            -->
            <table id="mesaj_tablosu" class="table cy-table w-100">
                <thead>
                    <tr>
                        <th style="width:38px">
                            <input type="checkbox" class="form-check-input" id="hepsini_sec"
                                   aria-label="Sayfadaki tüm mesajları seç">
                        </th>
                        <th>Gönderen</th>
                        <th>Konu</th>
                        <th style="width:110px">Durum</th>
                        <th style="width:140px">Tarih</th>
                        <th class="text-center" style="width:130px">İşlemler</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>


<!-- ================================================================
     MODAL – Mesaj görüntüleme
     ================================================================ -->
<div class="modal fade cy-modal" id="mesajModal" tabindex="-1" aria-labelledby="mesajModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 mb-0" id="mesajModalLabel">Mesaj</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <div class="modal-body">
                <dl class="cy-detail mb-3">
                    <dt>Gönderen</dt>
                    <dd><span id="m_ad"></span> <small class="cy-muted" id="m_uye"></small></dd>

                    <dt>E-posta</dt>
                    <dd><a href="#" id="m_eposta_link"><span id="m_eposta"></span></a></dd>

                    <dt>Konu</dt>
                    <dd id="m_konu"></dd>

                    <dt>Tarih</dt>
                    <dd id="m_tarih"></dd>

                    <dt>IP</dt>
                    <dd id="m_ip"></dd>

                    <dt>Tarayıcı</dt>
                    <dd><small class="cy-muted" id="m_tarayici"></small></dd>
                </dl>

                <hr>

                <!--
                    white-space: pre-wrap → mesajdaki satır sonları korunur.
                    İçerik JavaScript'te .text() ile basılır: mesajda HTML
                    olsa bile metin olarak görünür, çalışmaz (XSS koruması).
                -->
                <div id="m_mesaj" style="white-space:pre-wrap;word-break:break-word"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger cy-btn btn-sm" id="m_sil">Sil</button>
                <span class="flex-grow-1"></span>
                <button type="button" class="btn btn-outline-secondary cy-btn btn-sm" data-bs-dismiss="modal">Kapat</button>
                <a href="#" class="btn cy-btn cy-btn--primary btn-sm" id="m_yanitla">E-posta ile yanıtla</a>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
if (!$tabloVar) {
    require __DIR__ . '/_alt.php';
    return;
}

ob_start();
?>
<script>
$(function () {
    'use strict';

    var mesajModal  = new bootstrap.Modal(document.getElementById('mesajModal'));
    var aktifFiltre = '';
    var acikMesajId = null;
    var secilenler  = [];          // Onay kutusuyla seçilen mesaj ID'leri

    /* -------------------------------------------------------------
     *  LİSTE (sunucu taraflı DataTables)
     * ----------------------------------------------------------- */
    var tablo = $('#mesaj_tablosu').DataTable({
        processing: true,
        serverSide: true,
        order: [[4, 'desc']],
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        ajax: {
            url: CY.endpoint,
            type: 'POST',
            data: function (d) {
                d.action     = 'mesaj_list';
                d.csrf_token = CY.token;
                d.filtre     = aktifFiltre;
            },
            dataSrc: function (json) {
                // Her listelemede menü rozetini güncel tut.
                rozetGuncelle(json.okunmamis);
                return json.data;
            },
            error: function () {
                CY.notify('Mesajlar yüklenemedi.', 'danger');
            }
        },
        columnDefs: [
            { targets: 0, orderable: false, searchable: false, className: 'text-center' },
            { targets: 5, orderable: false, searchable: false, className: 'text-center' }
        ],
        language: CY.dtDil('Mesaj'),
        // Sayfa değişince "hepsini seç" kutusu yanıltıcı kalmasın.
        drawCallback: function () {
            $('#hepsini_sec').prop('checked', false);
            isaretle();
        }
    });

    /* -------------------------------------------------------------
     *  MENÜ / ZİL ROZETİ
     * -------------------------------------------------------------
     *  Sunucu her yanıtta güncel okunmamış sayısını gönderiyor.
     *  Sayfayı yenilemeden rozetleri tazeliyoruz.
     * ----------------------------------------------------------- */
    function rozetGuncelle(sayi) {
        if (typeof sayi !== 'number') { return; }

        var metin = sayi > 99 ? '99+' : sayi;

        $('.adm-menu__badge, .adm-navbar__count').each(function () {
            if (sayi > 0) {
                $(this).text(metin).show();
            } else {
                $(this).hide();
            }
        });
    }

    /* -------------------------------------------------------------
     *  DURUM SÜZGECİ
     * ----------------------------------------------------------- */
    $('.js-filtre').on('click', function () {
        $('.js-filtre').removeClass('active');
        $(this).addClass('active');

        aktifFiltre = $(this).data('filtre') || '';
        secimiTemizle();
        tablo.ajax.reload();
    });

    $('#yenile').on('click', function () {
        tablo.ajax.reload(null, false);
    });

    /* -------------------------------------------------------------
     *  SEÇİM YÖNETİMİ
     * ----------------------------------------------------------- */
    function isaretle() {
        // Sayfadaki kutuları hafızadaki seçime göre yeniden işaretle.
        $('#mesaj_tablosu').find('.js-sec').each(function () {
            $(this).prop('checked', secilenler.indexOf(parseInt(this.value, 10)) !== -1);
        });

        $('#secim_sayisi').text(secilenler.length);
        $('#toplu_cubuk').toggleClass('d-none', secilenler.length === 0);
    }

    function secimiTemizle() {
        secilenler = [];
        $('#hepsini_sec').prop('checked', false);
        isaretle();
    }

    $('#mesaj_tablosu').on('change', '.js-sec', function () {
        var id    = parseInt(this.value, 10);
        var sira  = secilenler.indexOf(id);

        if (this.checked && sira === -1) {
            secilenler.push(id);
        } else if (!this.checked && sira !== -1) {
            secilenler.splice(sira, 1);
        }

        isaretle();
    });

    $('#hepsini_sec').on('change', function () {
        var secili = this.checked;

        $('#mesaj_tablosu').find('.js-sec').each(function () {
            var id   = parseInt(this.value, 10);
            var sira = secilenler.indexOf(id);

            if (secili && sira === -1) { secilenler.push(id); }
            if (!secili && sira !== -1) { secilenler.splice(sira, 1); }
        });

        isaretle();
    });

    $('#secimi_temizle').on('click', secimiTemizle);

    /* -------------------------------------------------------------
     *  TOPLU İŞLEM
     * ----------------------------------------------------------- */
    $('.js-toplu').on('click', function () {
        var islem = $(this).data('islem');

        if (secilenler.length === 0) {
            CY.notify('Önce mesaj seçin.', 'info');
            return;
        }

        function calistir() {
            CY.post({ action: 'mesaj_toplu', islem: islem, ids: secilenler })
                .done(function (res) {
                    CY.notify(res.description, 'success');
                    rozetGuncelle(res.okunmamis);
                    secimiTemizle();
                    tablo.ajax.reload(null, false);
                })
                .fail(function (xhr) {
                    CY.notify(CY.hataMesaji(xhr, 'Toplu işlem başarısız.'), 'danger');
                });
        }

        if (islem === 'sil') {
            CY.onay({
                baslik: 'Mesajları Sil',
                metin:  secilenler.length + ' mesaj kalıcı olarak silinecek.',
                buton:  'Evet, Sil'
            }, calistir);
        } else {
            calistir();
        }
    });

    /* -------------------------------------------------------------
     *  MESAJ GÖRÜNTÜLE
     * ----------------------------------------------------------- */
    function mesajAc(id) {
        CY.post({ action: 'mesaj_getir', id: id })
            .done(function (res) {
                acikMesajId = res.id;

                // .text() kullanıyoruz: mesajdaki HTML çalıştırılmasın (XSS).
                $('#m_ad').text(res.ad);
                $('#m_uye').text(res.uye);
                $('#m_eposta').text(res.eposta);
                $('#m_konu').text(res.konu || '(konusuz)');
                $('#m_tarih').text(res.tarih);
                $('#m_ip').text(res.ip || '-');
                $('#m_tarayici').text(res.tarayici || '-');
                $('#m_mesaj').text(res.mesaj);
                $('#mesajModalLabel').text(res.konu || 'Mesaj #' + res.id);

                /* mailto adresini encodeURIComponent ile kuruyoruz:
                 * konuda & veya # geçerse bağlantı bozulmasın. */
                var konu = 'Re: ' + (res.konu || '');
                $('#m_yanitla').attr('href',
                    'mailto:' + encodeURIComponent(res.eposta) + '?subject=' + encodeURIComponent(konu));
                $('#m_eposta_link').attr('href', 'mailto:' + encodeURIComponent(res.eposta));

                rozetGuncelle(res.okunmamis);
                mesajModal.show();

                // Görüntülenen mesaj sunucuda okundu sayıldı; listeyi tazele.
                tablo.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                CY.notify(CY.hataMesaji(xhr, 'Mesaj getirilemedi.'), 'danger');
            });
    }

    $('#mesaj_tablosu').on('click', '.js-goster', function () {
        mesajAc($(this).data('id'));
    });

    /* -------------------------------------------------------------
     *  OKUNDU / OKUNMADI
     * ----------------------------------------------------------- */
    $('#mesaj_tablosu').on('click', '.js-okundu', function () {
        var $btn = $(this);

        CY.post({ action: 'mesaj_okundu', id: $btn.data('id'), deger: $btn.data('deger') })
            .done(function (res) {
                CY.notify(res.description, 'success');
                rozetGuncelle(res.okunmamis);
                tablo.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                CY.notify(CY.hataMesaji(xhr, 'İşlem başarısız.'), 'danger');
            });
    });

    /* -------------------------------------------------------------
     *  SİL
     * ----------------------------------------------------------- */
    function mesajSil(id, etiket) {
        CY.onay({
            baslik: 'Mesajı Sil',
            metin:  (etiket || 'Bu mesaj') + ' kalıcı olarak silinecek.',
            buton:  'Evet, Sil'
        }, function () {
            CY.post({ action: 'mesaj_sil', id: id })
                .done(function (res) {
                    CY.notify(res.description, 'success');
                    rozetGuncelle(res.okunmamis);
                    mesajModal.hide();
                    tablo.ajax.reload(null, false);
                })
                .fail(function (xhr) {
                    CY.notify(CY.hataMesaji(xhr, 'Silme işlemi başarısız.'), 'danger');
                });
        });
    }

    $('#mesaj_tablosu').on('click', '.js-sil', function () {
        mesajSil($(this).data('id'), '"' + $(this).data('label') + '" mesajı');
    });

    $('#m_sil').on('click', function () {
        if (acikMesajId !== null) { mesajSil(acikMesajId, 'Bu mesaj'); }
    });

    /* -------------------------------------------------------------
     *  DERİN BAĞLANTI (?id=12)
     * ----------------------------------------------------------- */
    <?php if ($acilacakId !== null && $acilacakId !== false): ?>
    mesajAc(<?= (int) $acilacakId ?>);
    <?php endif; ?>
});
</script>
<?php
$sayfaScript = ob_get_clean();
require __DIR__ . '/_alt.php';
