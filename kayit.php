<?php
/**
 * =====================================================================
 *  KAYIT OL (herkese açık)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Bu sayfa yalnızca yönetim panelindeki
 *  "Sistem › Yeni Kayıtlara Açık" ayarı AÇIK ise çalışır.
 *
 *  GÜVENLİK KARARLARI:
 *    • Yeni hesap HER ZAMAN "uye" rolüyle açılır. Rol formdan
 *      alınsaydı, isteyen kendini admin yapabilirdi.
 *    • Parola password_hash() ile özetlenir, düz metin saklanmaz.
 *    • Aynı e-posta/kullanıcı adı kontrolü hem PHP'de hem de
 *      veritabanındaki UNIQUE kısıtıyla yapılır (yarış koşulu).
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/system/config.php';

/* --- Erişim kuralları (çıktıdan önce) ----------------------------- */
if (auth_check()) {
    header('Location: hesabim.php');
    exit;
}

if (!setting_bool('sistem_kayit_acik')) {
    // Kayıtlar kapalı: sayfa hiç varmış gibi davranmak yerine
    // kullanıcıyı bilgilendirmek daha dürüst. 403 = "yasak".
    http_response_code(403);
    $kapali = true;
}

$hatalar = [];
$form    = ['ad' => '', 'soyad' => '', 'kullanici_adi' => '', 'eposta' => ''];

if (empty($kapali) && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $hatalar[] = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } else {
        [$ad,     $adHata]     = validate_name($_POST['ad'] ?? '', 'Ad');
        [$soyad,  $soyadHata]  = validate_name($_POST['soyad'] ?? '', 'Soyad');
        [$kadi,   $kadiHata]   = validate_username($_POST['kullanici_adi'] ?? '');
        [$eposta, $epostaHata] = validate_email($_POST['eposta'] ?? '', 'E-posta');

        $sifre  = (string) ($_POST['sifre'] ?? '');
        $sifre2 = (string) ($_POST['sifre_tekrar'] ?? '');

        // Formu tekrar çizerken kullanıcının yazdıkları kaybolmasın.
        $form = [
            'ad'            => $ad,
            'soyad'         => $soyad,
            'kullanici_adi' => $kadi,
            'eposta'        => $eposta,
        ];

        foreach ([$adHata, $soyadHata, $kadiHata, $epostaHata] as $alanHatasi) {
            if ($alanHatasi !== null) {
                $hatalar[] = $alanHatasi;
            }
        }

        $sifreHata = validate_password($sifre, 'Parola');
        if ($sifreHata !== null) {
            $hatalar[] = $sifreHata;
        } elseif (!hash_equals($sifre, $sifre2)) {
            $hatalar[] = 'Parolalar birbiriyle uyuşmuyor.';
        }

        if ($hatalar === [] && user_field_taken($db, 'eposta', $eposta)) {
            $hatalar[] = 'Bu e-posta adresiyle zaten bir hesap var.';
        }
        if ($hatalar === [] && user_field_taken($db, 'kullanici_adi', $kadi)) {
            $hatalar[] = 'Bu kullanıcı adı alınmış, başka bir tane deneyin.';
        }

        if ($hatalar === []) {
            try {
                $db->prepare(
                    'INSERT INTO kullanicilar (ad, soyad, kullanici_adi, eposta, sifre, rol, durum)
                     VALUES (:ad, :soyad, :kadi, :eposta, :sifre, :rol, :durum)'
                )->execute([
                    ':ad'     => $ad,
                    ':soyad'  => $soyad,
                    ':kadi'   => $kadi,
                    ':eposta' => $eposta,
                    ':sifre'  => hash_password($sifre),
                    // Rol BİLEREK sabit; formdan gelmiyor.
                    ':rol'    => 'uye',
                    ':durum'  => 'aktif',
                ]);

                header('Location: giris.php?kayit=tamam');
                exit;

            } catch (PDOException $e) {
                // 23000 = UNIQUE kısıtı ihlali. İki kişi aynı anda aynı
                // e-postayla kaydolmaya çalıştıysa burada yakalanır.
                if ($e->getCode() === '23000') {
                    $hatalar[] = 'Bu e-posta veya kullanıcı adı az önce alındı. Farklı bir tane deneyin.';
                } else {
                    $hatalar[] = APP_DEBUG
                        ? 'Veritabanı hatası: ' . $e->getMessage()
                        : 'Kayıt oluşturulamadı. Lütfen daha sonra tekrar deneyin.';
                }
            }
        }
    }
}

