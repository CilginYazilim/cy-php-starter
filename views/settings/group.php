<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Site Ayarları — tek grup
 * ---------------------------------------------------------------------
 *  Form "ayarlar" tablosundaki satırlardan OTOMATİK üretilir; her
 *  "tip" değeri farklı bir alana dönüşür.
 *
 *  @var string $grup, $baslik, $ikon
 *  @var array<int,array<string,mixed>> $rows
 *  @var array<int,array{anahtar:string,baslik:string,ikon:string,aktif:bool}> $komsular
 *  @var array<string,string> $errors, $old
 * =====================================================================
 */

use App\Core\Setting;

$rows     = $rows ?? [];
$errors   = $errors ?? [];
$old      = $old ?? [];
$komsular = $komsular ?? [];
?>

<div class="cy-page-head">
    <div>
        <nav class="cy-breadcrumb" aria-label="Konum">
            <a href="<?= e(url('panel/ayarlar')) ?>">Site Ayarları</a>
            <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
            <span><?= e($baslik) ?></span>
        </nav>

        <h2 class="cy-title"><?= icon($ikon, 'cy-icon') ?> <?= e($baslik) ?></h2>
        <p class="cy-subtitle"><?= e($subtitle ?? '') ?></p>
    </div>

    <div class="cy-page-head__actions">
        <a class="btn cy-btn cy-btn--ghost cy-btn--sm" href="<?= e(url('panel/ayarlar')) ?>">
            <?= icon('chevron', 'cy-icon cy-icon--sm cy-flip') ?> Tüm Bölümler
        </a>
    </div>
</div>

