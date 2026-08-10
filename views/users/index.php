<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Kullanıcı yönetimi
 * =====================================================================
 */

use App\Models\Role;

$roles = $roles ?? Role::options();
$ist   = $istatistik ?? ['toplam' => 0, 'aktif' => 0, 'pasif' => 0, 'yonetici' => 0, 'yeni' => 0];
?>

<?php /* SAYFA BAŞLIĞI ÜST ÇUBUKTA yazar; burada tekrar edilmez —
         aynı cümleyi iki kez okumak dikey alanı boşa harcıyor ve
         mobilde tabloyu ekranın dışına itiyordu. Yerine, listeye
         bakmadan önce bilinmesi gereken dört sayı duruyor.

         "total_records" gizli olarak korunuyor: users.js her AJAX
         yanıtında bu alanı güncelliyor ve kart da onunla canlı kalıyor. */ ?>
<div class="cy-stats">
    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--brand"><?= icon('users') ?></span>
        <span>
            <span class="cy-stat__label">Toplam Kullanıcı</span>
            <span class="cy-stat__value" id="total_records"><?= (int) $ist['toplam'] ?></span>
            <span class="cy-stat__hint">son 7 günde +<?= (int) $ist['yeni'] ?></span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--success"><?= icon('check') ?></span>
        <span>
            <span class="cy-stat__label">Aktif Hesap</span>
            <span class="cy-stat__value"><?= (int) $ist['aktif'] ?></span>
            <span class="cy-stat__hint">giriş yapabiliyor</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--warning"><?= icon('lock') ?></span>
        <span>
            <span class="cy-stat__label">Pasif / Askıda</span>
            <span class="cy-stat__value"><?= (int) $ist['pasif'] ?></span>
            <span class="cy-stat__hint">giriş yapamaz</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--brand"><?= icon('shield') ?></span>
        <span>
            <span class="cy-stat__label">Yönetici</span>
            <span class="cy-stat__value"><?= (int) $ist['yonetici'] ?></span>
            <span class="cy-stat__hint">tam yetkili hesap</span>
        </span>
    </div>
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

        <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm cy-filter-toggle" id="filters_toggle"
                data-bs-toggle="collapse" data-bs-target="#user_filters_panel"
                aria-expanded="false" aria-controls="user_filters_panel">
            <?= icon('filter', 'cy-icon cy-icon--sm') ?> Filtreler
            <span class="cy-badge cy-badge--count d-none" id="active_filter_count">0</span>
            <?= icon('chevron', 'cy-icon cy-icon--sm cy-filter-toggle__chevron') ?>
        </button>

        <?php /* "Yeni Kullanıcı" araç çubuğunun sağ ucunda: eylem,
                 üzerinde çalıştığı listenin yanında dursun. */ ?>
        <?php if (can('users.create')): ?>
            <button type="button" class="btn cy-btn cy-btn--primary cy-btn--sm cy-toolbar__end" id="add_button">
                <?= icon('plus', 'cy-icon cy-icon--sm') ?> Yeni Kullanıcı
            </button>
        <?php endif; ?>
    </div>

    <div class="collapse" id="user_filters_panel">
        <div class="cy-toolbar cy-toolbar--filters">
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

            <div class="cy-toolbar__daterange" role="group" aria-label="Kayıt tarihine göre filtrele">
                <input type="date" class="form-control" id="filter_date_from" aria-label="Başlangıç tarihi">
                <span class="cy-toolbar__daterange-sep">–</span>
                <input type="date" class="form-control" id="filter_date_to" aria-label="Bitiş tarihi">
            </div>

            <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm" id="reset_filters" disabled>Sıfırla</button>
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
