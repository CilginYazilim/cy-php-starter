<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Giriş
 * =====================================================================
 */

use App\Core\Setting;

$errors       = $errors ?? [];
$old          = $old ?? [];
$demoAccounts = $demoAccounts ?? [];
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

                            <?php /* BENİ HATIRLA
                                     İşaretlenirse tarayıcıya 30 günlük, JavaScript'in
                                     okuyamadığı (HttpOnly) bir jeton çerezi bırakılır.
                                     Parola ya da e-posta çereze ASLA yazılmaz; çerez
                                     her kullanımda yenilenir ve çıkış yapıldığında
                                     sunucu tarafında iptal edilir. */ ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1"
                                       name="hatirla" id="hatirla" <?= old($old, 'hatirla') !== '' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="hatirla">
                                    Beni hatırla
                                    <span class="cy-muted small d-block">Bu tarayıcıda 30 gün açık kalayım.</span>
                                </label>
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

                        <?php if ($demoAccounts !== []): ?>
                            <div class="cy-quick-login">
                                <div class="cy-quick-login__divider"><span>veya demo hesapla dene</span></div>

                                <div class="cy-quick-login__grid">
                                    <?php foreach ($demoAccounts as $account): ?>
                                        <button type="button"
                                                class="cy-quick-login__item is-<?= e($account['variant']) ?> js-quick-login"
                                                data-identifier="<?= e($account['identifier']) ?>"
                                                data-password="<?= e($account['password']) ?>">
                                            <?= icon($account['icon'], 'cy-icon cy-icon--sm') ?>
                                            <span class="cy-quick-login__text">
                                                <strong><?= e($account['label']) ?></strong>
                                                <small><?= e($account['name']) ?></small>
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <p class="cy-quick-login__hint">
                                    Bu butonlar sadece <code>APP_DEBUG=true</code> iken görünür. "Pasif" ve "Askıda"
                                    hesaplar bilerek giriş yapamaz — durum kontrolünün nasıl çalıştığını gösterir.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
