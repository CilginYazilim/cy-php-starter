<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – PROFİLİM
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Kullanıcı kendi bilgilerini, profil fotoğrafını ve parolasını
 *  buradan günceller.
 *
 *  ROL VE DURUM ALANLARI BİLEREK YOKTUR: kimse kendi rolünü
 *  yükseltemesin diye (privilege escalation / yetki yükseltme).
 *
 *  Kaydetme "POST → Redirect → GET" kalıbıyla yapılır: F5 formu
 *  tekrar göndermez ve sayfanın üst kısmı güncel bilgiyle çizilir.
 * =====================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/system/config.php';
require_once dirname(__DIR__) . '/system/panel.php';

require_role('editor', '../giris.php');

$aktifKullanici = auth_user($db);
$hatalar        = [];
$eskiDeger      = [];   // Hata olursa kullanıcının yazdıkları kaybolmasın

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $hatalar['genel'] = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } else {
        [$ad, $adHata]         = validate_name($_POST['ad'] ?? '', 'Ad');
        [$soyad, $soyadHata]   = validate_name($_POST['soyad'] ?? '', 'Soyad');
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

        if (mb_strlen($telefon, 'UTF-8') > 30) {
            $hatalar['telefon'] = 'Telefon en fazla 30 karakter olabilir.';
        }
        if (mb_strlen($hakkinda, 'UTF-8') > 1000) {
            $hatalar['hakkinda'] = 'Hakkında metni en fazla 1000 karakter olabilir.';
        }

        /* --- Parola değişikliği (opsiyonel) --- */
        $yeniSifre      = (string) ($_POST['yeni_sifre'] ?? '');
        $yeniSifreTekrar = (string) ($_POST['yeni_sifre_tekrar'] ?? '');
        $mevcutSifre    = (string) ($_POST['mevcut_sifre'] ?? '');
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
            } elseif ($yeniSifre !== $yeniSifreTekrar) {
                $hatalar['yeni_sifre_tekrar'] = 'Parolalar birbiriyle eşleşmiyor.';
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

            flash_set('success', $sifreDegisecek
                ? 'Profiliniz ve parolanız güncellendi.'
                : 'Profiliniz güncellendi.');

            /* Yönlendirme: aynı sayfaya GET ile dönüyoruz.
             * Böylece F5 formu tekrar göndermez ve üst şablon
             * güncel adı/e-postayı gösterir. */
            header('Location: profil.php');
            exit;
        }

        // Hata varsa girilen değerleri forma geri koy.
        $eskiDeger = [
            'ad'       => $ad,
            'soyad'    => $soyad,
            'eposta'   => $eposta,
            'telefon'  => $telefon,
            'hakkinda' => $hakkinda,
        ];
    }
}

$sayfaBaslik   = 'Profilim';
$sayfaAciklama = 'Hesap bilgilerinizi ve parolanızı yönetin.';
$aktifMenu     = 'profil';
$gerekenRol    = 'editor';
$kirintilar    = ['Profilim'];

require __DIR__ . '/_ust.php';

/** Formda gösterilecek değeri seçer: hatalıysa kullanıcının yazdığı. */
function profil_deger(array $eski, array $kullanici, string $alan): string
{
    return (string) ($eski[$alan] ?? $kullanici[$alan] ?? '');
}

$avatarAdres = avatar_url($aktifKullanici, '../');
?>

<?php if (isset($hatalar['genel'])): ?>
    <div class="alert alert-danger" role="alert"><?= e($hatalar['genel']) ?></div>
<?php elseif ($hatalar !== []): ?>
    <div class="alert alert-danger" role="alert">Lütfen formdaki hataları düzeltin.</div>
<?php endif; ?>

