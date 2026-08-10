<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: E-posta Merkezi
 * ---------------------------------------------------------------------
 *  İki sekme: "Gönder" (yazma ekranı) ve "Geçmiş" (giden mektuplar).
 *
 *  @var array<string,int>            $stats
 *  @var array<string,string>         $config
 *  @var array<string,string>         $roles
 *  @var array<int,array>             $users
 *  @var array<string,int>            $sayilar
 *  @var array{adres:string,konu:string} $prefill
 *  @var bool   $canSend
 *  @var bool   $yapilandi
 *  @var string $gonderen
 * =====================================================================
 */

$stats     = $stats ?? ['toplam' => 0, 'gonderildi' => 0, 'kuyrukta' => 0, 'basarisiz' => 0, 'bugun' => 0];
$prefill   = $prefill ?? ['adres' => '', 'konu' => ''];
$canSend   = $canSend ?? false;
$ilkSekme  = $canSend ? 'gonder' : 'gecmis';
?>

<?php /* SAYFA BAŞLIĞI ÜST ÇUBUKTA yazar; burada tekrar edilmiyor.
         "Gönderen" bilgisi de başlık altında değil, ait olduğu yerde:
         aşağıdaki kartlardan birinde. */ ?>
<div class="cy-stats">
    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--brand"><?= icon('send') ?></span>
        <span>
            <span class="cy-stat__label">Gönderilen</span>
            <span class="cy-stat__value"><?= (int) $stats['gonderildi'] ?></span>
            <span class="cy-stat__hint">bugün <?= (int) $stats['bugun'] ?></span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--warning"><?= icon('clock') ?></span>
        <span>
            <span class="cy-stat__label">Kuyrukta</span>
            <span class="cy-stat__value" id="stat_kuyrukta"><?= (int) $stats['kuyrukta'] ?></span>
            <span class="cy-stat__hint">gönderilmeyi bekliyor</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--danger"><?= icon('alert') ?></span>
        <span>
            <span class="cy-stat__label">Başarısız</span>
            <span class="cy-stat__value"><?= (int) $stats['basarisiz'] ?></span>
            <span class="cy-stat__hint">geçmişten tekrar deneyin</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--<?= ($yapilandi ?? false) ? 'success' : 'warning' ?>"><?= icon('user') ?></span>
        <span>
            <span class="cy-stat__label">Gönderen</span>
            <span class="cy-stat__value" style="font-size:.95rem; line-height:1.35; word-break:break-word">
                <?= e($gonderen ?? '—') ?>
            </span>
            <span class="cy-stat__hint">
                <?= ($yapilandi ?? false) ? 'gönderim açık' : 'yalnızca kayıt modu' ?> ·
                <?= (int) $stats['toplam'] ?> kayıt
            </span>
        </span>
    </div>
</div>

<?php if (!($yapilandi ?? false)): ?>
    <div class="cy-alert cy-alert--info mb-3">
        <strong>Gönderim yöntemi "Kayıt" modunda.</strong>
        Mektuplar kimseye gitmez, <code>storage/mail/</code> klasörüne <code>.eml</code> dosyası olarak yazılır —
        geliştirirken gerçek adreslere test maili gitmesini önler.
        Yayına alırken
        <?php if (can('settings.view')): ?>
            <a href="<?= e(url('panel/ayarlar')) ?>">Site Ayarları → E-posta</a>
        <?php else: ?>
            Site Ayarları → E-posta
        <?php endif; ?>
        bölümünden SMTP bilgilerinizi girin.
    </div>
<?php endif; ?>

