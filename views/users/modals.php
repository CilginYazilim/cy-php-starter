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
                                <div class="flex-grow-1 min-w-0">
                                    <input type="file" name="avatar" id="avatar" class="form-control form-control-sm"
                                           accept="image/jpeg,image/png,image/gif,image/webp"
                                           data-max-mb="<?= upload_max_mb() ?>" aria-describedby="avatar_ipucu">
                                    <div class="form-text" id="avatar_ipucu">JPG, PNG, GIF, WEBP · en fazla <?= upload_max_mb() ?> MB · kare kırpılır · isteğe bağlı</div>
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
                            <input type="text" name="kullanici_adi" id="kullanici_adi" class="form-control" maxlength="50" autocomplete="username"
                                   autocapitalize="none" spellcheck="false" aria-describedby="kullanici_adi_ipucu">
                            <div class="form-text" id="kullanici_adi_ipucu"><?= e(username_hint()) ?></div>
                            <div class="invalid-feedback" data-error-for="kullanici_adi"></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="eposta">E-posta <span class="text-danger">*</span></label>
                            <input type="email" name="eposta" id="eposta" class="form-control" maxlength="190" autocomplete="email" inputmode="email"
                                   aria-describedby="eposta_ipucu">
                            <div class="form-text" id="eposta_ipucu">Girişte ve bildirimlerde kullanılır; başka hesapta kayıtlı olamaz.</div>
                            <div class="invalid-feedback" data-error-for="eposta"></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="telefon">Telefon</label>
                            <input type="tel" name="telefon" id="telefon" class="form-control" maxlength="30" autocomplete="tel" inputmode="tel"
                                   aria-describedby="telefon_ipucu">
                            <div class="form-text" id="telefon_ipucu">İsteğe bağlı · örn. +90 5XX XXX XX XX</div>
                            <div class="invalid-feedback" data-error-for="telefon"></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="sifre">
                                Parola <span class="text-danger" id="password_required">*</span>
                            </label>
                            <div class="cy-password">
                                <input type="password" name="sifre" id="sifre" class="form-control" autocomplete="new-password"
                                       aria-describedby="password_hint">
                                <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster">
                                    <?= icon('eye', 'cy-icon cy-icon--sm') ?>
                                </button>
                            </div>
                            <?php /* users.js yeni kayıtta bu metni, düzenlemede "boş bırakın" uyarısını
                                     yazar; kural metni sunucudan gelir (password_hint). */ ?>
                            <div class="form-text" id="password_hint" data-varsayilan="<?= e(password_hint()) ?>"><?= e(password_hint()) ?></div>
                            <div class="invalid-feedback" data-error-for="sifre"></div>
                        </div>

                        <?php /* YALNIZCA YENİ KAYITTA (users.js düzenlemede gizler).
                                 Mektupta parola YAZMAZ: 48 saat geçerli "parolanızı
                                 belirleyin" bağlantısı gider (PasswordReset::setupLink). */ ?>
                        <?php $bilgiGidebilir = App\Core\PasswordReset::canSendLinks(); ?>
                        <div class="col-12" id="hesap_bilgisi_group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="hesap_bilgisi" id="hesap_bilgisi" value="1"
                                       aria-describedby="hesap_bilgisi_ipucu" <?= $bilgiGidebilir ? 'checked' : 'disabled' ?>>
                                <label class="form-check-label" for="hesap_bilgisi">Kullanıcıya hesap bilgisi gönder</label>
                                <div class="form-text mt-0" id="hesap_bilgisi_ipucu">
                                    <?php if (!$bilgiGidebilir): ?>
                                        E-posta gönderimi kapalı (Ayarlar → E-posta). Parolayı kullanıcıya kendiniz iletin.
                                    <?php else: ?>
                                        Parola mektupta yazmaz: kullanıcı <?= App\Core\PasswordReset::HESAP_ACILIS_SAAT ?> saat geçerli bir bağlantıyla
                                        kendi parolasını belirler. Parola alanını boş bırakabilirsiniz.
                                        <?php if (!App\Core\Mail\Mailer::canDeliver()): ?>(Geliştirme modu: bağlantı Panel → E-posta geçmişinde görünür.)<?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="invalid-feedback d-block" data-error-for="hesap_bilgisi"></div>
                            </div>
                        </div>

                        <?php /* Bir YÖNETİCİNİN ya da kendi hesabının e-postası veya
                                 parolası değişiyorsa işlemi yapanın parolası istenir
                                 (bkz. UserApiController::save). JS yalnızca gerektiğinde gösterir. */ ?>
                        <div class="col-12 d-none" id="reauth_group">
                            <label class="form-label" for="onay_parola">Kendi parolanız</label>
                            <div class="cy-password">
                                <input type="password" name="onay_parola" id="onay_parola" class="form-control"
                                       autocomplete="current-password" aria-describedby="onay_parola_hint">
                                <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster">
                                    <?= icon('eye', 'cy-icon cy-icon--sm') ?>
                                </button>
                            </div>
                            <div class="form-text" id="onay_parola_hint">Bu hesabın e-postasını ya da parolasını değiştiriyorsanız kendi parolanızla onaylayın.</div>
                            <div class="invalid-feedback" data-error-for="onay_parola"></div>
                        </div>
                    </div>

                    <?php if (can('users.role') || can('users.status')): ?>
                        <p class="cy-form-section">Yetkilendirme</p>
                        <div class="row g-3">
                            <?php if (can('users.role')): ?>
                                <fieldset class="col-12 col-md-6 cy-fieldset" aria-describedby="rol_aciklama hata-rol">
                                    <legend class="form-label">Rol</legend>
                                    <div class="cy-choice-group" id="rol_group">
                                        <?php foreach ($roles as $value => $label): ?>
                                            <input type="radio" class="cy-choice-group__input" name="rol"
                                                   id="rol_<?= e($value) ?>" value="<?= e($value) ?>"
                                                   <?= $value === App\Models\Role::MEMBER ? 'checked' : '' ?>>
                                            <label class="cy-choice-group__pill cy-role cy-role--<?= e(App\Models\Role::variant($value)) ?>"
                                                   for="rol_<?= e($value) ?>"><?= e($label) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <ul class="cy-choice-help" id="rol_aciklama">
                                        <?php foreach ($roles as $value => $label): ?>
                                            <?php if (App\Models\Role::description($value) !== ''): ?>
                                                <li><strong><?= e($label) ?>:</strong> <?= e(App\Models\Role::description($value)) ?></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="invalid-feedback d-block" id="hata-rol" data-error-for="rol"></div>
                                </fieldset>
                            <?php endif; ?>

                            <?php if (can('users.status')): ?>
                                <fieldset class="col-12 col-md-6 cy-fieldset" aria-describedby="durum_aciklama hata-durum">
                                    <legend class="form-label">Durum</legend>
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

                                        <input type="radio" class="cy-choice-group__input" name="durum" id="durum_onay_bekliyor" value="onay_bekliyor">
                                        <label class="cy-choice-group__pill cy-status is-hold" for="durum_onay_bekliyor">
                                            <span class="cy-status__dot"></span>Onay bekliyor
                                        </label>
                                    </div>
                                    <ul class="cy-choice-help" id="durum_aciklama">
                                        <li><strong>Aktif:</strong> giriş yapabilir.</li>
                                        <li><strong>Pasif:</strong> giriş yapamaz; kalıcı olarak kullanım dışı (ör. ayrılan çalışan). Veriler silinmez.</li>
                                        <li><strong>Askıda:</strong> giriş yapamaz; geçici durdurma (ör. inceleme süresince).</li>
                                        <li><strong>Onay bekliyor:</strong> e-posta doğrulaması tamamlanmamış.</li>
                                    </ul>
                                    <div class="invalid-feedback d-block" id="hata-durum" data-error-for="durum"></div>
                                </fieldset>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <p class="cy-form-section">Ek Bilgiler</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="hakkinda">Hakkında</label>
                            <textarea name="hakkinda" id="hakkinda" class="form-control" rows="2" maxlength="1000"
                                      aria-describedby="hakkinda_ipucu" data-sayac="#hakkinda_sayac"></textarea>
                            <div class="cy-field-meta">
                                <div class="form-text" id="hakkinda_ipucu">İsteğe bağlı · yalnızca panelde, kullanıcı ayrıntısında görünür.</div>
                                <span class="cy-sayac" id="hakkinda_sayac"></span>
                            </div>
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
                        <span class="cy-alert__icon"><?= icon('alert', 'cy-icon cy-icon--sm') ?></span>
                        <div class="cy-alert__body" id="detail_login_attempts_text"></div>
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
