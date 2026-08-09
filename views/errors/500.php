<?php /** GÖRÜNÜM: 500 – Sunucu hatası */ ?>
<div class="cy-error">
    <div>
        <div class="cy-error__code">500</div>
        <h1 class="cy-error__title">Bir şeyler ters gitti</h1>
        <p class="cy-error__text"><?= e($message ?? 'Beklenmeyen bir hata oluştu.') ?></p>
        <a class="btn cy-btn cy-btn--primary" href="<?= e(url('')) ?>">Yeniden dene</a>
    </div>
</div>
