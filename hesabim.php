<?php
/**
 * =====================================================================
 *  HESABIM (giriş gerektirir)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Kullanıcının ön yüzdeki profil sayfası. Yönetim panelindeki
 *  yonetim/profil.php ile benzer işi yapar; farkı şudur:
 *    • Panel sayfası "editor" ve üstü rol ister
 *    • Bu sayfa TÜM giriş yapmış kullanıcılara (üye dahil) açıktır
 *    • Burada ayrıca avatar yükleme vardır
 *
 *  GÜVENLİK: Formda rol ve durum alanı BİLEREK YOKTUR. Olsaydı
 *  herhangi bir üye kendi rolünü "admin" yapıp paneli ele geçirebilirdi
 *  (yetki yükseltme / privilege escalation).
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/system/config.php';

/* Giriş yoksa giriş sayfasına gönder. require_login() nereden
 * geldiğinizi oturuma yazar; giriş yapınca buraya geri dönersiniz. */
require_login('giris.php');

$kullanici = auth_user($db);

/* FLASH MESAJ
 * ---------------------------------------------------------------------
 * Başarılı kayıttan sonra sayfaya YÖNLENDİRİYORUZ (POST-Redirect-GET).
 * Böylece kullanıcı F5'e bastığında "formu tekrar gönder" uyarısı almaz
 * ve aynı işlem iki kez çalışmaz. Ama yönlendirme sırasında $bildirim
 * değişkeni kaybolurdu; bu yüzden mesajı bir sonraki isteğe taşımak
 * için oturuma yazıp orada bir kez okuyup siliyoruz. */
$bildirim = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$hatalar  = [];

