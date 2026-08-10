<?php
/**
 * =====================================================================
 *  PARÇA: E-posta önizleme + silme onayı modalları
 * ---------------------------------------------------------------------
 *  ÖNİZLEME NEDEN <iframe> İÇİNDE?
 *  Mektup, kendi <html>/<body> etiketlerini ve satır içi stillerini
 *  taşıyan TAM BİR SAYFADIR. Doğrudan panele gömseydik hem panelin
 *  stilleri mektubu bozardı hem de mektubun stilleri paneli. iframe
 *  ikisini birbirinden yalıtır ve mektubu tam olarak alıcının
 *  göreceği gibi gösterir.
 *
 *  srcdoc kullanıyoruz (src değil): içerik sunucudan yeniden
 *  istenmez, JavaScript ile doğrudan yerleştirilir.
 * =====================================================================
 */
?>

<div class="modal fade cy-modal" id="mailPreviewModal" tabindex="-1" aria-labelledby="mailPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="mailPreviewLabel">Mektup Önizleme</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <dl class="cy-detail mb-3" id="preview_meta">
                    <dt>Alıcı</dt> <dd id="preview_alici">—</dd>
                    <dt>Konu</dt>  <dd id="preview_konu">—</dd>
                    <dt>Durum</dt> <dd id="preview_durum">—</dd>
                    <dt>Tarih</dt> <dd id="preview_tarih">—</dd>
                </dl>

                <div class="cy-alert cy-alert--danger d-none mb-3 small" id="preview_hata"></div>

                <iframe id="preview_frame" title="E-posta önizleme" sandbox
                        style="width:100%; height:520px; border:1px solid var(--cy-border); border-radius:10px; background:#f1f4f8;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cy-btn cy-btn--ghost" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade cy-modal" id="mailDeleteModal" tabindex="-1" aria-labelledby="mailDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="mailDeleteLabel">Kaydı Sil</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body text-center">
                <span class="cy-empty__icon" style="background:var(--cy-danger-soft);color:var(--cy-danger)"><?= icon('trash') ?></span>
                <p class="mb-0"><strong id="mail_delete_label"></strong> kaydı silinecek. Bu yalnızca <u>geçmiş kaydını</u> siler; gönderilmiş mektup geri alınamaz.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn cy-btn cy-btn--danger cy-btn--sm" id="mail_delete_confirm">Evet, Sil</button>
            </div>
        </div>
    </div>
</div>
