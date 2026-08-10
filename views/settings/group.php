<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Site Ayarları — tek grup
 * ---------------------------------------------------------------------
 *  Form "ayarlar" tablosundaki satırlardan OTOMATİK üretilir; her
 *  "tip" değeri farklı bir alana dönüşür.
 *
 *  SAYFA BAŞLIĞI ÜST ÇUBUKTA yazar; burada tekrar edilmez. Bölümler
 *  arası geçiş de sol menüdeki "Site Ayarları" alt menüsündedir —
 *  aynı gezinmeyi iki kez çizmek ekranın üstünü boşa harcıyordu.
 *
 *  @var string $grup, $baslik, $ikon
 *  @var array<int,array<string,mixed>> $rows
 *  @var array<string,string> $errors, $old
 * =====================================================================
 */

use App\Core\Setting;

$rows     = $rows ?? [];
$errors   = $errors ?? [];
$old      = $old ?? [];
?>

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

            <?php /* FAVICON: logodan ayrı bir dosyadır. Sekmede görünen
                     simge 16 pikseldir; yatay bir logo orada okunmaz. */ ?>
            <div class="cy-card mt-3">
                <div class="cy-card__header">
                    <h3 class="cy-section-title mb-0"><?= icon('star', 'cy-icon cy-icon--sm') ?> Site Favicon</h3>
                </div>
                <div class="cy-card__body">
                    <div class="text-center mb-3">
                        <span class="cy-favicon-preview">
                            <img src="<?= e(Setting::faviconUrl()) ?>" alt="Mevcut favicon">
                        </span>
                    </div>

                    <form method="post" action="<?= e(url('panel/ayarlar/favicon')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="file" name="favicon" class="form-control form-control-sm mb-2"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block cy-btn--sm">
                            <?= icon('upload', 'cy-icon cy-icon--sm') ?> Faviconu Güncelle
                        </button>
                    </form>

                    <?php if (Setting::get('site_favicon') !== ''): ?>
                        <form method="post" action="<?= e(url('panel/ayarlar/favicon-sil')) ?>" class="mt-2"
                              data-confirm="Favicon kaldırılacak, varsayılan simge kullanılacak. Emin misiniz?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--block cy-btn--sm">
                                <?= icon('trash', 'cy-icon cy-icon--sm') ?> Faviconu Kaldır
                            </button>
                        </form>
                    <?php endif; ?>

                    <p class="cy-muted small mt-3 mb-0">
                        Kare bir görsel yükleyin; merkezden kırpılır ve 256 piksele indirilir.
                        Kurulumla birlikte varsayılan bir favicon zaten tanımlıdır.
                        Değişiklikten sonra tarayıcı eski simgeyi bir süre önbellekte tutabilir.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($grup === 'iletisim'): ?>
            <?php
            $whatsapp = App\Http\Controllers\Site\HomeController::whatsappLink();
            ?>
            <div class="cy-card">
                <div class="cy-card__header">
                    <h3 class="cy-section-title mb-0"><?= icon('whatsapp', 'cy-icon cy-icon--sm') ?> WhatsApp Önizleme</h3>
                </div>
                <div class="cy-card__body">
                    <?php if ($whatsapp === ''): ?>
                        <p class="cy-muted small mb-0">
                            Numara girip kaydettiğinizde ön yüzde alt bilgide, iletişim
                            sayfasında ve sağ alt köşede bir WhatsApp düğmesi belirir.
                        </p>
                    <?php else: ?>
                        <p class="cy-muted small mb-2">
                            Ziyaretçi düğmeye bastığında aşağıdaki adres açılır ve hazır
                            mesaj sohbet kutusuna yazılmış gelir.
                        </p>
                        <p class="cy-mono small text-break mb-3"><?= e($whatsapp) ?></p>
                        <a class="btn cy-btn cy-btn--whatsapp cy-btn--block cy-btn--sm"
                           href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">
                            <?= icon('whatsapp', 'cy-icon cy-icon--sm') ?> Şimdi Dene
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cy-card mt-3">
                <div class="cy-card__header">
                    <h3 class="cy-section-title mb-0"><?= icon('inbox', 'cy-icon cy-icon--sm') ?> Mesajlar Nereye Gidiyor?</h3>
                </div>
                <div class="cy-card__body">
                    <p class="cy-muted small mb-2">
                        İletişim formundan gelen her mesaj <strong>her koşulda</strong>
                        veritabanına yazılır ve <a class="cy-link" href="<?= e(url('panel/mesajlar')) ?>">Mesajlar</a>
                        ekranında görünür.
                    </p>
                    <?php if (Setting::get('mail_surucu', 'kayit') === 'kayit'): ?>
                        <p class="cy-alert cy-alert--warning small mb-0">
                            <?= icon('alert', 'cy-icon cy-icon--sm') ?>
                            E-posta yöntemi hâlâ “kayıt” modunda: bildirim mektubu
                            <u>kimseye ulaşmıyor</u>.
                            <a class="cy-link" href="<?= e(url('panel/ayarlar/eposta')) ?>">SMTP ayarlarını yapın</a>.
                        </p>
                    <?php else: ?>
                        <p class="cy-muted small mb-0">
                            Bildirim adresi: <strong><?= e(Setting::get('iletisim_eposta', 'tanımlı değil')) ?></strong>
                        </p>
                    <?php endif; ?>
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
            <?php
            $indeksle = Setting::bool('seo_indeksleme', true);
            $sitemap  = Setting::bool('seo_sitemap_aktif', true);

            /* Site haritasına giren sayfa sayısı: yönetici "yeni sayfam
             * haritada var mı?" sorusunu buradan tek bakışta yanıtlar. */
            $sayfaAdedi = 0;

            try {
                $sayfaAdedi = count((new App\Repositories\PageRepository(App\Core\Database::connection()))->sitemap());
            } catch (\Throwable) {
                $sayfaAdedi = 0;
            }
            ?>
            <div class="cy-card">
                <div class="cy-card__header">
                    <h3 class="cy-section-title mb-0"><?= icon('search', 'cy-icon cy-icon--sm') ?> Arama Motorları</h3>
                </div>
                <div class="cy-card__body">
                    <?php if (!$indeksle): ?>
                        <div class="cy-alert cy-alert--warning small mb-3">
                            <?= icon('alert', 'cy-icon cy-icon--sm') ?>
                            <strong>Site aramaya kapalı.</strong> Her sayfaya
                            <code class="cy-mono">noindex</code> ekleniyor ve robots.txt
                            tüm siteyi engelliyor. Yayına çıkarken bu ayarı açmayı unutmayın.
                        </div>
                    <?php endif; ?>

                    <dl class="cy-detail cy-detail--compact mb-3">
                        <dt>Site haritası</dt>
                        <dd>
                            <?php if ($sitemap && $indeksle): ?>
                                <a href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener">sitemap.xml</a>
                                <span class="cy-muted small d-block"><?= (int) $sayfaAdedi ?> içerik sayfası + ana sayfa</span>
                            <?php else: ?>
                                <span class="cy-muted">kapalı</span>
                            <?php endif; ?>
                        </dd>

                        <dt>robots.txt</dt>
                        <dd><a href="<?= e(url('robots.txt')) ?>" target="_blank" rel="noopener">robots.txt</a></dd>
                    </dl>

                    <p class="cy-muted small mb-0">
                        Her iki dosya da <strong>anlık üretilir</strong>: yeni bir sayfa
                        yayınladığınızda site haritasına kendiliğinden girer, taslağa
                        aldığınızda düşer. Sunucuda elle dosya oluşturmanız gerekmez —
                        varsa silin, yoksa <code class="cy-mono">.htaccess</code> gerçek
                        dosyayı öncelikli sayar ve buradaki ayarlar hiç okunmaz.
                    </p>
                </div>
            </div>

            <div class="cy-card mt-3">
                <div class="cy-card__header">
                    <h3 class="cy-section-title mb-0"><?= icon('external', 'cy-icon cy-icon--sm') ?> Paylaşım Önizlemesi</h3>
                </div>
                <div class="cy-card__body">
                    <p class="cy-muted small mb-2">
                        Bağlantı WhatsApp, X ya da LinkedIn'de paylaşıldığında böyle görünür.
                    </p>

                    <div class="cy-share-preview">
                        <img src="<?= e(Setting::shareImage()) ?>" alt="">
                        <div>
                            <strong><?= e(Setting::get('site_adi', '')) ?></strong>
                            <span><?= e(mb_strimwidth(Setting::get('site_aciklama'), 0, 110, '…', 'UTF-8')) ?></span>
                            <small><?= e(parse_url(App\Core\Url::origin(), PHP_URL_HOST) ?: 'localhost') ?></small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
