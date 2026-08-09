<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – SİTE AYARLARI
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  BU SAYFANIN ÖZELLİĞİ: Formdaki hiçbir alan elle yazılmamıştır.
 *  Tüm alanlar "ayarlar" tablosundaki satırlardan OTOMATİK üretilir.
 *
 *  Yeni bir ayar eklemek için tek yapmanız gereken, tabloya bir satır
 *  eklemektir:
 *
 *    INSERT INTO ayarlar (anahtar, deger, grup, tip, etiket, sira)
 *    VALUES ('site_favicon', '', 'genel', 'metin', 'Favicon', 70);
 *
 *  Bu sayfaya veya HTML'e dokunmanız gerekmez — alan kendiliğinden
 *  doğru tipte belirir.
 *
 *  NEDEN _ust.php'DEN ÖNCE KAYDEDİYORUZ?
 *  Kaydetme işini şablondan sonra yapsaydık, sayfanın üst kısmı
 *  (site adı, logo, tema rengi) ESKİ değerlerle çizilmiş olurdu.
 *  Önce kaydedip sonra çizince yönetici değişikliği anında görür.
 * =====================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/system/config.php';
require_once dirname(__DIR__) . '/system/panel.php';

// Ayarları yalnızca yöneticiler değiştirebilir.
require_role('admin', '../giris.php');

$bildirim = null;

/* ---------------------------------------------------------------------
 *  KAYDETME
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $bildirim = ['danger', 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'];
    } else {
        /* GÜVENLİK: $_POST['ayar'] dizisini olduğu gibi kaydetmiyoruz.
         * Kötü niyetli biri formda olmayan bir anahtar gönderebilir.
         * Bu yüzden veritabanındaki DÜZENLENEBİLİR ayarların listesini
         * çekip, sadece o listedeki anahtarları kabul ediyoruz. */
        $izinliler = $db->query(
            'SELECT anahtar, tip FROM ayarlar WHERE duzenlenebilir = 1'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $gonderilen   = (array) ($_POST['ayar'] ?? []);
        $kaydedilecek = [];
        $hatalar      = [];

        foreach ($izinliler as $anahtar => $tip) {
            if ($tip === 'onay') {
                /* Onay kutuları işaretli DEĞİLSE tarayıcı hiçbir şey
                 * göndermez. Bu yüzden "gelmedi" = "kapalı" demektir;
                 * eksik anahtarı atlamak yerine 0 yazmalıyız. */
                $kaydedilecek[$anahtar] = isset($gonderilen[$anahtar]) ? '1' : '0';
                continue;
            }

            // Diğer tipler: gönderilmediyse dokunma.
            if (!array_key_exists($anahtar, $gonderilen)) {
                continue;
            }

            $deger = trim((string) $gonderilen[$anahtar]);

            /* SUNUCU TARAFI DOĞRULAMA
             * -----------------------------------------------------
             * <input type="email"> yalnızca TARAYICIDA kontrol eder;
             * istek elle gönderildiğinde hiçbir işe yaramaz. Bu yüzden
             * tipe göre burada tekrar bakıyoruz. */
            if ($deger !== '') {
                if ($tip === 'eposta' && !filter_var($deger, FILTER_VALIDATE_EMAIL)) {
                    $hatalar[] = $anahtar . ': geçerli bir e-posta adresi değil.';
                    continue;
                }
                if ($tip === 'url' && !filter_var($deger, FILTER_VALIDATE_URL)) {
                    $hatalar[] = $anahtar . ': geçerli bir adres değil (http:// veya https:// ile başlamalı).';
                    continue;
                }
                if ($tip === 'sayi' && !is_numeric($deger)) {
                    $hatalar[] = $anahtar . ': sayı olmalıdır.';
                    continue;
                }
                if ($tip === 'renk' && !preg_match('/^#[0-9a-fA-F]{6}$/', $deger)) {
                    $hatalar[] = $anahtar . ': geçerli bir renk kodu değil.';
                    continue;
                }
            }

            /* Zaman diliminde hatalı bir değer, tüm sitenin tarih
             * fonksiyonlarını bozar; bu yüzden PHP'nin bildiği
             * listeye karşı doğruluyoruz. */
            if ($anahtar === 'sistem_zaman_dilimi' && $deger !== ''
                && !in_array($deger, timezone_identifiers_list(), true)) {
                $hatalar[] = 'sistem_zaman_dilimi: bilinmeyen zaman dilimi (örn. Europe/Istanbul).';
                continue;
            }

            $kaydedilecek[$anahtar] = $deger;
        }

        try {
            settings_save($db, $kaydedilecek);

            $bildirim = $hatalar === []
                ? ['success', count($kaydedilecek) . ' ayar kaydedildi.']
                : ['warning', count($kaydedilecek) . ' ayar kaydedildi, şunlar atlandı → ' . implode(' ', $hatalar)];
        } catch (Throwable $e) {
            $bildirim = ['danger', APP_DEBUG ? $e->getMessage() : 'Ayarlar kaydedilemedi.'];
        }
    }
}

