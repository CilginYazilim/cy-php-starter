<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Sistem Bilgisi
 * ---------------------------------------------------------------------
 *  Mobilde tek sütun, tablette iki, masaüstünde üç sütuna açılır.
 *  Tablolar taşarsa yatay kaydırılır (cy-table-wrap) — sayfanın
 *  kendisi ASLA yatay kaymaz.
 *
 *  @var array<int,array<string,string>> $ozet
 *  @var array<string,string> $uygulama, $sunucu, $altyapi
 *  @var array<int,array{ad:string,yol:string,ok:bool}> $klasorler
 *  @var array<int,array{ad:string,ok:bool,not:string}> $eklentiler
 *  @var array<int,array{label:string,ok:bool,detail:string}> $checks
 *  @var array<string,App\Core\Modules\Module> $moduller
 * =====================================================================
 */

$ozet           = $ozet ?? [];
$checks         = $checks ?? [];
$ayarChecks     = $ayarChecks ?? [];
$moduller       = $moduller ?? [];
$basarisizIsler = $basarisizIsler ?? [];

$sorunlu     = array_filter($checks, static fn (array $c): bool => !$c['ok']);
$ayarSorunlu = array_filter($ayarChecks, static fn (array $c): bool => !$c['ok']);
?>

<?php /* SAYFA BAŞLIĞI ÜST ÇUBUKTA yazar; burada tekrar edilmez. */ ?>

<!-- ÖZET KARTLARI -->
<div class="cy-stats">
    <?php foreach ($ozet as $kart): ?>
        <div class="cy-stat">
            <span class="cy-stat__icon cy-stat__icon--<?= e($kart['renk']) ?>"><?= icon($kart['ikon']) ?></span>
            <span>
                <span class="cy-stat__label"><?= e($kart['etiket']) ?></span>
                <span class="cy-stat__value"><?= e($kart['deger']) ?></span>
                <span class="cy-stat__hint"><?= e($kart['ipucu']) ?></span>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($sorunlu !== [] || $ayarSorunlu !== []): ?>
    <div class="cy-alert cy-alert--warning mb-3">
        <strong><?= count($sorunlu) + count($ayarSorunlu) ?> konu dikkatinizi bekliyor.</strong>
        Aşağıdaki listelerde turuncu işaretli maddelere bakın — her biri
        sorunun nasıl çözüleceğini de yazıyor.

        <?php if ($ayarSorunlu !== []): ?>
            <span class="d-block mt-2">
                <strong>Eksik yapılandırma:</strong>
                <?= e(implode(' · ', array_map(static fn (array $c): string => $c['label'], $ayarSorunlu))) ?>
            </span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- YAPILANDIRMA DENETİMİ
     Güvenlik denetiminden ayrı: orası "sunucu güvenli mi?", burası
     "site kullanılabilir durumda mı?" sorusunu yanıtlar. Her madde
     düzeltmenin yapılacağı ekrana bağlantı verir. -->
