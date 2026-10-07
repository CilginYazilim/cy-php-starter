<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Ornek modülü — CRUD, onay akışı ve RBAC örneği
 * ---------------------------------------------------------------------
 *  Bu dosya modülün KENDİ views/ klasöründedir ve
 *  $this->view('Ornek::index', ...) ile çağrılır.
 *
 *  Her düğme OrnekPolicy'ye sorularak gösterilir. Gizlemek yalnızca
 *  KOLAYLIKTIR; asıl kapı denetleyicidedir (aynı Policy).
 *
 *  @var array<int,array<string,mixed>> $kayitlar
 *  @var array<string,mixed>|null $duzenlenen  düzenleme formundaki kayıt
 *  @var Modules\Ornek\OrnekPolicy $policy
 *  @var array<string,array<string,bool>> $matris  yetki => rol => izin
 *  @var string $rol
 *  @var bool $dolu  tablo üst sınırda mı
 * =====================================================================
 */

use App\Models\Role;
use Modules\Ornek\OrnekPolicy;
use Modules\Ornek\Repositories\OrnekRepository;

$kayitlar   = $kayitlar ?? [];
$duzenlenen = $duzenlenen ?? null;
$errors     = $errors ?? [];
$old        = $old ?? [];
$matris     = $matris ?? [];
$rol        = $rol ?? '';
$dolu       = $dolu ?? false;

$sayac = array_count_values(array_map(static fn (array $k): string => (string) $k['durum'], $kayitlar));

$kapsamMetni = match ($policy->scope()) {
    OrnekPolicy::KAPSAM_HEPSI  => 'Bütün kayıtları görüyorsunuz (taslaklar dahil).',
    OrnekPolicy::KAPSAM_EDITOR => 'Yayındakileri, onay bekleyenleri ve kendi kayıtlarınızı görüyorsunuz; başkasının taslağı size gizli.',
    OrnekPolicy::KAPSAM_KENDI  => 'Yayındakileri ve kendi kayıtlarınızı görüyorsunuz; başkasının taslağı size gizli.',
    default                    => 'Yalnızca yayındaki kayıtları görüyorsunuz.',
};

$durumRengi = ['yayinda' => 'is-ok', 'onay' => 'is-warn', 'taslak' => ''];

/* Form: yeni kayıt ya da düzenleme. Hata sonrası eski girdi önceliklidir. */
$duzenleme = $duzenlenen !== null;
$formDeger = static fn (string $alan): string => array_key_exists($alan, $old)
    ? (string) $old[$alan]
    : (string) ($duzenlenen[$alan] ?? '');
$secenekler = $policy->formStatuses();
$seciliDurum = $formDeger('durum');
if (!in_array($seciliDurum, $secenekler, true)) {
    $seciliDurum = $secenekler[0];
}
$secenekAdi = ['yayinda' => 'Yayında', 'taslak' => 'Taslak', 'onay' => 'Onaya gönder'];
?>

<!-- BU BİR ŞABLON — modülün neden var olduğunu ilk bakışta anlatır -->
<section class="cy-panel cy-intro mb-3">
    <span class="cy-intro__icon"><?= icon('book', 'cy-icon cy-icon--sm') ?></span>
    <div class="cy-intro__body">
        <h3 class="cy-intro__title">Bu modül bir şablondur</h3>
        <p class="cy-intro__text">
            Kendi modülünüzü (Stok, Randevu, Blog…) yazarken bu klasörü örnek alın. Liste, ekleme, düzenleme,
            onay akışı ve rol tabanlı yetkinin (RBAC) her parçası birkaç satırlık, açıklamalı tek bir dosyadadır;
            çekirdeğe hiç dokunulmaz.
        </p>
        <ol class="cy-intro__steps">
            <li><code class="cy-mono">php cy make:module Stok</code> iskeleti üretir</li>
            <li>Yetkileri <code class="cy-mono">module.json</code> → <code class="cy-mono">"yetkiler"</code> bloğuna yazın</li>
            <li>Kayıt düzeyi kuralları <code class="cy-mono">OrnekPolicy</code> gibi tek bir sınıfta toplayın</li>
        </ol>
        <details class="cy-intro__more">
            <summary>Dosya yapısı ve rehber</summary>
            <pre class="cy-intro__tree">modules/Ornek/
