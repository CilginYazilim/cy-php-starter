<?php
/**
 * =====================================================================
 *  PARÇA: Ön yüz üst menü
 * ---------------------------------------------------------------------
 *  Menü SABİT DEĞİLDİR: "Ana Sayfa" dışındaki bağlantılar "sayfalar"
 *  tablosundan gelir (durum = yayin ve "menüde göster" işaretli
 *  olanlar). Panelden yeni bir sayfa açan kişi menüye eklemek için
 *  kod düzenlemek zorunda kalmaz.
 *
 *  MOBİL DÜZEN
 *  -----------
 *  Tema düğmesi HAMBURGERİN DIŞINDA durur. İçeride olduğu sürece
 *  temayı değiştirmek "menüyü aç → aşağı kaydır → düğmeyi bul"
 *  demekti; en sık kullanılan ikinci eylem için fazla yol. Artık
 *  marka ile hamburger arasında, tek dokunuşluk mesafede.
 *
 *  Hamburger simgesi menü açıkken çarpıya döner (CSS, aria-expanded
 *  özniteliğine bakar) — düğmenin ikinci basışta ne yapacağı
 *  simgeden okunur.
 * =====================================================================
 */

use App\Core\Database;
use App\Core\Setting;
use App\Repositories\PageRepository;

$user = $currentUser ?? null;

$links = [['route' => '', 'label' => 'Ana Sayfa']];

/* Menü her sayfada çizilir; sorgu tek ve indekslidir. Yine de
 * veritabanı henüz kurulmamışsa (ya da tablo yoksa) menü yüzünden
 * site çökmemeli — sessizce yalnızca "Ana Sayfa" gösteririz. */
try {
    foreach ((new PageRepository(Database::connection()))->menu() as $sayfa) {
        $links[] = ['route' => $sayfa->slug, 'label' => $sayfa->baslik];
    }
} catch (\Throwable) {
    // Menü kritik değildir; hata görünümü bozmasın.
}
?>
<nav class="navbar navbar-expand-lg cy-site-nav" id="cy_site_nav">
    <div class="container">
        <a class="navbar-brand cy-site-nav__brand" href="<?= e(url('')) ?>">
            <img src="<?= e(Setting::logoUrl()) ?>" alt="">
            <span><?= e(Setting::get('site_adi', $appName ?? '')) ?></span>
        </a>

        <div class="cy-site-nav__tools order-lg-3 ms-lg-2">
            <button type="button" class="cy-topbar__toggle" id="cy_theme_toggle"
                    aria-label="Açık/koyu tema" title="Açık/koyu tema">
                <span class="cy-theme-icon cy-theme-icon--light"><?= icon('moon') ?></span>
                <span class="cy-theme-icon cy-theme-icon--dark d-none"><?= icon('sun') ?></span>
            </button>

            <button class="navbar-toggler d-lg-none" type="button"
                    data-bs-toggle="collapse" data-bs-target="#cySiteNav"
                    aria-controls="cySiteNav" aria-expanded="false" aria-label="Menüyü aç/kapat">
                <?= icon('menu', 'cy-icon cy-nav-icon--open') ?>
                <?= icon('close', 'cy-icon cy-nav-icon--close') ?>
            </button>
        </div>

        <div class="collapse navbar-collapse order-lg-2" id="cySiteNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php foreach ($links as $link): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= is_route($link['route']) ? ' active' : '' ?>"
                           href="<?= e(url($link['route'])) ?>"
                           <?= is_route($link['route']) ? 'aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
                    </li>
                <?php endforeach; ?>

                <?php if ($user !== null): ?>
                    <li class="nav-item">
                        <a class="btn cy-btn cy-btn--soft cy-btn--sm ms-lg-2" href="<?= e(url('panel')) ?>">
                            <?= icon('dashboard', 'cy-icon cy-icon--sm') ?> Panele Git
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('giris')) ?>">Giriş Yap</a>
                    </li>
                    <?php if (Setting::bool('sistem_kayit_acik', false)): ?>
                        <li class="nav-item">
                            <a class="btn cy-btn cy-btn--primary cy-btn--sm ms-lg-2" href="<?= e(url('kayit')) ?>">Kayıt Ol</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
