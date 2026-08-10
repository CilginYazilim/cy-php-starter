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

<?php /* SAYFA BAŞLIĞI ÜST ÇUBUKTA yazar; burada tekrar edilmez.
         Yerine, açmadan önce bilinmesi gereken üç sayı duruyor. */ ?>
<div class="cy-stats">
    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--brand"><?= icon('settings') ?></span>
        <span>
            <span class="cy-stat__label">Toplam Ayar</span>
            <span class="cy-stat__value"><?= (int) ($toplam ?? 0) ?></span>
            <span class="cy-stat__hint"><?= count($kartlar) ?> bölümde</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--<?= $uyarili === [] ? 'success' : 'warning' ?>">
            <?= icon($uyarili === [] ? 'check' : 'alert') ?>
        </span>
        <span>
            <span class="cy-stat__label">Bekleyen Konu</span>
            <span class="cy-stat__value"><?= count($uyarili) ?></span>
            <span class="cy-stat__hint"><?= $uyarili === [] ? 'her şey tamam' : 'turuncu kartlara bakın' ?></span>
        </span>
    </div>

    <div class="cy-stat">
        <?php $smtpTamam = App\Core\Setting::get('mail_surucu', 'kayit') !== 'kayit'; ?>
        <span class="cy-stat__icon cy-stat__icon--<?= $smtpTamam ? 'success' : 'danger' ?>"><?= icon('send') ?></span>
        <span>
            <span class="cy-stat__label">E-posta</span>
            <span class="cy-stat__value" style="font-size:1.05rem"><?= $smtpTamam ? 'Gönderiyor' : 'Kapalı' ?></span>
            <span class="cy-stat__hint">
                <?= $smtpTamam ? e(App\Core\Setting::get('mail_host', 'PHP mail()')) : 'SMTP tanımlı değil' ?>
            </span>
        </span>
    </div>

    <div class="cy-stat">
        <?php $yayinda = !App\Core\Setting::bool('sistem_bakim_modu', false); ?>
        <span class="cy-stat__icon cy-stat__icon--<?= $yayinda ? 'success' : 'warning' ?>"><?= icon('globe') ?></span>
        <span>
            <span class="cy-stat__label">Site Durumu</span>
            <span class="cy-stat__value" style="font-size:1.05rem"><?= $yayinda ? 'Yayında' : 'Bakımda' ?></span>
            <span class="cy-stat__hint">
                <?= App\Core\Setting::bool('seo_indeksleme', true) ? 'aramaya açık' : 'aramaya kapalı' ?>
            </span>
        </span>
    </div>
</div>

<?php if ($uyarili !== []): ?>
    <div class="cy-alert cy-alert--warning mb-3">
        <strong><?= count($uyarili) ?> bölüm dikkatinizi bekliyor:</strong>
        <?= e(implode(', ', array_map(static fn (array $k): string => $k['baslik'], $uyarili))) ?>.
        Turuncu işaretli kartların altındaki cümle sorunun ne olduğunu da yazıyor.
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