<div class="row g-3">

    <!-- ============================================================
         SOL – KİMLİK KARTI
         ============================================================ -->
    <div class="col-lg-4">
        <div class="adm-card adm-card--brand">
            <div class="adm-card__body text-center">
                <div class="d-inline-block position-relative mb-3">
                    <?php if ($avatarAdres !== ''): ?>
                        <img src="<?= e($avatarAdres) ?>" class="cy-avatar cy-avatar--lg" alt="Profil fotoğrafınız"
                             id="avatar_onizleme">
                    <?php else: ?>
                        <span class="cy-avatar cy-avatar--initial cy-avatar--lg" id="avatar_onizleme">
                            <?= e(user_initials($aktifKullanici)) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h3 class="h6 mb-1"><?= e($aktifKullanici['ad'] . ' ' . $aktifKullanici['soyad']) ?></h3>
                <p class="cy-muted small mb-3">@<?= e((string) $aktifKullanici['kullanici_adi']) ?></p>

                <span class="cy-badge cy-badge--soft">
                    <?= e(auth_role_labels()[$aktifKullanici['rol']] ?? (string) $aktifKullanici['rol']) ?>
                </span>

                <!-- ---------- Avatar yükleme ---------- -->
                <div class="mt-4 text-start">
                    <label for="avatar_dosya" class="form-label">Profil fotoğrafı</label>
                    <div class="input-group input-group-sm">
                        <input type="file" class="form-control" id="avatar_dosya"
                               accept="image/png,image/jpeg,image/gif,image/webp">
                        <button type="button" class="btn btn-outline-secondary" id="avatar_yukle">Yükle</button>
                    </div>
                    <div class="form-text">
                        JPG, PNG, GIF veya WEBP · en fazla
                        <?= (int) (UPLOAD_MAX_BYTES / 1024 / 1024) ?> MB. Yükleme anında kaydedilir.
                    </div>

                    <?php if ($avatarAdres !== ''): ?>
                        <button type="button" class="btn btn-outline-danger cy-btn btn-sm mt-2 w-100" id="avatar_sil">
                            Fotoğrafı kaldır
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ---------- Hesap özeti ---------- -->
        <div class="adm-card">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('info') ?> Hesap Özeti</h3>
            </div>
            <div class="adm-card__body">
                <div class="adm-kv">
                    <span class="adm-kv__k">Durum</span>
                    <span class="adm-kv__v">
                        <?= e(auth_status_labels()[$aktifKullanici['durum']] ?? (string) $aktifKullanici['durum']) ?>
                    </span>
                </div>
                <div class="adm-kv">
                    <span class="adm-kv__k">Kayıt tarihi</span>
                    <span class="adm-kv__v"><?= e(format_date($aktifKullanici['created_at'])) ?></span>
                </div>
                <div class="adm-kv">
                    <span class="adm-kv__k">Son giriş</span>
                    <span class="adm-kv__v"><?= e(format_date($aktifKullanici['son_giris'])) ?></span>
                </div>
                <div class="adm-kv">
                    <span class="adm-kv__k">Son giriş IP</span>
                    <span class="adm-kv__v"><?= e((string) $aktifKullanici['son_giris_ip'] ?: '-') ?></span>
                </div>
                <div class="adm-kv">
                    <span class="adm-kv__k">Toplam giriş</span>
                    <span class="adm-kv__v"><?= (int) $aktifKullanici['giris_sayisi'] ?></span>
                </div>
            </div>
        </div>
    </div>


    <!-- ============================================================
         SAĞ – FORM
         ============================================================ -->
    <div class="col-lg-8">
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- ---------- Hesap bilgileri ---------- -->
            <div class="adm-card">
                <div class="adm-card__header">
                    <h3 class="adm-card__title"><?= panel_icon('user') ?> Hesap Bilgileri</h3>
                </div>

                <div class="adm-card__body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="ad" class="form-label">Ad <span class="text-danger">*</span></label>
                            <input type="text" name="ad" id="ad" maxlength="100"
                                   class="form-control<?= isset($hatalar['ad']) ? ' is-invalid' : '' ?>"
                                   value="<?= e(profil_deger($eskiDeger, $aktifKullanici, 'ad')) ?>">
                            <div class="invalid-feedback"><?= e($hatalar['ad'] ?? '') ?></div>
                        </div>

                        <div class="col-sm-6">
                            <label for="soyad" class="form-label">Soyad <span class="text-danger">*</span></label>
                            <input type="text" name="soyad" id="soyad" maxlength="100"
                                   class="form-control<?= isset($hatalar['soyad']) ? ' is-invalid' : '' ?>"
                                   value="<?= e(profil_deger($eskiDeger, $aktifKullanici, 'soyad')) ?>">
                            <div class="invalid-feedback"><?= e($hatalar['soyad'] ?? '') ?></div>
                        </div>

                        <div class="col-sm-6">
                            <label for="eposta" class="form-label">E-posta <span class="text-danger">*</span></label>
                            <input type="email" name="eposta" id="eposta" maxlength="190"
                                   class="form-control<?= isset($hatalar['eposta']) ? ' is-invalid' : '' ?>"
                                   value="<?= e(profil_deger($eskiDeger, $aktifKullanici, 'eposta')) ?>">
                            <div class="invalid-feedback"><?= e($hatalar['eposta'] ?? '') ?></div>
                        </div>

                        <div class="col-sm-6">
                            <label for="telefon" class="form-label">Telefon</label>
                            <input type="text" name="telefon" id="telefon" maxlength="30"
                                   class="form-control<?= isset($hatalar['telefon']) ? ' is-invalid' : '' ?>"
                                   value="<?= e(profil_deger($eskiDeger, $aktifKullanici, 'telefon')) ?>">
                            <div class="invalid-feedback"><?= e($hatalar['telefon'] ?? '') ?></div>
                        </div>

                        <div class="col-12">
                            <label for="kullanici_adi_goster" class="form-label">Kullanıcı adı</label>
                            <input type="text" id="kullanici_adi_goster" class="form-control" disabled
                                   value="<?= e((string) $aktifKullanici['kullanici_adi']) ?>">
                            <div class="form-text">
                                Kullanıcı adı buradan değiştirilemez — giriş kimliğinizdir.
                                Değişmesi gerekiyorsa bir yönetici "Kullanıcılar" ekranından güncelleyebilir.
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="hakkinda" class="form-label">Hakkında</label>
                            <textarea name="hakkinda" id="hakkinda" rows="3" maxlength="1000"
                                      class="form-control<?= isset($hatalar['hakkinda']) ? ' is-invalid' : '' ?>"><?= e(profil_deger($eskiDeger, $aktifKullanici, 'hakkinda')) ?></textarea>
                            <div class="invalid-feedback"><?= e($hatalar['hakkinda'] ?? '') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ---------- Parola ---------- -->
            <div class="adm-card adm-card--warn">
                <div class="adm-card__header">
                    <h3 class="adm-card__title"><?= panel_icon('key') ?> Parola Değiştir</h3>
                </div>

                <div class="adm-card__body">
                    <p class="cy-muted small">
                        Parolanızı değiştirmek istemiyorsanız bu üç alanı boş bırakın.
                        Güvenlik gereği mevcut parolanız sorulur.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="mevcut_sifre" class="form-label">Mevcut Parola</label>
                            <input type="password" name="mevcut_sifre" id="mevcut_sifre" autocomplete="current-password"
                                   class="form-control<?= isset($hatalar['mevcut_sifre']) ? ' is-invalid' : '' ?>">
                            <div class="invalid-feedback"><?= e($hatalar['mevcut_sifre'] ?? '') ?></div>
                        </div>

                        <div class="col-md-4">
                            <label for="yeni_sifre" class="form-label">Yeni Parola</label>
                            <input type="password" name="yeni_sifre" id="yeni_sifre" autocomplete="new-password"
                                   class="form-control<?= isset($hatalar['yeni_sifre']) ? ' is-invalid' : '' ?>">
                            <div class="form-text">En az 8 karakter, harf ve rakam.</div>
                            <div class="invalid-feedback"><?= e($hatalar['yeni_sifre'] ?? '') ?></div>
                        </div>

                        <div class="col-md-4">
                            <label for="yeni_sifre_tekrar" class="form-label">Yeni Parola (tekrar)</label>
                            <input type="password" name="yeni_sifre_tekrar" id="yeni_sifre_tekrar" autocomplete="new-password"
                                   class="form-control<?= isset($hatalar['yeni_sifre_tekrar']) ? ' is-invalid' : '' ?>">
                            <div class="invalid-feedback"><?= e($hatalar['yeni_sifre_tekrar'] ?? '') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="adm-savebar">
                <span class="cy-muted small">Değişiklikler kaydedildiğinde hemen geçerli olur.</span>
                <button type="submit" class="btn cy-btn cy-btn--primary">
                    <?= panel_icon('save') ?> Profili Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<?php
