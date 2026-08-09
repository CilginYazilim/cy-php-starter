<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Ana sayfa
 * =====================================================================
 */

use App\Core\Setting;

$siteAdi = Setting::get('site_adi', $appName ?? 'Yeni Proje');
?>

<section class="cy-hero">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-12 col-lg-7">
                <span class="cy-badge cy-badge--brand mb-3"><?= icon('shield', 'cy-icon cy-icon--sm') ?> Güvenli · OOP · Sıfır bağımlılık</span>
                <h1 class="cy-hero__title"><?= e($siteAdi) ?></h1>
                <p class="cy-hero__lead">
                    <?= e(Setting::get('site_slogan') !== '' ? Setting::get('site_slogan') : Setting::get('site_aciklama')) ?>
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a class="btn cy-btn cy-btn--primary" href="<?= e(url('iletisim')) ?>">
                        <?= icon('mail', 'cy-icon cy-icon--sm') ?> Bize Ulaşın
                    </a>
                    <a class="btn cy-btn cy-btn--ghost" href="<?= e(url('hakkimizda')) ?>">Hakkımızda</a>
                </div>
            </div>
            <div class="col-12 col-lg-5 text-center">
                <img src="<?= e(Setting::logoUrl()) ?>" alt="" class="cy-hero__logo">
            </div>
        </div>
    </div>
</section>

<section class="cy-section container pb-5">
    <h2 class="cy-section__title text-center">Sıfırdan yazmanıza gerek kalmayan altyapı</h2>
    <p class="cy-section__lead text-center mx-auto">
        Kimlik doğrulama, rol tabanlı yetkilendirme, tipli ayar sistemi ve güvenlik önlemleri hazır.
    </p>

    <div class="row g-3 mt-3">
        <div class="col-12 col-md-4">
            <div class="cy-card h-100 p-4">
                <span class="cy-stat__icon cy-stat__icon--brand mb-2"><?= icon('shield') ?></span>
                <h3 class="cy-section-title">Güvenlik</h3>
                <p class="cy-muted mb-0">CSRF, XSS, SQL Injection ve kaba kuvvet korumaları kutudan çıktığı gibi aktif.</p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="cy-card h-100 p-4">
                <span class="cy-stat__icon cy-stat__icon--success mb-2"><?= icon('users') ?></span>
                <h3 class="cy-section-title">Roller</h3>
                <p class="cy-muted mb-0">Yönetici, editör ve üye rolleri; yetkiler tek dosyadan yönetilir.</p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="cy-card h-100 p-4">
                <span class="cy-stat__icon cy-stat__icon--warning mb-2"><?= icon('settings') ?></span>
                <h3 class="cy-section-title">Tipli Ayarlar</h3>
                <p class="cy-muted mb-0">Yeni bir ayar eklemek, veritabanına tek satır eklemek kadar kolay.</p>
            </div>
        </div>
    </div>
</section>