$sayfaBaslik   = 'Kayıt Ol';
$aktifSayfa    = 'kayit';
$sayfaAciklama = 'Ücretsiz hesap oluşturun.';

require __DIR__ . '/_ust.php';
?>

<div class="container cy-auth">

    <?php if (!empty($kapali)): ?>

        <div class="cy-card">
            <div class="cy-card__body text-center py-5">
                <div style="font-size:2.5rem" aria-hidden="true">🔒</div>
                <h1 class="h5 mt-3">Yeni kayıtlar şu anda kapalı</h1>
                <p class="cy-muted">
                    Site yöneticisi kayıt alımını geçici olarak durdurmuş.
                    Hesabınız varsa giriş yapabilirsiniz.
                </p>
                <a href="giris.php" class="btn cy-btn cy-btn--primary">Giriş Yap</a>
            </div>
        </div>

    <?php else: ?>

        <div class="text-center mb-4">
            <h1 class="h4 mb-1">Hesap oluşturun</h1>
            <p class="cy-muted small mb-0">Bir dakikadan kısa sürer.</p>
        </div>

        <div class="cy-card">
            <div class="cy-card__body">

                <?php if ($hatalar !== []): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($hatalar as $hata): ?>
                                <li><?= e($hata) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="ad" class="form-label">Ad <span class="text-danger">*</span></label>
                            <input type="text" name="ad" id="ad" class="form-control" maxlength="100"
                                   autocomplete="given-name" value="<?= e($form['ad']) ?>">
                        </div>

                        <div class="col-sm-6">
                            <label for="soyad" class="form-label">Soyad <span class="text-danger">*</span></label>
                            <input type="text" name="soyad" id="soyad" class="form-control" maxlength="100"
                                   autocomplete="family-name" value="<?= e($form['soyad']) ?>">
                        </div>

                        <div class="col-12">
                            <label for="kullanici_adi" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                            <input type="text" name="kullanici_adi" id="kullanici_adi" class="form-control" maxlength="50"
                                   autocomplete="username" value="<?= e($form['kullanici_adi']) ?>">
                            <div class="form-text">Harf, rakam, nokta ve alt çizgi kullanabilirsiniz.</div>
                        </div>

                        <div class="col-12">
                            <label for="eposta" class="form-label">E-posta <span class="text-danger">*</span></label>
                            <input type="email" name="eposta" id="eposta" class="form-control" maxlength="190"
                                   autocomplete="email" value="<?= e($form['eposta']) ?>">
                        </div>

                        <div class="col-sm-6">
                            <label for="sifre" class="form-label">Parola <span class="text-danger">*</span></label>
                            <input type="password" name="sifre" id="sifre" class="form-control"
                                   autocomplete="new-password">
                            <div class="form-text">En az 8 karakter, bir harf ve bir rakam.</div>
                        </div>

                        <div class="col-sm-6">
                            <label for="sifre_tekrar" class="form-label">Parola (tekrar) <span class="text-danger">*</span></label>
                            <input type="password" name="sifre_tekrar" id="sifre_tekrar" class="form-control"
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn cy-btn cy-btn--primary w-100 mt-4">Hesabımı Oluştur</button>
                </form>

                <div class="cy-divider">zaten hesabınız var mı?</div>
                <a href="giris.php" class="btn btn-outline-secondary cy-btn w-100">Giriş Yap</a>

            </div>
        </div>

    <?php endif; ?>

    <p class="cy-footer-note mt-4 mb-0 text-center">
        <a href="index.php">← Siteye dön</a>
    </p>
</div>

<?php require __DIR__ . '/_alt.php'; ?>
