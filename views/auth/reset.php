<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Yeni parola belirle (e-postadaki bağlantı)
 * ---------------------------------------------------------------------
 *  Jeton gizli alanda taşınır. Sayfa "Referrer-Policy: no-referrer"
 *  ile gönderilir; adres çubuğundaki jeton başka siteye sızmaz.
 *
 *  @var string               $jeton
 *  @var array<string,string> $errors
 * =====================================================================
 */

use App\Core\Setting;

$errors = $errors ?? [];
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
                            <h1 class="cy-title mt-3 mb-1">Yeni parola belirleyin</h1>
                            <p class="cy-subtitle mb-0">Kaydettiğinizde diğer cihazlardaki oturumlar ve API anahtarları kapatılır.</p>
                        </div>

                        <form method="post" action="<?= e(url('parola-sifirla')) ?>" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="jeton" value="<?= e($jeton) ?>">

                            <div class="mb-3">
                                <label class="form-label" for="sifre">Yeni parola <span class="text-danger">*</span></label>
                                <div class="cy-password">
                                    <input type="password" class="form-control<?= isset($errors['sifre']) ? ' is-invalid' : '' ?>"
                                           id="sifre" name="sifre" autocomplete="new-password" autofocus required
                                           aria-describedby="sifre_hint">
                                    <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster">
                                        <?= icon('eye', 'cy-icon cy-icon--sm') ?>
                                    </button>
                                </div>
                                <div class="form-text" id="sifre_hint">En az 8 karakter; harf ve rakam içermelidir.</div>
                                <?php if (isset($errors['sifre'])): ?>
                                    <div class="invalid-feedback d-block" data-error-for="sifre"><?= e($errors['sifre']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="sifre_tekrar">Yeni parola (tekrar) <span class="text-danger">*</span></label>
                                <input type="password" class="form-control<?= isset($errors['sifre_tekrar']) ? ' is-invalid' : '' ?>"
                                       id="sifre_tekrar" name="sifre_tekrar" autocomplete="new-password" required>
                                <?php if (isset($errors['sifre_tekrar'])): ?>
                                    <div class="invalid-feedback d-block" data-error-for="sifre_tekrar"><?= e($errors['sifre_tekrar']) ?></div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block">
                                <?= icon('lock', 'cy-icon cy-icon--sm') ?> Parolamı Değiştir
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
