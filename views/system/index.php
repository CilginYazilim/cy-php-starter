<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Sistem Bilgisi
 * ---------------------------------------------------------------------
 *  SADE KARTLAR (cy-panel): başlık çizgisi yok, içerik kendi
 *  yüksekliğinde. Eskiden kartlar yanındakinin boyuna gerilirdi
 *  (h-100); tek satırlık "kuyruk temiz" yazısı koca bir boş kutuda
 *  kalıyor, sayfa bozuk görünüyordu. Bilgi kartları artık sütunlara
 *  akar (cy-masonry): mobilde bir, tablette iki, geniş ekranda üç.
 *
 *  Denetim listelerinde UYARILAR önce ve açıklamasıyla gelir; geçen
 *  maddeler tek satırdır (açıklaması üzerine gelince görünür).
 *
 *  @var array<int,array<string,string>> $ozet
 *  @var array<string,string> $uygulama, $sunucu, $altyapi
 *  @var array<int,array{ad:string,yol:string,ok:bool}> $klasorler
 *  @var array<int,array{ad:string,ok:bool,not:string}> $eklentiler
 *  @var array<int,array{label:string,ok:bool,detail:string}> $checks
 *  @var array<string,App\Core\Modules\Module> $moduller
 *  @var array<string,mixed> $kuyruklar  bkz. SystemController::queues()
 * =====================================================================
 */

$ozet                 = $ozet ?? [];
$bekleyenMigrationlar = $bekleyenMigrationlar ?? [];
$checks               = $checks ?? [];
$ayarChecks           = $ayarChecks ?? [];
$moduller             = $moduller ?? [];
$kuyruklar            = $kuyruklar ?? [];

$isler              = $kuyruklar['isler'] ?? ['bekleyen' => 0, 'calisan' => 0, 'basarisiz' => 0];
$eposta             = $kuyruklar['eposta'] ?? ['kuyrukta' => 0, 'bugun' => 0, 'basarisiz' => 0];
$basarisizIsler     = $kuyruklar['basarisizIsler'] ?? [];
$basarisizEpostalar = $kuyruklar['basarisizEpostalar'] ?? [];
$basarisizToplam    = (int) $isler['basarisiz'] + (int) $eposta['basarisiz'];

/* Uyarılar önce, geçenler sonra; kendi içlerinde sıra korunur. */
$sirala = static function (array $liste): array {
    usort($liste, static fn (array $a, array $b): int => (int) $a['ok'] <=> (int) $b['ok']);

    return $liste;
};

/** Denetim kartı: başlık, "x / y" rozeti ve sade liste. */
$denetimKarti = static function (string $baslik, string $ikon, string $aciklama, array $liste) use ($sirala): void {
    $sorunlu = count(array_filter($liste, static fn (array $c): bool => !$c['ok']));
    ?>
    <section class="cy-panel">
        <header class="cy-panel__head">
            <div>
                <h3 class="cy-panel__title"><?= icon($ikon, 'cy-icon cy-icon--sm') ?> <?= e($baslik) ?></h3>
                <p class="cy-panel__sub"><?= e($aciklama) ?></p>
            </div>
            <span class="cy-pill <?= $sorunlu === 0 ? 'is-ok' : 'is-warn' ?>">
                <?= count($liste) - $sorunlu ?> / <?= count($liste) ?>
            </span>
        </header>
        <ul class="cy-checks">
            <?php foreach ($sirala($liste) as $check): ?>
                <?php if ($check['ok']): ?>
                    <li class="cy-checks__item is-ok" title="<?= e($check['detail']) ?>">
                        <?= icon('check', 'cy-icon cy-icon--sm') ?>
                        <span><?= e($check['label']) ?></span>
                    </li>
                <?php else: ?>
                    <li class="cy-checks__item is-warn">
                        <?= icon('alert', 'cy-icon cy-icon--sm') ?>
                        <span>
                            <strong><?= e($check['label']) ?></strong>
                            <small><?= e($check['detail']) ?></small>
                            <?php if (($check['yol'] ?? '') !== ''): ?>
                                <a class="cy-link" href="<?= e(url($check['yol'])) ?>"><?= e($check['baglanti']) ?> →</a>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
};

