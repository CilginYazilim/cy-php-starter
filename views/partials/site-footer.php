<?php
/**
 * =====================================================================
 *  PARÇA: Ön yüz alt bilgi
 * ---------------------------------------------------------------------
 *  SOSYAL İKONLAR: her platformun KENDİ simgesi kullanılır. Uzun süre
 *  GitHub dışındaki hepsi aynı "dünya" ikonuyla çiziliyordu — çünkü
 *  markaların yolları icon() içinde hiç tanımlı değildi. Artık
 *  tanımlılar (bkz. app/Support/helpers.php → $brands).
 *
 *  WHATSAPP ayrı bir düğmedir ve hazır mesajı da taşır: ziyaretçi
 *  tıkladığında sohbet kutusunda yazı hazır bekler.
 *
 *  MOBİL SÜTUNLAR: "Site" ve "İletişim" blokları eskiden telefonda da
 *  yan yana (col-6) duruyordu. Uzun e-posta adresleri ve çok satırlı
 *  adres 160 piksellik sütuna sığmıyor, alt bilgi kırık görünüyordu.
 *  Artık telefonda ALT ALTA, tabletten itibaren yan yana.
 * =====================================================================
 */

use App\Core\Setting;
use App\Http\Controllers\Site\HomeController;

$socials = [
    'sosyal_facebook'  => ['label' => 'Facebook',    'icon' => 'facebook'],
    'sosyal_x'         => ['label' => 'X (Twitter)', 'icon' => 'x'],
    'sosyal_instagram' => ['label' => 'Instagram',   'icon' => 'instagram'],
    'sosyal_linkedin'  => ['label' => 'LinkedIn',    'icon' => 'linkedin'],
    'sosyal_youtube'   => ['label' => 'YouTube',     'icon' => 'youtube'],
    'sosyal_github'    => ['label' => 'GitHub',      'icon' => 'github'],
];

$whatsapp = HomeController::whatsappLink();
$eposta   = Setting::get('iletisim_eposta');
$telefon  = Setting::get('iletisim_telefon');
$adres    = Setting::get('iletisim_adres');
$saatler  = Setting::get('iletisim_saatler');

/* Alt bilgideki hızlı bağlantılar da menüyle aynı kaynaktan gelir. */
$sayfalar = [];

try {
    $sayfalar = (new App\Repositories\PageRepository(App\Core\Database::connection()))->menu();
} catch (\Throwable) {
    // Alt bilgi kritik değildir.
}

/* KAYNAKLAR SÜTUNU
 * ----------------
 * Şablonu indiren kişinin ilk sorusu "bunu nasıl kullanacağım"
 * oluyor. Cevap kütüphanede: her bileşenin çalışan örnek kodu orada
 * duruyor. Bağlantı alt bilgide sabit durur — hangi sayfada olursanız
 * olun bir tık uzakta. */