/* =====================================================================
 *  FORM İŞLEME
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $bildirim = ['danger', 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'];
    } else {

        $islem = (string) ($_POST['islem'] ?? 'bilgiler');

        /* ---------------------------------------------------------------
         *  1) AVATAR YÜKLEME
         * ------------------------------------------------------------ */
        if ($islem === 'avatar') {

            try {
                // upload_image() dosyanın GERÇEK türünü içeriğinden
                // (getimagesize) okur, adını sunucu üretir. Böylece
                // "resim.php.png" gibi dosyalar çalıştırılamaz.
                $yeniDosya = upload_image($_FILES['avatar'] ?? []);

                // Eski avatarı diskten sil ki çöp dosya birikmesin.
                // Dosya adını İSTEKTEN DEĞİL veritabanından okuyoruz.
                $eski = (string) $kullanici['avatar'];

                $db->prepare('UPDATE kullanicilar SET avatar = :avatar WHERE id = :id')
                   ->execute([':avatar' => $yeniDosya, ':id' => $kullanici['id']]);

                if ($eski !== '' && $eski !== $yeniDosya) {
                    delete_upload($eski);
                }

                $_SESSION['flash'] = ['success', 'Profil fotoğrafınız güncellendi.'];
                header('Location: hesabim.php');
                exit;

            } catch (RuntimeException $e) {
                $bildirim = ['danger', $e->getMessage()];
            }
        }

        /* ---------------------------------------------------------------
         *  2) AVATAR KALDIRMA
         * ------------------------------------------------------------ */
        elseif ($islem === 'avatar_sil') {

            $eski = (string) $kullanici['avatar'];

            if ($eski !== '') {
                $db->prepare("UPDATE kullanicilar SET avatar = '' WHERE id = :id")
                   ->execute([':id' => $kullanici['id']]);

                delete_upload($eski);
            }

            $_SESSION['flash'] = ['success', 'Profil fotoğrafınız kaldırıldı.'];
            header('Location: hesabim.php');
            exit;
        }

        /* ---------------------------------------------------------------
         *  3) BİLGİLER + PAROLA
         * ------------------------------------------------------------ */
        else {
            [$ad, $adHata]         = validate_name($_POST['ad'] ?? '', 'Ad');
            [$soyad, $soyadHata]   = validate_name($_POST['soyad'] ?? '', 'Soyad');
            [$eposta, $epostaHata] = validate_email($_POST['eposta'] ?? '');

            if ($adHata !== null)     { $hatalar['ad']     = $adHata; }
            if ($soyadHata !== null)  { $hatalar['soyad']  = $soyadHata; }
            if ($epostaHata !== null) { $hatalar['eposta'] = $epostaHata; }

            // E-posta başkasında kayıtlı mı? (kendi kaydımız hariç)
            if ($epostaHata === null && user_field_taken($db, 'eposta', $eposta, (int) $kullanici['id'])) {
                $hatalar['eposta'] = 'Bu e-posta adresi başka bir hesapta kayıtlı.';
            }

            $telefon  = trim((string) ($_POST['telefon'] ?? ''));
            $hakkinda = trim((string) ($_POST['hakkinda'] ?? ''));

            /* --- Parola değişikliği (opsiyonel) --- */
            $yeniSifre      = (string) ($_POST['yeni_sifre'] ?? '');
            $mevcutSifre    = (string) ($_POST['mevcut_sifre'] ?? '');
            $sifreDegisecek = $yeniSifre !== '';

            if ($sifreDegisecek) {
                /* GÜVENLİK: Parola değiştirirken MEVCUT parolayı sormak şarttır.
                 * Aksi halde açık kalmış bir oturumu ele geçiren biri parolayı
                 * değiştirip hesabı kalıcı olarak çalabilirdi. */
                if (!password_verify($mevcutSifre, (string) $kullanici['sifre'])) {
                    $hatalar['mevcut_sifre'] = 'Mevcut parolanız hatalı.';
                }

                $sifreHata = validate_password($yeniSifre, 'Yeni parola');
                if ($sifreHata !== null) {
                    $hatalar['yeni_sifre'] = $sifreHata;
                }
            }

            if ($hatalar === []) {
                $sql = 'UPDATE kullanicilar
                           SET ad = :ad, soyad = :soyad, eposta = :eposta,
                               telefon = :telefon, hakkinda = :hakkinda';

                $params = [
                    ':ad'       => $ad,
                    ':soyad'    => $soyad,
                    ':eposta'   => $eposta,
                    ':telefon'  => $telefon,
                    ':hakkinda' => $hakkinda,
                    ':id'       => $kullanici['id'],
                ];

                if ($sifreDegisecek) {
                    $sql .= ', sifre = :sifre';
                    $params[':sifre'] = hash_password($yeniSifre);
                }

                $sql .= ' WHERE id = :id';
                $db->prepare($sql)->execute($params);

                /* Parola değiştiyse oturum kimliğini yenile: eski bir
                 * oturum çerezini ele geçirmiş biri varsa devre dışı kalır. */
                if ($sifreDegisecek) {
                    session_regenerate_id(true);
                }

                $_SESSION['flash'] = ['success', $sifreDegisecek
                    ? 'Bilgileriniz ve parolanız güncellendi.'
                    : 'Bilgileriniz güncellendi.'];

                header('Location: hesabim.php');
                exit;
            } else {
                $bildirim = ['danger', 'Lütfen formdaki hataları düzeltin.'];

                // Kullanıcının yazdıkları kaybolmasın.
                $kullanici['ad']       = $ad;
                $kullanici['soyad']    = $soyad;
                $kullanici['eposta']   = $eposta;
                $kullanici['telefon']  = $telefon;
                $kullanici['hakkinda'] = $hakkinda;
            }
        }
    }
}

$sayfaBaslik   = 'Hesabım';
$aktifSayfa    = 'hesabim';
$sayfaAciklama = 'Hesap bilgilerinizi görüntüleyin ve düzenleyin.';

require __DIR__ . '/_ust.php';

// Üst şablon $aktifKullanici'yi önbellekten okur; POST sonrası
// güncellenmiş satırı göstermek için kendi değişkenimizi kullanıyoruz.
$avatar = avatar_url($kullanici);
?>