<?php /* Bölümler arası hızlı geçiş — sayfadan çıkmadan gezinme. */ ?>
<div class="cy-groupnav" role="tablist" aria-label="Ayar bölümleri">
    <?php foreach ($komsular as $komsu): ?>
        <a class="cy-groupnav__item<?= $komsu['aktif'] ? ' is-active' : '' ?>"
           href="<?= e(url('panel/ayarlar/' . $komsu['anahtar'])) ?>"
           <?= $komsu['aktif'] ? 'aria-current="page"' : '' ?>>
            <?= icon($komsu['ikon'], 'cy-icon cy-icon--sm') ?>
            <span><?= e($komsu['baslik']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($errors !== []): ?>
    <div class="cy-alert cy-alert--danger mb-3">
        Lütfen işaretli alanları düzeltin. Hiçbir ayar kaydedilmedi.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form method="post" action="<?= e(url('panel/ayarlar/' . $grup)) ?>" novalidate id="ayarlar_formu">
            <?= csrf_field() ?>

            <div class="cy-card">
                <div class="cy-card__body">
                    <div class="row g-3">
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $key       = (string) $row['anahtar'];
                            $type      = (string) $row['tip'];
                            $editable  = (int) $row['duzenlenebilir'] === 1;
                            $width     = in_array($type, ['uzun_metin'], true) ? 'col-12' : 'col-12 col-md-6';
                            $hasError  = array_key_exists($key, $errors);
                            $errorCls  = $hasError ? ' is-invalid' : '';

                            // Doğrulama başarısız olup geri döndüğümüzde kullanıcının
                            // YAZDIĞI değeri göster, kayıtlıyı değil.
                            $value = array_key_exists($key, $old) ? (string) $old[$key] : Setting::get($key);
                            ?>
                            <div class="<?= $width ?> cy-ayar"
                                 data-ayar="<?= e(mb_strtolower($row['etiket'] . ' ' . $key . ' ' . ($row['aciklama'] ?? ''), 'UTF-8')) ?>">

                                <label class="form-label" for="set_<?= e($key) ?>"><?= e($row['etiket']) ?></label>

                                <?php if (!$editable): ?>
                                    <input type="text" class="form-control" value="<?= e(Setting::get($key)) ?>" disabled>

                                <?php elseif ($type === 'onay'): ?>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               name="<?= e($key) ?>" id="set_<?= e($key) ?>" value="1"
                                               <?= $value === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="set_<?= e($key) ?>">Etkin</label>
                                    </div>

                                <?php elseif ($type === 'uzun_metin'): ?>
                                    <textarea class="form-control<?= $errorCls ?>" name="<?= e($key) ?>"
                                              id="set_<?= e($key) ?>" rows="4"><?= e($value) ?></textarea>

                                <?php elseif ($type === 'secim'): ?>
                                    <?php $options = json_decode((string) ($row['secenekler'] ?? '[]'), true) ?: []; ?>
                                    <select class="form-select" name="<?= e($key) ?>" id="set_<?= e($key) ?>">
                                        <?php foreach ($options as $option): ?>
                                            <option value="<?= e($option) ?>" <?= $value === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($type === 'sifre'): ?>
                                    <?php /* Parola ekranda GÖSTERİLMEZ. Boş bırakılırsa
                                            kayıtlı değer olduğu gibi korunur. */ ?>
                                    <div class="cy-password">
                                        <input type="password" class="form-control<?= $errorCls ?>" name="<?= e($key) ?>"
                                               id="set_<?= e($key) ?>" value="" autocomplete="new-password"
                                               placeholder="<?= Setting::get($key) !== '' ? '•••••••• (kayıtlı — değiştirmek için yazın)' : 'Tanımlı değil' ?>">
                                        <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster">
                                            <?= icon('eye', 'cy-icon cy-icon--sm') ?>
                                        </button>
                                    </div>

                                <?php elseif ($type === 'renk'): ?>
                                    <input type="color" class="form-control form-control-color"
                                           name="<?= e($key) ?>" id="set_<?= e($key) ?>"
                                           value="<?= e($value !== '' ? $value : '#0b5cb5') ?>">

                                <?php else: ?>
                                    <input type="<?= $type === 'sayi' ? 'number' : ($type === 'eposta' ? 'email' : ($type === 'url' ? 'url' : 'text')) ?>"
                                           class="form-control<?= $errorCls ?>" name="<?= e($key) ?>"
                                           id="set_<?= e($key) ?>" value="<?= e($value) ?>">
                                <?php endif; ?>

                                <?php if ($hasError): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors[$key]) ?></div>
                                <?php elseif (!empty($row['aciklama'])): ?>
                                    <div class="form-text"><?= e($row['aciklama']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php /* Yapışkan: uzun formda sayfanın sonuna inmeden kaydedilebilsin. */ ?>
                <div class="cy-card__footer cy-save-bar d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span class="cy-muted small cy-hide-xs">
                        Yalnızca <strong><?= e($baslik) ?></strong> bölümü kaydedilir.
                    </span>
                    <button type="submit" class="btn cy-btn cy-btn--primary">
                        <?= icon('save', 'cy-icon cy-icon--sm') ?> Kaydet
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-4">
        <?php /* Yan panel yalnızca ilgili bölümde görünür: logo "genel"de,
                e-posta sınaması "eposta"da. Her sayfada her şeyi göstermek
                kullanıcıyı arattırır. */ ?>

        <?php if ($grup === 'genel'): ?>
            <div class="cy-card">
                <div class="cy-card__header">
                    <h3 class="cy-section-title mb-0"><?= icon('upload', 'cy-icon cy-icon--sm') ?> Site Logosu</h3>
                </div>
                <div class="cy-card__body">
                    <div class="text-center mb-3">
                        <img src="<?= e(Setting::logoUrl()) ?>" alt="Mevcut logo" class="cy-avatar cy-avatar--lg">
                    </div>

                    <form method="post" action="<?= e(url('panel/ayarlar/logo')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="file" name="logo" class="form-control form-control-sm mb-2"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block cy-btn--sm">
                            <?= icon('upload', 'cy-icon cy-icon--sm') ?> Logoyu Güncelle
                        </button>
                    </form>

                    <?php if (Setting::get('site_logo') !== ''): ?>
                        <form method="post" action="<?= e(url('panel/ayarlar/logo-sil')) ?>" class="mt-2"
                              data-confirm="Logo kaldırılacak, varsayılan logo kullanılacak. Emin misiniz?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--block cy-btn--sm">
                                <?= icon('trash', 'cy-icon cy-icon--sm') ?> Logoyu Kaldır
                            </button>
                        </form>
                    <?php endif; ?>

                    <p class="cy-muted small mt-3 mb-0">
                        Görsel sunucuda yeniden üretilir: EXIF verisi ve gömülü içerik temizlenir.
                        En fazla <?= e(App\Core\Storage\Storage::humanSize((int) config('upload.max_bytes'))) ?>.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($grup === 'eposta' && can('settings.manage')): ?>
            <?php
            $surucu   = Setting::get('mail_surucu', 'kayit');
            $surucuAd = ['kayit' => 'Kayıt (diske yazar)', 'smtp' => 'SMTP', 'php' => 'PHP mail()'][$surucu] ?? $surucu;
            ?>
            <div class="cy-card" id="mail_test_card">
                <div class="cy-card__header">
                    <h3 class="cy-section-title mb-0"><?= icon('send', 'cy-icon cy-icon--sm') ?> E-posta Sınama</h3>
                </div>
                <div class="cy-card__body">
                    <p class="cy-muted small mb-2">
                        Şu anki yöntem: <strong><?= e($surucuAd) ?></strong>.
                        <?php if ($surucu === 'kayit'): ?>
                            Mektuplar gönderilmez, <code class="cy-mono">storage/mail/</code> klasörüne
                            <code class="cy-mono">.eml</code> olarak yazılır.
                        <?php endif; ?>
                    </p>
                    <p class="cy-muted small mb-3">
                        <strong>Önce ayarları kaydedin</strong>, sonra sınayın — sınama kayıtlı ayarlarla çalışır.
                    </p>

                    <label class="form-label" for="mail_test_adres">Sınama adresi</label>
                    <input type="email" class="form-control form-control-sm mb-2" id="mail_test_adres"
                           value="<?= e(auth()?->eposta ?? '') ?>" placeholder="ornek@site.com">

                    <div class="d-grid gap-2">
                        <button type="button" class="btn cy-btn cy-btn--primary cy-btn--sm" id="mail_test_gonder">
                            <span class="spinner-border spinner-border-sm me-1 d-none" id="mail_test_spinner"></span>
                            <?= icon('send', 'cy-icon cy-icon--sm') ?> Sınama E-postası Gönder
                        </button>
                        <?php if ($surucu === 'smtp'): ?>
                            <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm" id="mail_test_baglanti">
                                <?= icon('link', 'cy-icon cy-icon--sm') ?> Yalnızca Bağlantıyı Sına
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="cy-alert cy-alert--danger d-none mt-3 small" id="mail_test_sonuc" role="alert"></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($grup === 'sistem'): ?>
            <div class="cy-card">
                <div class="cy-card__header">
                    <h3 class="cy-section-title mb-0"><?= icon('shield', 'cy-icon cy-icon--sm') ?> Dikkat</h3>
                </div>
                <div class="cy-card__body">
                    <p class="cy-muted small mb-2">
                        <strong>Bakım modu</strong> açıkken siteyi yalnızca panele giriş yetkisi olanlar görür.
                    </p>
                    <p class="cy-muted small mb-0">
                        <strong>PWA</strong> açıkken ziyaretçiler siteyi telefonlarına uygulama gibi kurabilir.
                        Değişikliğin görünmesi için tarayıcı önbelleğinin tazelenmesi gerekebilir.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($grup === 'seo'): ?>
            <div class="cy-card">
                <div class="cy-card__header">
                    <h3 class="cy-section-title mb-0"><?= icon('search', 'cy-icon cy-icon--sm') ?> Arama Motorları</h3>
                </div>
                <div class="cy-card__body">
                    <dl class="cy-detail cy-detail--compact mb-0">
                        <dt>Site haritası</dt>
                        <dd><a href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener">sitemap.xml</a></dd>
                        <dt>robots.txt</dt>
                        <dd><a href="<?= e(url('robots.txt')) ?>" target="_blank" rel="noopener">robots.txt</a></dd>
                    </dl>
                    <p class="cy-muted small mt-3 mb-0">
                        İndeksleme kapalıyken her iki dosya da arama motorlarına
                        “taramayın” der ve sayfalara <code class="cy-mono">noindex</code> eklenir.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
