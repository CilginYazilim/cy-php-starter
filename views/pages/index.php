<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: İçerik sayfaları listesi
 * ---------------------------------------------------------------------
 *  Sayfa BAŞLIĞI VE AÇIKLAMASI ÜST ÇUBUKTA (topbar) yazar; burada
 *  tekrar etmiyoruz. Aynı cümleyi iki kez okutmak dikey alanı boşa
 *  harcıyor ve mobilde ekranın yarısını yiyordu.
 *
 *  @var array<int,App\Models\Page> $sayfalar
 *  @var array{toplam:int,yayin:int,taslak:int,menude:int} $istatistik
 * =====================================================================
 */

use App\Models\Page;

$sayfalar   = $sayfalar ?? [];
$istatistik = $istatistik ?? ['toplam' => 0, 'yayin' => 0, 'taslak' => 0, 'menude' => 0];
?>

<div class="cy-stats">
    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--brand"><?= icon('files') ?></span>
        <span>
            <span class="cy-stat__label">Toplam Sayfa</span>
            <span class="cy-stat__value"><?= (int) $istatistik['toplam'] ?></span>
            <span class="cy-stat__hint">içerik sayfası</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--success"><?= icon('check') ?></span>
        <span>
            <span class="cy-stat__label">Yayında</span>
            <span class="cy-stat__value"><?= (int) $istatistik['yayin'] ?></span>
            <span class="cy-stat__hint">ziyaretçi görebiliyor</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--warning"><?= icon('edit') ?></span>
        <span>
            <span class="cy-stat__label">Taslak</span>
            <span class="cy-stat__value"><?= (int) $istatistik['taslak'] ?></span>
            <span class="cy-stat__hint">henüz yayınlanmadı</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--brand"><?= icon('menu') ?></span>
        <span>
            <span class="cy-stat__label">Menüde</span>
            <span class="cy-stat__value"><?= (int) $istatistik['menude'] ?></span>
            <span class="cy-stat__hint">üst menüde görünüyor</span>
        </span>
    </div>
</div>

<div class="cy-card">
    <div class="cy-card__header">
        <div>
            <h2 class="cy-section-title mb-0"><?= icon('files', 'cy-icon cy-icon--sm') ?> Sayfa Listesi</h2>
            <p class="cy-muted small mb-0">Sıra numarası küçük olan sayfa menüde önce görünür.</p>
        </div>

        <?php if (can('pages.manage')): ?>
            <a class="btn cy-btn cy-btn--primary cy-btn--sm" href="<?= e(url('panel/sayfalar/yeni')) ?>">
                <?= icon('plus', 'cy-icon cy-icon--sm') ?> Yeni Sayfa
            </a>
        <?php endif; ?>
    </div>

    <div class="cy-card__body cy-card__body--flush">
        <?php if ($sayfalar === []): ?>
            <div class="cy-empty p-4 text-center">
                <span class="cy-empty__icon"><?= icon('files') ?></span>
                <p class="cy-empty__text mb-0">Henüz sayfa yok. Sağ üstteki düğmeyle ilkini oluşturun.</p>
            </div>
        <?php else: ?>
            <div class="cy-table-wrap">
                <table class="table cy-table w-100">
                    <thead>
                        <tr>
                            <th scope="col" style="width:64px">Sıra</th>
                            <th scope="col">Sayfa</th>
                            <th scope="col" class="cy-hide-sm">Adres</th>
                            <th scope="col" style="width:110px">Durum</th>
                            <th scope="col" style="width:150px" class="cy-hide-sm">Güncelleme</th>
                            <th scope="col" style="width:150px" class="text-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sayfalar as $sayfa): ?>
                            <tr>
                                <td class="cy-mono"><?= (int) $sayfa->sira ?></td>

                                <td>
                                    <div class="cy-cell-title">
                                        <strong><?= e($sayfa->baslik) ?></strong>
                                        <?php if ($sayfa->korumali): ?>
                                            <span class="cy-badge cy-badge--soft" title="Çekirdek sayfa: silinemez, adresi değişmez">
                                                <?= icon('lock', 'cy-icon cy-icon--sm') ?> Çekirdek
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($sayfa->menude): ?>
                                            <span class="cy-badge cy-badge--soft">Menüde</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="cy-muted small"><?= e($sayfa->onizleme()) ?></span>
                                </td>

                                <td class="cy-hide-sm">
                                    <code class="cy-mono">/<?= e($sayfa->slug) ?></code>
                                    <span class="cy-muted small d-block"><?= (int) $sayfa->kelimeSayisi() ?> kelime</span>
                                </td>

                                <td>
                                    <span class="cy-badge <?= $sayfa->yayinda() ? 'cy-badge--success' : 'cy-badge--warning' ?>">
                                        <?= e($sayfa->durumEtiketi()) ?>
                                    </span>
                                </td>

                                <td class="cy-hide-sm">
                                    <span class="cy-muted small"><?= e(Page::formatDate($sayfa->updatedAt)) ?></span>
                                </td>

                                <td>
                                    <div class="cy-row-actions">
                                        <?php if ($sayfa->yayinda()): ?>
                                            <a class="cy-btn-icon" href="<?= e(url($sayfa->slug)) ?>" target="_blank"
                                               rel="noopener" title="Sayfayı sitede aç">
                                                <?= icon('external', 'cy-icon cy-icon--sm') ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (can('pages.manage')): ?>
                                            <a class="cy-btn-icon" href="<?= e(url('panel/sayfalar/' . $sayfa->id)) ?>" title="Düzenle">
                                                <?= icon('edit', 'cy-icon cy-icon--sm') ?>
                                            </a>

                                            <?php if (!$sayfa->korumali): ?>
                                                <form method="post" action="<?= e(url('panel/sayfalar/' . $sayfa->id . '/sil')) ?>"
                                                      class="d-inline"
                                                      data-confirm="“<?= e($sayfa->baslik) ?>” sayfası kalıcı olarak silinecek. Emin misiniz?">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="cy-btn-icon cy-btn-icon--danger" title="Sil">
                                                        <?= icon('trash', 'cy-icon cy-icon--sm') ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="cy-card__footer d-flex flex-wrap justify-content-between gap-2">
        <span>Adresler doğrudan site kökünden çalışır: <code class="cy-mono">/hakkimizda</code></span>
        <span class="cy-hide-sm">İçerik kaydedilirken güvenlik süzgecinden geçer</span>
    </div>
</div>
