<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Kontrol paneli
 * ---------------------------------------------------------------------
 *  SADE DÜZEN: renkli gradyan şerit ve "canlı" kartlar kaldırıldı.
 *  Selamlama tek satır, sayılar düz kartlarda; her sayı ilgili sayfaya
 *  bağlantıdır. Göz önce rakama gider, süsleme dikkat dağıtmaz.
 *
 *  İstatistik yetkisi olmayan roller (editör, üye) bir boş sayfa
 *  yerine hesap özetini ve erişebildikleri bölümlerin kısayollarını
 *  görür — modüllerin sayfaları dahil.
 * =====================================================================
 */

use App\Core\Modules\Modules;
use App\Models\Role;

$currentUser    = $currentUser ?? null;
$stats          = $stats ?? null;
$messageStats   = $messageStats ?? null;
$mailStats      = $mailStats ?? null;
$latestMessages = $latestMessages ?? [];
$chart          = $chart ?? [];

/* ---------------------------------------------------------------------
 *  SELAMLAMA
 * ---------------------------------------------------------------------
 *  Saate göre selamlama + Türkçe uzun tarih + günün özeti. Harici bir
 *  tarih kütüphanesi kullanmadan (sıfır bağımlılık ilkesi) üretilir.
 * ------------------------------------------------------------------ */
$saat      = (int) date('G');
$selamlama = match (true) {
    $saat < 6  => 'İyi geceler',
    $saat < 12 => 'Günaydın',
    $saat < 18 => 'İyi günler',
    default    => 'İyi akşamlar',
};

$gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
$aylar  = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$bugun  = (int) date('j') . ' ' . $aylar[(int) date('n')] . ' ' . $gunler[(int) date('w')];

$ozetParcalari = [$bugun];

if ($messageStats !== null && $messageStats['unread'] > 0) {
    $ozetParcalari[] = $messageStats['unread'] . ' okunmamış mesaj';
}
if ($mailStats !== null && $mailStats['kuyrukta'] > 0) {
    $ozetParcalari[] = $mailStats['kuyrukta'] . ' e-posta kuyrukta';
}
if ($stats !== null && (int) $stats['last7'] > 0) {
    $ozetParcalari[] = 'son 7 günde ' . $stats['last7'] . ' yeni kayıt';
}

/* ---------------------------------------------------------------------
 *  KISAYOLLAR — rolün erişebildiği bölümler
 * ---------------------------------------------------------------------
 *  Modüllerin menü tanımı (module.json → "menu") burada da kullanılır:
 *  yeni bir modül açıldığında kontrol panelinde kendiliğinden görünür.
 * ------------------------------------------------------------------ */
$kisayollar = [];

foreach (Modules::enabled() as $modul) {
    if ($modul->menu !== null && can($modul->menu['can'])) {
        $kisayollar[] = [
            'yol'      => $modul->menu['route'],
            'ikon'     => $modul->menu['icon'],
            'baslik'   => $modul->menu['label'],
            'aciklama' => $modul->aciklama,
        ];
    }
}

$cekirdek = [
    ['pages.view',    'panel/sayfalar',     'files',    'Sayfalar',  'Hakkımızda gibi içerik sayfalarını düzenleyin.'],
    ['messages.view', 'panel/mesajlar',     'inbox',    'Mesajlar',  'İletişim formundan gelen mesajlar.'],
    ['users.view',    'panel/kullanicilar', 'users',    'Kullanıcılar', 'Hesaplar, roller ve durumlar.'],
    ['profile.view',  'panel/hesabim',      'user',     'Hesabım',   'Bilgileriniz, parolanız ve oturumlarınız.'],
];

foreach ($cekirdek as [$yetki, $yol, $ikon, $baslik, $aciklama]) {
    if (can($yetki)) {
        $kisayollar[] = ['yol' => $yol, 'ikon' => $ikon, 'baslik' => $baslik, 'aciklama' => $aciklama];
    }
}
?>