/* ---------------------------------------------------------------------
 *  SAYFAYI ÇİZ
 * ------------------------------------------------------------------ */
$sayfaBaslik   = 'Site Ayarları';
$sayfaAciklama = 'Formdaki alanlar "ayarlar" tablosundan otomatik üretilir.';
$aktifMenu     = 'ayarlar';
$gerekenRol    = 'admin';
$kirintilar    = ['Ayarlar'];

require __DIR__ . '/_ust.php';

$gruplar   = settings_grouped($db);
$etiketler = settings_group_labels();

// Grup sekmelerinin ikonları (tanımsız grup için varsayılan: settings).
$grupIkonlari = [
    'genel'    => 'settings',
    'iletisim' => 'mail',
    'sosyal'   => 'link',
    'seo'      => 'search',
    'sistem'   => 'server',
];
?>

<?php if ($bildirim !== null): ?>
    <div class="alert alert-<?= e($bildirim[0]) ?>" role="alert"><?= e($bildirim[1]) ?></div>
<?php endif; ?>

<form method="post" novalidate id="ayar_formu">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="row g-3">

        <!-- ========================================================
             SOL – GRUP SEKMELERİ + ARAMA
             ======================================================== -->
        <div class="col-lg-3">
            <div class="adm-card">
                <div class="adm-card__body">
                    <label for="ayar_ara" class="form-label">Ayar ara</label>
                    <input type="search" id="ayar_ara" class="form-control form-control-sm mb-3"
                           placeholder="örn. logo, e-posta, bakım">

                    <div class="adm-tabs nav nav-pills" role="tablist">
                        <?php $ilk = true; foreach ($gruplar as $grup => $satirlar): ?>
                            <button class="nav-link <?= $ilk ? 'active' : '' ?>" type="button"
                                    data-bs-toggle="tab" data-bs-target="#tab-<?= e($grup) ?>"
                                    role="tab" aria-selected="<?= $ilk ? 'true' : 'false' ?>">
                                <?= panel_icon($grupIkonlari[$grup] ?? 'settings') ?>
                                <span><?= e($etiketler[$grup] ?? ucfirst($grup)) ?></span>
                                <span class="ms-auto cy-muted small"><?= count($satirlar) ?></span>
                            </button>
                        <?php $ilk = false; endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================
             SAĞ – AYAR ALANLARI
             ======================================================== -->
        <div class="col-lg-9">
            <div class="tab-content">
                <?php $ilk = true; foreach ($gruplar as $grup => $satirlar): ?>
                    <div class="tab-pane fade <?= $ilk ? 'show active' : '' ?>" id="tab-<?= e($grup) ?>" role="tabpanel">
                        <div class="adm-card adm-card--brand">
                            <div class="adm-card__header">
                                <h3 class="adm-card__title">
                                    <?= panel_icon($grupIkonlari[$grup] ?? 'settings') ?>
                                    <?= e($etiketler[$grup] ?? ucfirst($grup)) ?>
                                </h3>
                            </div>

                            <div class="adm-card__body">
                                <?php foreach ($satirlar as $ayar): ?>
                                    <?php
                                    $ad        = 'ayar[' . $ayar['anahtar'] . ']';
                                    $id        = 'ayar_' . $ayar['anahtar'];
                                    $deger     = (string) ($ayar['deger'] ?? '');
                                    $kilitli   = ((int) $ayar['duzenlenebilir']) === 0;
                                    $devreDisi = $kilitli ? ' disabled' : '';

                                    /* Arama kutusu bu metinde arar: etiket + anahtar +
                                       açıklama. data-* özniteliğinde tuttuğumuz için
                                       JavaScript'in DOM'u taraması gerekmez. */
                                    $aranabilir = mb_strtolower(
                                        $ayar['etiket'] . ' ' . $ayar['anahtar'] . ' ' . (string) $ayar['aciklama'],
                                        'UTF-8'
                                    );
                                    ?>

                                    <div class="adm-setting js-ayar-satiri" data-ara="<?= e($aranabilir) ?>">

                                        <?php if ($ayar['anahtar'] === 'site_logo'): ?>
                                            <!-- ÖZEL ALAN: Logo
                                                 Dosya adını elle yazdırmak yerine yükleme
                                                 sunuyoruz. Yüklenen dosya AJAX ile kaydedilir
                                                 (action=logo_yukle) ve ayar anında güncellenir. -->
                                            <label class="form-label"><?= e($ayar['etiket']) ?></label>

                                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                                <img src="<?= e(site_logo_url('../')) ?>" alt="Logo önizleme"
                                                     id="logo_onizleme"
                                                     style="width:64px;height:64px;object-fit:contain;background:var(--cy-surface-soft);
                                                            border:1px solid var(--cy-border);border-radius:var(--cy-radius-sm);padding:6px">

                                                <div class="flex-grow-1" style="min-width:220px">
                                                    <div class="input-group input-group-sm">
                                                        <input type="file" class="form-control" id="logo_dosya"
                                                               accept="image/png,image/jpeg,image/gif,image/webp">
                                                        <button type="button" class="btn btn-outline-secondary" id="logo_yukle">
                                                            Yükle
                                                        </button>
                                                    </div>
                                                    <div class="form-text">
                                                        JPG, PNG, GIF veya WEBP · en fazla
                                                        <?= (int) (UPLOAD_MAX_BYTES / 1024 / 1024) ?> MB.
                                                        Yükleme anında kaydedilir.
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Dosya adı yine de düzenlenebilir kalsın
                                                 (upload/ klasörüne elle dosya atanlar için). -->
                                            <input type="text" name="<?= e($ad) ?>" id="<?= e($id) ?>"
                                                   class="form-control form-control-sm mt-2"
                                                   value="<?= e($deger) ?>" placeholder="upload/ içindeki dosya adı">

                                        <?php elseif ($ayar['tip'] === 'onay'): ?>
                                            <!-- Aç/kapa anahtarı -->
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                       name="<?= e($ad) ?>" id="<?= e($id) ?>" value="1"
                                                       <?= $deger === '1' ? 'checked' : '' ?><?= $devreDisi ?>>
                                                <label class="form-check-label fw-semibold" for="<?= e($id) ?>">
                                                    <?= e($ayar['etiket']) ?>
                                                </label>
                                            </div>

                                        <?php else: ?>
                                            <label for="<?= e($id) ?>" class="form-label">
                                                <?= e($ayar['etiket']) ?>
                                                <?php if ($kilitli): ?>
                                                    <span class="cy-badge cy-badge--soft ms-1" style="font-size:.7rem">salt okunur</span>
                                                <?php endif; ?>
                                            </label>

                                            <?php if ($ayar['tip'] === 'uzun_metin'): ?>
                                                <textarea name="<?= e($ad) ?>" id="<?= e($id) ?>" class="form-control"
                                                          rows="3"<?= $devreDisi ?>><?= e($deger) ?></textarea>

                                            <?php elseif ($ayar['tip'] === 'secim'): ?>
                                                <?php
                                                // "secenekler" sütunu JSON dizisi tutar: ["tr","en"]
                                                $secenekler = json_decode((string) $ayar['secenekler'], true);
                                                $secenekler = is_array($secenekler) ? $secenekler : [];
                                                ?>
                                                <select name="<?= e($ad) ?>" id="<?= e($id) ?>" class="form-select"<?= $devreDisi ?>>
                                                    <?php foreach ($secenekler as $secenek): ?>
                                                        <option value="<?= e((string) $secenek) ?>"
                                                            <?= $deger === (string) $secenek ? 'selected' : '' ?>>
                                                            <?= e((string) $secenek) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                            <?php else: ?>
                                                <?php
                                                // Kalan tipleri HTML input tipine eşle.
                                                $inputTipi = match ($ayar['tip']) {
                                                    'sayi'   => 'number',
                                                    'eposta' => 'email',
                                                    'url'    => 'url',
                                                    'renk'   => 'color',
                                                    default  => 'text',
                                                };
                                                ?>
                                                <input type="<?= e($inputTipi) ?>" name="<?= e($ad) ?>" id="<?= e($id) ?>"
                                                       class="form-control<?= $inputTipi === 'color' ? ' form-control-color' : '' ?>"
                                                       value="<?= e($deger) ?>"<?= $devreDisi ?>>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($ayar['aciklama'])): ?>
                                            <div class="form-text"><?= e((string) $ayar['aciklama']) ?></div>
                                        <?php endif; ?>

                                        <span class="adm-setting__key">setting('<?= e($ayar['anahtar']) ?>')</span>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Arama hiçbir şey bulamazsa görünür -->
                                <p class="cy-empty d-none js-bos">Bu grupta eşleşen ayar yok.</p>
                            </div>
                        </div>
                    </div>
                <?php $ilk = false; endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Uzun formda düğmeyi aramak zorunda kalmayın: alta yapışır. -->
    <div class="adm-savebar">
        <span class="cy-muted small">
            Değişiklikler tüm sekmelerde birlikte kaydedilir.
        </span>
        <div class="d-flex gap-2">
            <button type="reset" class="btn btn-outline-secondary cy-btn btn-sm">Geri Al</button>
            <button type="submit" class="btn cy-btn cy-btn--primary">
                <?= panel_icon('save') ?> Ayarları Kaydet
            </button>
        </div>
    </div>
