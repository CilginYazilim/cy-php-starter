<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Ana sayfa
 * ---------------------------------------------------------------------
 *  Bölümler ve metinler Panel → Ayarlar → Ana Sayfa'dan gelir (bkz.
 *  HomeController). İÇERİĞİ BOŞ OLAN BÖLÜM HİÇ BASILMAZ.
 *
 *  Tek <h1> karşılamadadır; her bölüm başlığı <h2>. Hareket (fade-up)
 *  home.js'tedir ve "hareketi azalt" tercihinde kapalıdır.
 *
 *  @var array<int,string> $bolumler
 *  @var array<string,mixed> $hero, $cta
 *  @var array<int,array<string,string>> $ozellikler, $adimlar, $sss, $iletisim
 *  @var array<int,array<string,mixed>> $roller
 *  @var App\Models\Page|null $hakkinda
 * =====================================================================
 */

use App\Core\Setting;
use App\Http\Controllers\Site\HomeController;

$bolumler   = $bolumler ?? ['hero'];
$hero       = $hero ?? [];
$ozellikler = $ozellikler ?? [];
$adimlar    = $adimlar ?? [];
$sss        = $sss ?? [];
$roller     = $roller ?? [];
$cta        = $cta ?? ['baslik' => '', 'metin' => '', 'dugmeler' => []];
$iletisim   = $iletisim ?? [];
$hakkinda   = $hakkinda ?? null;
$siteAdi    = Setting::get('site_adi', $appName ?? '');
$whatsapp   = HomeController::whatsappLink();
$goster     = static fn (string $bolum): bool => in_array($bolum, $bolumler, true);

/* Vitrin görseli: panelin açık ve koyu temada ekran görüntüleri. */
$vitrinAcik = 'images/vitrin/panel-acik.webp';
$vitrinVar  = ($hero['gorsel'] ?? '') === 'vitrin' && is_file(CY_BASE . '/assets/' . $vitrinAcik);
?>

