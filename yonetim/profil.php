<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – PROFİLİM
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Kullanıcı kendi bilgilerini ve parolasını buradan günceller.
 *  Rol ve durum alanları BİLEREK yoktur: kimse kendi rolünü
 *  yükseltemesin diye (privilege escalation).
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik = 'Profilim';
$aktifMenu   = 'profil';
$gerekenRol  = 'editor';

require __DIR__ . '/_ust.php';

$bildirim = null;
$hatalar  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $bildirim = ['danger', 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'];
    } else {
        [$ad, $adHata]       = validate_name($_POST['ad'] ?? '', 'Ad');
        [$soyad, $soyadHata] = validate_name($_POST['soyad'] ?? '', 'Soyad');
        [$eposta, $epostaHata] = validate_email($_POST['eposta'] ?? '');

        if ($adHata !== null)     { $hatalar['ad'] = $adHata; }
        if ($soyadHata !== null)  { $hatalar['soyad'] = $soyadHata; }
        if ($epostaHata !== null) { $hatalar['eposta'] = $epostaHata; }

        // E-posta başkasında kayıtlı mı? (kendi kaydımız hariç)
        if ($epostaHata === null && user_field_taken($db, 'eposta', $eposta, (int) $aktifKullanici['id'])) {
            $hatalar['eposta'] = 'Bu e-posta adresi başka bir hesapta kayıtlı.';
        }

        $telefon  = trim((string) ($_POST['telefon'] ?? ''));
        $hakkinda = trim((string) ($_POST['hakkinda'] ?? ''));

        /* --- Parola değişikliği (opsiyonel) --- */
        $yeniSifre    = (string) ($_POST['yeni_sifre'] ?? '');
        $mevcutSifre  = (string) ($_POST['mevcut_sifre'] ?? '');
        $sifreDegisecek = $yeniSifre !== '';

        if ($sifreDegisecek) {
            /* GÜVENLİK: Parola değiştirirken MEVCUT parolayı sormak şarttır.
             * Aksi halde birinin açık kalmış oturumunu ele geçiren biri,
             * parolayı değiştirip hesabı kalıcı olarak çalabilirdi. */
            if (!password_verify($mevcutSifre, $aktifKullanici['sifre'])) {
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
                ':id'       => $aktifKullanici['id'],
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

            $bildirim = ['success', 'Profiliniz güncellendi.'];

            // Ekrandaki bilgileri tazele.
            $aktifKullanici = find_user($db, (int) $aktifKullanici['id']);
        } else {
            $bildirim = ['danger', 'Lütfen formdaki hataları düzeltin.'];
            // Kullanıcının yazdıklarını kaybetmemek için forma geri koy.
            $aktifKullanici['ad']       = $ad;
            $aktifKullanici['soyad']    = $soyad;
            $aktifKullanici['eposta']   = $eposta;
            $aktifKullanici['telefon']  = $telefon;
            $aktifKullanici['hakkinda'] = $hakkinda;
        }
    }
}
?>

<?php if ($bildirim !== null): ?>
    <div class="alert alert-<?= e($bildirim[0]) ?>" role="alert"><?= e($bildirim[1]) ?></div>
<?php endif; ?>

<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <h2 class="h6 text-uppercase cy-muted mb-3">Hesap Bilgileri</h2>

    <div class="row g-3 mb-4">
        <div class="col-sm-6">
            <label for="ad" class="form-label">Ad <span class="text-danger">*</span></label>
            <input type="text" name="ad" id="ad" maxlength="100"
                   class="form-control<?= isset($hatalar['ad']) ? ' is-invalid' : '' ?>"
                   value="<?= e((string) $aktifKullanici['ad']) ?>">
            <div class="invalid-feedback"><?= e($hatalar['ad'] ?? '') ?></div>
        </div>

        <div class="col-sm-6">
            <label for="soyad" class="form-label">Soyad <span class="text-danger">*</span></label>
            <input type="text" name="soyad" id="soyad" maxlength="100"
                   class="form-control<?= isset($hatalar['soyad']) ? ' is-invalid' : '' ?>"
                   value="<?= e((string) $aktifKullanici['soyad']) ?>">
            <div class="invalid-feedback"><?= e($hatalar['soyad'] ?? '') ?></div>
        </div>

        <div class="col-sm-6">
            <label for="eposta" class="form-label">E-posta <span class="text-danger">*</span></label>
            <input type="email" name="eposta" id="eposta" maxlength="190"
                   class="form-control<?= isset($hatalar['eposta']) ? ' is-invalid' : '' ?>"
                   value="<?= e((string) $aktifKullanici['eposta']) ?>">
            <div class="invalid-feedback"><?= e($hatalar['eposta'] ?? '') ?></div>
        </div>

        <div class="col-sm-6">
            <label for="telefon" class="form-label">Telefon</label>
            <input type="text" name="telefon" id="telefon" class="form-control" maxlength="30"
                   value="<?= e((string) $aktifKullanici['telefon']) ?>">
        </div>

        <div class="col-12">
            <label for="hakkinda" class="form-label">Hakkında</label>
            <textarea name="hakkinda" id="hakkinda" class="form-control" rows="3"
                      maxlength="1000"><?= e((string) $aktifKullanici['hakkinda']) ?></textarea>
        </div>
    </div>

    <h2 class="h6 text-uppercase cy-muted mb-3">Parola Değiştir</h2>
    <p class="cy-muted small">Parolanızı değiştirmek istemiyorsanız bu iki alanı boş bırakın.</p>

    <div class="row g-3 mb-4">
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
            <div class="form-text">En az 8 karakter, harf ve rakam.</div>
            <div class="invalid-feedback"><?= e($hatalar['yeni_sifre'] ?? '') ?></div>
        </div>
    </div>

    <button type="submit" class="btn cy-btn cy-btn--primary">Profili Kaydet</button>
</form>

<hr class="my-4">

<h2 class="h6 text-uppercase cy-muted mb-3">Hesap Özeti</h2>
<dl class="cy-detail">
    <dt>Kullanıcı adı</dt>
    <dd><?= e((string) $aktifKullanici['kullanici_adi']) ?></dd>

    <dt>Rol</dt>
    <dd><?= e(auth_role_labels()[$aktifKullanici['rol']] ?? (string) $aktifKullanici['rol']) ?></dd>

    <dt>Durum</dt>
    <dd><?= e(auth_status_labels()[$aktifKullanici['durum']] ?? (string) $aktifKullanici['durum']) ?></dd>

    <dt>Kayıt tarihi</dt>
    <dd><?= e(format_date($aktifKullanici['created_at'])) ?></dd>

    <dt>Son giriş</dt>
    <dd><?= e(format_date($aktifKullanici['son_giris'])) ?></dd>

    <dt>Giriş sayısı</dt>
    <dd><?= (int) $aktifKullanici['giris_sayisi'] ?></dd>
</dl>

<?php require __DIR__ . '/_alt.php'; ?>
