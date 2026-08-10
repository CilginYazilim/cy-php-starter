<?php
/**
 * =====================================================================
 *  PARÇA: Kullanıcı sayfası modalları (ekle/düzenle, detay, silme onayı)
 * =====================================================================
 */

$roles = $roles ?? [];
?>

<!-- ================================================================
     MODAL 1 – EKLEME / DÜZENLEME
     ================================================================ -->
<div class="modal fade cy-modal" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <form method="post" id="user_form" enctype="multipart/form-data" novalidate>
            <div class="modal-content">

                <div class="modal-header">
                    <div class="cy-modal__heading">
                        <h2 class="modal-title" id="userModalLabel">Yeni Kullanıcı</h2>
                        <p class="cy-modal__subtitle" id="userModalSubtitle">Formu doldurup hesabı oluşturun.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>

                <div class="modal-body">
                    <div class="cy-alert cy-alert--danger d-none mb-3" id="form_alert" role="alert"></div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="avatar">Profil Görseli</label>
                            <div class="cy-upload">
                                <span class="cy-upload__preview">
                                    <img src="" alt="" id="avatar_preview" class="cy-avatar cy-avatar--md d-none">
                                    <span class="cy-avatar cy-avatar--md cy-avatar--initial" id="avatar_placeholder">
                                        <?= icon('user', 'cy-icon cy-icon--sm') ?>
                                    </span>
                                </span>
                                <div class="flex-grow-1" style="min-width:0">
                                    <input type="file" name="avatar" id="avatar" class="form-control form-control-sm"
                                           accept="image/jpeg,image/png,image/gif,image/webp">
                                    <div class="form-text">JPG, PNG, GIF, WEBP · en fazla 2 MB · isteğe bağlı</div>
                                    <div class="invalid-feedback d-block" data-error-for="avatar"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="cy-form-section">Hesap Bilgileri</p>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="ad">Ad <span class="text-danger">*</span></label>
                            <input type="text" name="ad" id="ad" class="form-control" maxlength="100" autocomplete="given-name">
                            <div class="invalid-feedback" data-error-for="ad"></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="soyad">Soyad <span class="text-danger">*</span></label>
                            <input type="text" name="soyad" id="soyad" class="form-control" maxlength="100" autocomplete="family-name">
                            <div class="invalid-feedback" data-error-for="soyad"></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="kullanici_adi">Kullanıcı Adı <span class="text-danger">*</span></label>
                            <input type="text" name="kullanici_adi" id="kullanici_adi" class="form-control" maxlength="50" autocomplete="username">
                            <div class="invalid-feedback" data-error-for="kullanici_adi"></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="eposta">E-posta <span class="text-danger">*</span></label>
                            <input type="email" name="eposta" id="eposta" class="form-control" maxlength="190" autocomplete="email" inputmode="email">
                            <div class="invalid-feedback" data-error-for="eposta"></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="telefon">Telefon</label>
                            <input type="tel" name="telefon" id="telefon" class="form-control" maxlength="30" autocomplete="tel" inputmode="tel">
                            <div class="invalid-feedback" data-error-for="telefon"></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="sifre">
                                Parola <span class="text-danger" id="password_required">*</span>
                            </label>
                            <div class="cy-password">
                                <input type="password" name="sifre" id="sifre" class="form-control" autocomplete="new-password">
                                <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster">
                                    <?= icon('eye', 'cy-icon cy-icon--sm') ?>
                                </button>
                            </div>
                            <div class="form-text" id="password_hint">En az 8 karakter; harf ve rakam içermelidir.</div>
                            <div class="invalid-feedback" data-error-for="sifre"></div>
                        </div>
                    </div>

                    <?php if (can('users.role') || can('users.status')): ?>
                        <p class="cy-form-section">Yetkilendirme</p>
                        <div class="row g-3">
                            <?php if (can('users.role')): ?>
                                <div class="col-12 col-md-6">
                                    <span class="form-label d-block">Rol</span>
                                    <div class="cy-choice-group" id="rol_group">
                                        <?php foreach ($roles as $value => $label): ?>
                                            <input type="radio" class="cy-choice-group__input" name="rol"
                                                   id="rol_<?= e($value) ?>" value="<?= e($value) ?>"
                                                   <?= $value === App\Models\Role::MEMBER ? 'checked' : '' ?>>
                                            <label class="cy-choice-group__pill cy-role cy-role--<?= e(App\Models\Role::variant($value)) ?>"
                                                   for="rol_<?= e($value) ?>"><?= e($label) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="invalid-feedback d-block" data-error-for="rol"></div>
                                </div>
                            <?php endif; ?>

                            <?php if (can('users.status')): ?>
                                <div class="col-12 col-md-6">
                                    <span class="form-label d-block">Durum</span>
                                    <div class="cy-choice-group" id="durum_group">
                                        <input type="radio" class="cy-choice-group__input" name="durum" id="durum_aktif" value="aktif" checked>
                                        <label class="cy-choice-group__pill cy-status is-active" for="durum_aktif">
                                            <span class="cy-status__dot"></span>Aktif
                                        </label>

                                        <input type="radio" class="cy-choice-group__input" name="durum" id="durum_pasif" value="pasif">
                                        <label class="cy-choice-group__pill cy-status is-passive" for="durum_pasif">
                                            <span class="cy-status__dot"></span>Pasif
                                        </label>

                                        <input type="radio" class="cy-choice-group__input" name="durum" id="durum_askida" value="askida">
                                        <label class="cy-choice-group__pill cy-status is-hold" for="durum_askida">
                                            <span class="cy-status__dot"></span>Askıda
                                        </label>
                                    </div>
                                    <div class="invalid-feedback d-block" data-error-for="durum"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <p class="cy-form-section">Ek Bilgiler</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="hakkinda">Hakkında</label>
                            <textarea name="hakkinda" id="hakkinda" class="form-control" rows="2" maxlength="1000"></textarea>
                            <div class="invalid-feedback" data-error-for="hakkinda"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn cy-btn cy-btn--ghost" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" id="submit_button" class="btn cy-btn cy-btn--primary">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="submit_spinner" role="status" aria-hidden="true"></span>
                        <span id="submit_label">Kaydet</span>
                    </button>
                </div>

                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="id" id="user_id" value="">
                <?= csrf_field() ?>
            </div>
        </form>
    </div>