/** Etiket / değer kartı. */
$bilgiKarti = static function (string $baslik, string $ikon, array $satirlar): void {
    ?>
    <section class="cy-panel">
        <header class="cy-panel__head">
            <h3 class="cy-panel__title"><?= icon($ikon, 'cy-icon cy-icon--sm') ?> <?= e($baslik) ?></h3>
        </header>
        <dl class="cy-kv">
            <?php foreach ($satirlar as $etiket => $deger): ?>
                <div class="cy-kv__row">
                    <dt><?= e((string) $etiket) ?></dt>
                    <dd><?= e((string) $deger) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>
    <?php
};
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

<?php if ($bekleyenMigrationlar !== []): ?>
    <?php /* SSH'siz hostlar için: komut satırına gerek kalmadan çalıştırılır
             (bkz. SystemController::migrate). */ ?>
    <section class="cy-panel cy-panel--warn mb-3" id="migration">
        <header class="cy-panel__head">
            <div>
                <h3 class="cy-panel__title"><?= icon('server', 'cy-icon cy-icon--sm') ?> <?= count($bekleyenMigrationlar) ?> migration bekliyor</h3>
                <p class="cy-panel__sub">
                    Kod güncellendi ama veritabanı henüz güncellenmedi. Sunucuda <code>php cy migrate</code>
                    çalıştırın ya da düğmeyi kullanın; önce veritabanının yedeğini alın.
                </p>
            </div>
            <?php if (can('system.manage')): ?>
                <form method="post" action="<?= e(url('panel/sistem/migrate')) ?>"
                      data-confirm="<?= count($bekleyenMigrationlar) ?> migration çalıştırılacak. Veritabanı yedeğiniz var mı?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--sm"><?= icon('check', 'cy-icon cy-icon--sm') ?> Çalıştır</button>
                </form>
            <?php endif; ?>
        </header>
        <ul class="cy-list">
            <?php foreach ($bekleyenMigrationlar as $ad): ?>
                <li class="cy-list__row"><code class="cy-mono"><?= e((string) $ad) ?></code></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<!-- DENETİMLER
     Yapılandırma: "site kullanılabilir durumda mı?" · Güvenlik: "sunucu
     güvenli mi?". Her uyarı düzeltmenin yapılacağı ekrana bağlantı verir. -->
<div class="row g-3 mb-3">
    <?php if ($ayarChecks !== []): ?>
        <div class="col-12 col-xl-6">
            <?php $denetimKarti('Yapılandırma', 'settings', 'Yayına çıkmadan önce tamamlanması gerekenler.', $ayarChecks); ?>
        </div>
    <?php endif; ?>
    <div class="col-12 <?= $ayarChecks !== [] ? 'col-xl-6' : '' ?>">
        <?php $denetimKarti('Güvenlik', 'shield', 'Sunucu ve uygulama ayarlarının güvenlik denetimi.', $checks); ?>
    </div>
</div>

