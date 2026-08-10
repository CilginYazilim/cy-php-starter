<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Erişim engellendi
 * ---------------------------------------------------------------------
 *  Üç durumu birden karşılar; hangisi olduğunu $status söyler:
 *    401 → oturum yok / düşmüş
 *    403 → oturum var ama yetki yok
 *    419 → güvenlik anahtarı (CSRF) geçersiz, sayfa bayatlamış
 *
 *  Mesaj ErrorHandler tarafından verilir ve KULLANICIYA GÖSTERİLMESİ
 *  güvenlidir (bkz. HttpException sınıf açıklaması).
 *
 *  @var int    $status
 *  @var string $message
 * =====================================================================
 */

$status  = (int) ($status ?? 403);
$message = (string) ($message ?? 'Bu işlem için yetkiniz bulunmuyor.');

/* 401 ve 419'da yapılacak şey bellidir: yeniden giriş yapmak.
   403'te ise giriş yapmak işe yaramaz — yetki eksiktir. */
$girisOnerilsin = in_array($status, [401, 419], true);
?>
<div class="cy-error">
    <div>
        <div class="cy-error__code"><?= (int) $status ?></div>

        <h1 class="cy-error__title">
            <?= $status === 419 ? 'Oturum süresi doldu' : 'Bu sayfaya erişim yetkiniz yok' ?>
        </h1>

        <p class="cy-error__text"><?= e($message) ?></p>

        <?php if ($girisOnerilsin): ?>
            <a class="btn cy-btn cy-btn--primary" href="<?= e(url('giris')) ?>">
                <?= icon('lock', 'cy-icon cy-icon--sm') ?> Giriş Yap
            </a>
            <a class="btn cy-btn cy-btn--ghost" href="<?= e(url('')) ?>">Ana Sayfa</a>
        <?php else: ?>
            <a class="btn cy-btn cy-btn--primary" href="<?= e(url('')) ?>">Ana Sayfaya Dön</a>
        <?php endif; ?>
    </div>
</div>
