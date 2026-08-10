<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Ornek modülü listesi
 * ---------------------------------------------------------------------
 *  Bu dosya modülün KENDİ views/ klasöründedir ve
 *  $this->view('Ornek::index', ...) ile çağrılır.
 *
 *  @var array<int,array<string,mixed>> $kayitlar
 * =====================================================================
 */

$kayitlar = $kayitlar ?? [];
$errors   = $errors ?? [];
$old      = $old ?? [];
?>

<div class="cy-page-head">
    <div>
        <h2 class="cy-title">Ornek</h2>
        <p class="cy-subtitle"><strong><?= count($kayitlar) ?></strong> kayıt listeleniyor.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="cy-card">
            <div class="cy-card__header">
                <h3 class="cy-section-title mb-0">Yeni Kayıt</h3>
            </div>
            <form method="post" action="<?= e(url('panel/ornek/kaydet')) ?>" class="cy-card__body" novalidate>
                <?= csrf_field() ?>

                <label class="form-label" for="baslik">Başlık <span class="text-danger">*</span></label>
                <input type="text" name="baslik" id="baslik" maxlength="150" autocomplete="off"
                       class="form-control<?= isset($errors['baslik']) ? ' is-invalid' : '' ?>"
                       value="<?= old($old, 'baslik') ?>">
                <?php if (isset($errors['baslik'])): ?>
                    <div class="invalid-feedback d-block"><?= e($errors['baslik']) ?></div>
                <?php endif; ?>

                <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block mt-3">
                    <?= icon('plus', 'cy-icon cy-icon--sm') ?> Ekle
                </button>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="cy-card">
            <div class="cy-card__body cy-card__body--flush">
                <?php if ($kayitlar === []): ?>
                    <div class="cy-empty">
                        <span class="cy-empty__icon"><?= icon('inbox') ?></span>
                        <p class="cy-empty__text">Henüz kayıt yok. Soldaki formdan ekleyebilirsiniz.</p>
                    </div>
                <?php else: ?>
                    <div class="cy-table-wrap">
                        <table class="table cy-table w-100">
                            <thead>
                                <tr>
                                    <th scope="col" style="width:70px">#</th>
                                    <th scope="col">Başlık</th>
                                    <th scope="col" class="cy-hide-sm" style="width:160px">Eklenme</th>
                                    <th scope="col" style="width:80px" class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kayitlar as $kayit): ?>
                                    <tr>
                                        <td class="cy-id">#<?= (int) $kayit['id'] ?></td>
                                        <td><?= e($kayit['baslik']) ?></td>
                                        <td class="cy-hide-sm cy-cell-muted cy-nowrap">
                                            <?= e(human_date($kayit['created_at'] ?? null)) ?>
                                        </td>
                                        <td class="text-center">
                                            <?php /* data-confirm: onay penceresini app.js açar.
                                                     Satır içi onsubmit KULLANILMAZ — CSP yasaklar. */ ?>
                                            <form method="post"
                                                  action="<?= e(url('panel/ornek/sil/' . (int) $kayit['id'])) ?>"
                                                  data-confirm="Bu kayıt silinecek. Emin misiniz?">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="cy-btn-icon cy-btn-icon--delete" title="Sil">
                                                    <?= icon('trash', 'cy-icon cy-icon--sm') ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
