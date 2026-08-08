<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – KULLANICI YÖNETİMİ
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Listeleme, ekleme, düzenleme ve silme işlemleri AJAX ile
 *  system/ajax.php üzerinden yapılır (action=kullanici_*).
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik = 'Kullanıcılar';
$aktifMenu   = 'kullanicilar';
$gerekenRol  = 'admin';

require __DIR__ . '/_ust.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="cy-muted mb-0">
        Rolleri ve hesap durumlarını buradan yönetin.
        Kendi hesabınızın rolünü düşüremez veya kendinizi silemezsiniz.
    </p>
    <button type="button" id="yeni_kullanici" class="btn cy-btn cy-btn--primary">
        <span aria-hidden="true">＋</span> Yeni Kullanıcı
    </button>
</div>

<div class="table-responsive">
    <!--
        <th> SAYISI, system/ajax.php içindeki handle_kullanici_list()
        fonksiyonundan dönen dizi uzunluğuyla AYNI olmalıdır (6).
    -->
    <table id="kullanici_tablosu" class="table cy-table w-100">
        <thead>
            <tr>
                <th>#</th>
                <th>Ad Soyad</th>
                <th>E-posta</th>
                <th>Rol</th>
                <th>Durum</th>
                <th class="text-center">İşlemler</th>
            </tr>
        </thead>
    </table>
</div>


<!-- ================================================================
     MODAL – Kullanıcı ekleme / düzenleme
     ================================================================ -->
<div class="modal fade cy-modal" id="kullaniciModal" tabindex="-1" aria-labelledby="kullaniciModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="kullanici_form" novalidate>
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6 mb-0" id="kullaniciModalLabel">Yeni Kullanıcı</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="form_alert" role="alert"></div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="ad" class="form-label">Ad <span class="text-danger">*</span></label>
                            <input type="text" name="ad" id="ad" class="form-control" maxlength="100">
                            <div class="invalid-feedback" data-error-for="ad"></div>
                        </div>
                        <div class="col-sm-6">
                            <label for="soyad" class="form-label">Soyad <span class="text-danger">*</span></label>
                            <input type="text" name="soyad" id="soyad" class="form-control" maxlength="100">
                            <div class="invalid-feedback" data-error-for="soyad"></div>
                        </div>
                        <div class="col-sm-6">
                            <label for="kullanici_adi" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                            <input type="text" name="kullanici_adi" id="kullanici_adi" class="form-control" maxlength="50">
                            <div class="invalid-feedback" data-error-for="kullanici_adi"></div>
                        </div>
                        <div class="col-sm-6">
                            <label for="eposta" class="form-label">E-posta <span class="text-danger">*</span></label>
                            <input type="email" name="eposta" id="eposta" class="form-control" maxlength="190">
                            <div class="invalid-feedback" data-error-for="eposta"></div>
                        </div>
                        <div class="col-sm-6">
                            <label for="rol" class="form-label">Rol</label>
                            <select name="rol" id="rol" class="form-select">
                                <?php foreach (auth_role_labels() as $deger => $etiket): ?>
                                    <option value="<?= e($deger) ?>"><?= e($etiket) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-error-for="rol"></div>
                        </div>
                        <div class="col-sm-6">
                            <label for="durum" class="form-label">Durum</label>
                            <select name="durum" id="durum" class="form-select">
                                <?php foreach (auth_status_labels() as $deger => $etiket): ?>
                                    <option value="<?= e($deger) ?>"><?= e($etiket) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-error-for="durum"></div>
                        </div>
                        <div class="col-sm-6">
                            <label for="telefon" class="form-label">Telefon</label>
                            <input type="text" name="telefon" id="telefon" class="form-control" maxlength="30">
                            <div class="invalid-feedback" data-error-for="telefon"></div>
                        </div>
                        <div class="col-sm-6">
                            <label for="sifre" class="form-label">
                                Parola <span class="text-danger" id="sifre_zorunlu">*</span>
                            </label>
                            <input type="password" name="sifre" id="sifre" class="form-control" autocomplete="new-password">
                            <div class="form-text" id="sifre_ipucu">En az 8 karakter, harf ve rakam.</div>
                            <div class="invalid-feedback" data-error-for="sifre"></div>
                        </div>
                        <div class="col-12">
                            <label for="hakkinda" class="form-label">Hakkında</label>
                            <textarea name="hakkinda" id="hakkinda" class="form-control" rows="2" maxlength="1000"></textarea>
                            <div class="invalid-feedback" data-error-for="hakkinda"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary cy-btn" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" id="kaydet_butonu" class="btn cy-btn cy-btn--primary">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="kaydet_spinner" role="status" aria-hidden="true"></span>
                        <span id="kaydet_etiket">Kaydet</span>
                    </button>
                </div>

                <input type="hidden" name="id" id="kullanici_id" value="">
            </div>
        </form>
    </div>
