<?php
/**
 * =====================================================================
 *  PARÇA: Sol menü (panel)
 * ---------------------------------------------------------------------
 *  Menü öğeleri bir DİZİDEN üretilir; her öğe bir yetkiye bağlıdır.
 *  Asıl koruma routes/web.php'deki ara katmanlardadır; menü gizleme
 *  yalnızca arayüz düzenidir.
 * =====================================================================
 */

use App\Core\Setting;

/** @var App\Models\User|null $currentUser */
$user = $currentUser ?? null;

$unread = can('messages.view')
    ? (new App\Repositories\MessageRepository(App\Core\Database::connection()))->countUnread()
    : 0;

$menu = [
    [
        'label' => 'Genel',
        'items' => [
            ['route' => 'panel', 'icon' => 'dashboard', 'label' => 'Kontrol Paneli', 'can' => 'dashboard.view'],
        ],
    ],
    [
        'label' => 'Yönetim',
        'items' => [
            ['route' => 'panel/kullanicilar', 'icon' => 'users', 'label' => 'Kullanıcılar', 'can' => 'users.view'],
            ['route' => 'panel/mesajlar', 'icon' => 'mail', 'label' => 'Mesajlar', 'can' => 'messages.view', 'badge' => $unread],
            ['route' => 'panel/ayarlar', 'icon' => 'settings', 'label' => 'Site Ayarları', 'can' => 'settings.view'],
            ['route' => 'panel/sistem', 'icon' => 'server', 'label' => 'Sistem Bilgisi', 'can' => 'system.view'],
        ],
    ],
    [
        'label' => 'Hesabım',
        'items' => [
            ['route' => 'panel/hesabim', 'icon' => 'user', 'label' => 'Profilim', 'can' => 'profile.view'],
        ],
    ],
];
?>
<aside class="cy-sidebar" id="cy_sidebar" aria-label="Ana gezinme">

    <a class="cy-sidebar__brand" href="<?= e(url('panel')) ?>">
        <span class="cy-sidebar__logo">
            <img src="<?= e(Setting::logoUrl()) ?>" alt="">
        </span>
        <span class="cy-sidebar__title">
            <strong><?= e(Setting::get('site_adi', $appName ?? '')) ?></strong>
            <span><?= e($appBrand ?? 'Çılgın Yazılım') ?></span>
        </span>
    </a>

    <nav class="cy-sidebar__nav">
        <?php foreach ($menu as $group): ?>
            <?php
            $visible = array_filter($group['items'], static fn (array $item): bool => $item['can'] === null || can($item['can']));

            if ($visible === []) {
                continue;
            }
            ?>
            <div class="cy-nav-group">
                <span class="cy-nav-group__label"><?= e($group['label']) ?></span>

                <?php foreach ($visible as $item): ?>
                    <a class="cy-nav-link<?= is_route($item['route']) ? ' is-active' : '' ?>"
                       href="<?= e(url($item['route'])) ?>"
                       data-title="<?= e($item['label']) ?>"
                       <?= is_route($item['route']) ? 'aria-current="page"' : '' ?>>
                        <?= icon($item['icon']) ?>
                        <span class="cy-nav-link__text"><?= e($item['label']) ?></span>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="cy-nav-link__badge"><?= (int) $item['badge'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <?php if ($user !== null): ?>
        <div class="cy-sidebar__footer">
            <a class="cy-side-user text-decoration-none" href="<?= e(url('panel/hesabim')) ?>">
                <?php if ($user->avatarUrl() !== ''): ?>
                    <img class="cy-avatar cy-avatar--sm" src="<?= e($user->avatarUrl()) ?>" alt="">
                <?php else: ?>
                    <span class="cy-avatar cy-avatar--sm cy-avatar--initial"><?= e($user->initials()) ?></span>
                <?php endif; ?>

                <span class="cy-side-user__info">
                    <span class="cy-side-user__name"><?= e($user->fullName()) ?></span>
                    <span class="cy-side-user__role"><?= e($user->roleLabel()) ?></span>
                </span>
            </a>
        </div>
    <?php endif; ?>
</aside>