<!-- KURULUM VE ÖRNEK VERİ — yalnızca yapılacak bir iş varsa görünür. -->
<?php
$kurulumKlasoru = $kurulumKlasoru ?? false;
$ornekVeri      = $ornekVeri ?? false;
$bakimKilidi    = !can('system.manage') ? 'Bu işlemler için yetkiniz yok.' : \App\Core\Demo::lockReason('panel/sistem/kurulum-sil');
?>
<?php if ($kurulumKlasoru || $ornekVeri): ?>
    <section class="cy-panel mb-3" id="kurulum">
        <header class="cy-panel__head">
            <div>
                <h3 class="cy-panel__title"><?= icon('settings', 'cy-icon cy-icon--sm') ?> Kurulum ve örnek veri</h3>
                <p class="cy-panel__sub">Yayına çıkmadan önce kurulum klasörünü silin; örnek veriyle kurduysanız gerçek projeye geçerken kaldırın.</p>
            </div>
        </header>
        <ul class="cy-modules">
            <?php if ($kurulumKlasoru): ?>
                <li class="cy-module">
                    <span class="cy-module__icon"><?= icon('alert', 'cy-icon cy-icon--sm') ?></span>
                    <span class="cy-module__body">
                        <strong>Kurulum klasörü sunucuda</strong>
                        <span class="cy-module__desc">Sihirbaz kilitli ama <code class="cy-mono">kurulum/</code> klasörü duruyor. Silmek en güvenlisidir; geri dönüşü yoktur.</span>
                    </span>
                    <form method="post" action="<?= e(url('panel/sistem/kurulum-sil')) ?>" class="cy-module__toggle"
                          data-confirm="kurulum/ klasörü kalıcı olarak silinecek. Devam edilsin mi?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm" <?= $bakimKilidi !== null ? 'disabled title="' . e($bakimKilidi) . '"' : '' ?>>
                            <?= icon('trash', 'cy-icon cy-icon--sm') ?> Klasörü sil
                        </button>
                    </form>
                </li>
            <?php endif; ?>
            <?php if ($ornekVeri): ?>
                <?php $demoAcik = \App\Core\Demo::enabled(); ?>
                <li class="cy-module">
                    <span class="cy-module__icon"><?= icon('users', 'cy-icon cy-icon--sm') ?></span>
                    <span class="cy-module__body">
                        <strong>Örnek veri yüklü</strong>
                        <span class="cy-module__desc">
                            Demo hesapları, örnek mesajlar ve e-postalar, demo sayfaları, örnek sayfa içerikleri (Hakkımızda),
                            Örnek Modül kayıtları, ana sayfa vitrini ve örnek marka ayarları (slogan, sosyal hesaplar, anahtar kelimeler).
                            Kendi hesaplarınıza ve yazdığınız içeriğe dokunulmaz.
                            <?php if ($demoAcik): ?><br>Demo modu açık; kaldırmak için önce <code class="cy-mono">.env</code> içinde <code class="cy-mono">APP_DEMO=false</code> yapın.<?php endif; ?>
                        </span>
                    </span>
                    <form method="post" action="<?= e(url('panel/sistem/demo-kaldir')) ?>" class="cy-module__toggle"
                          data-confirm="Örnek veri kaldırılacak (demo hesapları, mesajları, sayfaları, Örnek Modül kayıtları). Örnek sayfa içerikleri (Hakkımızda) ve marka ayarları da nötre döner; sizin değiştirdikleriniz ve site adı kalır. Devam edilsin mi?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm" <?= $bakimKilidi !== null || $demoAcik ? 'disabled' : '' ?>>
                            <?= icon('trash', 'cy-icon cy-icon--sm') ?> Örnek veriyi kaldır
                        </button>
                    </form>
                </li>
            <?php endif; ?>
        </ul>
    </section>
<?php endif; ?>

<!-- MODÜLLER — panelden aç/kapat (bkz. SystemController::toggleModule).
     Düğme bir <button>: JavaScript olmadan da çalışır. Açarken modülün
     tabloları kurulur; kapatmak hiçbir veriyi silmez. -->
<?php
$acikModul  = count(array_filter($moduller, static fn ($m): bool => $m->aktif));
$modulKilit = !can('system.manage')
    ? 'Modülleri açıp kapatma yetkiniz yok.'
    : \App\Core\Demo::lockReason('panel/sistem/modul');
