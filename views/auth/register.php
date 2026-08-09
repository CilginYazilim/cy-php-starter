<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Kayıt
 * =====================================================================
 */

use App\Core\Setting;

$errors = $errors ?? [];
$old    = $old ?? [];
?>

<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-9 col-md-7 col-lg-6">
            <div class="cy-card">
                <div class="cy-card__body p-4">
                    <div class="text-center mb-4">
                        <img src="<?= e(Setting::logoUrl()) ?>" alt="" style="width:56px;height:56px;object-fit:contain">
                        <h1 class="cy-title mt-3 mb-1">Hesap oluşturun</h1>
                        <p class="cy-subtitle mb-0">Birkaç bilgiyle üye olun.</p>
                    </div>

                    <form method="post" action="<?= e(url('kayit')) ?>" novalidate>
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ad">Ad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control<?= isset($errors['ad']) ? ' is-invalid' : '' ?>"
                                       id="ad" name="ad" value="<?= old($old, 'ad') ?>" maxlength="100" autocomplete="given-name" required>
                                <?php if (isset($errors['ad'])): ?><div class="invalid-feedback"><?= e($errors['ad']) ?></div><?php endif; ?>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="soyad">Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control<?= isset($errors['soyad']) ? ' is-invalid' : '' ?>"
                                       id="soyad" name="soyad" value="<?= old($old, 'soyad') ?>" maxlength="100" autocomplete="family-name" required>
                                <?php if (isset($errors['soyad'])): ?><div class="invalid-feedback"><?= e($errors['soyad']) ?></div><?php endif; ?>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="kullanici_adi">Kullanıcı Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control<?= isset($errors['kullanici_adi']) ? ' is-invalid' : '' ?>"
                                       id="kullanici_adi" name="kullanici_adi" value="<?= old($old, 'kullanici_adi') ?>" maxlength="50" autocomplete="username" required>
                                <?php if (isset($errors['kullanici_adi'])): ?><div class="invalid-feedback"><?= e($errors['kullanici_adi']) ?></div><?php endif; ?>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="eposta">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control<?= isset($errors['eposta']) ? ' is-invalid' : '' ?>"
                                       id="eposta" name="eposta" value="<?= old($old, 'eposta') ?>" maxlength="190" autocomplete="email" required>
                                <?php if (isset($errors['eposta'])): ?><div class="invalid-feedback"><?= e($errors['eposta']) ?></div><?php endif; ?>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="sifre">Parola <span class="text-danger">*</span></label>
                                <div class="cy-password">
                                    <input type="password" class="form-control<?= isset($errors['sifre']) ? ' is-invalid' : '' ?>"
                                           id="sifre" name="sifre" autocomplete="new-password" required>
                                    <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster"><?= icon('eye', 'cy-icon cy-icon--sm') ?></button>
                                </div>
                                <div class="form-text">En az 8 karakter; harf ve rakam içermelidir.</div>
                                <?php if (isset($errors['sifre'])): ?><div class="invalid-feedback d-block"><?= e($errors['sifre']) ?></div><?php endif; ?>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="sifre_tekrar">Parola (Tekrar) <span class="text-danger">*</span></label>
                                <input type="password" class="form-control<?= isset($errors['sifre_tekrar']) ? ' is-invalid' : '' ?>"
                                       id="sifre_tekrar" name="sifre_tekrar" autocomplete="new-password" required>
                                <?php if (isset($errors['sifre_tekrar'])): ?><div class="invalid-feedback"><?= e($errors['sifre_tekrar']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block mt-3">
                            <?= icon('check', 'cy-icon cy-icon--sm') ?> Hesap Oluştur
                        </button>
                    </form>

                    <p class="text-center cy-muted mt-3 mb-0">
                        Zaten hesabınız var mı? <a class="cy-link" href="<?= e(url('giris')) ?>">Giriş yapın</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
