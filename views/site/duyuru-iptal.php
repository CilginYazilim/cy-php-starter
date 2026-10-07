<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Duyurulardan çıkış (mektuptaki imzalı bağlantı)
 * ---------------------------------------------------------------------
 *  GET onay sayfasını gösterir; tercih yalnızca düğmeyle (POST) değişir.
 *  Bkz. Site\NotificationController.
 *
 *  @var string $durum  onay | zaten | kapatildi | gecersiz | hata
 *  @var string $eylem  imzalı adres (formun gönderileceği yer)
 * =====================================================================
 */

$durum = $durum ?? 'gecersiz';
$eylem = $eylem ?? '';
$hesap = url('panel/hesabim') . '#bildirimler';
?>

<section class="cy-auth-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-9 col-md-7 col-lg-5">
                <div class="cy-card cy-auth-card">
                    <div class="cy-card__body p-4 text-center">
                        <?php if ($durum === 'onay'): ?>
                            <span class="cy-empty__icon"><?= icon('mail') ?></span>
                            <h1 class="cy-title mt-3 mb-2">Duyuruları kapatın</h1>
                            <p class="cy-subtitle mb-3">
                                Toplu duyuruları artık almak istemiyorsanız aşağıdaki düğmeye basın. Parola ve
                                e-posta değişikliği gibi güvenlik bildirimleri gelmeye devam eder.
                            </p>
                            <?php /* CSRF jetonu YOK: yetki adresteki imzadır (posta
                                     istemcisinin tek tık isteği de jeton taşıyamaz). */ ?>
                            <form method="post" action="<?= e($eylem) ?>">
                                <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block">Duyuruları kapat</button>
                            </form>
                            <p class="cy-muted small mt-3 mb-0">Vazgeçtiyseniz bu sayfayı kapatmanız yeterli.</p>
                        <?php elseif ($durum === 'kapatildi' || $durum === 'zaten'): ?>
                            <span class="cy-empty__icon"><?= icon('check') ?></span>
                            <h1 class="cy-title mt-3 mb-2"><?= $durum === 'zaten' ? 'Duyurular zaten kapalı' : 'Duyurular kapatıldı' ?></h1>
                            <p class="cy-subtitle mb-3">
                                Toplu duyuruları almayacaksınız. Parola ve e-posta değişikliği gibi
                                güvenlik bildirimleri gelmeye devam eder.
                            </p>
                            <p class="cy-muted small mb-0">
                                Fikrinizi değiştirirseniz giriş yapıp
                                <a class="cy-link" href="<?= e($hesap) ?>">Hesabım → E-posta Bildirimleri</a>'nden
                                yeniden açabilirsiniz.
                            </p>
                        <?php elseif ($durum === 'hata'): ?>
                            <span class="cy-empty__icon"><?= icon('alert') ?></span>
                            <h1 class="cy-title mt-3 mb-2">Şu anda kaydedilemedi</h1>
                            <p class="cy-subtitle mb-0">Lütfen biraz sonra yeniden deneyin ya da Hesabım ekranından kapatın.</p>
                        <?php else: ?>
                            <span class="cy-empty__icon"><?= icon('alert') ?></span>
                            <h1 class="cy-title mt-3 mb-2">Bağlantı geçersiz</h1>
                            <p class="cy-subtitle mb-0">
                                Bağlantı eksik kopyalanmış ya da hesap artık yok. Duyuruları giriş yapıp
                                <a class="cy-link" href="<?= e($hesap) ?>">Hesabım</a> ekranından kapatabilirsiniz.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
