<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Duyurulardan çıkış (mektuptaki imzalı bağlantı)
 * ---------------------------------------------------------------------
 *  @var bool $kapatildi
 *  @var bool $gecersiz   İmza tutmadı ya da hesap yok
 * =====================================================================
 */

$kapatildi = $kapatildi ?? false;
$gecersiz  = $gecersiz ?? true;
?>

<section class="cy-auth-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-9 col-md-7 col-lg-5">
                <div class="cy-card cy-auth-card">
                    <div class="cy-card__body p-4 text-center">
                        <?php if ($kapatildi): ?>
                            <span class="cy-empty__icon"><?= icon('check') ?></span>
                            <h1 class="cy-title mt-3 mb-2">Duyurular kapatıldı</h1>
                            <p class="cy-subtitle mb-3">
                                Toplu duyuruları artık almayacaksınız. Parola ve e-posta değişikliği gibi
                                güvenlik bildirimleri gelmeye devam eder.
                            </p>
                            <p class="cy-muted small mb-0">
                                Fikrinizi değiştirirseniz giriş yapıp
                                <a class="cy-link" href="<?= e(url('panel/hesabim') . '#bildirimler') ?>">Hesabım → E-posta Bildirimleri</a>'nden
                                yeniden açabilirsiniz.
                            </p>
                        <?php elseif ($gecersiz): ?>
                            <span class="cy-empty__icon"><?= icon('alert') ?></span>
                            <h1 class="cy-title mt-3 mb-2">Bağlantı geçersiz</h1>
                            <p class="cy-subtitle mb-0">
                                Bağlantı eksik kopyalanmış ya da hesap artık yok. Duyuruları giriş yapıp
                                <a class="cy-link" href="<?= e(url('panel/hesabim') . '#bildirimler') ?>">Hesabım</a> ekranından kapatabilirsiniz.
                            </p>
                        <?php else: ?>
                            <span class="cy-empty__icon"><?= icon('alert') ?></span>
                            <h1 class="cy-title mt-3 mb-2">Şu anda kaydedilemedi</h1>
                            <p class="cy-subtitle mb-0">Lütfen biraz sonra yeniden deneyin ya da Hesabım ekranından kapatın.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
