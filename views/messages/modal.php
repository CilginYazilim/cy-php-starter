<?php /** PARÇA: Mesaj detay + silme onayı modalları */ ?>

<div class="modal fade cy-modal" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="messageModalLabel">Mesaj</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <?php /* GÖNDEREN ŞERİDİ: baş harf + ad + adres. Tanım
                        listesinin ilk iki satırı olarak durduğunda
                        mesajın kimden geldiği gözden kaçıyordu. */ ?>
                <div class="cy-msg-sender">
                    <span class="cy-avatar cy-avatar--sm cy-avatar--initial" id="msg_bashari">?</span>
                    <div class="cy-msg-sender__body">
                        <strong id="msg_ad"></strong>
                        <a class="cy-link small" id="msg_eposta_link" href="#"><span id="msg_eposta"></span></a>
                    </div>
                    <span class="cy-badge" id="msg_durum">—</span>
                </div>

                <div class="cy-msg-subject">
                    <span class="cy-msg-subject__label">Konu</span>
                    <strong id="msg_konu"></strong>
                </div>

                <div class="cy-msg-body" id="msg_metin"></div>

                <dl class="cy-detail cy-detail--compact mt-3 mb-0">
                    <dt>Tarih</dt> <dd id="msg_tarih"></dd>
                    <dt>Üye</dt>   <dd id="msg_uye"></dd>
                    <dt>IP</dt>    <dd id="msg_ip"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cy-btn cy-btn--ghost" data-bs-dismiss="modal">Kapat</button>

                <?php /* ÜÇ YANIT YOLU.
                        "Posta Programım" bir mailto: bağlantısıdır ve
                        yalnızca işletim sisteminde KAYITLI bir posta
                        istemcisi varsa çalışır. Geliştirme makinelerinin
                        çoğunda yoktur; düğmeye basılır, hiçbir şey olmaz
                        ve düğme "bozuk" sanılır. Bu yüzden yanına
                        "Adresi Kopyala" eklendi: her koşulda çalışan,
                        aynı işi gören bir çıkış yolu. */ ?>
                <button type="button" class="btn cy-btn cy-btn--ghost" id="msg_copy"
                        title="Gönderenin e-posta adresini panoya kopyala">
                    <?= icon('copy', 'cy-icon cy-icon--sm') ?> Adresi Kopyala
                </button>

                <a class="btn cy-btn cy-btn--ghost" id="msg_reply" href="#"
                   title="Bilgisayarınızdaki posta programında yeni ileti açar">
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
