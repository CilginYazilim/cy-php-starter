<?php
/**
 * =====================================================================
 *  ANA SAYFA (herkese açık)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Bu sayfa iki iş yapar:
 *    1. Şablonun ön yüz tasarımını gösterir (hero, kartlar, alt bilgi)
 *    2. Oturum durumuna göre farklı içerik sunar
 *
 *  ► Yeni projeye başlarken: "TANITIM BÖLÜMLERİ" yorumları arasındaki
 *    kısmı silip yerine kendi içeriğinizi yazın. Kabuk (_ust.php /
 *    _alt.php) olduğu gibi kalsın.
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik   = '';          // Ana sayfada <title> sadece site adı olsun
$aktifSayfa    = 'anasayfa';

require __DIR__ . '/_ust.php';

/* Kurulum klasörü az önce silindiyse bir kez teşekkür mesajı göster.
 * (kurulum/index.php buraya ?kurulum=temizlendi ile yönlendirir.) */
$kurulumTemizlendi = ($_GET['kurulum'] ?? '') === 'temizlendi';

/* İstatistikler: sayıları yalnızca giriş yapmış kullanıcılara
 * gösteriyoruz. Kaç üyeniz olduğu, dışarıya verilmesi gerekmeyen
 * bir bilgidir — meraklı ziyaretçiye ipucu vermeyelim. */
$istatistik = null;
if ($aktifKullanici !== null) {
    $istatistik = [
        'kullanici' => count_rows($db, 'kullanicilar'),
        'ayar'      => count_rows($db, 'ayarlar'),
        'mesaj'     => count_rows($db, 'mesajlar'),
    ];
}
?>

<?php if ($kurulumTemizlendi): ?>
    <div class="container pt-4">
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <span style="font-size:1.4rem">✅</span>
            <div>
                <strong>Kurulum klasörü silindi.</strong>
                Projeniz artık temiz ve canlıya çıkmaya hazır.
            </div>
        </div>
    </div>
<?php endif; ?>


<!-- ================= HERO ================= -->
<section class="container pt-4 pt-lg-5">
    <div class="cy-hero">
        <span class="cy-hero__eyebrow">
            <span aria-hidden="true">⚡</span> Çılgın Yazılım Şablonu
        </span>

        <?php if ($aktifKullanici !== null): ?>
            <!-- ---------- OTURUM AÇIK ---------- -->
            <h1 class="cy-hero__title">
                Tekrar hoş geldiniz, <?= e($aktifKullanici['ad']) ?>.
            </h1>
            <p class="cy-hero__lead">
                <?= e(auth_role_labels()[$aktifKullanici['rol']] ?? $aktifKullanici['rol']) ?>
                rolüyle giriş yaptınız.
                <?php if ($aktifKullanici['son_giris'] !== null): ?>
                    Son girişiniz: <?= e(format_date($aktifKullanici['son_giris'])) ?>.
                <?php endif; ?>
            </p>
            <div class="cy-hero__actions">
                <a href="hesabim.php" class="btn cy-btn cy-btn--onbrand">Hesabıma Git</a>
                <?php if (auth_at_least('editor')): ?>
                    <a href="yonetim/index.php" class="btn cy-btn cy-btn--glass">Yönetim Paneli</a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- ---------- OTURUM KAPALI ---------- -->
            <h1 class="cy-hero__title">
                <?= e((string) setting('site_adi', APP_NAME)) ?>
            </h1>
            <p class="cy-hero__lead">
                <?= e((string) setting('site_aciklama', APP_DESCRIPTION)) ?>
            </p>
            <div class="cy-hero__actions">
                <a href="giris.php" class="btn cy-btn cy-btn--onbrand">Giriş Yap</a>
                <?php if ($kayitAcik): ?>
                    <a href="kayit.php" class="btn cy-btn cy-btn--glass">Ücretsiz Kayıt Ol</a>
                <?php else: ?>
                    <a href="hakkimizda.php" class="btn cy-btn cy-btn--glass">Daha Fazla Bilgi</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>


<?php if ($istatistik !== null): ?>
<!-- ================= İSTATİSTİKLER (sadece üyeler) ================= -->
<section class="container pt-4">
    <div class="cy-stats">
        <div class="cy-stat">
            <div class="cy-stat__value"><?= (int) $istatistik['kullanici'] ?></div>
            <div class="cy-stat__label">Kullanıcı</div>
        </div>
        <div class="cy-stat">
            <div class="cy-stat__value"><?= (int) $istatistik['ayar'] ?></div>
            <div class="cy-stat__label">Site Ayarı</div>
        </div>
        <div class="cy-stat">
            <div class="cy-stat__value"><?= (int) $istatistik['mesaj'] ?></div>
            <div class="cy-stat__label">Mesaj</div>
        </div>
        <div class="cy-stat">
            <div class="cy-stat__value"><?= e((string) setting('sistem_surum', '1.0.0')) ?></div>
            <div class="cy-stat__label">Sürüm</div>
        </div>
    </div>
