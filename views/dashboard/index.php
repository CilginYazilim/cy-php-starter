<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Kontrol paneli
 * =====================================================================
 */

use App\Models\Role;

$stats          = $stats ?? null;
$messageStats   = $messageStats ?? null;
$mailStats      = $mailStats ?? null;
$latestMessages = $latestMessages ?? [];

/* ---------------------------------------------------------------------
 *  KARŞILAMA BANNER'I
 * ---------------------------------------------------------------------
 *  Saate göre selamlama + Türkçe uzun tarih + o günkü öne çıkan
 *  gelişmelerden bir özet cümlesi. Harici bir tarih/yerelleştirme
 *  kütüphanesi kullanmadan (bkz. proje geneli "sıfır bağımlılık"
 *  ilkesi) elle üretiyoruz.
 * ------------------------------------------------------------------ */
$saat     = (int) date('G');
$selamlama = match (true) {
    $saat < 6  => 'İyi geceler',
    $saat < 12 => 'Günaydın',
    $saat < 18 => 'İyi günler',
    default    => 'İyi akşamlar',
};

$gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
$aylar  = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$bugun  = (int) date('j') . ' ' . $aylar[(int) date('n')] . ' ' . $gunler[(int) date('w')];

$ozetParcalari = [];

if ($messageStats !== null && $messageStats['unread'] > 0) {
    $ozetParcalari[] = $messageStats['unread'] . ' okunmamış mesaj';
}
if ($mailStats !== null && $mailStats['kuyrukta'] > 0) {
    $ozetParcalari[] = $mailStats['kuyrukta'] . ' e-posta kuyrukta';
}
if ($stats !== null && (int) $stats['last7'] > 0) {
    $ozetParcalari[] = 'son 7 günde ' . $stats['last7'] . ' yeni kayıt';
}

$ozetMetni = $ozetParcalari !== [] ? implode(' · ', $ozetParcalari) . '.' : 'Her şey yolunda görünüyor.';
?>

<div class="cy-welcome">
    <div class="cy-welcome__text">
        <h2 class="cy-welcome__title"><?= e($selamlama) ?>, <?= e($currentUser?->ad ?? '') ?> 👋</h2>
        <p class="cy-welcome__meta"><?= e($bugun) ?></p>
        <p class="cy-welcome__summary"><?= e($ozetMetni) ?></p>
    </div>

    <?php if (can('users.create') || can('messages.view') || can('settings.view')): ?>
        <div class="cy-welcome__actions">
            <?php if (can('users.create')): ?>
                <a class="btn cy-btn cy-btn--on-brand" href="<?= e(url('panel/kullanicilar', ['ekle' => 1])) ?>">
                    <?= icon('plus', 'cy-icon cy-icon--sm') ?> Yeni Kullanıcı
                </a>
            <?php endif; ?>
            <?php if (can('messages.view')): ?>
                <a class="btn cy-btn cy-btn--on-brand" href="<?= e(url('panel/mesajlar')) ?>">
                    <?= icon('inbox', 'cy-icon cy-icon--sm') ?> Mesajlar
                </a>
            <?php endif; ?>
            <?php if (can('settings.view')): ?>
                <a class="btn cy-btn cy-btn--on-brand" href="<?= e(url('panel/ayarlar')) ?>">
                    <?= icon('settings', 'cy-icon cy-icon--sm') ?> Ayarlar
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($stats !== null): ?>

    <div class="cy-stats">
        <div class="cy-stat cy-stat--vivid cy-stat--brand">
            <span class="cy-stat__icon"><?= icon('users') ?></span>
            <span>
                <span class="cy-stat__label">Toplam Kullanıcı</span>
                <span class="cy-stat__value"><?= (int) $stats['total'] ?></span>
                <span class="cy-stat__hint">son 7 günde +<?= (int) $stats['last7'] ?></span>
            </span>
        </div>

        <div class="cy-stat cy-stat--vivid cy-stat--success">
            <span class="cy-stat__icon"><?= icon('check') ?></span>
            <span>
                <span class="cy-stat__label">Aktif Hesap</span>
                <span class="cy-stat__value"><?= (int) $stats['active'] ?></span>
                <span class="cy-stat__hint"><?= (int) $stats['passive'] ?> pasif/askıda</span>
            </span>
        </div>

        <?php if ($messageStats !== null): ?>
            <div class="cy-stat cy-stat--vivid cy-stat--warning">
                <span class="cy-stat__icon"><?= icon('mail') ?></span>
                <span>
                    <span class="cy-stat__label">Mesajlar</span>
                    <span class="cy-stat__value"><?= (int) $messageStats['total'] ?></span>
                    <span class="cy-stat__hint"><?= (int) $messageStats['unread'] ?> okunmamış</span>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($mailStats !== null): ?>
            <div class="cy-stat cy-stat--vivid cy-stat--<?= $mailStats['basarisiz'] > 0 ? 'danger' : 'brand' ?>">
                <span class="cy-stat__icon"><?= icon('send') ?></span>
                <span>
                    <span class="cy-stat__label">E-posta Kuyruğu</span>
                    <span class="cy-stat__value"><?= (int) $mailStats['kuyrukta'] ?></span>
                    <span class="cy-stat__hint">
                        <?= (int) $mailStats['bugun'] ?> bugün gönderildi<?= $mailStats['basarisiz'] > 0 ? ' · ' . (int) $mailStats['basarisiz'] . ' başarısız' : '' ?>
                    </span>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="cy-card h-100">
                <div class="cy-card__header">
                    <div>
                        <h2 class="cy-section-title">Kayıt Hareketi</h2>
                        <p class="cy-subtitle mb-0">Son 14 günde eklenen kullanıcılar</p>
                    </div>
                    <span class="cy-badge cy-badge--brand">
                        <?= icon('activity', 'cy-icon cy-icon--sm') ?>
                        toplam <?= (int) array_sum(array_column($chart ?? [], 'value')) ?>
                    </span>
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

    <?php if ($messageStats !== null): ?>
        <div class="row g-3 mt-0">
            <div class="col-12">
                <div class="cy-card">
                    <div class="cy-card__header">
                        <div>
                            <h2 class="cy-section-title">Son Mesajlar</h2>
                            <p class="cy-subtitle mb-0"><?= (int) $messageStats['unread'] ?> okunmamış mesaj</p>
                        </div>
                        <?php if (can('messages.view')): ?>
                            <a class="cy-link" href="<?= e(url('panel/mesajlar')) ?>" style="font-size:.8125rem">Tümü</a>
                        <?php endif; ?>
                    </div>
                    <div class="cy-card__body cy-card__body--flush">
                        <div class="cy-list">
                            <?php foreach ($latestMessages as $mesaj): ?>
                                <div class="cy-list__item">
                                    <span class="cy-avatar cy-avatar--sm cy-avatar--initial"><?= e(mb_strtoupper(mb_substr($mesaj->ad, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                                    <span class="cy-list__body">
                                        <span class="cy-list__title"><?= e($mesaj->ad) ?> — <?= e($mesaj->konu !== '' ? $mesaj->konu : $mesaj->preview(40)) ?></span>
                                        <span class="cy-list__meta"><?= e(\App\Models\Message::formatDate($mesaj->createdAt)) ?></span>
                                    </span>
                                    <?php if (!$mesaj->okundu): ?>
                                        <span class="cy-badge cy-badge--brand">Yeni</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($latestMessages === []): ?>
                                <div class="cy-empty">Henüz mesaj yok.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
