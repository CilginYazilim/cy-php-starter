<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Hesabım
 * =====================================================================
 */

use App\Models\Role;
use App\Models\User;

$profile = $user ?? $currentUser ?? null;
$errors  = $errors ?? [];
$old     = $old ?? [];

if ($profile === null) {
    return;
}

$value = static fn (string $field, string $fallback) => old($old, $field, $fallback);

/* "Mevcut parola" alanı YALNIZCA e-posta değişince görünür. Hep açık
 * duran bir parola alanı tarayıcının otomatik doldurmasını tetikliyordu:
 * Chrome parolayı oraya, kullanıcı adını da hemen üstteki Telefon
 * alanına yazıyordu; "Bilgileri Kaydet" telefonu bozuyordu. */
$epostaDegisti = isset($errors['eposta_sifre'])
    || mb_strtolower(trim((string) ($old['eposta'] ?? $profile->eposta))) !== mb_strtolower($profile->eposta);
?>

<?php /* SAYFA BAŞLIĞI ÜST ÇUBUKTA yazar; burada tekrar edilmez. */ ?>
<div class="cy-profile-grid">
    <div class="cy-card cy-profile-card">
        <img class="cy-avatar cy-avatar--xl<?= $profile->avatarUrl() === '' ? ' d-none' : '' ?>" id="profile_preview"
             src="<?= e($profile->avatarUrl()) ?>" alt="">
        <span class="cy-avatar cy-avatar--xl cy-avatar--initial<?= $profile->avatarUrl() !== '' ? ' d-none' : '' ?>" id="profile_preview_initial">
            <?= e($profile->initials()) ?>
        </span>

        <h3 class="cy-profile-card__name"><?= e($profile->fullName()) ?></h3>
        <p class="cy-profile-card__mail mb-2">@<?= e($profile->kullaniciAdi) ?> · <?= e($profile->eposta) ?></p>

        <span class="cy-role cy-role--<?= e(Role::variant($profile->rol)) ?>"><?= e($profile->roleLabel()) ?></span>

        <div class="cy-meta-list">
            <div class="cy-meta-list__row"><?= icon('phone', 'cy-icon cy-icon--sm') ?>
                <span><?= $profile->telefon !== '' ? e($profile->telefon) : '<em class="cy-muted">Telefon eklenmemiş</em>' ?></span>
            </div>
            <div class="cy-meta-list__row"><?= icon('calendar', 'cy-icon cy-icon--sm') ?>
                <span>Kayıt: <?= e(User::formatDate($profile->createdAt)) ?></span>
            </div>
            <div class="cy-meta-list__row"><?= icon('lock', 'cy-icon cy-icon--sm') ?>
                <span>Son giriş: <?= e(User::formatDate($profile->sonGiris)) ?></span>
            </div>
        </div>

        <form method="post" action="<?= e(url('panel/hesabim/avatar')) ?>" enctype="multipart/form-data" class="mt-3">
            <?= csrf_field() ?>
            <label class="form-label small mb-1" for="profil_avatar">Profil fotoğrafı</label>
            <input type="file" name="avatar" id="profil_avatar" class="form-control form-control-sm js-image-input"
                   accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="profil_avatar_ipucu"
                   data-preview="#profile_preview" data-placeholder="#profile_preview_initial">
            <div class="form-text mb-2" id="profil_avatar_ipucu">JPG, PNG, GIF veya WEBP · en fazla <?= upload_max_mb() ?> MB · ortadan kare kırpılır</div>
            <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm cy-btn--block">
                <?= icon('upload', 'cy-icon cy-icon--sm') ?> Fotoğrafı Değiştir
            </button>
        </form>

        <?php if ($profile->avatar !== ''): ?>
            <form method="post" action="<?= e(url('panel/hesabim/avatar-sil')) ?>" class="mt-2">
                <?= csrf_field() ?>
                <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm cy-btn--block">Fotoğrafı Kaldır</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-column gap-3">
        <div class="cy-card">
            <div class="cy-card__header"><h3 class="cy-section-title">Kişisel Bilgiler</h3></div>

            <form method="post" action="<?= e(url('panel/hesabim/guncelle')) ?>" novalidate>
                <?= csrf_field() ?>
                <?php /* Parola yöneticileri hangi hesabın parolası sorulduğunu bu
                         gizli alandan anlar; yoksa kullanıcı adını sayfadaki ilk
                         metin alanına (Telefon) yazıyorlardı. Adı yok: gönderilmez. */ ?>
                <input type="text" autocomplete="username" value="<?= e($profile->kullaniciAdi) ?>" class="d-none" tabindex="-1" aria-hidden="true">
                <div class="cy-card__body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="ad">Ad <span class="text-danger">*</span></label>
                            <input type="text" name="ad" id="ad" maxlength="100" autocomplete="given-name"
                                   class="form-control<?= isset($errors['ad']) ? ' is-invalid' : '' ?>"
                                   value="<?= $value('ad', $profile->ad) ?>">
                            <?php if (isset($errors['ad'])): ?><div class="invalid-feedback"><?= e($errors['ad']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="soyad">Soyad <span class="text-danger">*</span></label>
                            <input type="text" name="soyad" id="soyad" maxlength="100" autocomplete="family-name"
                                   class="form-control<?= isset($errors['soyad']) ? ' is-invalid' : '' ?>"
                                   value="<?= $value('soyad', $profile->soyad) ?>">
                            <?php if (isset($errors['soyad'])): ?><div class="invalid-feedback"><?= e($errors['soyad']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="eposta">E-posta <span class="text-danger">*</span></label>
                            <input type="email" name="eposta" id="eposta" maxlength="190" autocomplete="email" inputmode="email"
                                   class="form-control<?= isset($errors['eposta']) ? ' is-invalid' : '' ?>"
                                   data-original="<?= e($profile->eposta) ?>" aria-describedby="eposta_ipucu"
                                   value="<?= $value('eposta', $profile->eposta) ?>">
                            <div class="form-text" id="eposta_ipucu">Bildirimler ve parola sıfırlama bağlantısı bu adrese gelir. Değiştirirseniz eski adrese bilgi verilir.</div>
                            <?php if (isset($errors['eposta'])): ?><div class="invalid-feedback"><?= e($errors['eposta']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="telefon">Telefon</label>
                            <input type="tel" name="telefon" id="telefon" maxlength="30" autocomplete="tel" inputmode="tel"
                                   class="form-control<?= isset($errors['telefon']) ? ' is-invalid' : '' ?>"
                                   aria-describedby="telefon_ipucu"
                                   value="<?= $value('telefon', $profile->telefon) ?>">
                            <div class="form-text" id="telefon_ipucu">İsteğe bağlı · örn. +90 5XX XXX XX XX · yalnızca site yöneticileri görür</div>
                            <?php if (isset($errors['telefon'])): ?><div class="invalid-feedback"><?= e($errors['telefon']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="hakkinda">Hakkımda</label>
                            <textarea name="hakkinda" id="hakkinda" rows="3" maxlength="1000"
                                      aria-describedby="hakkinda_ipucu" data-sayac="#hakkinda_sayac"
                                      class="form-control<?= isset($errors['hakkinda']) ? ' is-invalid' : '' ?>"><?= $value('hakkinda', $profile->hakkinda) ?></textarea>
                            <div class="cy-field-meta">
                                <div class="form-text" id="hakkinda_ipucu">İsteğe bağlı · kendinizi bir iki cümleyle tanıtın; yalnızca panelde görünür.</div>
                                <span class="cy-sayac" id="hakkinda_sayac"></span>
                            </div>
                            <?php if (isset($errors['hakkinda'])): ?><div class="invalid-feedback"><?= e($errors['hakkinda']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12<?= $epostaDegisti ? '' : ' d-none' ?>" data-reveal-on-change="#eposta">
                            <label class="form-label" for="eposta_sifre">Mevcut Parola <span class="text-danger">*</span></label>
                            <input type="password" name="eposta_sifre" id="eposta_sifre" autocomplete="current-password"
                                   <?= $epostaDegisti ? '' : 'disabled' ?>
                                   class="form-control<?= isset($errors['eposta_sifre']) ? ' is-invalid' : '' ?>">
                            <?php if (isset($errors['eposta_sifre'])): ?>
                                <div class="invalid-feedback"><?= e($errors['eposta_sifre']) ?></div>
                            <?php else: ?>
                                <div class="form-text">E-posta hesabınızın kurtarma adresidir; değiştirirken parolanız sorulur.</div>
                            <?php endif; ?>
                        </div>

                        <!-- DİKKAT: "rol" ve "durum" alanı BİLEREK yok. -->
                    </div>
                </div>
                <div class="cy-card__footer d-flex justify-content-end">
                    <button type="submit" class="btn cy-btn cy-btn--primary"><?= icon('check', 'cy-icon cy-icon--sm') ?> Bilgileri Kaydet</button>
                </div>
            </form>
        </div>

        <div class="cy-card">
            <div class="cy-card__header"><h3 class="cy-section-title">Parola Değiştir</h3></div>
            <form method="post" action="<?= e(url('panel/hesabim/parola')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="cy-card__body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="mevcut_sifre">Mevcut Parola</label>
                            <div class="cy-password">
                                <input type="password" name="mevcut_sifre" id="mevcut_sifre" autocomplete="current-password"
                                       class="form-control<?= isset($errors['mevcut_sifre']) ? ' is-invalid' : '' ?>">
                                <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster"><?= icon('eye', 'cy-icon cy-icon--sm') ?></button>
                            </div>
                            <?php if (isset($errors['mevcut_sifre'])): ?><div class="invalid-feedback d-block"><?= e($errors['mevcut_sifre']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="yeni_sifre">Yeni Parola</label>
                            <div class="cy-password">
                                <input type="password" name="yeni_sifre" id="yeni_sifre" autocomplete="new-password"
                                       aria-describedby="yeni_sifre_ipucu"
                                       class="form-control<?= isset($errors['yeni_sifre']) ? ' is-invalid' : '' ?>">
                                <button type="button" class="cy-password__toggle js-toggle-password" aria-label="Parolayı göster"><?= icon('eye', 'cy-icon cy-icon--sm') ?></button>
                            </div>
                            <div class="form-text" id="yeni_sifre_ipucu"><?= e(password_hint()) ?></div>
                            <?php if (isset($errors['yeni_sifre'])): ?><div class="invalid-feedback d-block"><?= e($errors['yeni_sifre']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="yeni_sifre_tekrar">Yeni Parola (Tekrar)</label>
                            <input type="password" name="yeni_sifre_tekrar" id="yeni_sifre_tekrar" autocomplete="new-password"
                                   class="form-control<?= isset($errors['yeni_sifre_tekrar']) ? ' is-invalid' : '' ?>">
                            <?php if (isset($errors['yeni_sifre_tekrar'])): ?><div class="invalid-feedback d-block"><?= e($errors['yeni_sifre_tekrar']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="cy-card__footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="cy-muted small">Parola değişince diğer cihazlardaki oturumlarınız kapanır, API anahtarlarınız iptal edilir.</span>
                    <button type="submit" class="btn cy-btn cy-btn--ghost"><?= icon('lock', 'cy-icon cy-icon--sm') ?> Parolayı Güncelle</button>
                </div>
            </form>
        </div>

        <div class="cy-card">
            <div class="cy-card__header"><h3 class="cy-section-title">Oturumlar</h3></div>
            <div class="cy-card__body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <p class="mb-0 cy-muted small">
                    Ortak bir bilgisayarda çıkış yapmayı unuttuysanız ya da hesabınızın başka biri
                    tarafından kullanıldığından şüpheleniyorsanız bu cihaz dışındaki tüm oturumları
                    ve "beni hatırla" kayıtlarını kapatın.
                </p>
                <form method="post" action="<?= e(url('panel/hesabim/oturumlari-kapat')) ?>"
                      class="d-flex flex-column align-items-end gap-2"
                      data-confirm="Bu cihaz dışındaki tüm oturumlarınız kapatılacak. Devam edilsin mi?">
                    <?= csrf_field() ?>
                    <?php if (($apiTokens ?? null) !== null): ?>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="api_anahtarlari" value="1" id="api_anahtarlari" checked>
                            <label class="form-check-label small" for="api_anahtarlari">API anahtarlarımı da iptal et</label>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn cy-btn cy-btn--ghost"><?= icon('logout', 'cy-icon cy-icon--sm') ?> Diğer Cihazlardan Çıkış Yap</button>
                </form>
            </div>

            <?php /* MOBİL OTURUMLAR: uygulama POST /api/v1/oturum ile giriş yapınca
                     buraya bir satır düşer (ApiToken, tür "oturum"). Kaybolan bir
                     telefonun erişimi parola değiştirmeden tek tıkla kapatılır. */ ?>
            <div class="cy-card__body border-top">
                <h4 class="cy-eyebrow mb-2"><?= icon('mobil', 'cy-icon cy-icon--sm') ?> Bağlı cihazlar (mobil uygulama)</h4>
                <?php if (($mobilOturumlar ?? []) === []): ?>
                    <p class="cy-muted small mb-0">
                        Mobil uygulamadan giriş yapılmamış. Uygulama <code>POST /api/v1/oturum</code> ile giriş yaptığında
                        cihaz burada listelenir ve buradan kapatılabilir.
                    </p>
                <?php else: ?>
                    <div class="cy-table-wrap">
                        <table class="table cy-table cy-table--tight cy-table--cards w-100">
                            <thead>
                                <tr><th data-kart="ana">Cihaz</th><th>Açıldı</th><th>Son kullanım</th><th>Bitiş</th><th data-kart="islem"><span class="cy-sr-only">İşlem</span></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mobilOturumlar as $oturum): ?>
                                    <tr>
                                        <td><strong><?= e((string) (($oturum['cihaz'] ?? '') !== '' ? $oturum['cihaz'] : $oturum['ad'])) ?></strong></td>
                                        <td><?= e(User::formatDate($oturum['created_at'] ?? null)) ?></td>
                                        <td><?= e(User::formatDate($oturum['son_kullanim'] ?? null)) ?></td>
                                        <td>
                                            <?php if ((int) ($oturum['suresi_doldu'] ?? 0) === 1): ?>
                                                <span class="text-danger">süresi doldu</span>
                                            <?php else: ?>
                                                <?= e(User::formatDate($oturum['son_gecerlilik'] ?? null)) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="post" action="<?= e(url('panel/hesabim/cihaz/sil')) ?>"
                                                  data-confirm="Bu cihazdaki oturum kapatılacak. Emin misiniz?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="oturum_id" value="<?= (int) $oturum['id'] ?>">
                                                <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm"><?= icon('logout', 'cy-icon cy-icon--sm') ?> Oturumu Kapat</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($apiTokens ?? null) !== null): ?>
            <?php /* API ANAHTARLARI. Anahtarın açık hali veritabanında TUTULMAZ
                     (yalnızca SHA-256 özeti); bu yüzden yalnızca üretildiği an,
                     bir kez gösterilir. */ ?>
            <div class="cy-card">
                <div class="cy-card__header"><h3 class="cy-section-title"><?= icon('code', 'cy-icon cy-icon--sm') ?> API Anahtarları</h3></div>
                <div class="cy-card__body">
                    <?php if (!empty($yeniAnahtar) && is_string($yeniAnahtar)): ?>
                        <div class="cy-alert cy-alert--success mb-3">
                            <strong>Yeni anahtarınız:</strong> şimdi kopyalayın, bu sayfadan ayrılınca bir daha gösterilmez.
                            <div class="input-group mt-2">
                                <input type="text" class="form-control font-monospace" readonly value="<?= e($yeniAnahtar) ?>"
                                       aria-label="Yeni API anahtarı" id="yeni_api_anahtari">
                                <button type="button" class="btn cy-btn cy-btn--primary"
                                        data-copy-target="#yeni_api_anahtari" data-copy-label="API anahtarı">
                                    <?= icon('copy', 'cy-icon cy-icon--sm') ?> Kopyala
                                </button>
                            </div>
                            <div class="small mt-2">
                                Kullanım: <code>curl -H "Authorization: Bearer <?= e(substr($yeniAnahtar, 0, 8)) ?>…" <?= e(App\Core\Url::absolute('api/v1/ben')) ?></code>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($apiTokens === []): ?>
                        <p class="cy-muted small">Henüz anahtarınız yok. Mobil uygulama ya da başka bir sunucu bu siteye
                            <code>/api/v1/…</code> uçlarından erişecekse bir anahtar üretin.</p>
                    <?php else: ?>
                        <div class="cy-table-wrap mb-3">
                            <table class="table cy-table cy-table--tight w-100">
                                <thead>
                                    <tr><th>Ad</th><th>Ön ek</th><th>Kapsam</th><th>Son kullanım</th><th>Geçerlilik</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apiTokens as $anahtar): ?>
                                        <tr>
                                            <td><?= e((string) $anahtar['ad']) ?></td>
                                            <td><code><?= e((string) $anahtar['onek']) ?>…</code></td>
                                            <td>
                                                <?php if (($anahtar['kapsam'] ?? 'yazma') === 'okuma'): ?>
                                                    <span class="cy-pill">Yalnız okuma</span>
                                                <?php else: ?>
                                                    <span class="cy-pill is-warn">Okuma + yazma</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e(User::formatDate($anahtar['son_kullanim'] ?? null)) ?></td>
                                            <td>
                                                <?php if ((int) ($anahtar['suresi_doldu'] ?? 0) === 1): ?>
                                                    <span class="text-danger">süresi doldu</span>
                                                <?php elseif (!empty($anahtar['son_gecerlilik'])): ?>
                                                    <?= e(User::formatDate((string) $anahtar['son_gecerlilik'])) ?>
                                                <?php else: ?>
                                                    süresiz
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <form method="post" action="<?= e(url('panel/hesabim/api-anahtari/sil')) ?>"
                                                      data-confirm="Bu anahtar iptal edilecek; onu kullanan istemciler erişemeyecek. Emin misiniz?">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="anahtar_id" value="<?= (int) $anahtar['id'] ?>">
                                                    <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--sm"><?= icon('trash', 'cy-icon cy-icon--sm') ?> İptal Et</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="cy-muted small mb-3">
                            <?= icon('lock', 'cy-icon cy-icon--sm') ?>
                            Güvenlik gereği anahtarın tamamı saklanmaz (yalnızca SHA-256 özeti); üretildiği anda
                            <strong>bir kez</strong> gösterilir, burada yalnızca ön eki görünür. Kaybettiyseniz iptal edip
                            yenisini üretin.
                        </p>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('panel/hesabim/api-anahtari')) ?>" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="text" autocomplete="username" value="<?= e($profile->kullaniciAdi) ?>" class="d-none" tabindex="-1" aria-hidden="true">
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="anahtar_adi">Anahtar adı</label>
                            <input type="text" name="anahtar_adi" id="anahtar_adi" maxlength="100" class="form-control"
                                   autocomplete="off" placeholder="örn. Raporlama betiği" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="anahtar_kapsam">Kapsam</label>
                            <select name="anahtar_kapsam" id="anahtar_kapsam" class="form-select">
                                <option value="okuma" selected>Yalnız okuma</option>
                                <option value="yazma">Okuma + yazma</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="anahtar_gun">Geçerlilik</label>
                            <select name="anahtar_gun" id="anahtar_gun" class="form-select">
                                <option value="30">30 gün</option>
                                <option value="90" selected>90 gün</option>
                                <option value="180">180 gün</option>
                                <option value="365">1 yıl</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="anahtar_sifre">Mevcut parola</label>
                            <input type="password" name="anahtar_sifre" id="anahtar_sifre" class="form-control"
                                   autocomplete="current-password" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block"><?= icon('plus', 'cy-icon cy-icon--sm') ?> Üret</button>
                        </div>
                    </form>
                    <p class="cy-muted small mt-2 mb-0">Anahtar adı yalnızca sizin içindir: hangi uygulamanın kullandığını hatırlatır.
                        Anahtar sizin yetkilerinizle çalışır; sizden fazlasına asla erişemez.
                        "Yalnız okuma" anahtarı veri değiştiremez (GET dışındaki istekler 403 alır).
                        Parolanızı değiştirdiğinizde bütün anahtarlarınız iptal edilir.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php /* E-POSTA BİLDİRİMLERİ (bkz. App\Core\NotificationPrefs).
                 Duyurular kişiseldir; yönetici bildirimleri site ayarıdır ve
                 yalnızca ayar yetkisi olana görünür. Güvenlik mektupları
                 KAPATILAMAZ — bunu açıkça yazıyoruz. */ ?>
        <?php $tercih = $bildirimTercihi ?? App\Core\NotificationPrefs::VARSAYILAN; ?>
        <div class="cy-card" id="bildirimler">
            <div class="cy-card__header"><h3 class="cy-section-title"><?= icon('bell', 'cy-icon cy-icon--sm') ?> E-posta Bildirimleri</h3></div>
            <form method="post" action="<?= e(url('panel/hesabim/bildirimler')) ?>">
                <?= csrf_field() ?>
                <div class="cy-card__body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="duyuru" id="bildirim_duyuru" value="1"
                               aria-describedby="bildirim_duyuru_ipucu" <?= $tercih[App\Core\NotificationPrefs::DUYURU] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="bildirim_duyuru">Duyurular</label>
                        <div class="form-text mt-0" id="bildirim_duyuru_ipucu">Site yöneticisinin bütün üyelere ya da bir role gönderdiği toplu duyurular.</div>
                    </div>

                    <?php if (!empty($siteBildirimleri)): ?>
                        <?php $iletisimAdresi = App\Core\Mail\Mailer::adminAddress(); ?>
                        <p class="cy-form-section mt-0">Yönetici bildirimleri <span class="cy-muted small fw-normal">(site geneli)</span></p>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="yeni_mesaj" id="bildirim_yeni_mesaj" value="1"
                                   <?= setting_bool('mail_bildirim_yeni_mesaj', true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="bildirim_yeni_mesaj">Yeni iletişim mesajı</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="yeni_uye" id="bildirim_yeni_uye" value="1"
                                   <?= setting_bool('mail_bildirim_yeni_uye', false) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="bildirim_yeni_uye">Yeni üye kaydı</label>
                        </div>
                        <p class="form-text mb-3">
                            Bu ikisi iletişim adresine gider<?= $iletisimAdresi !== '' ? ' (' . e($iletisimAdresi) . ')' : '' ?>
                            ve Ayarlar → E-posta ekranındaki ayarlarla aynıdır.
                        </p>
                    <?php endif; ?>

                    <div class="cy-alert cy-alert--info mb-0 py-2 small" role="note">
                        <span class="cy-alert__icon"><?= icon('lock', 'cy-icon cy-icon--sm') ?></span>
                        <div class="cy-alert__body">
                            <strong>Güvenlik mektupları kapatılamaz:</strong> parolanız ya da e-posta adresiniz değiştiğinde,
                            hesap silme isteğinde ve parola sıfırlamada her zaman haber veririz. Hesabınıza başkası
                            girerse fark etmenizin yolu bu mektuplardır.
                        </div>
                    </div>
                </div>
                <div class="cy-card__footer d-flex justify-content-end">
                    <button type="submit" class="btn cy-btn cy-btn--primary"><?= icon('check', 'cy-icon cy-icon--sm') ?> Tercihleri Kaydet</button>
                </div>
            </form>
        </div>

        <?php if (!empty($hesapSilme)): ?>
            <?php /* HESABI SİL (KVKK). Silme 7 gün bekler; bu sürede giriş yapmak
                     silmeyi iptal eder. Bkz. App\Core\AccountDeletion. */ ?>
            <div class="cy-card cy-card--danger">
                <div class="cy-card__header"><h3 class="cy-section-title"><?= icon('trash', 'cy-icon cy-icon--sm') ?> Hesabımı Sil</h3></div>
                <form method="post" action="<?= e(url('panel/hesabim/sil')) ?>" class="cy-card__body"
                      data-confirm="Hesabınız <?= App\Core\AccountDeletion::GUN ?> gün sonra kalıcı olarak silinecek ve şimdi çıkış yapacaksınız. Devam edilsin mi?">
                    <?= csrf_field() ?>
                    <input type="text" autocomplete="username" value="<?= e($profile->kullaniciAdi) ?>" class="d-none" tabindex="-1" aria-hidden="true">
                    <p class="cy-muted small">
                        Hesabınız <strong><?= App\Core\AccountDeletion::GUN ?> gün sonra</strong> kalıcı olarak silinir; bu süre içinde
                        giriş yaparsanız silme iptal edilir. Bütün oturumlarınız ve API anahtarlarınız hemen kapatılır.
                        Gönderdiğiniz mesajlar ve yazdığınız içerikler sitede kalır, hesabınızla bağı kopar.
                    </p>
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="silme_sifre">Onay için parolanız <span class="text-danger">*</span></label>
                            <input type="password" name="silme_sifre" id="silme_sifre" class="form-control<?= isset($errors['silme_sifre']) ? ' is-invalid' : '' ?>"
                                   autocomplete="current-password" required>
                            <?php if (isset($errors['silme_sifre'])): ?>
                                <div class="invalid-feedback d-block" data-error-for="silme_sifre"><?= e($errors['silme_sifre']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <button type="submit" class="btn cy-btn cy-btn--danger cy-btn--block"><?= icon('trash', 'cy-icon cy-icon--sm') ?> Hesabımı Sil</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
