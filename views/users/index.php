<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Kullanıcı yönetimi
 * =====================================================================
 */

use App\Models\Role;

$roles = $roles ?? Role::options();
?>

<div class="cy-page-head">
    <div>
        <h2 class="cy-title">Kullanıcılar</h2>
        <p class="cy-subtitle">Toplam <strong id="total_records">0</strong> kayıt listeleniyor.</p>
    </div>

    <?php if (can('users.create')): ?>
        <div class="cy-page-head__actions">
            <button type="button" class="btn cy-btn cy-btn--primary" id="add_button">
                <?= icon('plus', 'cy-icon cy-icon--sm') ?> Yeni Kullanıcı
            </button>
        </div>
    <?php endif; ?>
</div>

<div class="cy-card">
    <div class="cy-toolbar">
        <div class="cy-toolbar__search">
            <div class="cy-input-icon">
                <?= icon('search', 'cy-icon cy-icon--sm') ?>
                <input type="search" class="form-control" id="table_search"
                       placeholder="Ad, soyad, e-posta veya kullanıcı adı ara…" autocomplete="off">
            </div>
        </div>

        <div class="cy-toolbar__filters">
            <select class="form-select" id="filter_role" aria-label="Role göre filtrele">
                <option value="">Tüm roller</option>
                <?php foreach ($roles as $value => $label): ?>
                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <select class="form-select" id="filter_status" aria-label="Duruma göre filtrele">
                <option value="">Tüm durumlar</option>
                <option value="aktif">Aktif</option>
                <option value="pasif">Pasif</option>
                <option value="askida">Askıda</option>
            </select>

            <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm" id="reset_filters">Sıfırla</button>
        </div>
    </div>

    <div class="cy-card__body cy-card__body--flush">
        <div class="cy-table-wrap">
            <table id="user_table" class="table cy-table w-100">
                <thead>
                    <tr>
                        <th scope="col" style="width:64px">#</th>
                        <th scope="col" style="width:56px"><span class="cy-sr-only">Görsel</span></th>
                        <th scope="col">Kullanıcı</th>
                        <th scope="col" class="cy-hide-sm">E-posta</th>
                        <th scope="col" style="width:110px">Rol</th>
                        <th scope="col" style="width:110px" class="cy-hide-xs">Durum</th>
                        <th scope="col" class="cy-hide-sm" style="width:150px">Kayıt Tarihi</th>
                        <th scope="col" style="width:130px" class="text-center">İşlemler</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <div class="cy-card__footer d-flex flex-wrap justify-content-between gap-2">
        <span>Sunucu taraflı DataTables · CSRF korumalı AJAX</span>
        <span class="cy-hide-sm">Rol tabanlı yetkilendirme aktif</span>
    </div>
</div>

<?php App\Core\View::partial('users/modals', ['roles' => $roles]); ?>
