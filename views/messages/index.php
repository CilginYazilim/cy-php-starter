<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Mesaj yönetimi
 * ---------------------------------------------------------------------
 *  SAYFA BAŞLIĞI ÜST ÇUBUKTA yazar; burada tekrar edilmez. Toplu
 *  işlem düğmeleri de listenin başlığının yanına alındı: üzerinde
 *  çalıştıkları tablodan uzakta durmaları, hangi satırları
 *  etkileyeceklerini belirsizleştiriyordu.
 *
 *  @var array{toplam:int,okunmamis:int,bugun:int} $istatistik
 * =====================================================================
 */

use App\Core\Setting;

$ist = $istatistik ?? ['toplam' => 0, 'okunmamis' => 0, 'bugun' => 0];

/* E-posta hâlâ "kayıt" modundaysa bildirimler kimseye ulaşmıyordur.
 * Mesajlar ekranı bunu söylemeye en uygun yerdir: kullanıcı zaten
 * "neden haberim olmadı?" diye buraya bakar. */
$smtpEksik = Setting::get('mail_surucu', 'kayit') === 'kayit';
?>

<div class="cy-stats">
    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--brand"><?= icon('inbox') ?></span>
        <span>
            <span class="cy-stat__label">Toplam Mesaj</span>
            <span class="cy-stat__value" id="total_records"><?= (int) $ist['toplam'] ?></span>
            <span class="cy-stat__hint">iletişim formundan</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--<?= $ist['okunmamis'] > 0 ? 'warning' : 'success' ?>">
            <?= icon($ist['okunmamis'] > 0 ? 'bell' : 'check') ?>
        </span>
        <span>
            <span class="cy-stat__label">Okunmamış</span>
            <span class="cy-stat__value" id="unread_count"><?= (int) $ist['okunmamis'] ?></span>
            <span class="cy-stat__hint"><?= $ist['okunmamis'] > 0 ? 'yanıt bekliyor' : 'hepsi okundu' ?></span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--success"><?= icon('calendar') ?></span>
        <span>
            <span class="cy-stat__label">Bugün Gelen</span>
            <span class="cy-stat__value"><?= (int) $ist['bugun'] ?></span>
            <span class="cy-stat__hint">son 24 saat</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--<?= $smtpEksik ? 'danger' : 'success' ?>"><?= icon('send') ?></span>
        <span>
            <span class="cy-stat__label">Bildirim E-postası</span>
            <span class="cy-stat__value" style="font-size:1.05rem"><?= $smtpEksik ? 'Kapalı' : 'Açık' ?></span>
            <span class="cy-stat__hint">
                <?= $smtpEksik ? 'SMTP tanımlı değil' : e(Setting::get('iletisim_eposta', 'adres tanımsız')) ?>
            </span>
        </span>
    </div>
</div>

<?php if ($smtpEksik && can('settings.manage')): ?>
    <div class="cy-alert cy-alert--warning mb-3">
        <strong><?= icon('alert', 'cy-icon cy-icon--sm') ?> Yeni mesaj bildirimi gönderilmiyor.</strong>
        E-posta yöntemi “kayıt” modunda: mektuplar <code class="cy-mono">storage/mail/</code>
        klasörüne yazılıyor, kimseye ulaşmıyor. Bu ekranı düzenli kontrol edin ya da
        <a class="cy-link" href="<?= e(url('panel/ayarlar/eposta')) ?>">SMTP ayarlarını yapın</a>.
    </div>
<?php endif; ?>

<div class="cy-card">
    <div class="cy-card__header">
        <div>
            <h2 class="cy-section-title mb-0"><?= icon('inbox', 'cy-icon cy-icon--sm') ?> Gelen Kutusu</h2>
            <p class="cy-muted small mb-0">Satırı açmak mesajı otomatik olarak okundu işaretler.</p>
        </div>

        <?php if (can('messages.manage')): ?>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn cy-btn cy-btn--ghost cy-btn--sm" id="bulk_read" disabled>
                    <?= icon('check', 'cy-icon cy-icon--sm') ?> Okundu İşaretle
                </button>
                <button type="button" class="btn cy-btn cy-btn--danger cy-btn--sm" id="bulk_delete" disabled>
                    <?= icon('trash', 'cy-icon cy-icon--sm') ?> Seçilenleri Sil
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="cy-toolbar">
        <div class="cy-toolbar__search">
            <div class="cy-input-icon">
                <?= icon('search', 'cy-icon cy-icon--sm') ?>
                <input type="search" class="form-control" id="table_search" placeholder="Ad, e-posta, konu veya mesaj ara…" autocomplete="off">
            </div>
        </div>
        <div class="cy-toolbar__filters ms-auto">
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
                        <th scope="col" style="width:150px" class="cy-hide-sm">Tarih</th>
                        <th scope="col" style="width:130px" class="text-center">İşlemler</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <div class="cy-card__footer d-flex flex-wrap justify-content-between gap-2">
        <span>Sunucu taraflı DataTables · CSRF korumalı AJAX</span>
        <span class="cy-hide-sm">Mesajlar veritabanında saklanır, e-postadan bağımsızdır</span>
    </div>
</div>

<?php App\Core\View::partial('messages/modal'); ?>