<div class="cy-page-head">
    <div class="container">
        <nav class="cy-breadcrumb" aria-label="Konum">
            <a href="index.php">Ana Sayfa</a> <span aria-hidden="true">›</span> Hesabım
        </nav>
        <h1>Hesabım</h1>
        <p>Kişisel bilgilerinizi ve güvenlik ayarlarınızı buradan yönetin.</p>
    </div>
</div>

<section class="cy-section container">

    <?php if ($bildirim !== null): ?>
        <div class="alert alert-<?= e($bildirim[0]) ?>" role="alert"><?= e($bildirim[1]) ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ================= SOL: PROFİL KARTI ================= -->
        <div class="col-lg-4">
            <div class="cy-card mb-4">
                <div class="cy-card__body text-center">

                    <?php if ($avatar !== ''): ?>
                        <img src="<?= e($avatar) ?>" class="cy-avatar cy-avatar--lg cy-avatar--circle mx-auto d-block" alt="Profil fotoğrafınız">
                    <?php else: ?>
                        <span class="cy-avatar cy-avatar--initial cy-avatar--lg cy-avatar--circle mx-auto d-flex">
                            <?= e(user_initials($kullanici)) ?>
                        </span>
                    <?php endif; ?>

                    <h2 class="h5 mt-3 mb-1">
                        <?= e($kullanici['ad'] . ' ' . $kullanici['soyad']) ?>
                    </h2>
                    <p class="cy-muted small mb-3">@<?= e((string) $kullanici['kullanici_adi']) ?></p>

                    <span class="cy-badge cy-badge--soft">
                        <?= e(auth_role_labels()[$kullanici['rol']] ?? (string) $kullanici['rol']) ?>
                    </span>

                    <!-- ---------- Avatar yükleme ---------- -->
                    <form method="post" enctype="multipart/form-data" class="mt-4 text-start">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="islem" value="avatar">

                        <label for="avatar" class="form-label">Profil fotoğrafı</label>
                        <input type="file" name="avatar" id="avatar" class="form-control form-control-sm"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="form-text">
                            JPG, PNG, GIF veya WebP &middot; en fazla
                            <?= (int) (UPLOAD_MAX_BYTES / 1024 / 1024) ?> MB
                        </div>

                        <button type="submit" class="btn cy-btn cy-btn--primary btn-sm w-100 mt-3">
                            Fotoğrafı Yükle
                        </button>
                    </form>

                    <?php if ($avatar !== ''): ?>
                        <form method="post" class="mt-2"
                              onsubmit="return confirm('Profil fotoğrafınız silinecek. Onaylıyor musunuz?');">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="islem" value="avatar_sil">
                            <button type="submit" class="btn btn-outline-danger cy-btn btn-sm w-100">
                                Fotoğrafı Kaldır
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ---------- Hesap özeti ---------- -->
            <div class="cy-card">
                <div class="cy-card__header">
                    <h2 class="cy-brand__title mb-0">Hesap Özeti</h2>
                </div>
                <div class="cy-card__body">
                    <dl class="cy-detail mb-0">
                        <dt>Durum</dt>
                        <dd><?= e(auth_status_labels()[$kullanici['durum']] ?? (string) $kullanici['durum']) ?></dd>

                        <dt>Kayıt tarihi</dt>
                        <dd><?= e(format_date($kullanici['created_at'])) ?></dd>

                        <dt>Son giriş</dt>
                        <dd><?= e(format_date($kullanici['son_giris'])) ?></dd>

                        <dt>Giriş sayısı</dt>
                        <dd><?= (int) $kullanici['giris_sayisi'] ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- ================= SAĞ: FORMLAR ================= -->
        <div class="col-lg-8">
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="islem" value="bilgiler">

                <div class="cy-card mb-4">
                    <div class="cy-card__header">
                        <h2 class="cy-brand__title mb-0">Kişisel Bilgiler</h2>
                    </div>
                    <div class="cy-card__body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="ad" class="form-label">Ad <span class="text-danger">*</span></label>
                                <input type="text" name="ad" id="ad" maxlength="100"
                                       class="form-control<?= isset($hatalar['ad']) ? ' is-invalid' : '' ?>"
                                       value="<?= e((string) $kullanici['ad']) ?>">
                                <div class="invalid-feedback"><?= e($hatalar['ad'] ?? '') ?></div>
                            </div>

                            <div class="col-sm-6">
                                <label for="soyad" class="form-label">Soyad <span class="text-danger">*</span></label>
                                <input type="text" name="soyad" id="soyad" maxlength="100"
                                       class="form-control<?= isset($hatalar['soyad']) ? ' is-invalid' : '' ?>"
                                       value="<?= e((string) $kullanici['soyad']) ?>">
                                <div class="invalid-feedback"><?= e($hatalar['soyad'] ?? '') ?></div>
                            </div>

                            <div class="col-sm-6">
                                <label for="eposta" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" name="eposta" id="eposta" maxlength="190"
                                       class="form-control<?= isset($hatalar['eposta']) ? ' is-invalid' : '' ?>"
                                       value="<?= e((string) $kullanici['eposta']) ?>">
                                <div class="invalid-feedback"><?= e($hatalar['eposta'] ?? '') ?></div>
                            </div>

                            <div class="col-sm-6">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="text" name="telefon" id="telefon" class="form-control" maxlength="30"
                                       value="<?= e((string) $kullanici['telefon']) ?>">
                            </div>

                            <div class="col-12">
                                <label for="kullanici_adi_goster" class="form-label">Kullanıcı Adı</label>
                                <!-- readonly: kullanıcı adı kimliğin parçasıdır; değiştirilmesi
                                     eski bağlantıları ve kayıtları bozar. Gerekirse yönetici
                                     panelden değiştirir. -->
                                <input type="text" id="kullanici_adi_goster" class="form-control" readonly
                                       value="<?= e((string) $kullanici['kullanici_adi']) ?>">
                                <div class="form-text">Kullanıcı adı değiştirilemez.</div>
                            </div>

                            <div class="col-12">
                                <label for="hakkinda" class="form-label">Hakkımda</label>
                                <textarea name="hakkinda" id="hakkinda" class="form-control" rows="4"
                                          maxlength="1000"><?= e((string) $kullanici['hakkinda']) ?></textarea>
                                <div class="form-text">Kendinizden kısaca bahsedin (en fazla 1000 karakter).</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cy-card mb-4">
                    <div class="cy-card__header">
                        <h2 class="cy-brand__title mb-0">Parola Değiştir</h2>
                        <p class="cy-brand__subtitle mb-0">
                            Değiştirmek istemiyorsanız bu iki alanı boş bırakın.
                        </p>
                    </div>
                    <div class="cy-card__body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="mevcut_sifre" class="form-label">Mevcut Parola</label>
                                <input type="password" name="mevcut_sifre" id="mevcut_sifre" autocomplete="current-password"
                                       class="form-control<?= isset($hatalar['mevcut_sifre']) ? ' is-invalid' : '' ?>">
                                <div class="invalid-feedback"><?= e($hatalar['mevcut_sifre'] ?? '') ?></div>
                            </div>

                            <div class="col-sm-6">
                                <label for="yeni_sifre" class="form-label">Yeni Parola</label>
                                <input type="password" name="yeni_sifre" id="yeni_sifre" autocomplete="new-password"
                                       class="form-control<?= isset($hatalar['yeni_sifre']) ? ' is-invalid' : '' ?>">
                                <div class="form-text">En az 8 karakter, bir harf ve bir rakam.</div>
                                <div class="invalid-feedback"><?= e($hatalar['yeni_sifre'] ?? '') ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn cy-btn cy-btn--primary">Değişiklikleri Kaydet</button>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/_alt.php'; ?>