</div>


<!-- ================================================================
     MODAL 2 – DETAY
     ================================================================ -->
<div class="modal fade cy-modal" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="detailModalLabel">Kullanıcı Detayı</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <img src="" alt="" id="detail_image" class="cy-avatar cy-avatar--xl d-none">
                    <span id="detail_initial" class="cy-avatar cy-avatar--xl cy-avatar--initial d-none"></span>
                    <h3 class="h5 mt-3 mb-1" id="detail_fullname"></h3>
                    <div class="d-flex justify-content-center gap-2">
                        <span id="detail_role"></span>
                        <span id="detail_status"></span>
                    </div>
                </div>
                <dl class="cy-detail">
                    <dt>Kayıt No</dt>       <dd id="detail_id"></dd>
                    <dt>Kullanıcı Adı</dt>  <dd id="detail_kadi"></dd>
                    <dt>E-posta</dt>        <dd id="detail_email"></dd>
                    <dt>Telefon</dt>        <dd id="detail_phone"></dd>
                    <dt>Hakkında</dt>       <dd id="detail_bio"></dd>
                    <dt>Son Giriş</dt>      <dd id="detail_login"></dd>
                    <dt>Kayıt Tarihi</dt>   <dd id="detail_created"></dd>
                </dl>

                <?php if (can('users.view')): ?>
                    <div class="cy-alert cy-alert--warning mt-3 d-none" id="detail_login_attempts">
                        <?= icon('alert', 'cy-icon cy-icon--sm') ?>
                        <span id="detail_login_attempts_text"></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cy-btn cy-btn--ghost" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn cy-btn cy-btn--primary d-none" id="detail_edit_button">
                    <?= icon('edit', 'cy-icon cy-icon--sm') ?> Düzenle
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ================================================================
     MODAL 3 – SİLME ONAYI
     ================================================================ -->
<div class="modal fade cy-modal" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="deleteModalLabel">Kullanıcıyı Sil</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body text-center">
                <span class="cy-empty__icon" style="background:var(--cy-danger-soft);color:var(--cy-danger)"><?= icon('trash') ?></span>
                <p class="mb-0"><strong id="delete_label"></strong> hesabı ve varsa profil görseli <u>kalıcı olarak</u> silinecek.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn cy-btn cy-btn--danger cy-btn--sm" id="confirm_delete">Evet, Sil</button>
            </div>
        </div>
    </div>
</div>
