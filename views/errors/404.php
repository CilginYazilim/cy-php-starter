<?php /** GÖRÜNÜM: 404 – Sayfa bulunamadı */ ?>
<div class="cy-error">
    <div>
        <div class="cy-error__code">404</div>
        <h1 class="cy-error__title">Sayfa bulunamadı</h1>
        <p class="cy-error__text">
            Aradığınız sayfa taşınmış veya hiç var olmamış olabilir.
            <?php if (!empty($path)): ?><br><code class="cy-mono"><?= e($path) ?></code><?php endif; ?>
        </p>
        <a class="btn cy-btn cy-btn--primary" href="<?= e(url('')) ?>"><?= icon('globe', 'cy-icon cy-icon--sm') ?> Ana Sayfaya Dön</a>
    </div>
</div>
