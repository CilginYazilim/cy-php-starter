<?php
/**
 * =====================================================================
 *  404 – SAYFA BULUNAMADI
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Kök dizindeki .htaccess dosyasındaki "ErrorDocument 404" satırı
 *  sayesinde, olmayan bir adres istendiğinde Apache bu sayfayı gösterir.
 *
 *  DOĞRU HTTP KODU ÖNEMLİDİR: Sayfa 200 ("başarılı") dönerse arama
 *  motorları bu hata sayfasını gerçek içerik sanıp dizine ekler.
 * =====================================================================
 */

declare(strict_types=1);

http_response_code(404);

$sayfaBaslik   = 'Sayfa bulunamadı';
$aktifSayfa    = '';
$sayfaAciklama = 'Aradığınız sayfa taşınmış veya hiç var olmamış olabilir.';

require __DIR__ . '/_ust.php';
?>

<section class="cy-section container">
    <div class="cy-card">
        <div class="cy-card__body text-center py-5">

            <div style="font-size:clamp(3rem,12vw,6rem); font-weight:800; line-height:1; color:var(--cy-brand-600);">
                404
            </div>

            <h1 class="h4 mt-3">Bu sayfayı bulamadık</h1>
            <p class="cy-muted mx-auto" style="max-width:46ch;">
                Adres yanlış yazılmış, sayfa taşınmış ya da silinmiş olabilir.
                Aşağıdaki bağlantılardan devam edebilirsiniz.
            </p>

            <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                <a href="index.php" class="btn cy-btn cy-btn--primary">Ana Sayfa</a>
                <a href="iletisim.php" class="btn btn-outline-secondary cy-btn">İletişim</a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/_alt.php'; ?>
