<?php
/**
 * =====================================================================
 *  PARÇA: Üst çubuk (panel)
 * =====================================================================
 */

use App\Models\Role;

/** @var App\Models\User|null $currentUser */
$user = $currentUser ?? null;
?>
<header class="cy-topbar">

    <button type="button" class="cy-topbar__toggle" id="cy_sidebar_toggle"
            aria-label="Menüyü aç/kapat" aria-controls="cy_sidebar" aria-expanded="false">
        <?= icon('menu') ?>
    </button>

    <div class="cy-topbar__heading">
        <h1><?= e($pageTitle ?? 'Panel') ?></h1>
        <?php if (!empty($pageSubtitle)): ?>
            <p><?= e($pageSubtitle) ?></p>
        <?php endif; ?>
    </div>

    <div class="cy-topbar__actions">
        <button type="button" class="cy-topbar__toggle" id="cy_theme_toggle" aria-label="Açık/koyu tema" title="Açık/koyu tema">
            <span class="cy-theme-icon cy-theme-icon--light"><?= icon('moon') ?></span>
            <span class="cy-theme-icon cy-theme-icon--dark d-none"><?= icon('sun') ?></span>
        </button>

        <?php if ($user !== null): ?>
            <div class="dropdown">
                <button class="cy-usermenu" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <?php if ($user->avatarUrl() !== ''): ?>
                        <img class="cy-avatar cy-avatar--sm" src="<?= e($user->avatarUrl()) ?>" alt="">
                    <?php else: ?>
                        <span class="cy-avatar cy-avatar--sm cy-avatar--initial"><?= e($user->initials()) ?></span>
                    <?php endif; ?>
                    <span class="cy-usermenu__name"><?= e($user->ad) ?></span>
                </button>

                <div class="dropdown-menu dropdown-menu-end cy-dropdown">
                    <div class="cy-dropdown__header">
                        <strong><?= e($user->fullName()) ?></strong>
                        <span><?= e($user->eposta) ?></span>
                        <span class="cy-role cy-role--<?= e(Role::variant($user->rol)) ?> mt-1"><?= e($user->roleLabel()) ?></span>
                    </div>

                    <a class="dropdown-item" href="<?= e(url('panel/hesabim')) ?>">
                        <?= icon('user', 'cy-icon cy-icon--sm') ?> Hesabım
                    </a>
                    <a class="dropdown-item" href="<?= e(url('')) ?>">
                        <?= icon('globe', 'cy-icon cy-icon--sm') ?> Siteyi Görüntüle
                    </a>

                    <?php if (can('system.view')): ?>
                        <a class="dropdown-item" href="<?= e(url('panel/sistem')) ?>">
                            <?= icon('server', 'cy-icon cy-icon--sm') ?> Sistem
                        </a>
                    <?php endif; ?>

                    <hr class="cy-divider my-1">

                    <form method="post" action="<?= e(url('cikis')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item dropdown-item--danger w-100">
                            <?= icon('logout', 'cy-icon cy-icon--sm') ?> Çıkış Yap
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>
