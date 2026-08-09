<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Mesaj yönetimi
 * =====================================================================
 */
?>

<div class="cy-page-head">
    <div>
        <h2 class="cy-title">Mesajlar</h2>
        <p class="cy-subtitle">Toplam <strong id="total_records">0</strong> mesaj · <strong id="unread_count">0</strong> okunmamış</p>
    </div>

    <div class="cy-page-head__actions">
        <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm" id="bulk_read" disabled>
            <?= icon('check', 'cy-icon cy-icon--sm') ?> Okundu İşaretle
        </button>
        <button type="button" class="btn cy-btn cy-btn--danger cy-btn--sm" id="bulk_delete" disabled>
            <?= icon('trash', 'cy-icon cy-icon--sm') ?> Seçilenleri Sil
        </button>
    </div>
</div>

<div class="cy-card">
    <div class="cy-toolbar">
        <div class="cy-toolbar__search">
            <div class="cy-input-icon">
                <?= icon('search', 'cy-icon cy-icon--sm') ?>
                <input type="search" class="form-control" id="table_search" placeholder="Ad, e-posta, konu veya mesaj ara…" autocomplete="off">
            </div>
        </div>
        <div class="cy-toolbar__filters">
            <select class="form-select" id="filter_status" aria-label="Duruma göre filtrele">
                <option value="">Tümü</option>
                <option value="okunmamis">Okunmamış</option>
                <option value="okunmus">Okunmuş</option>
            </select>
        </div>
    </div>

    <div class="cy-card__body cy-card__body--flush">
        <div class="cy-table-wrap">
            <table id="message_table" class="table cy-table w-100">
                <thead>
                    <tr>
                        <th scope="col" style="width:40px"><input type="checkbox" class="form-check-input" id="select_all" aria-label="Tümünü seç"></th>
                        <th scope="col">Gönderen</th>
                        <th scope="col">Konu</th>
                        <th scope="col" style="width:110px">Durum</th>
                        <th scope="col" style="width:150px">Tarih</th>
                        <th scope="col" style="width:130px" class="text-center">İşlemler</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<?php App\Core\View::partial('messages/modal'); ?>