<div class="cy-card">
    <div class="cy-card__header cy-card__header--tabs">
        <ul class="nav nav-pills cy-tabnav" role="tablist">
            <?php if ($canSend): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pane-gonder" type="button" role="tab">
                        <?= icon('send', 'cy-icon cy-icon--sm') ?> Gönder
                    </button>
                </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link<?= $ilkSekme === 'gecmis' ? ' active' : '' ?>" data-bs-toggle="pill" data-bs-target="#pane-gecmis" type="button" role="tab">
                    <?= icon('inbox', 'cy-icon cy-icon--sm') ?> Geçmiş
                </button>
            </li>
        </ul>
    </div>

    <div class="cy-card__body">
        <div class="tab-content">

            <?php if ($canSend): ?>
                <!-- ==================== GÖNDER ==================== -->
                <div class="tab-pane fade show active" id="pane-gonder" role="tabpanel">
                    <form id="mail_form" novalidate>
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <div class="col-12 col-lg-5">
                                <label class="form-label" for="hedef">Kime gidecek?</label>
                                <select class="form-select" name="hedef" id="hedef">
                                    <option value="elle">Elle yazacağım adresler</option>
                                    <option value="kullanici">Tek bir kullanıcı</option>
                                    <option value="tumu">Tüm aktif kullanıcılar (<?= (int) ($sayilar['tumu'] ?? 0) ?>)</option>
                                    <?php foreach (($roles ?? []) as $key => $label): ?>
                                        <option value="rol:<?= e($key) ?>">
                                            Rol: <?= e($label) ?> (<?= (int) ($sayilar['rol:' . $key] ?? 0) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Toplu gönderimde her alıcı mektubu <u>ayrı ayrı</u> alır; adresler birbirini görmez.</div>
                            </div>

                            <div class="col-12 col-lg-7" id="alan_adresler">
                                <label class="form-label" for="adresler">E-posta adresleri</label>
                                <textarea class="form-control" name="adresler" id="adresler" rows="2"
                                          placeholder="ali@ornek.com, veli@ornek.com"><?= e($prefill['adres']) ?></textarea>
                                <div class="invalid-feedback" data-error-for="adresler"></div>
                                <div class="form-text">Virgül, boşluk veya alt satırla ayırın.</div>
                            </div>

                            <div class="col-12 col-lg-7 d-none" id="alan_kullanici">
                                <label class="form-label" for="kullanici_id">Kullanıcı</label>
                                <select class="form-select" name="kullanici_id" id="kullanici_id">
                                    <option value="">— seçin —</option>
                                    <?php foreach (($users ?? []) as $user): ?>
                                        <option value="<?= (int) $user['id'] ?>"><?= e($user['etiket']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="cy-alert cy-alert--info mb-0 py-2 small" id="alici_ozet">
                                    Alıcı sayısı hesaplanıyor…
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="cy-divider my-1">
                            </div>

                            <div class="col-12 col-lg-8">
                                <label class="form-label" for="konu">Konu <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="konu" id="konu" maxlength="200"
                                       value="<?= e($prefill['konu']) ?>" placeholder="Örn: Şubat ayı duyurusu">
                                <div class="invalid-feedback" data-error-for="konu"></div>
                            </div>

                            <div class="col-12 col-lg-4">
                                <label class="form-label" for="baslik">Mektup başlığı</label>
                                <input type="text" class="form-control" name="baslik" id="baslik" maxlength="200"
                                       placeholder="Boşsa konu kullanılır">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="govde">Mesaj <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="govde" id="govde" rows="10" maxlength="20000"
                                          placeholder="Merhaba,&#10;&#10;Bu ay sitemizde neler değişti...&#10;&#10;Boş satır bırakarak paragraf oluşturabilirsiniz. https://... ile başlayan adresler otomatik bağlantıya dönüşür."></textarea>
                                <div class="invalid-feedback" data-error-for="govde"></div>
                                <div class="form-text">
                                    Düz metin yazın: satır sonları ve paragraflar korunur, adresler tıklanabilir olur.
                                    HTML etiketleri güvenlik gereği metin olarak görünür.
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label" for="dugme_metni">Düğme metni</label>
                                <input type="text" class="form-control" name="dugme_metni" id="dugme_metni" maxlength="60"
                                       placeholder="Örn: Siteye Git">
                                <div class="invalid-feedback" data-error-for="dugme_metni"></div>
                            </div>

                            <div class="col-12 col-md-8">
                                <label class="form-label" for="dugme_url">Düğme adresi</label>
                                <input type="url" class="form-control" name="dugme_url" id="dugme_url" maxlength="255"
                                       placeholder="https://...">
                                <div class="invalid-feedback" data-error-for="dugme_url"></div>
                            </div>
                        </div>

                        <!-- İLERLEME -->
                        <div class="mt-4 d-none" id="gonderim_ilerleme">
                            <div class="d-flex justify-content-between small mb-1">
                                <span id="ilerleme_metin">Gönderiliyor…</span>
                                <span id="ilerleme_yuzde">%0</span>
                            </div>
                            <div class="progress" style="height:10px">
                                <div class="progress-bar" id="ilerleme_cubuk" role="progressbar" style="width:0%"></div>
                            </div>
                            <p class="cy-muted small mt-2 mb-0" id="ilerleme_not">
                                Bu sayfayı kapatmayın. Kapatırsanız kalan mektuplar kuyrukta bekler, geçmiş sekmesinden devam edebilirsiniz.
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                            <button type="button" class="btn cy-btn cy-btn--ghost" id="mail_onizle">
                                <?= icon('eye', 'cy-icon cy-icon--sm') ?> Önizle
                            </button>
                            <button type="submit" class="btn cy-btn cy-btn--primary" id="mail_gonder">
                                <span class="spinner-border spinner-border-sm me-1 d-none" id="mail_spinner"></span>
                                <?= icon('send', 'cy-icon cy-icon--sm') ?> <span id="mail_gonder_metin">Gönder</span>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ==================== GEÇMİŞ ==================== -->
            <div class="tab-pane fade<?= $ilkSekme === 'gecmis' ? ' show active' : '' ?>" id="pane-gecmis" role="tabpanel">
                <div class="cy-toolbar px-0 pt-0">
                    <div class="cy-toolbar__search">
                        <div class="cy-input-icon">
                            <?= icon('search', 'cy-icon cy-icon--sm') ?>
                            <input type="search" class="form-control" id="mail_search" placeholder="Alıcı veya konu ara…" autocomplete="off">
                        </div>
                    </div>
                    <div class="cy-toolbar__filters">
                        <select class="form-select" id="filter_durum" aria-label="Duruma göre filtrele">
                            <option value="">Tüm durumlar</option>
                            <option value="gonderildi">Gönderildi</option>
                            <option value="kuyrukta">Kuyrukta</option>
                            <option value="basarisiz">Başarısız</option>
                        </select>
                        <select class="form-select" id="filter_tur" aria-label="Türe göre filtrele">
                            <option value="">Tüm türler</option>
                            <?php foreach (App\Models\MailLog::typeLabels() as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($canSend): ?>
                            <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm" id="kuyrugu_isle">
                                <?= icon('refresh', 'cy-icon cy-icon--sm') ?> Kuyruğu Gönder
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cy-table-wrap">
                    <table id="mail_table" class="table cy-table w-100">
                        <thead>
                            <tr>
                                <th scope="col">Alıcı</th>
                                <th scope="col">Konu</th>
                                <th scope="col" style="width:130px">Tür</th>
                                <th scope="col" style="width:140px">Durum</th>
                                <th scope="col" style="width:150px">Tarih</th>
                                <th scope="col" style="width:120px" class="text-center">İşlemler</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<?php App\Core\View::partial('mail/modals'); ?>
