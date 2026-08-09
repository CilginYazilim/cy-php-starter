<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Kontrol paneli
 * =====================================================================
 */

use App\Models\Role;

$stats        = $stats ?? null;
$messageStats = $messageStats ?? null;
?>

<?php if ($stats !== null): ?>

    <div class="cy-stats">
        <div class="cy-stat">
            <span class="cy-stat__icon cy-stat__icon--brand"><?= icon('users') ?></span>
            <span>
                <span class="cy-stat__label">Toplam Kullanıcı</span>
                <span class="cy-stat__value"><?= (int) $stats['total'] ?></span>
                <span class="cy-stat__hint">son 7 günde +<?= (int) $stats['last7'] ?></span>
            </span>
        </div>

        <div class="cy-stat">
            <span class="cy-stat__icon cy-stat__icon--success"><?= icon('check') ?></span>
            <span>
                <span class="cy-stat__label">Aktif Hesap</span>
                <span class="cy-stat__value"><?= (int) $stats['active'] ?></span>
                <span class="cy-stat__hint"><?= (int) $stats['passive'] ?> pasif/askıda</span>
            </span>
        </div>

        <?php if ($messageStats !== null): ?>
            <div class="cy-stat">
                <span class="cy-stat__icon cy-stat__icon--warning"><?= icon('mail') ?></span>
                <span>
                    <span class="cy-stat__label">Mesajlar</span>
                    <span class="cy-stat__value"><?= (int) $messageStats['total'] ?></span>
                    <span class="cy-stat__hint"><?= (int) $messageStats['unread'] ?> okunmamış</span>
                </span>
            </div>
        <?php endif; ?>

        <div class="cy-stat">
            <span class="cy-stat__icon cy-stat__icon--danger"><?= icon('shield') ?></span>
            <span>
                <span class="cy-stat__label">Yönetici</span>
                <span class="cy-stat__value"><?= (int) $stats['admins'] ?></span>
                <span class="cy-stat__hint"><?= (int) $stats['editors'] ?> editör</span>
            </span>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="cy-card h-100">
                <div class="cy-card__header">
                    <div>
                        <h2 class="cy-section-title">Kayıt Hareketi</h2>
                        <p class="cy-subtitle mb-0">Son 14 günde eklenen kullanıcılar</p>
                    </div>
                    <span class="cy-badge cy-badge--brand"><?= icon('activity', 'cy-icon cy-icon--sm') ?> 14 gün</span>
                </div>
                <div class="cy-card__body">
                    <?php $max = max(1, max(array_column($chart ?? [], 'value'))); ?>
                    <div class="cy-chart">
                        <?php foreach (($chart ?? []) as $point): ?>
                            <?php $height = (int) round(($point['value'] / $max) * 100); ?>
                            <div class="cy-chart__bar" title="<?= e($point['label']) ?>: <?= (int) $point['value'] ?> kayıt">
                                <span class="cy-chart__fill" style="height: <?= max(3, $height) ?>%"></span>
                                <span class="cy-chart__label"><?= e($point['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="cy-card h-100">
                <div class="cy-card__header">
                    <h2 class="cy-section-title">Son Eklenenler</h2>
                    <?php if (can('users.view')): ?>
                        <a class="cy-link" href="<?= e(url('panel/kullanicilar')) ?>" style="font-size:.8125rem">Tümü</a>
                    <?php endif; ?>
                </div>
                <div class="cy-card__body cy-card__body--flush">
                    <div class="cy-list">
                        <?php foreach (($latestUsers ?? []) as $item): ?>
                            <div class="cy-list__item">
                                <?php if ($item->avatarUrl() !== ''): ?>
                                    <img class="cy-avatar cy-avatar--sm" src="<?= e($item->avatarUrl()) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="cy-avatar cy-avatar--sm cy-avatar--initial"><?= e($item->initials()) ?></span>
                                <?php endif; ?>
                                <span class="cy-list__body">
                                    <span class="cy-list__title"><?= e($item->fullName()) ?></span>
                                    <span class="cy-list__meta"><?= e(human_date($item->createdAt)) ?></span>
                                </span>
                                <span class="cy-role cy-role--<?= e(Role::variant($item->rol)) ?>"><?= e($item->roleLabel()) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <?php if (($latestUsers ?? []) === []): ?>
                            <div class="cy-empty">Henüz kullanıcı yok.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="cy-card">
        <div class="cy-card__body d-flex flex-wrap align-items-center gap-3">
            <?php if ($currentUser !== null && $currentUser->avatarUrl() !== ''): ?>
                <img class="cy-avatar cy-avatar--md" src="<?= e($currentUser->avatarUrl()) ?>" alt="">
            <?php elseif ($currentUser !== null): ?>
                <span class="cy-avatar cy-avatar--md cy-avatar--initial"><?= e($currentUser->initials()) ?></span>
            <?php endif; ?>

            <div class="flex-grow-1" style="min-width:0">
                <h2 class="cy-section-title mb-1"><?= e($currentUser?->fullName() ?? '') ?></h2>
                <p class="cy-subtitle mb-0">
                    <?= e($currentUser?->eposta ?? '') ?> ·
                    son giriş: <?= e(\App\Models\User::formatDate($currentUser?->sonGiris)) ?>
                </p>
            </div>

            <a class="btn cy-btn cy-btn--ghost" href="<?= e(url('panel/hesabim')) ?>">
                <?= icon('user', 'cy-icon cy-icon--sm') ?> Hesabımı Düzenle
            </a>
        </div>
    </div>

<?php endif; ?>
