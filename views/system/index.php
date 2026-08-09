<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Sistem Bilgisi
 * =====================================================================
 */

use App\Models\Role;

$info   = $info ?? [];
$checks = $checks ?? [];

$passed = count(array_filter($checks, static fn (array $c): bool => $c['ok']));
$total  = count($checks);
?>

<div class="cy-page-head">
    <div>
        <h2 class="cy-title">Sistem Bilgisi</h2>
        <p class="cy-subtitle">Kurulum bilgileri ve güvenlik denetim listesi.</p>
    </div>
    <div class="cy-page-head__actions">
        <span class="cy-badge <?= $passed === $total ? 'cy-badge--brand' : '' ?>">
            <?= icon('shield', 'cy-icon cy-icon--sm') ?> <?= $passed ?>/<?= $total ?> denetim geçildi
        </span>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="cy-card h-100">
            <div class="cy-card__header">
                <h3 class="cy-section-title">Güvenlik Denetim Listesi</h3>
            </div>
            <div class="cy-card__body cy-card__body--flush">
                <div class="cy-timeline">
                    <?php foreach ($checks as $check): ?>
                        <div class="cy-timeline__item">
                            <span class="cy-timeline__icon cy-timeline__icon--<?= $check['ok'] ? 'success' : 'warning' ?>">
                                <?= icon($check['ok'] ? 'check' : 'alert', 'cy-icon cy-icon--sm') ?>
                            </span>
                            <div class="cy-timeline__body">
                                <p class="cy-timeline__text"><strong><?= e($check['label']) ?></strong></p>
                                <span class="cy-timeline__meta"><?= e($check['detail']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5 d-flex flex-column gap-3">
        <div class="cy-card">
            <div class="cy-card__header"><h3 class="cy-section-title">Kurulum Bilgileri</h3></div>
            <div class="cy-card__body">
                <dl class="cy-detail">
                    <?php foreach ($info as $label => $value): ?>
                        <dt><?= e($label) ?></dt>
                        <dd class="cy-mono"><?= e($value) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <div class="cy-card">
            <div class="cy-card__header"><h3 class="cy-section-title">Roller ve Yetkiler</h3></div>
            <div class="cy-card__body cy-card__body--flush">
                <div class="cy-table-wrap">
                    <table class="table cy-table">
                        <thead><tr><th scope="col">Rol</th><th scope="col">Yetkiler</th></tr></thead>
                        <tbody>
                            <?php foreach (Role::options() as $key => $label): ?>
                                <tr>
                                    <td><span class="cy-role cy-role--<?= e(Role::variant($key)) ?>"><?= e($label) ?></span></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach (Role::abilities($key) as $ability): ?>
                                                <span class="cy-badge" style="font-size:.6875rem"><?= e($ability) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="cy-card__footer">
                Yetkiler <code class="cy-mono">app/Models/Role.php</code> dosyasında tanımlıdır.
            </div>
        </div>
    </div>
</div>