├─ module.json              künye, menü, rol yetkileri
├─ routes.php               adresler + can:… yetki kapıları
├─ migrations/              tablo ve sütunlar (php cy migrate)
├─ seeders/OrnekIcerik.php  kurulumda örnek veri
├─ src/OrnekPolicy.php      "bu kullanıcı bu kayda ne yapabilir?"
├─ src/Repositories/        SQL'in tek yeri (hazırlıklı sorgular)
├─ src/Controllers/         liste, ekle, düzenle, durum, sil
└─ views/index.php          bu ekran</pre>
            <p class="cy-intro__text mb-0">
                Adım adım anlatım: <code class="cy-mono">modules/Ornek/README.md</code> ·
                <a class="cy-link" href="https://github.com/CilginYazilim/cy-php-starter/blob/main/modules/Ornek/README.md" target="_blank" rel="noopener">GitHub'da oku</a>
                <?php if (can('system.view')): ?>
                    · İşiniz bitince <a class="cy-link" href="<?= e(url('panel/sistem')) ?>#moduller">Sistem → Modüller</a>'den kapatabilirsiniz.
                <?php endif; ?>
            </p>
        </details>
    </div>
</section>

<div class="row g-3">

    <!-- SOL: yetkileriniz + form -->
    <div class="col-12 col-lg-4">

        <section class="cy-panel mb-3">
            <header class="cy-panel__head">
                <h3 class="cy-panel__title"><?= icon('shield', 'cy-icon cy-icon--sm') ?> Sizin yetkileriniz</h3>
                <span class="cy-role cy-role--<?= e(Role::variant($rol)) ?>"><?= e(Role::label($rol)) ?></span>
            </header>
            <ul class="cy-checks">
                <?php foreach (OrnekPolicy::YETKILER as $yetki => $etiket): ?>
                    <?php $var = $policy->can($yetki); ?>
                    <li class="cy-checks__item <?= $var ? 'is-ok' : 'is-no' ?>" title="<?= e($yetki) ?>">
                        <?= icon($var ? 'check' : 'x-circle', 'cy-icon cy-icon--sm') ?>
                        <span><?= e($etiket) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="cy-panel__note"><?= icon('eye', 'cy-icon cy-icon--sm') ?> <?= e($kapsamMetni) ?></p>
        </section>

        <?php if ($duzenleme || $policy->canCreate()): ?>
            <section class="cy-panel mb-3" id="form">
                <header class="cy-panel__head">
                    <h3 class="cy-panel__title">
                        <?= icon($duzenleme ? 'edit' : 'plus', 'cy-icon cy-icon--sm') ?>
                        <?= $duzenleme ? 'Kaydı düzenle' : 'Yeni kayıt' ?>
                    </h3>
                    <?php if ($duzenleme): ?>
                        <a class="cy-link small" href="<?= e(url('panel/ornek')) ?>">Vazgeç</a>
                    <?php endif; ?>
                </header>

                <?php if (!$duzenleme && $dolu): ?>
                    <p class="cy-panel__note is-warn mt-0">
                        Tablo üst sınırda (<?= OrnekRepository::UST_SINIR ?> kayıt). Yeni kayıt için önce birkaç kayıt silinmeli.
                    </p>
                <?php else: ?>
                    <form method="post" novalidate
                          action="<?= e(url($duzenleme ? 'panel/ornek/duzenle/' . (int) $duzenlenen['id'] : 'panel/ornek/kaydet')) ?>">
                        <?= csrf_field() ?>

                        <label class="form-label" for="baslik">Başlık <span class="text-danger">*</span></label>
                        <input type="text" name="baslik" id="baslik" maxlength="150" autocomplete="off"
                               class="form-control<?= isset($errors['baslik']) ? ' is-invalid' : '' ?>"
                               aria-describedby="baslik_ipucu"
                               value="<?= e($formDeger('baslik')) ?>">
                        <div class="form-text" id="baslik_ipucu">Listede görünen ad · en fazla 150 karakter.</div>
                        <?php if (isset($errors['baslik'])): ?>
                            <div class="invalid-feedback d-block"><?= e($errors['baslik']) ?></div>
                        <?php endif; ?>

                        <label class="form-label mt-2" for="aciklama">Açıklama</label>
                        <textarea name="aciklama" id="aciklama" maxlength="255" rows="2"
                                  aria-describedby="aciklama_ipucu" data-sayac="#aciklama_sayac"
                                  class="form-control<?= isset($errors['aciklama']) ? ' is-invalid' : '' ?>"><?= e($formDeger('aciklama')) ?></textarea>
                        <div class="cy-field-meta">
                            <div class="form-text" id="aciklama_ipucu">İsteğe bağlı · başlığın altında tek satır özet.</div>
                            <span class="cy-sayac" id="aciklama_sayac"></span>
                        </div>
                        <?php if (isset($errors['aciklama'])): ?>
                            <div class="invalid-feedback d-block"><?= e($errors['aciklama']) ?></div>
                        <?php endif; ?>

                        <label class="form-label mt-2" for="durum">Durum</label>
                        <select name="durum" id="durum" class="form-select<?= isset($errors['durum']) ? ' is-invalid' : '' ?>">
                            <?php foreach ($secenekler as $deger): ?>
                                <option value="<?= e($deger) ?>" <?= $seciliDurum === $deger ? 'selected' : '' ?>><?= e($secenekAdi[$deger]) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$policy->canPublish()): ?>
                            <small class="form-text">
                                Kaydınız bir editör onaylayınca yayına çıkar.
                                <?= $duzenleme && ($duzenlenen['durum'] ?? '') === 'yayinda' ? 'Yayındaki kaydı değiştirirseniz yeniden onaya düşer.' : '' ?>
                            </small>
                        <?php endif; ?>

                        <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block mt-3">
                            <?= icon($duzenleme ? 'save' : 'plus', 'cy-icon cy-icon--sm') ?> <?= $duzenleme ? 'Kaydet' : 'Ekle' ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!$duzenleme && $policy->canManageAll()): ?>
                    <form method="post" action="<?= e(url('panel/ornek/ornek-uret')) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--block" <?= $dolu ? 'disabled' : '' ?>>
                            <?= icon('zap', 'cy-icon cy-icon--sm') ?> Rastgele 5 kayıt ekle
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <!-- SAĞ: kayıtlar + yetki matrisi -->
    <div class="col-12 col-lg-8">

        <section class="cy-panel mb-3">
            <header class="cy-panel__head">
                <div>
                    <h3 class="cy-panel__title"><?= icon('inbox', 'cy-icon cy-icon--sm') ?> Kayıtlar</h3>
                    <p class="cy-panel__sub">
                        <?= count($kayitlar) ?> kayıt görüyorsunuz ·
                        <?= (int) ($sayac['yayinda'] ?? 0) ?> yayında ·
                        <?= (int) ($sayac['onay'] ?? 0) ?> onay bekliyor ·
                        <?= (int) ($sayac['taslak'] ?? 0) ?> taslak
                    </p>
                </div>
            </header>

            <?php if ($kayitlar === []): ?>
                <p class="cy-panel__empty">
                    Görebileceğiniz kayıt yok.
                    <?= $policy->canManageAll() ? 'Soldaki "Rastgele 5 kayıt ekle" düğmesiyle deneme kayıtları üretebilirsiniz.' : '' ?>
                </p>
            <?php else: ?>
                <ul class="cy-list">
                    <?php foreach ($kayitlar as $kayit): ?>
                        <?php
                        $sahipAdi = trim((string) ($kayit['sahip_ad'] ?? '') . ' ' . (string) ($kayit['sahip_soyad'] ?? ''));
                        $benim    = $policy->owns($kayit);
                        $gecisler = $policy->transitions($kayit);
                        $duzenler = $policy->canEdit($kayit);
                        $secili   = $duzenleme && (int) $duzenlenen['id'] === (int) $kayit['id'];
                        ?>
                        <li class="cy-list__row cy-ornek<?= $secili ? ' is-editing' : '' ?>">
                            <span class="cy-ornek__main">
                                <strong>
                                    <?= e((string) $kayit['baslik']) ?>
                                    <span class="cy-pill <?= $durumRengi[$kayit['durum']] ?? '' ?>"><?= e(OrnekRepository::DURUMLAR[$kayit['durum']] ?? $kayit['durum']) ?></span>
                                </strong>
                                <?php if ((string) $kayit['aciklama'] !== ''): ?>
                                    <small><?= e((string) $kayit['aciklama']) ?></small>
                                <?php endif; ?>
                                <small class="cy-ornek__meta">
                                    <?php if ($sahipAdi !== ''): ?>
                                        <span class="cy-role cy-role--<?= e(Role::variant((string) $kayit['sahip_rol'])) ?>"><?= e(Role::label((string) $kayit['sahip_rol'])) ?></span>
                                        <?= e($sahipAdi) ?><?= $benim ? ' (siz)' : '' ?>
                                    <?php else: ?>
                                        silinmiş kullanıcı
                                    <?php endif; ?>
                                    · <?= e(human_date($kayit['created_at'] ?? null)) ?>
                                </small>
                            </span>

                            <span class="cy-ornek__actions">
                                <?php foreach ($gecisler as $hedef): ?>
                                    <form method="post" action="<?= e(url('panel/ornek/durum/' . (int) $kayit['id'])) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="durum" value="<?= e($hedef) ?>">
                                        <button type="submit" class="cy-ornek__step<?= $hedef === 'yayinda' ? ' is-primary' : '' ?>">
                                            <?= e($policy->transitionLabel($kayit, $hedef)) ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>

                                <?php if ($duzenler): ?>
                                    <a class="cy-btn-icon" href="<?= e(url('panel/ornek/duzenle/' . (int) $kayit['id'])) ?>#form" title="Düzenle">
                                        <?= icon('edit', 'cy-icon cy-icon--sm') ?>
                                    </a>
                                    <?php /* data-confirm: onay penceresini app.js açar.
                                             Satır içi onsubmit KULLANILMAZ — CSP yasaklar. */ ?>
                                    <form method="post" action="<?= e(url('panel/ornek/sil/' . (int) $kayit['id'])) ?>"
                                          data-confirm="Bu kayıt silinecek. Emin misiniz?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="cy-btn-icon cy-btn-icon--danger" title="Sil">
                                            <?= icon('trash', 'cy-icon cy-icon--sm') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($gecisler === [] && !$duzenler): ?>
                                    <span class="cy-ornek__lock" title="<?= e($policy->denialReason($kayit)) ?>">
                                        <?= icon('lock', 'cy-icon cy-icon--sm') ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- YETKİ MATRİSİ — Role::can'in gerçek cevabı -->
        <section class="cy-panel">
            <header class="cy-panel__head">
                <div>
                    <h3 class="cy-panel__title"><?= icon('users', 'cy-icon cy-icon--sm') ?> Yetki matrisi (RBAC)</h3>
                    <p class="cy-panel__sub">Rol × yetki tablosu, kodun o anki cevabıyla üretilir. Sizin rolünüz vurgulu.</p>
                </div>
            </header>
            <div class="cy-table-wrap">
                <table class="table cy-table cy-table--tight cy-matrix w-100">
                    <thead>
                        <tr>
                            <th scope="col">Yetki</th>
                            <?php foreach (Role::options() as $rolAdi => $rolEtiketi): ?>
                                <th scope="col" class="text-center<?= $rolAdi === $rol ? ' is-current' : '' ?>"><?= e($rolEtiketi) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (OrnekPolicy::YETKILER as $yetki => $etiket): ?>
                            <tr>
                                <td><?= e($etiket) ?><small class="d-block cy-mono cy-muted"><?= e($yetki) ?></small></td>
                                <?php foreach (Role::options() as $rolAdi => $rolEtiketi): ?>
                                    <?php $izin = $matris[$yetki][$rolAdi] ?? false; ?>
                                    <td class="text-center<?= $rolAdi === $rol ? ' is-current' : '' ?>">
                                        <span class="cy-matrix__mark <?= $izin ? 'is-yes' : 'is-no' ?>"
                                              aria-label="<?= $izin ? 'var' : 'yok' ?>"><?= icon($izin ? 'check' : 'x-circle', 'cy-icon cy-icon--sm') ?></span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="cy-ornek__rules">
                <li><strong>Rol → yetki:</strong> Yönetici her yetkiye sahiptir. Editör ve üyenin yetkileri
                    <code>modules/Ornek/module.json</code> → <code>"yetkiler"</code> bloğundan gelir; çekirdeğe dokunulmaz.</li>
                <li><strong>Onay akışı:</strong> Üye yazar ve onaya gönderir; editör onaylayınca yayına çıkar. Üye yayındaki
                    kaydını değiştirirse kayıt yeniden onaya düşer.</li>
                <li><strong>Satır düzeyi:</strong> Herkes yalnızca kendi kaydını düzenler ve siler (yönetici hariç);
                    başkasının taslağı görünmez (<code>OrnekPolicy</code>).</li>
                <li><strong>Sunucu da denetler:</strong> Düğmeyi gizlemek güvenlik değildir. Kilitli bir kayda elle
                    gönderilen istek <code>403</code>, göremediğiniz bir kayda gönderilen istek <code>404</code> alır.</li>
            </ul>
        </section>
    </div>
</div>
