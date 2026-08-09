<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Giriş
 * =====================================================================
 */

use App\Core\Setting;

$errors = $errors ?? [];
$old    = $old ?? [];
?>

<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-8 col-md-6 col-lg-5">
            <div class="cy-card">
                <div class="cy-card__body p-4">
                    <div class="text-center mb-4">
                        <img src="<?= e(Setting::logoUrl()) ?>" alt="" style="width:56px;height:56px;object-fit:contain">
                        <h1 class="cy-title mt-3 mb-1">Giriş yapın</h1>
                        <p class="cy-subtitle mb-0">Devam etmek için hesap bilgilerinizi girin.</p>
                    </div>

                    <form method="post" action="<?= e(url('giris')) ?>" id="cy_login_form" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label" for="identifier">E-posta veya Kullanıcı Adı</label>
                            <input type="text" class="form-control<?= isset($errors['identifier']) ? ' is-invalid' : '' ?>"
                                   id="identifier" name="identifier" value="<?= old($old, 'identifier') ?>"
                                   autocomplete="username" autofocus required>
                            <?php if (isset($errors['identifier'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['identifier']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Parola</label>
                            <div class="cy-password">
                                <input type="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                                       id="password" name="password" autocomplete="current-password" required>
                                <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster">
                                    <?= icon('eye', 'cy-icon cy-icon--sm') ?>
                                </button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block">
                            <?= icon('logout', 'cy-icon cy-icon--sm') ?> Giriş Yap
                        </button>
                    </form>

                    <?php if (Setting::bool('sistem_kayit_acik', false)): ?>
                        <p class="text-center cy-muted mt-3 mb-0">
                            Hesabınız yok mu? <a class="cy-link" href="<?= e(url('kayit')) ?>">Kayıt olun</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