</form>

<?php
ob_start();
?>
<script>
$(function () {
    'use strict';

    /* -------------------------------------------------------------
     *  AYAR ARAMA
     *  Tüm sekmelerdeki satırları aynı anda süzer.
     * ----------------------------------------------------------- */
    $('#ayar_ara').on('input', function () {
        var aranan = $(this).val().toLocaleLowerCase('tr');

        $('.js-ayar-satiri').each(function () {
            var uygun = aranan === '' || $(this).data('ara').indexOf(aranan) !== -1;
            $(this).toggleClass('d-none', !uygun);
        });

        // Her sekmede "sonuç yok" mesajını ayrı ayrı kontrol et.
        $('.tab-pane').each(function () {
            var kalan = $(this).find('.js-ayar-satiri').not('.d-none').length;
            $(this).find('.js-bos').toggleClass('d-none', kalan > 0);
        });
    });

    /* -------------------------------------------------------------
     *  LOGO YÜKLEME
     * -------------------------------------------------------------
     *  Dosya yüklerken FormData kullanmak ZORUNLUDUR; normal POST
     *  verisiyle dosya gönderilemez.
     * ----------------------------------------------------------- */
    $('#logo_yukle').on('click', function () {
        var girdi = document.getElementById('logo_dosya');

        if (!girdi.files || girdi.files.length === 0) {
            CY.notify('Önce bir dosya seçin.', 'info');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Yükleniyor…');

        var veri = new FormData();
        veri.append('action', 'logo_yukle');
        veri.append('logo', girdi.files[0]);

        CY.upload(veri)
            .done(function (res) {
                CY.notify(res.description, 'success');

                // Önizlemeyi ve dosya adı alanını tazele.
                // Sona eklenen zaman damgası tarayıcı önbelleğini atlatır.
                $('#logo_onizleme').attr('src', '../' + res.url + '?v=' + Date.now());
                $('#ayar_site_logo').val(res.dosya);
                girdi.value = '';
            })
            .fail(function (xhr) {
                CY.notify(CY.hataMesaji(xhr, 'Logo yüklenemedi.'), 'danger');
            })
            .always(function () {
                $btn.prop('disabled', false).text('Yükle');
            });
    });

    /* -------------------------------------------------------------
     *  KAYDEDİLMEMİŞ DEĞİŞİKLİK UYARISI
     * -------------------------------------------------------------
     *  Uzun bir formu doldurup yanlışlıkla sekmeyi kapatmak can
     *  sıkıcıdır. Değişiklik varsa tarayıcı standart uyarısını gösterir.
     * ----------------------------------------------------------- */
    var degisti = false;

    $('#ayar_formu').on('change input', ':input', function () { degisti = true; });
    $('#ayar_formu').on('submit', function () { degisti = false; });

    $(window).on('beforeunload', function () {
        if (degisti) { return 'Kaydedilmemiş değişiklikleriniz var.'; }
    });
});
</script>
<?php
$sayfaScript = ob_get_clean();
require __DIR__ . '/_alt.php';
