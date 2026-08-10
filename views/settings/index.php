<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Site Ayarları — genel bakış
 * ---------------------------------------------------------------------
 *  Her grup bir karttır. Kart, o grubun MEVCUT DURUMUNU da gösterir
 *  ("Kayıt modu — mektuplar gönderilmiyor" gibi); böylece kullanıcı
 *  altı sayfayı tek tek açmadan neyin eksik olduğunu görür.
 *
 *  @var array<int,array<string,mixed>> $kartlar
 *  @var int $toplam
 * =====================================================================
 */

$kartlar = $kartlar ?? [];
$uyarili = array_filter($kartlar, static fn (array $k): bool => (bool) $k['uyari']);
?>

<div class="cy-page-head">
    <div>
        <h2 class="cy-title">Site Ayarları</h2>
        <p class="cy-subtitle">
            <strong><?= (int) ($toplam ?? 0) ?></strong> ayar, <strong><?= count($kartlar) ?></strong> bölümde.
            Düzenlemek istediğiniz bölümü seçin.
        </p>
    </div>
</div>

<?php if ($uyarili !== []): ?>
    <div class="cy-alert cy-alert--warning mb-3">
        <strong><?= count($uyarili) ?> bölüm dikkatinizi bekliyor.</strong>
        Turuncu işaretli kartlara bakın — yayına çıkmadan önce
        tamamlanması gereken ayarlar var.
    </div>
<?php endif; ?>

<div class="cy-setting-grid">
    <?php foreach ($kartlar as $kart): ?>
        <a class="cy-setting-card<?= $kart['uyari'] ? ' is-warning' : '' ?>"
           href="<?= e(url('panel/ayarlar/' . $kart['anahtar'])) ?>">

            <span class="cy-setting-card__icon">
                <?= icon($kart['ikon']) ?>
            </span>

            <span class="cy-setting-card__body">
                <span class="cy-setting-card__title">
                    <?= e($kart['baslik']) ?>
                    <span class="cy-badge cy-badge--count"><?= (int) $kart['adet'] ?></span>
                </span>

                <span class="cy-setting-card__desc"><?= e($kart['aciklama']) ?></span>

                <?php if ($kart['ozet'] !== ''): ?>
                    <span class="cy-setting-card__state">
                        <?= icon($kart['uyari'] ? 'alert' : 'check', 'cy-icon cy-icon--sm') ?>
                        <?= e($kart['ozet']) ?>
                    </span>
                <?php endif; ?>
            </span>

            <span class="cy-setting-card__go"><?= icon('chevron', 'cy-icon cy-icon--sm') ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="cy-card mt-3">
    <div class="cy-card__body">
        <p class="cy-muted small mb-0">
            <?= icon('alert', 'cy-icon cy-icon--sm') ?>
            <strong>Yeni ayar eklemek:</strong> <code class="cy-mono">ayarlar</code> tablosuna bir satır
            eklemeniz yeterlidir — form otomatik üretilir, kodda değişiklik gerekmez.
            Ayrıntı için <code class="cy-mono">SISTEM.md</code> → “Yapılandırma ve ortam”.
        </p>
    </div>
</div>
