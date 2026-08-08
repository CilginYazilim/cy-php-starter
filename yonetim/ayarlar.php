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
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik = 'Ayarlar';
$aktifMenu   = 'ayarlar';
$gerekenRol  = 'admin';   // Ayarları sadece yöneticiler değiştirebilir

require __DIR__ . '/_ust.php';

$bildirim = null;

/* ---------------------------------------------------------------------
 *  KAYDETME
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $bildirim = ['danger', 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'];
    } else {
        /* GÜVENLİK: $_POST['ayar'] dizisini olduğu gibi kaydetmiyoruz.
         * Kötü niyetli biri formda olmayan bir anahtar gönderebilir.
         * Bu yüzden veritabanındaki DÜZENLENEBİLİR ayarların listesini
         * çekip, sadece o listedeki anahtarları kabul ediyoruz. */
        $izinliler = $db->query(
            'SELECT anahtar, tip FROM ayarlar WHERE duzenlenebilir = 1'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $gonderilen = (array) ($_POST['ayar'] ?? []);
        $kaydedilecek = [];

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

            $kaydedilecek[$anahtar] = trim((string) $gonderilen[$anahtar]);
        }

        try {
            settings_save($db, $kaydedilecek);
            $bildirim = ['success', count($kaydedilecek) . ' ayar kaydedildi.'];
        } catch (Throwable $e) {
            $bildirim = ['danger', APP_DEBUG ? $e->getMessage() : 'Ayarlar kaydedilemedi.'];
        }
    }
}

$gruplar   = settings_grouped($db);
$etiketler = settings_group_labels();
?>

<?php if ($bildirim !== null): ?>
    <div class="alert alert-<?= e($bildirim[0]) ?>" role="alert"><?= e($bildirim[1]) ?></div>
<?php endif; ?>

<p class="cy-muted mb-4">
    Bu formdaki alanlar <code>ayarlar</code> tablosundan otomatik üretilir.
    Yeni bir ayar eklemek için tabloya satır eklemeniz yeterlidir.
</p>

<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <!-- Gruplar için sekmeler -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <?php $ilk = true; foreach ($gruplar as $grup => $satirlar): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $ilk ? 'active' : '' ?>" type="button"
                        data-bs-toggle="tab" data-bs-target="#tab-<?= e($grup) ?>"
                        role="tab" aria-selected="<?= $ilk ? 'true' : 'false' ?>">
                    <?= e($etiketler[$grup] ?? ucfirst($grup)) ?>
                </button>
            </li>
        <?php $ilk = false; endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php $ilk = true; foreach ($gruplar as $grup => $satirlar): ?>
            <div class="tab-pane fade <?= $ilk ? 'show active' : '' ?>" id="tab-<?= e($grup) ?>" role="tabpanel">

                <?php foreach ($satirlar as $ayar): ?>
                    <?php
                    $ad        = 'ayar[' . $ayar['anahtar'] . ']';
                    $id        = 'ayar_' . $ayar['anahtar'];
                    $deger     = (string) ($ayar['deger'] ?? '');
                    $kilitli   = ((int) $ayar['duzenlenebilir']) === 0;
                    $devreDisi = $kilitli ? ' disabled' : '';
                    ?>

                    <div class="mb-3">
                        <?php if ($ayar['tip'] === 'onay'): ?>
                            <!-- Aç/kapa anahtarı -->
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       name="<?= e($ad) ?>" id="<?= e($id) ?>" value="1"
                                       <?= $deger === '1' ? 'checked' : '' ?><?= $devreDisi ?>>
                                <label class="form-check-label" for="<?= e($id) ?>">
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

                        <div class="form-text cy-muted" style="font-size:.75rem">
                            <code>setting('<?= e($ayar['anahtar']) ?>')</code>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>
        <?php $ilk = false; endforeach; ?>
    </div>

    <div class="mt-4 pt-3" style="border-top:1px solid var(--cy-border)">
        <button type="submit" class="btn cy-btn cy-btn--primary">Ayarları Kaydet</button>
    </div>
</form>

<?php require __DIR__ . '/_alt.php'; ?>