<?php if ($goster('hero')): ?>
    <!-- ================= KARŞILAMA ================= -->
    <section class="cy-home-hero" aria-labelledby="hero-baslik">
        <div class="cy-home-container cy-home-hero__inner">
            <div class="cy-home-hero__text" data-reveal>
                <?php if (($hero['rozet'] ?? '') !== ''): ?>
                    <span class="cy-home-badge"><?= e($hero['rozet']) ?></span>
                <?php endif; ?>

                <h1 class="cy-home-hero__title" id="hero-baslik"><?= e($hero['baslik'] ?? $siteAdi) ?></h1>

                <?php if (($hero['metin'] ?? '') !== ''): ?>
                    <p class="cy-home-hero__lead"><?= e($hero['metin']) ?></p>
                <?php endif; ?>

                <div class="cy-home-hero__actions">
                    <?php foreach ($hero['dugmeler'] ?? [] as $dugme): ?>
                        <a class="btn cy-btn <?= $dugme['tur'] === 'birincil' ? 'cy-btn--primary' : 'cy-btn--ghost' ?> cy-btn--lg"
                           href="<?= e($dugme['adres']) ?>"<?= $dugme['dis'] ? ' target="_blank" rel="noopener"' : '' ?>>
                            <?= e($dugme['metin']) ?>
                            <?= icon($dugme['dis'] ? 'external' : 'chevron', 'cy-icon cy-icon--sm') ?>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($whatsapp !== ''): ?>
                        <a class="btn cy-btn cy-btn--whatsapp cy-btn--lg" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">
                            <?= icon('whatsapp', 'cy-icon cy-icon--sm') ?> WhatsApp
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (($hero['guven'] ?? '') !== ''): ?>
                    <p class="cy-home-hero__trust"><?= e($hero['guven']) ?></p>
                <?php endif; ?>
            </div>

            <div class="cy-home-hero__visual" data-reveal>
                <?php if ($vitrinVar): ?>
                    <figure class="cy-browser">
                        <div class="cy-browser__bar" aria-hidden="true"><span></span><span></span><span></span><em><?= e(parse_url(App\Core\Url::absolute('panel'), PHP_URL_HOST) ?: 'panel') ?>/panel</em></div>
                        <img class="cy-shot cy-shot--light" src="<?= e(asset($vitrinAcik)) ?>" width="1280" height="800"
                             alt="<?= e($siteAdi) ?> yönetim paneli: kontrol paneli ekranı" fetchpriority="high">
                        <img class="cy-shot cy-shot--dark" src="<?= e(asset('images/vitrin/panel-koyu.webp')) ?>" width="1280" height="800"
                             alt="" aria-hidden="true" loading="lazy">
                    </figure>
                    <ul class="cy-home-chips" aria-label="Öne çıkanlar">
                        <li><?= icon('shield', 'cy-icon cy-icon--sm') ?> CSRF korumalı</li>
                        <li><?= icon('users', 'cy-icon cy-icon--sm') ?> 3 rol</li>
                        <li><?= icon('code', 'cy-icon cy-icon--sm') ?> REST API</li>
                    </ul>
                <?php elseif (($hero['gorsel'] ?? '') !== '' && ($hero['gorsel'] ?? '') !== 'vitrin'): ?>
                    <?php $gorsel = str_starts_with($hero['gorsel'], 'http') ? $hero['gorsel'] : App\Core\Uploader::url($hero['gorsel']); ?>
                    <img class="cy-home-hero__img" src="<?= e($gorsel) ?>" alt="<?= e($siteAdi) ?>" fetchpriority="high">
                <?php else: ?>
                    <div class="cy-home-logo">
                        <?= logo_img(['alt' => $siteAdi, 'width' => 160, 'height' => 160], buyuk: true) ?>
                        <span><?= e($siteAdi) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($goster('teknoloji')): ?>
    <!-- ================= TEKNOLOJİ ŞERİDİ ================= -->
    <section class="cy-home-tech" aria-label="Kullanılan teknolojiler">
        <ul class="cy-home-container cy-home-tech__list">
            <?php foreach ([['code', 'PHP 8.1+'], ['database', 'MySQL / MariaDB'], ['server', 'PDO'], ['dashboard', 'Bootstrap 5'], ['link', 'REST API'], ['mobil', 'PWA']] as [$ikon, $ad]): ?>
                <li><?= icon($ikon, 'cy-icon cy-icon--sm') ?> <?= e($ad) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($goster('adimlar') && $adimlar !== []): ?>
    <!-- ================= ADIMLAR ================= -->
    <section class="cy-home-section" aria-labelledby="adimlar-baslik">
        <div class="cy-home-container">
            <header class="cy-home-head" data-reveal>
                <h2 id="adimlar-baslik"><?= count($adimlar) ?> adımda çalışan proje</h2>
            </header>
            <ol class="cy-home-steps">
                <?php foreach ($adimlar as $i => $adim): ?>
                    <li data-reveal>
                        <span class="cy-home-steps__no" aria-hidden="true"><?= $i + 1 ?></span>
                        <h3><?= e($adim['baslik'] ?? '') ?></h3>
                        <?php if (($adim['metin'] ?? '') !== ''): ?><p><?= e($adim['metin']) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
<?php endif; ?>

