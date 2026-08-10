<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Hesabım
 * =====================================================================
 */

use App\Models\Role;
use App\Models\User;

$profile = $user ?? $currentUser ?? null;
$errors  = $errors ?? [];
$old     = $old ?? [];

if ($profile === null) {
    return;
}

$value = static fn (string $field, string $fallback) => old($old, $field, $fallback);
?>

<?php /* SAYFA BAŞLIĞI ÜST ÇUBUKTA yazar; burada tekrar edilmez. */ ?>
<div class="cy-profile-grid">
    <div class="cy-card cy-profile-card">
        <img class="cy-avatar cy-avatar--xl<?= $profile->avatarUrl() === '' ? ' d-none' : '' ?>" id="profile_preview"
             src="<?= e($profile->avatarUrl()) ?>" alt="">
        <span class="cy-avatar cy-avatar--xl cy-avatar--initial<?= $profile->avatarUrl() !== '' ? ' d-none' : '' ?>" id="profile_preview_initial">
            <?= e($profile->initials()) ?>
        </span>

        <h3 class="cy-profile-card__name"><?= e($profile->fullName()) ?></h3>
        <p class="cy-profile-card__mail mb-2">@<?= e($profile->kullaniciAdi) ?> · <?= e($profile->eposta) ?></p>

        <span class="cy-role cy-role--<?= e(Role::variant($profile->rol)) ?>"><?= e($profile->roleLabel()) ?></span>

        <div class="cy-meta-list">
            <div class="cy-meta-list__row"><?= icon('phone', 'cy-icon cy-icon--sm') ?>
                <span><?= $profile->telefon !== '' ? e($profile->telefon) : '<em class="cy-muted">Telefon eklenmemiş</em>' ?></span>
            </div>
            <div class="cy-meta-list__row"><?= icon('calendar', 'cy-icon cy-icon--sm') ?>
                <span>Kayıt: <?= e(User::formatDate($profile->createdAt)) ?></span>
            </div>
            <div class="cy-meta-list__row"><?= icon('lock', 'cy-icon cy-icon--sm') ?>
                <span>Son giriş: <?= e(User::formatDate($profile->sonGiris)) ?></span>
            </div>
        </div>

        <form method="post" action="<?= e(url('panel/hesabim/avatar')) ?>" enctype="multipart/form-data" class="mt-3">
            <?= csrf_field() ?>
            <input type="file" name="avatar" class="form-control form-control-sm mb-2 js-image-input"
                   accept="image/jpeg,image/png,image/gif,image/webp"
                   data-preview="#profile_preview" data-placeholder="#profile_preview_initial">
            <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm cy-btn--block">
                <?= icon('upload', 'cy-icon cy-icon--sm') ?> Fotoğrafı Değiştir
            </button>
        </form>

        <?php if ($profile->avatar !== ''): ?>
            <form method="post" action="<?= e(url('panel/hesabim/avatar-sil')) ?>" class="mt-2">
                <?= csrf_field() ?>
                <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm cy-btn--block">Fotoğrafı Kaldır</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-column gap-3">
        <div class="cy-card">
            <div class="cy-card__header"><h3 class="cy-section-title">Kişisel Bilgiler</h3></div>

            <form method="post" action="<?= e(url('panel/hesabim/guncelle')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="cy-card__body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="ad">Ad <span class="text-danger">*</span></label>
                            <input type="text" name="ad" id="ad" maxlength="100" autocomplete="given-name"
                                   class="form-control<?= isset($errors['ad']) ? ' is-invalid' : '' ?>"
                                   value="<?= $value('ad', $profile->ad) ?>">
                            <?php if (isset($errors['ad'])): ?><div class="invalid-feedback"><?= e($errors['ad']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="soyad">Soyad <span class="text-danger">*</span></label>
                            <input type="text" name="soyad" id="soyad" maxlength="100" autocomplete="family-name"
                                   class="form-control<?= isset($errors['soyad']) ? ' is-invalid' : '' ?>"
                                   value="<?= $value('soyad', $profile->soyad) ?>">
                            <?php if (isset($errors['soyad'])): ?><div class="invalid-feedback"><?= e($errors['soyad']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="eposta">E-posta <span class="text-danger">*</span></label>
                            <input type="email" name="eposta" id="eposta" maxlength="190" autocomplete="email"
                                   class="form-control<?= isset($errors['eposta']) ? ' is-invalid' : '' ?>"
                                   value="<?= $value('eposta', $profile->eposta) ?>">
                            <?php if (isset($errors['eposta'])): ?><div class="invalid-feedback"><?= e($errors['eposta']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="telefon">Telefon</label>
                            <input type="tel" name="telefon" id="telefon" maxlength="30" autocomplete="tel"
                                   class="form-control<?= isset($errors['telefon']) ? ' is-invalid' : '' ?>"
                                   value="<?= $value('telefon', $profile->telefon) ?>">
                            <?php if (isset($errors['telefon'])): ?><div class="invalid-feedback"><?= e($errors['telefon']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="hakkinda">Hakkımda</label>
                            <textarea name="hakkinda" id="hakkinda" rows="3" maxlength="1000"
                                      class="form-control<?= isset($errors['hakkinda']) ? ' is-invalid' : '' ?>"><?= $value('hakkinda', $profile->hakkinda) ?></textarea>
                            <?php if (isset($errors['hakkinda'])): ?><div class="invalid-feedback"><?= e($errors['hakkinda']) ?></div><?php endif; ?>
                        </div>

                        <!-- DİKKAT: "rol" ve "durum" alanı BİLEREK yok. -->
                    </div>
                </div>
                <div class="cy-card__footer d-flex justify-content-end">
                    <button type="submit" class="btn cy-btn cy-btn--primary"><?= icon('check', 'cy-icon cy-icon--sm') ?> Bilgileri Kaydet</button>
                </div>
            </form>
        </div>

        <div class="cy-card">
            <div class="cy-card__header"><h3 class="cy-section-title">Parola Değiştir</h3></div>
            <form method="post" action="<?= e(url('panel/hesabim/parola')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="cy-card__body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="mevcut_sifre">Mevcut Parola</label>
                            <div class="cy-password">
                                <input type="password" name="mevcut_sifre" id="mevcut_sifre" autocomplete="current-password"
                                       class="form-control<?= isset($errors['mevcut_sifre']) ? ' is-invalid' : '' ?>">
                                <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster"><?= icon('eye', 'cy-icon cy-icon--sm') ?></button>
                            </div>
                            <?php if (isset($errors['mevcut_sifre'])): ?><div class="invalid-feedback d-block"><?= e($errors['mevcut_sifre']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="yeni_sifre">Yeni Parola</label>
                            <div class="cy-password">
                                <input type="password" name="yeni_sifre" id="yeni_sifre" autocomplete="new-password"
                                       class="form-control<?= isset($errors['yeni_sifre']) ? ' is-invalid' : '' ?>">
                                <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster"><?= icon('eye', 'cy-icon cy-icon--sm') ?></button>
                            </div>
                            <?php if (isset($errors['yeni_sifre'])): ?><div class="invalid-feedback d-block"><?= e($errors['yeni_sifre']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="yeni_sifre_tekrar">Yeni Parola (Tekrar)</label>
                            <input type="password" name="yeni_sifre_tekrar" id="yeni_sifre_tekrar" autocomplete="new-password"
                                   class="form-control<?= isset($errors['yeni_sifre_tekrar']) ? ' is-invalid' : '' ?>">
                            <?php if (isset($errors['yeni_sifre_tekrar'])): ?><div class="invalid-feedback d-block"><?= e($errors['yeni_sifre_tekrar']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="cy-card__footer d-flex justify-content-end">
                    <button type="submit" class="btn cy-btn cy-btn--ghost"><?= icon('lock', 'cy-icon cy-icon--sm') ?> Parolayı Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>