<?php if ($ayarChecks !== []): ?>
    <div class="cy-card mb-3">
        <div class="cy-card__header">
            <div>
                <h3 class="cy-section-title mb-0"><?= icon('settings', 'cy-icon cy-icon--sm') ?> Yapılandırma Denetimi</h3>
                <p class="cy-muted small mb-0">Yayına çıkmadan önce tamamlanması gerekenler.</p>
            </div>
            <span class="cy-badge <?= $ayarSorunlu === [] ? 'cy-badge--success' : 'cy-badge--warning' ?>">
                <?= count($ayarChecks) - count($ayarSorunlu) ?> / <?= count($ayarChecks) ?> tamam
            </span>
        </div>
        <div class="cy-card__body">
            <ul class="cy-checklist">
                <?php foreach ($ayarChecks as $check): ?>
                    <li class="cy-checklist__item<?= $check['ok'] ? '' : ' is-warning' ?>">
                        <span class="cy-checklist__mark">
                            <?= icon($check['ok'] ? 'check' : 'alert', 'cy-icon cy-icon--sm') ?>
                        </span>
                        <span class="cy-checklist__body">
                            <strong><?= e($check['label']) ?></strong>
                            <span><?= e($check['detail']) ?></span>

                            <?php if (!$check['ok'] && ($check['yol'] ?? '') !== ''): ?>
                                <a class="cy-link small d-inline-block mt-1" href="<?= e(url($check['yol'])) ?>">
                                    <?= e($check['baglanti']) ?> <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
                                </a>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- GÜVENLİK DENETİMİ -->
    <div class="col-12 col-xl-7">
        <div class="cy-card h-100">
            <div class="cy-card__header">
                <h3 class="cy-section-title mb-0"><?= icon('shield', 'cy-icon cy-icon--sm') ?> Güvenlik Denetimi</h3>
            </div>
            <div class="cy-card__body">
                <ul class="cy-checklist">
                    <?php foreach ($checks as $check): ?>
                        <li class="cy-checklist__item<?= $check['ok'] ? '' : ' is-warning' ?>">
                            <span class="cy-checklist__mark">
                                <?= icon($check['ok'] ? 'check' : 'alert', 'cy-icon cy-icon--sm') ?>
                            </span>
                            <span class="cy-checklist__body">
                                <strong><?= e($check['label']) ?></strong>
                                <span><?= e($check['detail']) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="row g-3">

            <!-- UYGULAMA -->
            <div class="col-12 col-md-6 col-xl-12">
                <div class="cy-card">
                    <div class="cy-card__header">
                        <h3 class="cy-section-title mb-0"><?= icon('dashboard', 'cy-icon cy-icon--sm') ?> Uygulama</h3>
                    </div>
                    <div class="cy-card__body">
                        <dl class="cy-detail cy-detail--compact mb-0">
                            <?php foreach (($uygulama ?? []) as $etiket => $deger): ?>
                                <dt><?= e($etiket) ?></dt>
                                <dd><?= e($deger) ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- SUNUCU -->
            <div class="col-12 col-md-6 col-xl-12">
                <div class="cy-card">
                    <div class="cy-card__header">
                        <h3 class="cy-section-title mb-0"><?= icon('server', 'cy-icon cy-icon--sm') ?> Sunucu</h3>
                    </div>
                    <div class="cy-card__body">
                        <dl class="cy-detail cy-detail--compact mb-0">
                            <?php foreach (($sunucu ?? []) as $etiket => $deger): ?>
                                <dt><?= e($etiket) ?></dt>
                                <dd><?= e($deger) ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ALTYAPI -->
    <div class="col-12 col-lg-6">
        <div class="cy-card h-100">
            <div class="cy-card__header">
                <h3 class="cy-section-title mb-0"><?= icon('activity', 'cy-icon cy-icon--sm') ?> Altyapı</h3>
            </div>
            <div class="cy-card__body">
                <dl class="cy-detail cy-detail--compact mb-0">
                    <?php foreach (($altyapi ?? []) as $etiket => $deger): ?>
                        <dt><?= e($etiket) ?></dt>
                        <dd><?= e($deger) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- KLASÖR İZİNLERİ -->
    <div class="col-12 col-lg-6">
        <div class="cy-card h-100">
            <div class="cy-card__header">
                <h3 class="cy-section-title mb-0"><?= icon('lock', 'cy-icon cy-icon--sm') ?> Yazma İzinleri</h3>
            </div>
            <div class="cy-card__body cy-card__body--flush">
                <div class="cy-table-wrap">
                    <table class="table cy-table cy-table--tight w-100">
                        <tbody>
                            <?php foreach (($klasorler ?? []) as $klasor): ?>
                                <tr>
                                    <td style="width:34%"><?= e($klasor['ad']) ?></td>
                                    <td><code class="cy-mono"><?= e($klasor['yol']) ?></code></td>
                                    <td class="text-end" style="width:110px">
                                        <span class="cy-status <?= $klasor['ok'] ? 'is-active' : 'is-passive' ?>">
                                            <span class="cy-status__dot"></span>
                                            <?= $klasor['ok'] ? 'Yazılabilir' : 'İzin yok' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PHP EKLENTİLERİ -->
    <div class="col-12 col-lg-6">
        <div class="cy-card h-100">
            <div class="cy-card__header">
                <h3 class="cy-section-title mb-0"><?= icon('database', 'cy-icon cy-icon--sm') ?> PHP Eklentileri</h3>
            </div>
            <div class="cy-card__body cy-card__body--flush">
                <div class="cy-table-wrap">
                    <table class="table cy-table cy-table--tight w-100">
                        <tbody>
                            <?php foreach (($eklentiler ?? []) as $eklenti): ?>
                                <tr>
                                    <td style="width:120px"><code class="cy-mono"><?= e($eklenti['ad']) ?></code></td>
                                    <td class="cy-cell-muted"><?= e($eklenti['not']) ?></td>
                                    <td class="text-end" style="width:60px">
                                        <?= icon($eklenti['ok'] ? 'check' : 'alert',
                                                 'cy-icon cy-icon--sm ' . ($eklenti['ok'] ? 'text-success' : 'text-warning')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- KUYRUK (İŞLER TABLOSU) -->
    <div class="col-12 col-lg-6">
        <div class="cy-card h-100">
            <div class="cy-card__header">
                <h3 class="cy-section-title mb-0"><?= icon('clock', 'cy-icon cy-icon--sm') ?> Kuyruk — Başarısız İşler</h3>
                <?php if (can('system.manage') && $basarisizIsler !== []): ?>
                    <div class="d-flex gap-2">
                        <form method="post" action="<?= e(url('panel/sistem/kuyruk/tekrar')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm">
                                <?= icon('refresh', 'cy-icon cy-icon--sm') ?> Tümünü Yeniden Dene
                            </button>
                        </form>
                        <form method="post" action="<?= e(url('panel/sistem/kuyruk/temizle')) ?>"
                              data-confirm="Tüm başarısız işler kalıcı olarak silinsin mi?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm">
                                <?= icon('trash', 'cy-icon cy-icon--sm') ?> Temizle
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <div class="cy-card__body<?= $basarisizIsler === [] ? '' : ' cy-card__body--flush' ?>">
                <?php if ($basarisizIsler === []): ?>
                    <p class="cy-muted small mb-0">Başarısız iş yok — kuyruk temiz.</p>
                <?php else: ?>
                    <div class="cy-table-wrap">
                        <table class="table cy-table cy-table--tight w-100">
                            <tbody>
                                <?php foreach ($basarisizIsler as $is): ?>
                                    <tr>
                                        <td>
                                            <span class="cy-user-cell__name"><?= e((string) $is['sinif']) ?></span>
                                            <span class="cy-user-cell__meta"><?= e((string) $is['hata']) ?></span>
                                        </td>
                                        <td class="cy-cell-muted cy-nowrap" style="width:80px">
                                            <?= (int) $is['deneme'] ?>/<?= (int) $is['max_deneme'] ?> deneme
                                        </td>
                                        <td class="cy-cell-muted cy-nowrap text-end" style="width:130px">
                                            <?= e(\App\Models\User::formatDate((string) $is['created_at'])) ?>
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

    <!-- MODÜLLER -->
    <div class="col-12 col-lg-6">
        <div class="cy-card h-100">
            <div class="cy-card__header">
                <h3 class="cy-section-title mb-0"><?= icon('server', 'cy-icon cy-icon--sm') ?> Modüller</h3>
            </div>
            <div class="cy-card__body<?= $moduller === [] ? '' : ' cy-card__body--flush' ?>">
                <?php if ($moduller === []): ?>
                    <p class="cy-muted small mb-0">
                        Henüz modül yok. Oluşturmak için:
                        <code class="cy-mono">php cy make:module Stok</code>
                    </p>
                <?php else: ?>
                    <div class="cy-table-wrap">
                        <table class="table cy-table cy-table--tight w-100">
                            <tbody>
                                <?php foreach ($moduller as $modul): ?>
                                    <tr>
                                        <td>
                                            <span class="cy-user-cell__name"><?= e($modul->baslik) ?></span>
                                            <span class="cy-user-cell__meta"><?= e($modul->aciklama) ?></span>
                                        </td>
                                        <td class="cy-cell-muted cy-nowrap" style="width:70px">v<?= e($modul->surum) ?></td>
                                        <td class="text-end" style="width:90px">
                                            <span class="cy-status <?= $modul->aktif ? 'is-active' : 'is-passive' ?>">
                                                <span class="cy-status__dot"></span>
                                                <?= $modul->aktif ? 'Açık' : 'Kapalı' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="cy-card__footer">
                <span class="cy-muted small">
                    Açma/kapatma: <code class="cy-mono">php cy module --enable=Ad</code>
                </span>
            </div>
        </div>
    </div>

</div>
