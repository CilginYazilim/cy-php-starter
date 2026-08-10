<?php /** GÖRÜNÜM: Çevrimdışı — servis çalışanı ağ yokken bunu gösterir. */ ?>
<div class="cy-error">
    <div>
        <div class="cy-error__code"><?= icon('globe', 'cy-icon cy-icon--lg') ?></div>
        <h1 class="cy-error__title">İnternet bağlantısı yok</h1>
        <p class="cy-error__text">
            Sayfayı görüntülemek için bağlantınızı kontrol edip tekrar deneyin.
            Daha önce açtığınız bazı sayfalar çevrimdışı da çalışabilir.
        </p>
        <a class="btn cy-btn cy-btn--primary" href="<?= e(url('')) ?>">Yeniden dene</a>
    </div>
</div>
