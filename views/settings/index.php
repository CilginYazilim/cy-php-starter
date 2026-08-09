<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Site Ayarları
 * ---------------------------------------------------------------------
 *  Form, "ayarlar" tablosundaki satırlardan OTOMATİK üretilir.
 *  Yeni bir ayar eklemek için tabloya satır eklemeniz yeterlidir.
 *
 *  @var array<string,array<int,array<string,mixed>>> $groups
 *  @var array<string,string> $labels
 * =====================================================================
 */

use App\Core\Flash;
use App\Core\Setting;

$groups = $groups ?? [];
$labels = $labels ?? [];
$errors = Flash::errors();
?>

<div class="cy-page-head">
    <div>
        <h2 class="cy-title">Site Ayarları</h2>
        <p class="cy-subtitle">Değişiklikler kaydedildiği anda tüm sitede etkili olur.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form method="post" action="<?= e(url('panel/ayarlar')) ?>" novalidate>
            <?= csrf_field() ?>

            <?php
            $groupKeys = array_keys($labels);
            $firstTab  = $groupKeys[0] ?? array_key_first($groups);
            ?>

            <div class="cy-card">
                <div class="cy-card__header cy-card__header--tabs">
                    <ul class="nav nav-pills cy-tabnav" role="tablist">
                        <?php foreach ($groups as $groupKey => $rows): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link<?= $groupKey === $firstTab ? ' active' : '' ?>"
                                        id="tab-<?= e($groupKey) ?>" data-bs-toggle="pill"
                                        data-bs-target="#pane-<?= e($groupKey) ?>" type="button" role="tab">
                                    <?= e($labels[$groupKey] ?? ucfirst($groupKey)) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="cy-card__body">
                    <div class="tab-content">
                        <?php foreach ($groups as $groupKey => $rows): ?>
                            <div class="tab-pane fade<?= $groupKey === $firstTab ? ' show active' : '' ?>" id="pane-<?= e($groupKey) ?>" role="tabpanel">
                                <div class="row g-3">
                                    <?php foreach ($rows as $row): ?>
                                        <?php
                                        $key      = (string) $row['anahtar'];
                                        $type     = (string) $row['tip'];
                                        $value    = Setting::get($key);
                                        $editable = (int) $row['duzenlenebilir'] === 1;
                                        $width    = in_array($type, ['uzun_metin'], true) ? 'col-12' : 'col-12 col-md-6';
                                        ?>
                                        <div class="<?= $width ?>">
                                            <label class="form-label" for="set_<?= e($key) ?>"><?= e($row['etiket']) ?></label>

                                            <?php if (!$editable): ?>
                                                <input type="text" class="form-control" value="<?= e($value) ?>" disabled>

                                            <?php elseif ($type === 'onay'): ?>
                                                <div class="form-check form-switch mt-2">
                                                    <input class="form-check-input" type="checkbox" role="switch"
                                                           name="<?= e($key) ?>" id="set_<?= e($key) ?>" value="1"
                                                           <?= $value === '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="set_<?= e($key) ?>">Etkin</label>
                                                </div>

                                            <?php elseif ($type === 'uzun_metin'): ?>
                                                <textarea class="form-control" name="<?= e($key) ?>" id="set_<?= e($key) ?>" rows="3"><?= e($value) ?></textarea>

                                            <?php elseif ($type === 'secim'): ?>
                                                <?php $options = json_decode((string) ($row['secenekler'] ?? '[]'), true) ?: []; ?>
                                                <select class="form-select" name="<?= e($key) ?>" id="set_<?= e($key) ?>">
                                                    <?php foreach ($options as $option): ?>
                                                        <option value="<?= e($option) ?>" <?= $value === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                                    <?php endforeach; ?>
                                                </select>

                                            <?php elseif ($type === 'renk'): ?>
                                                <input type="color" class="form-control form-control-color" name="<?= e($key) ?>" id="set_<?= e($key) ?>" value="<?= e($value !== '' ? $value : '#0b5cb5') ?>">

                                            <?php else: ?>
                                                <input type="<?= $type === 'sayi' ? 'number' : ($type === 'eposta' ? 'email' : ($type === 'url' ? 'url' : 'text')) ?>"
                                                       class="form-control" name="<?= e($key) ?>" id="set_<?= e($key) ?>" value="<?= e($value) ?>">
                                            <?php endif; ?>

                                            <?php if (!empty($row['aciklama'])): ?>
                                                <div class="form-text"><?= e($row['aciklama']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if ($groupKey === 'genel'): ?>
                                        <div class="col-12">
                                            <hr class="cy-divider">
                                            <label class="form-label">Site Logosu</label>
                                            <div class="cy-upload">
                                                <span class="cy-upload__preview">
                                                    <img src="<?= e(Setting::logoUrl()) ?>" alt="" class="cy-avatar cy-avatar--md">
                                                </span>
                                                <div class="flex-grow-1" style="min-width:0">
                                                    <p class="cy-muted small mb-1">Logoyu değiştirmek için ayrı bir formla yükleyin (aşağıda).</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cy-card__footer d-flex justify-content-end">
                    <button type="submit" class="btn cy-btn cy-btn--primary">
                        <?= icon('save', 'cy-icon cy-icon--sm') ?> Ayarları Kaydet
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-4">
        <div class="cy-card">
            <div class="cy-card__header">
                <h3 class="cy-section-title">Site Logosu</h3>
            </div>
            <form method="post" action="<?= e(url('panel/ayarlar/logo')) ?>" enctype="multipart/form-data" class="cy-card__body">
                <?= csrf_field() ?>
                <div class="text-center mb-3">
                    <img src="<?= e(Setting::logoUrl()) ?>" alt="" class="cy-avatar cy-avatar--lg">
                </div>
                <input type="file" name="logo" class="form-control form-control-sm mb-2" accept="image/jpeg,image/png,image/gif,image/webp">
                <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--block cy-btn--sm">
                    <?= icon('upload', 'cy-icon cy-icon--sm') ?> Logoyu Güncelle
                </button>
            </form>
        </div>
    </div>
</div>
