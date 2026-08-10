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

use App\Core\Modules\Modules;
use App\Core\Setting;

/** @var App\Models\User|null $currentUser */
$user = $currentUser ?? null;

$unread = can('messages.view')
    ? (new App\Repositories\MessageRepository(App\Core\Database::connection()))->countUnread()
    : 0;

/* Ayarlar tek satırda değil, konu başlıklarına göre gruplanmıştır
 * (bkz. SettingsController). Alt menü bu grupları birebir yansıtır;
 * yeni bir ayar grubu eklendiğinde menüye elle dokunmak gerekmez. */
$ayarAltMenu = [];

foreach (Setting::groupLabels() as $anahtar => $baslik) {
    $ayarAltMenu[] = [
        'route' => 'panel/ayarlar/' . $anahtar,
        'label' => $baslik,
    ];
}

/* ---------------------------------------------------------------------
 *  MODÜL MENÜSÜ
 * ---------------------------------------------------------------------
 *  Açık her modül, module.json içinde bir "menu" bloğu tanımlayarak
 *  sol menüde kendi bağlantısını gösterebilir:
 *
 *      "menu": { "route": "panel/stok", "icon": "database",
 *                "label": "Stok", "can": "stok.view" }
 *
 *  Tanımlamazsa makul bir varsayılan üretilir. Çekirdek, hangi
 *  modüllerin var olduğunu bilmez — listeyi modüllerin kendisi
 *  doldurur.
 * ------------------------------------------------------------------ */
$modulOgeleri = [];

foreach (Modules::enabled() as $modul) {
    $json = $modul->yol . DIRECTORY_SEPARATOR . 'module.json';
    $data = is_file($json) ? (json_decode((string) @file_get_contents($json), true) ?: []) : [];

    // "menu": false → modül menüde görünmek istemiyor.
    if (array_key_exists('menu', $data) && $data['menu'] === false) {
        continue;
    }

    $tanim = is_array($data['menu'] ?? null) ? $data['menu'] : [];
    $slug  = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $modul->ad) ?? $modul->ad);

    $modulOgeleri[] = [
        'route' => (string) ($tanim['route'] ?? 'panel/' . $slug),
        'icon'  => (string) ($tanim['icon']  ?? 'server'),
        'label' => (string) ($tanim['label'] ?? $modul->baslik),
        'can'   => (string) ($tanim['can']   ?? str_replace('-', '_', $slug) . '.view'),
    ];
}

$menu = [
    [
        'label' => 'Genel',
        'items' => [
            ['route' => 'panel', 'icon' => 'dashboard', 'label' => 'Kontrol Paneli', 'can' => 'dashboard.view'],
        ],
    ],
];

if ($modulOgeleri !== []) {
    $menu[] = ['label' => 'Modüller', 'items' => $modulOgeleri];
}

$menu[] = [
    'label' => 'İçerik',
    'items' => [
        ['route' => 'panel/sayfalar', 'icon' => 'files', 'label' => 'Sayfalar', 'can' => 'pages.view'],
    ],
];

$menu[] = [
    'label' => 'İletişim',
    'items' => [
        ['route' => 'panel/mesajlar', 'icon' => 'inbox', 'label' => 'Mesajlar', 'can' => 'messages.view', 'badge' => $unread],
        ['route' => 'panel/eposta',   'icon' => 'send',  'label' => 'E-posta',  'can' => 'mail.view'],
    ],
];

$menu[] = [
    'label' => 'Yönetim',
    'items' => [
        ['route' => 'panel/kullanicilar', 'icon' => 'users',    'label' => 'Kullanıcılar',   'can' => 'users.view'],
        ['route' => 'panel/ayarlar',      'icon' => 'settings', 'label' => 'Site Ayarları',  'can' => 'settings.view', 'children' => $ayarAltMenu],
        ['route' => 'panel/sistem',       'icon' => 'server',   'label' => 'Sistem Bilgisi', 'can' => 'system.view'],
    ],
];

$menu[] = [
    'label' => 'Hesabım',
    'items' => [
        ['route' => 'panel/hesabim', 'icon' => 'user', 'label' => 'Profilim', 'can' => 'profile.view'],
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
                    <?php $children = $item['children'] ?? []; ?>

                    <?php if ($children === []): ?>
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
                    <?php else: ?>
                        <?php $active = is_route($item['route']); ?>
                        <div class="cy-nav-item<?= $active ? ' is-open' : '' ?>">
                            <button type="button"
                                    class="cy-nav-link cy-nav-parent<?= $active ? ' is-active' : '' ?>"
                                    data-title="<?= e($item['label']) ?>"
                                    aria-expanded="<?= $active ? 'true' : 'false' ?>">
                                <?= icon($item['icon']) ?>
                                <span class="cy-nav-link__text"><?= e($item['label']) ?></span>
                                <?= icon('chevron', 'cy-icon cy-icon--sm cy-nav-parent__chevron') ?>
                            </button>

                            <div class="cy-nav-submenu">
                                <div class="cy-nav-submenu__inner">
                                    <a class="cy-nav-sublink<?= App\Core\Url::current() === trim($item['route'], '/') ? ' is-active' : '' ?>"
                                       href="<?= e(url($item['route'])) ?>">Genel Bakış</a>

                                    <?php foreach ($children as $child): ?>
                                        <a class="cy-nav-sublink<?= is_route($child['route']) ? ' is-active' : '' ?>"
                                           href="<?= e(url($child['route'])) ?>"
                                           <?= is_route($child['route']) ? 'aria-current="page"' : '' ?>>
                                            <?= e($child['label']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