<?php if ($goster('ozellikler') && $ozellikler !== []): ?>
    <!-- ================= ÖZELLİKLER (bento) ================= -->
    <section class="cy-home-section cy-home-section--alt" aria-labelledby="ozellik-baslik">
        <div class="cy-home-container">
            <header class="cy-home-head" data-reveal>
                <span class="cy-eyebrow">Neler var?</span>
                <h2 id="ozellik-baslik">Sıfırdan yazmanıza gerek kalmayan altyapı</h2>
            </header>
            <div class="cy-bento">
                <?php foreach ($ozellikler as $i => $ozellik): ?>
                    <article class="cy-bento__item<?= $i < 2 ? ' is-wide' : '' ?>" data-reveal>
                        <?php if (($ozellik['ikon'] ?? '') !== ''): ?>
                            <span class="cy-bento__icon"><?= icon($ozellik['ikon']) ?></span>
                        <?php endif; ?>
                        <h3><?= e($ozellik['baslik'] ?? '') ?></h3>
                        <?php if (($ozellik['metin'] ?? '') !== ''): ?><p><?= e($ozellik['metin']) ?></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($goster('roller') && $roller !== []): ?>
    <!-- ================= ROLLERİ DENEYİN (demo modu) ================= -->
    <section class="cy-home-section" aria-labelledby="roller-baslik">
        <div class="cy-home-container">
            <header class="cy-home-head" data-reveal>
                <span class="cy-eyebrow">Canlı demo</span>
                <h2 id="roller-baslik">Rolleri deneyin</h2>
                <p>Her rol paneli farklı görür. Bir rol seçin, giriş ekranında o hesap hazır gelsin.</p>
            </header>
            <div class="cy-roles">
                <?php foreach ($roller as $rol): ?>
                    <article class="cy-roles__card" data-reveal>
                        <span class="cy-role cy-role--<?= e($rol['variant'] === 'member' ? 'member' : $rol['variant']) ?>"><?= e($rol['etiket']) ?></span>
                        <ul>
                            <?php foreach ($rol['yapabilir'] as $madde): ?>
                                <li><?= icon('check', 'cy-icon cy-icon--sm') ?> <?= e($madde) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="btn cy-btn cy-btn--ghost" href="<?= e(url('giris', ['demo' => $rol['kullanici']])) ?>">
                            Bu rolle gir <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($goster('kod')): ?>
    <!-- ================= KOD ÖRNEĞİ ================= -->
    <section class="cy-home-section cy-home-section--alt" aria-labelledby="kod-baslik">
        <div class="cy-home-container"><div class="cy-code-wrap">
            <header class="cy-home-head" data-reveal>
                <span class="cy-eyebrow">Geliştirici dostu</span>
                <h2 id="kod-baslik">Okunur kod, birkaç satırda iş</h2>
            </header>
            <?php
            $ornekler = [
                'olay'  => ['Olay', "Events::listen(UserRegistered::class, function (UserRegistered \$olay): void {\n    Logger::info('Yeni üye', ['id' => \$olay->user->id]);\n});"],
                'modul' => ['Modül', "php cy make:module Stok\n# Panel → Sistem Bilgisi → Modüller → Stok: Aç\n# /panel/stok hazır: listeleme, ekleme, silme"],
                'api'   => ['API', "curl -X POST https://site.com/api/v1/oturum \\\n  -d kullanici=ali.yonetici -d parola=… -d cihaz=\"iPhone\"\n\ncurl -H \"Authorization: Bearer cy_…\" https://site.com/api/v1/ben"],
            ];
            ?>
            <div class="cy-code" data-reveal data-tabs>
                <div class="cy-code__tabs" role="tablist" aria-label="Kod örnekleri">
                    <?php foreach (array_keys($ornekler) as $i => $anahtar): ?>
                        <button type="button" role="tab" id="sekme-<?= e($anahtar) ?>" aria-controls="kod-<?= e($anahtar) ?>"
                                aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>"><?= e($ornekler[$anahtar][0]) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($ornekler as $anahtar => [$baslik, $kod]): ?>
                    <div class="cy-code__panel" role="tabpanel" id="kod-<?= e($anahtar) ?>" aria-labelledby="sekme-<?= e($anahtar) ?>">
                        <button type="button" class="cy-code__copy" data-copy-target="#kod-<?= e($anahtar) ?> code" data-copy-label="Kod">Kopyala</button>
                        <pre><code><?= e($kod) ?></code></pre>
                    </div>
                <?php endforeach; ?>
            </div>
        </div></div>
    </section>
<?php endif; ?>

