<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Genel hata sayfası (405, 429, 503 …)
 * ---------------------------------------------------------------------
 *  Eskiden kendine ait görünümü olmayan her durum "500 – Bir şeyler
 *  ters gitti" şablonuna düşüyordu: hız sınırına takılan ziyaretçi
 *  ya da bakım modundaki siteyi açan kişi "sunucu hatası" görüyordu.
 *  Bu şablon durum koduna göre doğru başlığı ve öneriyi gösterir.
 *
 *  @var int    $status
 *  @var string $title
 *  @var string $message
 * =====================================================================
 */

$status  = (int) ($status ?? 500);
$message = (string) ($message ?? '');

$baslik = match ($status) {
    405     => 'Bu adres bu şekilde açılamaz',
    429     => 'Biraz yavaşlayalım',
    503     => 'Kısa bir bakım çalışması yapıyoruz',
    default => (string) ($title ?? 'İstek tamamlanamadı'),
};
?>
<div class="cy-error">
    <div>
        <div class="cy-error__code"><?= $status ?></div>
        <h1 class="cy-error__title"><?= e($baslik) ?></h1>

        <?php if ($message !== ''): ?>
            <p class="cy-error__text"><?= e($message) ?></p>
        <?php endif; ?>

        <?php if ($status === 429): ?>
            <p class="cy-error__text">Birkaç dakika bekleyip tekrar deneyin.</p>
        <?php elseif ($status === 503): ?>
            <p class="cy-error__text">Lütfen biraz sonra tekrar uğrayın.</p>
        <?php endif; ?>

        <a class="btn cy-btn cy-btn--primary" href="<?= e(url('')) ?>">Ana Sayfaya Dön</a>
    </div>
</div>