</div>


<!-- ================================================================
     MODAL – Silme onayı
     ================================================================ -->
<div class="modal fade cy-modal" id="silModal" tabindex="-1" aria-labelledby="silModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 mb-0" id="silModalLabel">Kullanıcıyı Sil</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body text-center">
                <div class="delete-icon" aria-hidden="true">!</div>
                <p class="mb-0"><strong id="sil_etiket"></strong> hesabı kalıcı olarak silinecek.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary cy-btn btn-sm" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-danger cy-btn btn-sm" id="sil_onayla">Evet, Sil</button>
            </div>
        </div>
    </div>
</div>

<?php
/* Sayfaya özel JavaScript'i yakalayıp _alt.php'ye veriyoruz; o da
 * ortak CY nesnesinden SONRA, </body>'den önce yazdırır. */
ob_start();
?>
<script>
$(function () {
    'use strict';

    var kullaniciModal = new bootstrap.Modal(document.getElementById('kullaniciModal'));
    var silModal       = new bootstrap.Modal(document.getElementById('silModal'));
    var $form          = $('#kullanici_form');
    var silinecekId    = null;

    /* -------------------------------------------------------------
     *  LİSTE (sunucu taraflı DataTables)
     * ----------------------------------------------------------- */
    var tablo = $('#kullanici_tablosu').DataTable({
        processing: true,
        serverSide: true,
        order: [[0, 'desc']],
        pageLength: 10,
        ajax: {
            url: CY.endpoint,
            type: 'POST',
            data: function (d) {
                d.action = 'kullanici_list';
                d.csrf_token = CY.token;
            },
            error: function () {
                CY.notify('Kullanıcılar yüklenemedi.', 'danger');
            }
        },
        columnDefs: [
            { targets: 0, width: '60px', className: 'cy-id' },
            { targets: 5, orderable: false, searchable: false, className: 'text-center' }
        ],
        language: {
            emptyTable: 'Kullanıcı bulunmuyor.',
            info: '_TOTAL_ kullanıcıdan _START_ – _END_ arası',
            infoEmpty: 'Gösterilecek kullanıcı yok',
            infoFiltered: '(toplam _MAX_ kayıt içinden filtrelendi)',
            lengthMenu: 'Sayfada _MENU_ kayıt göster',
            loadingRecords: 'Yükleniyor…',
            processing: 'İşleniyor…',
            search: 'Ara:',
            searchPlaceholder: 'Ad, e-posta veya kullanıcı adı',
            zeroRecords: 'Eşleşen kullanıcı bulunamadı.',
            paginate: { first: 'İlk', last: 'Son', next: 'Sonraki', previous: 'Önceki' }
        }
    });

    /* -------------------------------------------------------------
     *  FORM YARDIMCILARI
     * ----------------------------------------------------------- */
    function formuSifirla() {
        $form[0].reset();
        $('#kullanici_id').val('');
        $('#form_alert').addClass('d-none').text('');
        CY.clearErrors($form);
    }

    function yuklemeDurumu(yukleniyor) {
        $('#kaydet_butonu').prop('disabled', yukleniyor);
        $('#kaydet_spinner').toggleClass('d-none', !yukleniyor);
        $('#kaydet_etiket').text(yukleniyor ? 'Kaydediliyor…' : 'Kaydet');
    }

    /* -------------------------------------------------------------
     *  YENİ KULLANICI
     * ----------------------------------------------------------- */
    $('#yeni_kullanici').on('click', function () {
        formuSifirla();
        $('#kullaniciModalLabel').text('Yeni Kullanıcı');
        // Yeni kayıtta parola zorunlu.
        $('#sifre_zorunlu').removeClass('d-none');
        $('#sifre_ipucu').text('En az 8 karakter, harf ve rakam.');
        kullaniciModal.show();
    });

    /* -------------------------------------------------------------
     *  DÜZENLE
     *  Butonlar AJAX ile sonradan geldiği için olayı sabit bir üst
     *  elemana bağlıyoruz (event delegation).
     * ----------------------------------------------------------- */
    $('#kullanici_tablosu').on('click', '.js-duzenle', function () {
        var id = $(this).data('id');

        CY.post({ action: 'kullanici_getir', id: id })
            .done(function (res) {
                formuSifirla();
                $('#kullaniciModalLabel').text('Kullanıcıyı Düzenle');
                $('#kullanici_id').val(res.id);
                $('#ad').val(res.ad);
                $('#soyad').val(res.soyad);
                $('#kullanici_adi').val(res.kullanici_adi);
                $('#eposta').val(res.eposta);
                $('#rol').val(res.rol);
                $('#durum').val(res.durum);
                $('#telefon').val(res.telefon);
                $('#hakkinda').val(res.hakkinda);

                // Düzenlemede parola boş bırakılabilir.
                $('#sifre_zorunlu').addClass('d-none');
                $('#sifre_ipucu').text('Değiştirmek istemiyorsanız boş bırakın.');

                kullaniciModal.show();
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};
                CY.notify(res.description || 'Kullanıcı getirilemedi.', 'danger');
            });
    });

    /* -------------------------------------------------------------
     *  KAYDET (ekle + düzenle ortak)
     * ----------------------------------------------------------- */
    $form.on('submit', function (e) {
        e.preventDefault();
        CY.clearErrors($form);
        $('#form_alert').addClass('d-none').text('');
        yuklemeDurumu(true);

        var veri = {};
        $.each($form.serializeArray(), function (_, alan) {
            veri[alan.name] = alan.value;
        });
        veri.action = $('#kullanici_id').val() ? 'kullanici_guncelle' : 'kullanici_ekle';

        CY.post(veri)
            .done(function (res) {
                kullaniciModal.hide();
                formuSifirla();
                CY.notify(res.description, 'success');
                tablo.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};
                if (res.errors) {
                    CY.showErrors($form, res.errors);
                }
                $('#form_alert').removeClass('d-none').text(res.description || 'İşlem tamamlanamadı.');
            })
            .always(function () {
                yuklemeDurumu(false);
            });
    });

    /* -------------------------------------------------------------
     *  SİL
     * ----------------------------------------------------------- */
    $('#kullanici_tablosu').on('click', '.js-sil', function () {
        silinecekId = $(this).data('id');
        $('#sil_etiket').text($(this).data('label') || '#' + silinecekId);
        silModal.show();
    });

    $('#sil_onayla').on('click', function () {
        if (silinecekId === null) { return; }

        var $btn = $(this).prop('disabled', true);

        CY.post({ action: 'kullanici_sil', id: silinecekId })
            .done(function (res) {
                CY.notify(res.description, 'success');
                tablo.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};
                CY.notify(res.description || 'Silme işlemi başarısız.', 'danger');
            })
            .always(function () {
                $btn.prop('disabled', false);
                silModal.hide();
                silinecekId = null;
            });
    });

    // Modal kapanınca formu temizle (eski veriler kalmasın).
    $('#kullaniciModal').on('hidden.bs.modal', formuSifirla);
});
</script>
<?php
$sayfaScript = ob_get_clean();
require __DIR__ . '/_alt.php';
