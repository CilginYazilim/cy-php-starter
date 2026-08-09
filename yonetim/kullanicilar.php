<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – KULLANICI YÖNETİMİ
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Listeleme, ekleme, düzenleme, hızlı durum değiştirme ve silme
 *  işlemleri AJAX ile system/ajax.php üzerinden yapılır
 *  (action=kullanici_*).
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik   = 'Kullanıcılar';
$sayfaAciklama = 'Hesapları, rolleri ve erişim durumlarını yönetin.';
$aktifMenu     = 'kullanicilar';
$gerekenRol    = 'admin';
$kirintilar    = ['Kullanıcılar'];

require __DIR__ . '/_ust.php';

// Üstteki özet kutuları için sayılar.
$sayilar = panel_stats($db);
?>

<!-- ================================================================
     ÖZET KUTULARI
     ================================================================ -->
<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3">
        <div class="info-box">
            <span class="info-box__icon info-box__icon--brand"><?= panel_icon('users') ?></span>
            <div>
                <div class="info-box__label">Toplam</div>
                <div class="info-box__value"><?= (int) $sayilar['kullanici_toplam'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box">
            <span class="info-box__icon info-box__icon--ok"><?= panel_icon('check') ?></span>
            <div>
                <div class="info-box__label">Aktif</div>
                <div class="info-box__value"><?= (int) $sayilar['kullanici_aktif'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box">
            <span class="info-box__icon info-box__icon--warn"><?= panel_icon('shield') ?></span>
            <div>
                <div class="info-box__label">Yönetici</div>
                <div class="info-box__value"><?= (int) $sayilar['kullanici_yonetici'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box">
            <span class="info-box__icon info-box__icon--danger"><?= panel_icon('x') ?></span>
            <div>
                <div class="info-box__label">Pasif / Askıda</div>
                <div class="info-box__value"><?= (int) $sayilar['kullanici_pasif'] ?></div>
            </div>
        </div>
    </div>
</div>


<div class="adm-card adm-card--brand">
    <div class="adm-card__header">
        <h3 class="adm-card__title"><?= panel_icon('users') ?> Kullanıcı Listesi</h3>

        <div class="adm-filters">
            <select id="filtre_rol" class="form-select form-select-sm" aria-label="Role göre süz">
                <option value="">Tüm roller</option>
                <?php foreach (auth_role_labels() as $deger => $etiket): ?>
                    <option value="<?= e($deger) ?>"><?= e($etiket) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filtre_durum" class="form-select form-select-sm" aria-label="Duruma göre süz">
                <option value="">Tüm durumlar</option>
                <?php foreach (auth_status_labels() as $deger => $etiket): ?>
                    <option value="<?= e($deger) ?>"><?= e($etiket) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="button" id="yeni_kullanici" class="btn cy-btn cy-btn--primary btn-sm">
                <?= panel_icon('plus') ?> Yeni Kullanıcı
            </button>
        </div>
    </div>

    <div class="adm-card__body adm-card__body--flush">
        <p class="cy-muted small px-3 pt-3 mb-0">
            Kendi hesabınızın rolünü düşüremez, kendinizi pasife alamaz veya silemezsiniz —
            aksi halde panele bir daha giremezdiniz.
        </p>

        <div class="table-responsive">
            <!--
                <th> SAYISI, system/ajax.php içindeki handle_kullanici_list()
                fonksiyonundan dönen dizi uzunluğuyla AYNI olmalıdır (6).
            -->
            <table id="kullanici_tablosu" class="table cy-table w-100">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Kullanıcı</th>
                        <th>E-posta</th>
                        <th style="width:110px">Rol</th>
                        <th style="width:170px">Durum</th>
                        <th class="text-center" style="width:140px">İşlemler</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>


<!-- ================================================================
     MODAL – Kullanıcı ekleme / düzenleme
     ================================================================ -->
<div class="modal fade cy-modal" id="kullaniciModal" tabindex="-1" aria-labelledby="kullaniciModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
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
                            <div class="form-text">İngilizce harf, rakam, nokta ve alt çizgi.</div>
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
                            <div class="form-text">Yönetici her şeye, editör yalnızca içeriğe erişir.</div>
                            <div class="invalid-feedback" data-error-for="rol"></div>
                        </div>
                        <div class="col-sm-6">
                            <label for="durum" class="form-label">Durum</label>
                            <select name="durum" id="durum" class="form-select">
                                <?php foreach (auth_status_labels() as $deger => $etiket): ?>
                                    <option value="<?= e($deger) ?>"><?= e($etiket) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Yalnızca "Aktif" hesaplar giriş yapabilir.</div>
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
                            <div class="input-group">
                                <input type="password" name="sifre" id="sifre" class="form-control" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" id="sifre_uret"
                                        title="Rastgele güçlü parola üret">Üret</button>
                            </div>
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

<?php
/* Sayfaya özel JavaScript'i yakalayıp _alt.php'ye veriyoruz; o da
 * ortak CY nesnesinden SONRA, </body>'den önce yazdırır. */
ob_start();
?>
<script>
$(function () {
    'use strict';

    var kullaniciModal = new bootstrap.Modal(document.getElementById('kullaniciModal'));
    var $form          = $('#kullanici_form');

    /* -------------------------------------------------------------
     *  LİSTE (sunucu taraflı DataTables)
     * ----------------------------------------------------------- */
    var tablo = $('#kullanici_tablosu').DataTable({
        processing: true,
        serverSide: true,
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        ajax: {
            url: CY.endpoint,
            type: 'POST',
            data: function (d) {
                d.action       = 'kullanici_list';
                d.csrf_token   = CY.token;
                d.filtre_rol   = $('#filtre_rol').val();
                d.filtre_durum = $('#filtre_durum').val();
            },
            error: function () {
                CY.notify('Kullanıcılar yüklenemedi.', 'danger');
            }
        },
        columnDefs: [
            { targets: 0, width: '60px', className: 'cy-id' },
            { targets: 5, orderable: false, searchable: false, className: 'text-center' }
        ],
        language: CY.dtDil('Kullanıcı')
    });

    // Süzgeç değişince listeyi baştan yükle (1. sayfaya dön).
    $('#filtre_rol, #filtre_durum').on('change', function () {
        tablo.ajax.reload();
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

    /* Rastgele parola üretici.
     * crypto.getRandomValues() kullanıyoruz: Math.random() tahmin
     * edilebilir olduğu için parola üretiminde ASLA kullanılmamalıdır.
     * Karakter havuzunda O/0, l/1 gibi karışan harfler yok. */
    $('#sifre_uret').on('click', function () {
        var havuz = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        var uzunluk = 14;
        var sonuc = '';

        if (window.crypto && window.crypto.getRandomValues) {
            var sayilar = new Uint32Array(uzunluk);
            window.crypto.getRandomValues(sayilar);
            for (var i = 0; i < uzunluk; i++) {
                sonuc += havuz.charAt(sayilar[i] % havuz.length);
            }
        } else {
            CY.notify('Tarayıcınız güvenli parola üretimini desteklemiyor.', 'danger');
            return;
        }

        // Parolayı görebilsin ki bir yere not edebilsin.
        $('#sifre').attr('type', 'text').val(sonuc).trigger('focus');
        CY.notify('Parola üretildi. Kaydetmeden önce bir yere not edin.', 'info');
    });

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
                CY.notify(CY.hataMesaji(xhr, 'Kullanıcı getirilemedi.'), 'danger');
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
                $('#form_alert').removeClass('d-none').text(CY.hataMesaji(xhr, 'İşlem tamamlanamadı.'));
            })
            .always(function () {
                yuklemeDurumu(false);
            });
    });

    /* -------------------------------------------------------------
     *  HIZLI DURUM DEĞİŞTİRME
     * ----------------------------------------------------------- */
    $('#kullanici_tablosu').on('click', '.js-durum', function () {
        var $btn = $(this).prop('disabled', true);

        CY.post({ action: 'kullanici_durum', id: $btn.data('id'), durum: $btn.data('durum') })
            .done(function (res) {
                CY.notify(res.description, 'success');
                tablo.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                CY.notify(CY.hataMesaji(xhr, 'Durum değiştirilemedi.'), 'danger');
                $btn.prop('disabled', false);
            });
    });

    /* -------------------------------------------------------------
     *  SİL (ortak onay penceresiyle)
     * ----------------------------------------------------------- */
    $('#kullanici_tablosu').on('click', '.js-sil', function () {
        var id     = $(this).data('id');
        var etiket = $(this).data('label') || ('#' + id);

        CY.onay({
            baslik: 'Kullanıcıyı Sil',
            metin:  '"' + etiket + '" hesabı kalıcı olarak silinecek.',
            buton:  'Evet, Sil'
        }, function () {
            CY.post({ action: 'kullanici_sil', id: id })
                .done(function (res) {
                    CY.notify(res.description, 'success');
                    tablo.ajax.reload(null, false);
                })
                .fail(function (xhr) {
                    CY.notify(CY.hataMesaji(xhr, 'Silme işlemi başarısız.'), 'danger');
                });
        });
    });

    // Modal kapanınca formu temizle (eski veriler kalmasın).
    $('#kullaniciModal').on('hidden.bs.modal', function () {
        formuSifirla();
        $('#sifre').attr('type', 'password');
    });
});
</script>
<?php
$sayfaScript = ob_get_clean();
require __DIR__ . '/_alt.php';