ob_start();
?>
<script>
$(function () {
    'use strict';

    /* -------------------------------------------------------------
     *  AVATAR YÜKLEME
     * -------------------------------------------------------------
     *  Ana formdan BAĞIMSIZ çalışır: dosya seçilir seçilmez
     *  yüklenir. Böylece kullanıcı formu kaydetmeyi unutsa bile
     *  fotoğrafı kaybolmaz.
     * ----------------------------------------------------------- */
    $('#avatar_yukle').on('click', function () {
        var girdi = document.getElementById('avatar_dosya');

        if (!girdi.files || girdi.files.length === 0) {
            CY.notify('Önce bir dosya seçin.', 'info');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Yükleniyor…');

        var veri = new FormData();
        veri.append('action', 'avatar_yukle');
        veri.append('avatar', girdi.files[0]);

        CY.upload(veri)
            .done(function (res) {
                CY.notify(res.description, 'success');
                // Sayfayı yenilemek en temizi: kenar çubuğundaki ve üst
                // çubuktaki avatar da aynı anda güncellenir.
                window.location.reload();
            })
            .fail(function (xhr) {
                CY.notify(CY.hataMesaji(xhr, 'Fotoğraf yüklenemedi.'), 'danger');
                $btn.prop('disabled', false).text('Yükle');
            });
    });

    $('#avatar_sil').on('click', function () {
        CY.onay({
            baslik: 'Fotoğrafı Kaldır',
            metin:  'Profil fotoğrafınız silinecek ve baş harfleriniz gösterilecek.',
            buton:  'Evet, Kaldır'
        }, function () {
            CY.post({ action: 'avatar_sil' })
                .done(function () {
                    window.location.reload();
                })
                .fail(function (xhr) {
                    CY.notify(CY.hataMesaji(xhr, 'Fotoğraf kaldırılamadı.'), 'danger');
                });
        });
    });

    /* -------------------------------------------------------------
     *  PAROLA EŞLEŞME KONTROLÜ (anlık geri bildirim)
     * -------------------------------------------------------------
     *  Bu SADECE kullanıcı deneyimi içindir; asıl kontrol sunucuda
     *  yapılır. Tarayıcıdaki hiçbir doğrulamaya güvenilmez.
     * ----------------------------------------------------------- */
    $('#yeni_sifre, #yeni_sifre_tekrar').on('input', function () {
        var yeni   = $('#yeni_sifre').val();
        var tekrar = $('#yeni_sifre_tekrar').val();

        $('#yeni_sifre_tekrar').toggleClass('is-invalid', tekrar !== '' && yeni !== tekrar);
    });
});
</script>
<?php
$sayfaScript = ob_get_clean();
require __DIR__ . '/_alt.php';
