<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Ornek modülü — RBAC örneği
 * ---------------------------------------------------------------------
 *  Bu dosya modülün KENDİ views/ klasöründedir ve
 *  $this->view('Ornek::index', ...) ile çağrılır.
 *
 *  Her düğme OrnekPolicy'ye sorularak gösterilir. Gizlemek yalnızca
 *  KOLAYLIKTIR; asıl kapı denetleyicidedir (aynı Policy).
 *
 *  @var array<int,array<string,mixed>> $kayitlar
 *  @var Modules\Ornek\OrnekPolicy $policy
 *  @var array<string,array<string,bool>> $matris  yetki => rol => izin
 *  @var string $rol
 * =====================================================================
 */

use App\Models\Role;
use Modules\Ornek\OrnekPolicy;
use Modules\Ornek\Repositories\OrnekRepository;

$kayitlar = $kayitlar ?? [];
$errors   = $errors ?? [];
$old      = $old ?? [];
$matris   = $matris ?? [];
$rol      = $rol ?? '';

$yayinda = count(array_filter($kayitlar, static fn (array $k): bool => $k['durum'] === 'yayinda'));

$kapsamMetni = match ($policy->scope()) {
    OrnekPolicy::KAPSAM_HEPSI => 'Bütün kayıtları görüyorsunuz (taslaklar dahil).',
    OrnekPolicy::KAPSAM_KENDI => 'Yayındaki kayıtları ve kendi taslaklarınızı görüyorsunuz; başkasının taslağı size gizli.',
    default                   => 'Yalnızca yayındaki kayıtları görüyorsunuz; taslaklar size gizli.',
};
?>

<div class="row g-3">

    <!-- SOL: yetkileriniz + yeni kayıt -->
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
                        <span><?= e($etiket) ?> <code class="cy-mono cy-muted small"><?= e($yetki) ?></code></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="cy-panel__note"><?= icon('eye', 'cy-icon cy-icon--sm') ?> <?= e($kapsamMetni) ?></p>
        </section>

        <?php if ($policy->canCreate()): ?>
            <section class="cy-panel mb-3">
                <header class="cy-panel__head">
                    <h3 class="cy-panel__title"><?= icon('plus', 'cy-icon cy-icon--sm') ?> Yeni kayıt</h3>
                </header>
                <form method="post" action="<?= e(url('panel/ornek/kaydet')) ?>" novalidate>
                    <?= csrf_field() ?>

                    <label class="form-label" for="baslik">Başlık <span class="text-danger">*</span></label>
                    <input type="text" name="baslik" id="baslik" maxlength="150" autocomplete="off"
                           class="form-control<?= isset($errors['baslik']) ? ' is-invalid' : '' ?>"
                           value="<?= old($old, 'baslik') ?>">
                    <?php if (isset($errors['baslik'])): ?>
                        <div class="invalid-feedback d-block"><?= e($errors['baslik']) ?></div>
                    <?php endif; ?>

                    <label class="form-label mt-2" for="aciklama">Açıklama</label>
                    <input type="text" name="aciklama" id="aciklama" maxlength="255" autocomplete="off"
                           class="form-control<?= isset($errors['aciklama']) ? ' is-invalid' : '' ?>"
                           value="<?= old($old, 'aciklama') ?>">

                    <label class="form-label mt-2" for="durum">Durum</label>
                    <select name="durum" id="durum" class="form-select">
                        <?php foreach (OrnekRepository::DURUMLAR as $deger => $ad): ?>
                            <option value="<?= e($deger) ?>" <?= old($old, 'durum') === $deger ? 'selected' : '' ?>><?= e($ad) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block mt-3">
                        <?= icon('plus', 'cy-icon cy-icon--sm') ?> Ekle
                    </button>
                </form>

                <?php if ($policy->canManageAll()): ?>
                    <form method="post" action="<?= e(url('panel/ornek/ornek-uret')) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn cy-btn cy-btn--ghost cy-btn--block">
                            <?= icon('zap', 'cy-icon cy-icon--sm') ?> Rastgele 5 örnek ekle
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
                    <p class="cy-panel__sub"><?= count($kayitlar) ?> kayıt görüyorsunuz · <?= $yayinda ?> yayında · <?= count($kayitlar) - $yayinda ?> taslak</p>
                </div>
            </header>

            <?php if ($kayitlar === []): ?>
                <p class="cy-panel__note mb-0">
                    Görebileceğiniz kayıt yok.
                    <?= $policy->canManageAll() ? 'Soldaki "Rastgele 5 örnek ekle" düğmesiyle deneme kayıtları üretebilirsiniz.' : '' ?>
                </p>
            <?php else: ?>
                <ul class="cy-list">
                    <?php foreach ($kayitlar as $kayit): ?>
                        <?php
                        $sahipAdi = trim((string) ($kayit['sahip_ad'] ?? '') . ' ' . (string) ($kayit['sahip_soyad'] ?? ''));
                        $benim    = (int) ($kayit['kullanici_id'] ?? 0) === (int) \App\Core\Auth::id();
                        ?>
                        <li class="cy-list__row cy-ornek">
                            <span class="cy-ornek__main">
                                <strong>
                                    <?= e((string) $kayit['baslik']) ?>
                                    <span class="cy-pill <?= $kayit['durum'] === 'yayinda' ? 'is-ok' : '' ?>"><?= e(OrnekRepository::DURUMLAR[$kayit['durum']] ?? $kayit['durum']) ?></span>
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
                                <?php if ($policy->canModify($kayit)): ?>
                                    <form method="post" action="<?= e(url('panel/ornek/durum/' . (int) $kayit['id'])) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="cy-btn-icon" title="<?= $kayit['durum'] === 'yayinda' ? 'Taslağa al' : 'Yayınla' ?>">
                                            <?= icon($kayit['durum'] === 'yayinda' ? 'eye-off' : 'eye', 'cy-icon cy-icon--sm') ?>
                                        </button>
                                    </form>
                                    <?php /* data-confirm: onay penceresini app.js açar.
                                             Satır içi onsubmit KULLANILMAZ — CSP yasaklar. */ ?>
                                    <form method="post" action="<?= e(url('panel/ornek/sil/' . (int) $kayit['id'])) ?>"
                                          data-confirm="Bu kayıt silinecek. Emin misiniz?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="cy-btn-icon cy-btn-icon--delete" title="Sil">
                                            <?= icon('trash', 'cy-icon cy-icon--sm') ?>
                                        </button>
                                    </form>
                                <?php else: ?>
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
                <li><strong>Satır düzeyi:</strong> Editör yalnızca kendi kaydını yönetir ve başkasının taslağını görmez;
                    üye yalnızca yayındakileri görür (<code>OrnekPolicy</code>).</li>
                <li><strong>Sunucu da denetler:</strong> Düğmeyi gizlemek güvenlik değildir. Kilitli bir kayda elle
                    gönderilen istek <code>403</code>, göremediğiniz bir kayda gönderilen istek <code>404</code> alır.</li>
            </ul>
        </section>
    </div>
</div>
