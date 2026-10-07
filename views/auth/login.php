<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Giriş
 * =====================================================================
 */

use App\Core\PasswordReset;
use App\Core\Registration;
use App\Core\Setting;

$errors       = $errors ?? [];
$old          = $old ?? [];
$demoAccounts = $demoAccounts ?? [];
$demoMode     = $demoMode ?? false;
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

                        <?php /* Bakımda ekran açık kalır (yönetici girebilsin); üyeye
                                 önceden söylenir, girerse Auth::attempt reddeder. */ ?>
                        <?php if (Setting::bool('sistem_bakim_modu', false)): ?>
                            <div class="cy-alert cy-alert--info mb-3" role="status">
                                <span class="cy-alert__icon"><?= icon('info', 'cy-icon cy-icon--sm') ?></span>
                                <div class="cy-alert__body"><?= e(App\Core\Auth::BAKIM_MESAJI) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?= e(url('giris')) ?>" id="cy_login_form" novalidate>
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label class="form-label" for="identifier">E-posta veya Kullanıcı Adı</label>
                                <input type="text" class="form-control<?= isset($errors['identifier']) ? ' is-invalid' : '' ?>"
                                       id="identifier" name="identifier" value="<?= old($old, 'identifier') ?>"
                                       autocomplete="username" autocapitalize="none" spellcheck="false" autofocus required>
                                <?php if (isset($errors['identifier'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors['identifier']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-baseline gap-2">
                                    <label class="form-label" for="password">Parola</label>
                                    <?php /* Bağlantı yalnızca site e-posta gönderebiliyorsa ve
                                             ayar açıksa görünür (bkz. PasswordReset::enabled). */ ?>
                                    <?php if (PasswordReset::enabled()): ?>
                                        <a class="cy-link small" href="<?= e(url('parolami-unuttum')) ?>">Parolamı unuttum</a>
                                    <?php endif; ?>
                                </div>
                                <div class="cy-password">
                                    <input type="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                                           id="password" name="password" autocomplete="current-password"
                                           <?= PasswordReset::enabled() ? '' : 'aria-describedby="password_ipucu"' ?> required>
                                    <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster">
                                        <?= icon('eye', 'cy-icon cy-icon--sm') ?>
                                    </button>
                                </div>
                                <?php if (!PasswordReset::enabled()): ?>
                                    <div class="form-text" id="password_ipucu">Parolanızı unuttuysanız site yöneticisinden yeni parola isteyin.</div>
                                <?php endif; ?>
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

                        <?php if (Registration::isOpen()): ?>
                            <p class="text-center cy-muted mt-3 mb-0">
                                Hesabınız yok mu? <a class="cy-link" href="<?= e(url('kayit')) ?>">Kayıt olun</a>
                            </p>
                        <?php endif; ?>

                        <?php /* DEMO HESAPLAR — tek tıkla giriş
                                 Satıra tıklamak alanları doldurur ve formu gönderir
                                 (login.js); giriş normal yoldan, CSRF ve kaba kuvvet
                                 korumasıyla yapılır. Liste YALNIZCA demo modunda
                                 (APP_DEMO=true) dolar; geliştirmede parolasız bir
                                 not çıkar. Bkz. App\Core\Demo::loginAccounts. */ ?>
                        <?php if ($demoAccounts !== []): ?>
                            <div class="cy-quick-login" data-demo-modu="<?= $demoMode ? '1' : '0' ?>">
                                <div class="cy-quick-login__divider"><span>Demo hesaplar · tek tıkla giriş</span></div>

                                <div class="cy-quick-login__list">
                                    <?php foreach ($demoAccounts as $account): ?>
                                        <?php $vurgu = ($vurgulanan ?? '') === $account['kullanici_adi']; ?>
                                        <button type="button"
                                                class="cy-quick-login__item js-quick-login<?= $vurgu ? ' is-highlight' : '' ?>"
                                                data-identifier="<?= e($account['kullanici_adi']) ?>"
                                                data-password="<?= e($account['parola']) ?>"
                                                aria-label="<?= e($account['etiket']) ?> hesabıyla giriş yap">
                                            <span class="cy-quick-login__badge is-<?= e($account['variant']) ?>">
                                                <?= icon($account['icon'], 'cy-icon cy-icon--sm') ?>
                                                <?= e($account['etiket']) ?>
                                            </span>
                                            <span class="cy-quick-login__text">
                                                <strong><?= e($account['ad'] . ' ' . $account['soyad']) ?></strong>
                                                <small>
                                                    <code><?= e($account['kullanici_adi']) ?></code>
                                                    · <code><?= e($account['parola']) ?></code>
                                                </small>
                                            </span>
                                            <?= icon('chevron', 'cy-icon cy-icon--sm cy-quick-login__arrow') ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <p class="cy-quick-login__hint">
                                    Bu hesaplar yalnızca örnek veridir; bir satıra tıklamanız giriş için yeterli.
                                    Demo hesaplarla hesap bilgileri, kullanıcılar, site ayarları ve e-posta
                                    gönderimi kilitlidir.
                                </p>
                            </div>
                        <?php elseif (!empty($ornekVeriNotu)): ?>
                            <?php /* Geliştirme ortamı + örnek veri: parola SAYFAYA YAZILMAZ. */ ?>
                            <p class="cy-quick-login__hint text-center mt-3 mb-0">
                                Örnek veri yüklü; demo hesaplar
                                <a class="cy-link" href="https://github.com/CilginYazilim/cy-php-starter#demo-hesaplar" target="_blank" rel="noopener">README'de</a>.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
