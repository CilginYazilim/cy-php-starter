<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Parolamı unuttum (e-posta iste)
 * ---------------------------------------------------------------------
 *  Yanıt her durumda aynıdır; bkz. PasswordResetController.
 *
 *  @var array<string,string> $errors, $old
 * =====================================================================
 */

use App\Core\PasswordReset;
use App\Core\Setting;

$errors = $errors ?? [];
$old    = $old ?? [];
?>

<section class="cy-auth-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-8 col-md-6 col-lg-5">
                <div class="cy-card cy-auth-card">
                    <div class="cy-card__body p-4">
                        <div class="text-center mb-4">
                            <span class="cy-auth-card__logo">
                                <img src="<?= e(Setting::logoUrl()) ?>" alt="">
                            </span>
                            <h1 class="cy-title mt-3 mb-1">Parolanızı mı unuttunuz?</h1>
                            <p class="cy-subtitle mb-0">Hesabınızın e-posta adresini yazın; yeni parola belirlemeniz için bir bağlantı gönderelim.</p>
                        </div>

                        <form method="post" action="<?= e(url('parolami-unuttum')) ?>" novalidate>
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label class="form-label" for="eposta">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control<?= isset($errors['eposta']) ? ' is-invalid' : '' ?>"
                                       id="eposta" name="eposta" value="<?= old($old, 'eposta') ?>" maxlength="190"
                                       autocomplete="email" inputmode="email" autofocus required>
                                <?php if (isset($errors['eposta'])): ?>
                                    <div class="invalid-feedback d-block" data-error-for="eposta"><?= e($errors['eposta']) ?></div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block">
                                <?= icon('send', 'cy-icon cy-icon--sm') ?> Bağlantı Gönder
                            </button>
                        </form>

                        <p class="cy-muted small text-center mt-3 mb-0">
                            Bağlantı <?= PasswordReset::DAKIKA ?> dakika geçerlidir ve bir kez kullanılabilir.
                        </p>
                        <p class="text-center cy-muted mt-2 mb-0">
                            <a class="cy-link" href="<?= e(url('giris')) ?>">Giriş ekranına dön</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