?>
<section class="cy-panel mb-3" id="moduller">
    <header class="cy-panel__head">
        <div>
            <h3 class="cy-panel__title"><?= icon('box', 'cy-icon cy-icon--sm') ?> Modüller</h3>
            <p class="cy-panel__sub">Açık modülün menüsü, sayfaları ve yetkileri devrededir. Kapatmak tablolarını ve kayıtlarını silmez.</p>
        </div>
        <span class="cy-pill"><?= $acikModul ?> / <?= count($moduller) ?> açık</span>
    </header>

    <?php if ($moduller === []): ?>
        <p class="cy-panel__note">Henüz modül yok. Oluşturmak için: <code class="cy-mono">php cy make:module Stok</code></p>
    <?php else: ?>
        <ul class="cy-modules">
            <?php foreach ($moduller as $modul): ?>
                <?php
                $rolYetkisi = array_sum(array_map('count', $modul->yetkiler));
                $migrasyon  = $modul->hasMigrations() ? count(glob($modul->migrationsPath() . '/*.php') ?: []) : 0;
                ?>
                <li class="cy-module<?= $modul->aktif ? ' is-on' : '' ?>">
                    <span class="cy-module__icon"><?= icon($modul->menu['icon'] ?? 'box', 'cy-icon cy-icon--sm') ?></span>

                    <span class="cy-module__body">
                        <strong><?= e($modul->baslik) ?> <small>v<?= e($modul->surum) ?></small></strong>
                        <?php if ($modul->aciklama !== ''): ?>
                            <span class="cy-module__desc"><?= e($modul->aciklama) ?></span>
                        <?php endif; ?>
                        <span class="cy-module__meta">
                            <span class="cy-mono">modules/<?= e($modul->ad) ?></span>
                            <?php if ($rolYetkisi > 0): ?><span><?= $rolYetkisi ?> rol yetkisi</span><?php endif; ?>
                            <?php if ($migrasyon > 0): ?><span><?= $migrasyon ?> migration</span><?php endif; ?>
                            <?php if ($modul->aktif && $modul->menu !== null): ?>
                                <a class="cy-link" href="<?= e(url($modul->menu['route'])) ?>">Modüle git →</a>
                            <?php endif; ?>
                        </span>
                    </span>

                    <form method="post" action="<?= e(url('panel/sistem/modul/' . $modul->ad)) ?>" class="cy-module__toggle"
                          <?php if ($modul->aktif): ?>data-confirm="<?= e($modul->baslik) ?> kapatılsın mı? Menüsü ve sayfaları kapanır; tabloları ve kayıtları silinmez."<?php endif; ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="durum" value="<?= $modul->aktif ? '0' : '1' ?>">
                        <button type="submit" class="cy-switch<?= $modul->aktif ? ' is-on' : '' ?>" role="switch"
                                aria-checked="<?= $modul->aktif ? 'true' : 'false' ?>"
                                aria-label="<?= e($modul->baslik) ?> modülünü <?= $modul->aktif ? 'kapat' : 'aç' ?>"
                                <?php if ($modulKilit !== null): ?>disabled title="<?= e($modulKilit) ?>"<?php endif; ?>>
                            <span class="cy-switch__track" aria-hidden="true"><span class="cy-switch__thumb"></span></span>
                            <span class="cy-switch__text"><?= $modul->aktif ? 'Açık' : 'Kapalı' ?></span>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="cy-panel__note">
            Yeni modül için <code class="cy-mono">php cy make:module Stok</code> ya da Örnek Modül'ü kopyalayın —
            adım adım rehber: <code class="cy-mono">modules/Ornek/README.md</code>.
            <?php if ($modulKilit !== null): ?><br><span class="text-muted"><?= e($modulKilit) ?></span><?php endif; ?>
        </p>
    <?php endif; ?>
</section>