<?php if ($goster('sss') && $sss !== []): ?>
    <!-- ================= SIK SORULAN SORULAR ================= -->
    <section class="cy-home-section" aria-labelledby="sss-baslik">
        <div class="cy-home-container"><div class="cy-faq-wrap">
            <header class="cy-home-head" data-reveal>
                <h2 id="sss-baslik">Sık sorulan sorular</h2>
            </header>
            <div class="cy-faq">
                <?php foreach ($sss as $soru): ?>
                    <details data-reveal>
                        <summary><?= e($soru['soru'] ?? '') ?></summary>
                        <p><?= e($soru['cevap'] ?? '') ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div></div>
    </section>
<?php endif; ?>

<?php if ($goster('hakkimizda') && $hakkinda !== null): ?>
    <!-- ================= HAKKIMIZDA ÖZETİ ================= -->
    <section class="cy-home-section cy-home-section--alt" aria-labelledby="hakkinda-baslik">
        <div class="cy-home-container cy-home-about" data-reveal>
            <div>
                <span class="cy-eyebrow"><?= e($hakkinda->baslik) ?></span>
                <h2 id="hakkinda-baslik"><?= e($siteAdi) ?> hakkında</h2>
            </div>
            <div>
                <?php /* Sayfanın DÜZ METİN özeti; tam metin sayfanın kendisinde. */ ?>
                <p><?= e(mb_strimwidth(\App\Core\Html::toText($hakkinda->icerik), 0, 420, '…', 'UTF-8')) ?></p>
                <a class="btn cy-btn cy-btn--ghost" href="<?= e(url($hakkinda->slug)) ?>">
                    Devamını oku <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
                </a>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($goster('iletisim') && $iletisim !== []): ?>
    <!-- ================= İLETİŞİM KARTLARI ================= -->
    <section class="cy-home-section" aria-labelledby="iletisim-baslik">
        <div class="cy-home-container">
            <header class="cy-home-head" data-reveal>
                <h2 id="iletisim-baslik">Bize ulaşın</h2>
            </header>
            <div class="cy-contact-grid">
                <?php foreach ($iletisim as $kart): ?>
                    <?php $baglanti = $kart['link'] !== ''; ?>
                    <<?= $baglanti ? 'a' : 'div' ?> class="cy-contact-card"<?= $baglanti ? ' href="' . e($kart['link']) . '"' . (str_starts_with($kart['link'], 'http') ? ' target="_blank" rel="noopener"' : '') : '' ?> data-reveal>
                        <span class="cy-contact-card__icon"><?= icon($kart['ikon']) ?></span>
                        <span class="cy-contact-card__body">
                            <span class="cy-contact-card__label"><?= e($kart['etiket']) ?></span>
                            <?php /* E-posta "@"tan sonra kırılabilsin; kelimenin ortasından değil. */ ?>
                            <span class="cy-contact-card__value"><?= $kart['ikon'] === 'mail' ? str_replace('@', '@<wbr>', e($kart['deger'])) : nl2br(e($kart['deger'])) ?></span>
                        </span>
                    </<?= $baglanti ? 'a' : 'div' ?>>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($goster('cta') && ($cta['baslik'] ?? '') !== ''): ?>
    <!-- ================= SON BANT ================= -->
    <section class="cy-home-section cy-home-section--tight" aria-labelledby="cta-baslik">
        <div class="cy-home-container">
            <div class="cy-home-cta" data-reveal>
                <div>
                    <h2 id="cta-baslik"><?= e($cta['baslik']) ?></h2>
                    <?php if (($cta['metin'] ?? '') !== ''): ?><p><?= e($cta['metin']) ?></p><?php endif; ?>
                </div>
                <?php if ($cta['dugmeler'] !== []): ?>
                    <div class="cy-home-cta__actions">
                        <?php foreach ($cta['dugmeler'] as $i => $dugme): ?>
                            <?php $dis = str_starts_with($dugme['adres'], 'http') && !str_starts_with($dugme['adres'], App\Core\Url::origin()); ?>
                            <a class="btn cy-btn <?= $i === 0 ? 'cy-btn--light' : 'cy-btn--outline-light' ?>" href="<?= e($dugme['adres']) ?>"<?= $dis ? ' target="_blank" rel="noopener"' : '' ?>>
                                <?= e($dugme['metin']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