<div class="cy-hello">
    <div class="cy-hello__text">
        <h2 class="cy-hello__title"><?= e($selamlama) ?>, <?= e($currentUser?->ad ?? '') ?></h2>
        <p class="cy-hello__meta"><?= e(implode(' · ', $ozetParcalari)) ?></p>
    </div>

    <?php if (can('users.create') || can('settings.view')): ?>
        <div class="cy-hello__actions">
            <?php if (can('users.create')): ?>
                <a class="btn cy-btn cy-btn--ghost cy-btn--sm" href="<?= e(url('panel/kullanicilar', ['ekle' => 1])) ?>">
                    <?= icon('plus', 'cy-icon cy-icon--sm') ?> Yeni Kullanıcı
                </a>
            <?php endif; ?>
            <?php if (can('settings.view')): ?>
                <a class="btn cy-btn cy-btn--ghost cy-btn--sm" href="<?= e(url('panel/ayarlar')) ?>">
                    <?= icon('settings', 'cy-icon cy-icon--sm') ?> Ayarlar
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($stats !== null): ?>

    <?php
    /* Her kart ilgili sayfaya gider; yetki yoksa düz kutu kalır. */
    $kartlar = [
        ['users',  'brand',   'Kullanıcı',  (int) $stats['total'],  'son 7 günde +' . (int) $stats['last7'], can('users.view') ? 'panel/kullanicilar' : ''],
        ['check',  'success', 'Aktif hesap', (int) $stats['active'], (int) $stats['passive'] . ' pasif / askıda', can('users.view') ? 'panel/kullanicilar' : ''],
    ];

    if ($messageStats !== null) {
        $kartlar[] = ['inbox', $messageStats['unread'] > 0 ? 'warning' : 'brand', 'Mesaj', (int) $messageStats['total'], (int) $messageStats['unread'] . ' okunmamış', 'panel/mesajlar'];
    }

    if ($mailStats !== null) {
        $kartlar[] = [
            'send',
            $mailStats['basarisiz'] > 0 ? 'danger' : 'brand',
            'E-posta kuyruğu',
            (int) $mailStats['kuyrukta'],
            (int) $mailStats['bugun'] . ' bugün gitti' . ($mailStats['basarisiz'] > 0 ? ' · ' . (int) $mailStats['basarisiz'] . ' başarısız' : ''),
            'panel/eposta',
        ];
    }
    ?>

    <div class="cy-stats">
        <?php foreach ($kartlar as [$ikon, $renk, $etiket, $deger, $ipucu, $yol]): ?>
            <?php $etiketAdi = $yol !== '' ? 'a' : 'div'; ?>
            <<?= $etiketAdi ?> class="cy-stat<?= $yol !== '' ? ' cy-stat--link' : '' ?>"<?= $yol !== '' ? ' href="' . e(url($yol)) . '"' : '' ?>>
                <span class="cy-stat__icon cy-stat__icon--<?= e($renk) ?>"><?= icon($ikon) ?></span>
                <span>
                    <span class="cy-stat__label"><?= e($etiket) ?></span>
                    <span class="cy-stat__value"><?= $deger ?></span>
                    <span class="cy-stat__hint"><?= e($ipucu) ?></span>
                </span>
            </<?= $etiketAdi ?>>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <section class="cy-panel cy-panel--fill">
                <header class="cy-panel__head">
                    <div>
                        <h3 class="cy-panel__title"><?= icon('activity', 'cy-icon cy-icon--sm') ?> Kayıt hareketi</h3>
                        <p class="cy-panel__sub">Son 14 günde eklenen kullanıcılar</p>
                    </div>
                    <span class="cy-pill">toplam <?= (int) array_sum(array_column($chart, 'value')) ?></span>
                </header>

                <?php $max = max([1, ...array_column($chart, 'value')]); ?>
                <div class="cy-chart">
                    <?php foreach ($chart as $point): ?>
                        <?php $height = (int) round(($point['value'] / $max) * 100); ?>
                        <div class="cy-chart__bar" title="<?= e($point['label']) ?>: <?= (int) $point['value'] ?> kayıt">
                            <?php if ($point['value'] > 0): ?>
                                <span class="cy-chart__value"><?= (int) $point['value'] ?></span>
                            <?php endif; ?>
                            <span class="cy-chart__fill<?= $point['value'] === 0 ? ' is-zero' : '' ?>" style="height: <?= max(2, $height) ?>%"></span>
                            <span class="cy-chart__label"><?= e($point['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="cy-panel cy-panel--fill">
                <header class="cy-panel__head">
                    <h3 class="cy-panel__title"><?= icon('users', 'cy-icon cy-icon--sm') ?> Son eklenenler</h3>
                    <?php if (can('users.view')): ?>
                        <a class="cy-link small" href="<?= e(url('panel/kullanicilar')) ?>">Tümü →</a>
                    <?php endif; ?>
                </header>
                <ul class="cy-people">
                    <?php foreach (($latestUsers ?? []) as $item): ?>
                        <li class="cy-people__row">
                            <?php if ($item->avatarUrl() !== ''): ?>
                                <img class="cy-avatar cy-avatar--sm" src="<?= e($item->avatarUrl()) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="cy-avatar cy-avatar--sm cy-avatar--initial"><?= e($item->initials()) ?></span>
                            <?php endif; ?>
                            <span class="cy-people__body">
                                <strong><?= e($item->fullName()) ?></strong>
                                <small><?= e(human_date($item->createdAt)) ?></small>
                            </span>
                            <span class="cy-role cy-role--<?= e(Role::variant($item->rol)) ?>"><?= e($item->roleLabel()) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (($latestUsers ?? []) === []): ?>
                    <p class="cy-panel__empty">Henüz kullanıcı yok.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <?php if ($messageStats !== null): ?>
        <section class="cy-panel mt-3">
            <header class="cy-panel__head">
                <div>
                    <h3 class="cy-panel__title"><?= icon('inbox', 'cy-icon cy-icon--sm') ?> Son mesajlar</h3>
                    <p class="cy-panel__sub"><?= (int) $messageStats['unread'] ?> okunmamış</p>
                </div>
                <a class="cy-link small" href="<?= e(url('panel/mesajlar')) ?>">Tümü →</a>
            </header>
            <ul class="cy-people">
                <?php foreach ($latestMessages as $mesaj): ?>
                    <li class="cy-people__row">
                        <span class="cy-avatar cy-avatar--sm cy-avatar--initial"><?= e(mb_strtoupper(mb_substr($mesaj->ad, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                        <span class="cy-people__body">
                            <strong><?= e($mesaj->ad) ?> <span class="cy-people__muted">— <?= e($mesaj->konu !== '' ? $mesaj->konu : $mesaj->preview(40)) ?></span></strong>
                            <small><?= e(\App\Models\Message::formatDate($mesaj->createdAt)) ?></small>
                        </span>
                        <?php if (!$mesaj->okundu): ?>
                            <span class="cy-pill is-info">Yeni</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($latestMessages === []): ?>
                <p class="cy-panel__empty">Henüz mesaj yok.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

<?php else: ?>

    <section class="cy-panel mb-3">
        <div class="cy-me">
            <?php if ($currentUser !== null && $currentUser->avatarUrl() !== ''): ?>
                <img class="cy-avatar cy-avatar--md" src="<?= e($currentUser->avatarUrl()) ?>" alt="">
            <?php elseif ($currentUser !== null): ?>
                <span class="cy-avatar cy-avatar--md cy-avatar--initial"><?= e($currentUser->initials()) ?></span>
            <?php endif; ?>

            <div class="cy-me__body">
                <strong><?= e($currentUser?->fullName() ?? '') ?></strong>
                <small>
                    <?php if ($currentUser !== null): ?>
                        <span class="cy-role cy-role--<?= e(Role::variant($currentUser->rol)) ?>"><?= e($currentUser->roleLabel()) ?></span>
                    <?php endif; ?>
                    <?= e($currentUser?->eposta ?? '') ?> · son giriş <?= e(\App\Models\User::formatDate($currentUser?->sonGiris)) ?>
                </small>
            </div>
        </div>
    </section>

<?php endif; ?>

<?php if ($kisayollar !== [] && $stats === null): ?>
    <h3 class="cy-section-label">Erişebildiğiniz bölümler</h3>
    <div class="cy-shortcuts">
        <?php foreach ($kisayollar as $kisayol): ?>
            <a class="cy-shortcut" href="<?= e(url($kisayol['yol'])) ?>">
                <span class="cy-shortcut__icon"><?= icon($kisayol['ikon'], 'cy-icon cy-icon--sm') ?></span>
                <span class="cy-shortcut__body">
                    <strong><?= e($kisayol['baslik']) ?></strong>
                    <?php if ($kisayol['aciklama'] !== ''): ?>
                        <small><?= e($kisayol['aciklama']) ?></small>
                    <?php endif; ?>
                </span>
                <?= icon('chevron', 'cy-icon cy-icon--sm cy-shortcut__go') ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