<!-- BİLGİ KARTLARI — sütunlara akar, boş kutu kalmaz -->
<div class="cy-masonry">

    <!-- KUYRUKLAR (iş + e-posta) -->
    <section class="cy-panel">
        <header class="cy-panel__head">
            <h3 class="cy-panel__title"><?= icon('clock', 'cy-icon cy-icon--sm') ?> Kuyruklar</h3>
            <span class="cy-pill <?= $basarisizToplam === 0 ? 'is-ok' : 'is-danger' ?>">
                <?= $basarisizToplam === 0 ? 'Başarısız yok' : $basarisizToplam . ' başarısız' ?>
            </span>
        </header>

        <div class="cy-queue">
            <div class="cy-queue__row">
                <span class="cy-queue__name">İş kuyruğu <small>arka plan işleri</small></span>
                <span class="cy-queue__nums">
                    <span><b><?= (int) $isler['bekleyen'] ?></b> bekliyor</span>
                    <span><b><?= (int) ($isler['calisan'] ?? 0) ?></b> çalışıyor</span>
                    <span class="<?= (int) $isler['basarisiz'] > 0 ? 'is-danger' : '' ?>"><b><?= (int) $isler['basarisiz'] ?></b> başarısız</span>
                </span>
            </div>
            <div class="cy-queue__row">
                <span class="cy-queue__name">E-posta kuyruğu <small><?= e((string) ($kuyruklar['gonderen'] ?? '')) ?></small></span>
                <span class="cy-queue__nums">
                    <span><b><?= (int) $eposta['kuyrukta'] ?></b> kuyrukta</span>
                    <span><b><?= (int) $eposta['bugun'] ?></b> bugün gitti</span>
                    <span class="<?= (int) $eposta['basarisiz'] > 0 ? 'is-danger' : '' ?>"><b><?= (int) $eposta['basarisiz'] ?></b> başarısız</span>
                </span>
            </div>
        </div>

        <p class="cy-panel__note<?= ($kuyruklar['surucu'] ?? 'kayit') === 'kayit' ? ' is-warn' : '' ?>">
            <strong>Gönderim:</strong> <?= e((string) ($kuyruklar['surucuAdi'] ?? '')) ?>.
            <?php if (($kuyruklar['surucu'] ?? 'kayit') === 'kayit'): ?>
                Bu yöntemde mektuplar başarısız sayılmaz; sınama mektupları da klasöre düşer.
                <?php if (can('settings.view')): ?>
                    <a class="cy-link" href="<?= e(url('panel/ayarlar/eposta')) ?>">SMTP ayarları →</a>
                <?php endif; ?>
            <?php endif; ?>
        </p>

        <?php if ($basarisizIsler !== []): ?>
            <div class="cy-panel__section">
                <div class="cy-panel__section-head">
                    <span>Başarısız işler</span>
                    <?php if (can('system.manage')): ?>
                        <span class="d-flex gap-1">
                            <form method="post" action="<?= e(url('panel/sistem/kuyruk/tekrar')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm"><?= icon('refresh', 'cy-icon cy-icon--sm') ?> Yeniden dene</button>
                            </form>
                            <form method="post" action="<?= e(url('panel/sistem/kuyruk/temizle')) ?>"
                                  data-confirm="Tüm başarısız işler kalıcı olarak silinsin mi?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm"><?= icon('trash', 'cy-icon cy-icon--sm') ?> Temizle</button>
                            </form>
                        </span>
                    <?php endif; ?>
                </div>
                <ul class="cy-list">
                    <?php foreach ($basarisizIsler as $is): ?>
                        <li class="cy-list__row">
                            <span>
                                <strong><?= e((string) $is['sinif']) ?></strong>
                                <small><?= e((string) $is['hata']) ?></small>
                            </span>
                            <span class="cy-list__meta">
                                <?= (int) $is['deneme'] ?>/<?= (int) $is['max_deneme'] ?> ·
                                <?= e(\App\Models\User::formatDate((string) $is['created_at'])) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($basarisizEpostalar !== []): ?>
            <div class="cy-panel__section">
                <div class="cy-panel__section-head">
                    <span>Başarısız e-postalar</span>
                    <?php if (can('mail.view')): ?>
                        <a class="cy-link small" href="<?= e(url('panel/eposta')) ?>">Tümü →</a>
                    <?php endif; ?>
                </div>
                <ul class="cy-list">
                    <?php foreach ($basarisizEpostalar as $mektup): ?>
                        <li class="cy-list__row">
                            <span>
                                <strong><?= e((string) $mektup['konu']) ?></strong>
                                <small><?= e((string) $mektup['alici_eposta']) ?> — <?= e((string) $mektup['hata']) ?></small>
                            </span>
                            <span class="cy-list__meta"><?= e(\App\Models\User::formatDate((string) $mektup['created_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (can('mail.view')): ?>
            <a class="cy-panel__foot" href="<?= e(url('panel/eposta')) ?>">E-posta geçmişi <?= icon('chevron', 'cy-icon cy-icon--sm') ?></a>
        <?php endif; ?>
    </section>

    <?php $bilgiKarti('Uygulama', 'dashboard', $uygulama ?? []); ?>
    <?php $bilgiKarti('Sunucu', 'server', $sunucu ?? []); ?>
    <?php $bilgiKarti('Altyapı', 'activity', $altyapi ?? []); ?>

    <!-- YAZMA İZİNLERİ -->
    <section class="cy-panel">
        <header class="cy-panel__head">
            <h3 class="cy-panel__title"><?= icon('lock', 'cy-icon cy-icon--sm') ?> Yazma İzinleri</h3>
        </header>
        <ul class="cy-list">
            <?php foreach (($klasorler ?? []) as $klasor): ?>
                <li class="cy-list__row">
                    <span>
                        <strong><?= e($klasor['ad']) ?></strong>
                        <small class="cy-mono"><?= e($klasor['yol']) ?></small>
                    </span>
                    <span class="cy-dot <?= $klasor['ok'] ? 'is-ok' : 'is-danger' ?>"><?= $klasor['ok'] ? 'Yazılabilir' : 'İzin yok' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- PHP EKLENTİLERİ — açıklama üzerine gelince görünür -->
    <section class="cy-panel">
        <header class="cy-panel__head">
            <h3 class="cy-panel__title"><?= icon('database', 'cy-icon cy-icon--sm') ?> PHP Eklentileri</h3>
        </header>
        <div class="cy-chips">
            <?php foreach (($eklentiler ?? []) as $eklenti): ?>
                <span class="cy-chip <?= $eklenti['ok'] ? 'is-ok' : 'is-warn' ?>" title="<?= e($eklenti['not']) ?>">
                    <?= icon($eklenti['ok'] ? 'check' : 'alert', 'cy-icon cy-icon--sm') ?><?= e($eklenti['ad']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </section>

</div>