</section>
<?php endif; ?>


<!-- =====================================================================
     TANITIM BÖLÜMLERİ — BAŞLANGIÇ
     Kendi projenizde buradan aşağısını silip yerine içeriğinizi yazın.
     ================================================================== -->

<section class="cy-section container">
    <div class="text-center mb-5">
        <span class="cy-eyebrow">Neler hazır?</span>
        <h2 class="cy-section__title">Sıfırdan yazmanıza gerek kalmayan altyapı</h2>
        <p class="cy-section__lead mx-auto">
            Her yeni projede tekrar tekrar yazılan parçalar bu şablonda
            hazır geliyor: kurulum, oturum, yetki, ayarlar ve tasarım.
        </p>
    </div>

    <div class="cy-grid">
        <article class="cy-feature">
            <div class="cy-feature__icon" aria-hidden="true">🧙</div>
            <h3 class="cy-feature__title">Kurulum Sihirbazı</h3>
            <p class="cy-feature__text">
                Beş adımda veritabanını oluşturur, şemayı kurar, yönetici
                hesabını açar ve <code>.env</code> dosyasını yazar.
                Bitince kendi klasörünü siler.
            </p>
        </article>

        <article class="cy-feature">
            <div class="cy-feature__icon" aria-hidden="true">🔐</div>
            <h3 class="cy-feature__title">Oturum ve Yetki</h3>
            <p class="cy-feature__text">
                <code>password_hash</code>, oturum sabitleme koruması,
                hatalı giriş kilidi ve <em>admin / editör / üye</em>
                rol hiyerarşisi.
            </p>
        </article>

        <article class="cy-feature">
            <div class="cy-feature__icon" aria-hidden="true">⚙️</div>
            <h3 class="cy-feature__title">Ayarlar Tablosu</h3>
            <p class="cy-feature__text">
                Site adı, logo, sosyal medya, SEO ve bakım modu
                veritabanında. Yeni ayar eklemek için bir satır yeter —
                form otomatik üretilir.
            </p>
        </article>

        <article class="cy-feature">
            <div class="cy-feature__icon" aria-hidden="true">🛡️</div>
            <h3 class="cy-feature__title">Güvenlik Varsayılanları</h3>
            <p class="cy-feature__text">
                Hazır prepared statement, CSRF anahtarı, XSS kaçışlama,
                güvenli dosya yükleme ve sıkılaştırılmış oturum çerezi.
            </p>
        </article>

        <article class="cy-feature">
            <div class="cy-feature__icon" aria-hidden="true">🎨</div>
            <h3 class="cy-feature__title">Tasarım Kalıbı</h3>
            <p class="cy-feature__text">
                CSS değişkenleriyle kurulmuş marka teması, otomatik koyu
                mod ve hazır bileşenler. Tema rengini panelden
                değiştirebilirsiniz.
            </p>
        </article>

        <article class="cy-feature">
            <div class="cy-feature__icon" aria-hidden="true">📦</div>
            <h3 class="cy-feature__title">Sıfır Bağımlılık</h3>
            <p class="cy-feature__text">
                Composer yok, npm yok. jQuery, Bootstrap ve DataTables
                dosyaları projeyle birlikte gelir; internetsiz de çalışır.
            </p>
        </article>
    </div>
</section>


<!-- ---------- Çağrı (Call to action) ---------- -->
<section class="container pb-5">
    <div class="cy-card">
        <div class="cy-card__body">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="cy-eyebrow">Başlayalım</span>
                    <h2 class="cy-section__title mb-2">
                        <?php if ($aktifKullanici !== null): ?>
                            Şablon size hazır
                        <?php else: ?>
                            Sorularınız mı var?
                        <?php endif; ?>
                    </h2>
                    <p class="cy-section__lead">
                        <?php if ($aktifKullanici !== null): ?>
                            Ayarları düzenleyin, kendi tablolarınızı ekleyin ve
                            bu sayfayı kendi içeriğinizle değiştirin.
                        <?php else: ?>
                            İletişim sayfasından bize yazın; en kısa sürede
                            dönüş yapalım.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <?php if (is_admin()): ?>
                        <a href="yonetim/ayarlar.php" class="btn cy-btn cy-btn--primary">Ayarları Düzenle</a>
                    <?php else: ?>
                        <a href="iletisim.php" class="btn cy-btn cy-btn--primary">İletişime Geç</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================================
     TANITIM BÖLÜMLERİ — SON
     ================================================================== -->

<?php require __DIR__ . '/_alt.php'; ?>