$kaynaklar = [
    [
        'url'   => 'https://cilginyazilim.com/kutuphane',
        'label' => 'Kod Kütüphanesi',
        'icon'  => 'book',
    ],
    [
        'url'   => 'https://cilginyazilim.com/kutuphane/php-baslangic-sablonu',
        'label' => 'PHP Başlangıç Şablonu',
        'icon'  => 'file',
    ],
    [
        'url'   => 'https://github.com/CilginYazilim/cy-php-starter',
        'label' => 'GitHub Deposu',
        'icon'  => 'github',
    ],
];
?>
<footer class="cy-site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="cy-site-nav__brand mb-3">
                    <img src="<?= e(Setting::logoUrl()) ?>" alt="">
                    <span><?= e(Setting::get('site_adi', $appName ?? '')) ?></span>
                </div>

                <p class="cy-muted small mb-3">
                    <?= e(Setting::get('site_slogan', Setting::get('site_aciklama'))) ?>
                </p>

                <?php if ($socials !== []): ?>
                    <div class="cy-social">
                        <?php foreach ($socials as $key => $meta): ?>
                            <?php $url = Setting::get($key); ?>
                            <?php if ($url !== ''): ?>
                                <a class="cy-social__link cy-social__link--<?= e($meta['icon']) ?>"
                                   href="<?= e($url) ?>" target="_blank" rel="noopener"
                                   title="<?= e($meta['label']) ?>" aria-label="<?= e($meta['label']) ?>">
                                    <?= icon($meta['icon'], 'cy-icon cy-icon--sm') ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($whatsapp !== ''): ?>
                            <a class="cy-social__link cy-social__link--whatsapp"
                               href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"
                               title="WhatsApp" aria-label="WhatsApp">
                                <?= icon('whatsapp', 'cy-icon cy-icon--sm') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-sm-6 col-lg-2">
                <span class="cy-eyebrow d-block mb-2">Site</span>
                <ul class="cy-footer-list">
                    <li><a href="<?= e(url('')) ?>">Ana Sayfa</a></li>
                    <?php foreach ($sayfalar as $sayfa): ?>
                        <li><a href="<?= e(url($sayfa->slug)) ?>"><?= e($sayfa->baslik) ?></a></li>
                    <?php endforeach; ?>
                    <?php if (($currentUser ?? null) === null): ?>
                        <li><a href="<?= e(url('giris')) ?>">Giriş Yap</a></li>
                    <?php else: ?>
                        <li><a href="<?= e(url('panel')) ?>">Yönetim Paneli</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <span class="cy-eyebrow d-block mb-2">Örnek Kodlar</span>
                <ul class="cy-footer-list cy-footer-list--icons">
                    <?php foreach ($kaynaklar as $kaynak): ?>
                        <li>
                            <?= icon($kaynak['icon'], 'cy-icon cy-icon--sm') ?>
                            <a href="<?= e($kaynak['url']) ?>" target="_blank" rel="noopener">
                                <?= e($kaynak['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-12 col-lg-3">
                <span class="cy-eyebrow d-block mb-2">İletişim</span>
                <ul class="cy-footer-list cy-footer-list--icons">
                    <?php if ($eposta !== ''): ?>
                        <li>
                            <?= icon('mail', 'cy-icon cy-icon--sm') ?>
                            <a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($telefon !== ''): ?>
                        <li>
                            <?= icon('phone', 'cy-icon cy-icon--sm') ?>
                            <a href="tel:<?= e(preg_replace('/\D+/', '', $telefon)) ?>"><?= e($telefon) ?></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($whatsapp !== ''): ?>
                        <li>
                            <?= icon('whatsapp', 'cy-icon cy-icon--sm') ?>
                            <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">
                                <?= e(Setting::get('iletisim_whatsapp')) ?> · WhatsApp'tan yazın
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($saatler !== ''): ?>
                        <li><?= icon('clock', 'cy-icon cy-icon--sm') ?> <span><?= e($saatler) ?></span></li>
                    <?php endif; ?>

                    <?php if ($adres !== ''): ?>
                        <li><?= icon('map', 'cy-icon cy-icon--sm') ?> <span><?= nl2br(e($adres)) ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <hr class="cy-divider">

        <div class="cy-site-footer__bottom d-flex flex-wrap justify-content-between gap-2 small cy-muted">
            <span><?= date('Y') ?> © <?= e(Setting::get('site_adi', $appName ?? '')) ?>. Tüm hakları saklıdır.</span>
            <span>
                <a class="cy-link" href="https://cilginyazilim.com/kutuphane" target="_blank" rel="noopener">Kod Kütüphanesi</a>
                ·
                <a class="cy-link" href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a>
                ile geliştirildi
            </span>
        </div>
    </div>
</footer>

<?php if ($whatsapp !== ''): ?>
    <?php /* Sabit WhatsApp düğmesi: mobilde iletişim kurmanın en kısa
             yolu. Yalnızca numara tanımlıysa basılır. */ ?>
    <a class="cy-whatsapp-fab" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"
       aria-label="WhatsApp'tan yazın" title="WhatsApp'tan yazın">
        <?= icon('whatsapp') ?>
        <span>WhatsApp</span>
    </a>
<?php endif; ?>
