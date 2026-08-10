<?php /** PARÇA: Mesaj detay + silme onayı modalları */ ?>

<div class="modal fade cy-modal" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="messageModalLabel">Mesaj</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <dl class="cy-detail mb-3">
                    <dt>Gönderen</dt> <dd id="msg_ad"></dd>
                    <dt>E-posta</dt>  <dd id="msg_eposta"></dd>
                    <dt>Konu</dt>     <dd id="msg_konu"></dd>
                    <dt>Tarih</dt>    <dd id="msg_tarih"></dd>
                    <dt>Üye</dt>      <dd id="msg_uye"></dd>
                    <dt>IP</dt>       <dd id="msg_ip"></dd>
                </dl>
                <hr class="cy-divider">
                <p class="mb-0" id="msg_metin" style="white-space:pre-wrap"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cy-btn cy-btn--ghost" data-bs-dismiss="modal">Kapat</button>

                <?php /* İki yanıt yolu: kendi posta programınızla (mailto)
                        ya da panelin e-posta merkeziyle. İkincisi mektubu
                        site kimliğiyle gönderir ve geçmişe kaydeder. */ ?>
                <a class="btn cy-btn cy-btn--ghost" id="msg_reply" href="#">
                    <?= icon('mail', 'cy-icon cy-icon--sm') ?> Posta Programım
                </a>

                <?php if (can('mail.send')): ?>
                    <a class="btn cy-btn cy-btn--primary" id="msg_reply_panel" href="#">
                        <?= icon('send', 'cy-icon cy-icon--sm') ?> Panelden Yanıtla
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade cy-modal" id="deleteMessageModal" tabindex="-1" aria-labelledby="deleteMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="deleteMessageModalLabel">Mesajı Sil</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body text-center">
                <span class="cy-empty__icon" style="background:var(--cy-danger-soft);color:var(--cy-danger)"><?= icon('trash') ?></span>
                <p class="mb-0"><strong id="delete_message_label"></strong> mesajı <u>kalıcı olarak</u> silinecek.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn cy-btn cy-btn--danger cy-btn--sm" id="confirm_delete_message">Evet, Sil</button>
            </div>
        </div>
    </div>
</div>
