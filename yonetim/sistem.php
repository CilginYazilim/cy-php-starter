<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – SİSTEM BİLGİSİ VE SAĞLIK KONTROLÜ
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  "Sitede bir sorun var" denildiğinde ilk bakılacak ekran.
 *  Sunucu ortamını, veritabanı durumunu ve tipik yapılandırma
 *  hatalarını tek sayfada toplar.
 *
 *  NEDEN phpinfo() DEĞİL?
 *  phpinfo() çıktısı yol, kullanıcı adı ve modül listesi gibi
 *  saldırgana yarayacak ÇOK fazla bilgi verir. Burada yalnızca
 *  yöneticinin gerçekten ihtiyaç duyduğu alanlar gösterilir.
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik   = 'Sistem Bilgisi';
$sayfaAciklama = 'Sunucu ortamı, veritabanı durumu ve sağlık kontrolleri.';
$aktifMenu     = 'sistem';
$gerekenRol    = 'admin';
$kirintilar    = ['Sistem Bilgisi'];

require __DIR__ . '/_ust.php';

/* ---------------------------------------------------------------------
 *  VERİTABANI BİLGİLERİ
 * ------------------------------------------------------------------ */
$mysqlSurum = 'bilinmiyor';
try {
    $mysqlSurum = (string) $db->getAttribute(PDO::ATTR_SERVER_VERSION);
} catch (PDOException $e) {
    // Sürüm okunamıyorsa sayfa yine de açılmalı.
}

/* Tablo listesi, satır sayısı ve disk boyutu.
 * information_schema.TABLES tahmini satır sayısı verir (InnoDB'de
 * TABLE_ROWS kesin değildir) — bu yüzden yanına "≈" koyuyoruz. */
$tablolar = [];
try {
    $stmt = $db->prepare(
        'SELECT TABLE_NAME AS ad,
                TABLE_ROWS AS satir,
                (DATA_LENGTH + INDEX_LENGTH) AS boyut,
                ENGINE AS motor,
                TABLE_COLLATION AS karsilastirma
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = :db
          ORDER BY TABLE_NAME'
    );
    $stmt->execute([':db' => DB_NAME]);
    $tablolar = $stmt->fetchAll();
} catch (PDOException $e) {
    $tablolar = [];
}

$toplamBoyut = array_sum(array_map(static fn($t) => (float) $t['boyut'], $tablolar));

/* ---------------------------------------------------------------------
 *  UPLOAD KLASÖRÜ
 * ------------------------------------------------------------------ */
$upDosyaSayisi = 0;
$upBoyut       = 0.0;

if (is_dir(UPLOAD_DIR)) {
    foreach (glob(UPLOAD_DIR . '*') ?: [] as $dosya) {
        if (is_file($dosya)) {
            $upDosyaSayisi++;
            $upBoyut += (float) filesize($dosya);
        }
    }
}

/* ---------------------------------------------------------------------
 *  SAĞLIK KONTROLLERİ
 * ---------------------------------------------------------------------
 *  Her kontrol: [durum, başlık, açıklama]
 *  durum → 'ok' | 'warn' | 'down'
 * ------------------------------------------------------------------ */
$kontroller = [];

// --- PHP sürümü ---
$kontroller[] = version_compare(PHP_VERSION, '8.0.0', '>=')
    ? ['ok', 'PHP sürümü', PHP_VERSION . ' — şablonun gerektirdiği 8.0+ karşılanıyor.']
    : ['down', 'PHP sürümü', PHP_VERSION . ' — şablon PHP 8.0 ve üzeri ister. Sunucunuzu güncelleyin.'];

// --- Zorunlu eklentiler ---
foreach (['pdo_mysql' => 'Veritabanı bağlantısı', 'mbstring' => 'Türkçe karakter işlemleri'] as $ext => $ne) {
    $kontroller[] = extension_loaded($ext)
        ? ['ok', 'PHP eklentisi: ' . $ext, $ne . ' için gerekli, yüklü.']
        : ['down', 'PHP eklentisi: ' . $ext, $ne . ' için ZORUNLU ama yüklü değil.'];
}

// --- Önerilen eklentiler ---
$kontroller[] = extension_loaded('gd') || extension_loaded('imagick')
    ? ['ok', 'Görsel işleme (gd)', 'Yüklü — görsel doğrulama sorunsuz çalışır.']
    : ['warn', 'Görsel işleme (gd)', 'Yüklü değil. getimagesize() çoğu durumda yeterlidir, ancak gd önerilir.'];

// --- Hata ayıklama modu ---
$kontroller[] = APP_DEBUG
    ? ['warn', 'Hata ayıklama modu', 'AÇIK. Yayına alırken .env dosyasında APP_DEBUG=false yapın.']
    : ['ok', 'Hata ayıklama modu', 'Kapalı — hata detayları ziyaretçilere gösterilmiyor.'];

// --- Kurulum klasörü ---
$kontroller[] = is_dir(dirname(__DIR__) . '/kurulum')
    ? ['down', 'Kurulum klasörü', '"kurulum/" hâlâ sunucuda. Veritabanınızı sıfırlamak isteyen birine kapı açık — SİLİN.']
    : ['ok', 'Kurulum klasörü', 'Silinmiş — doğru.'];

// --- .env dosyası ---
$envYolu = dirname(__DIR__) . '/.env';
$kontroller[] = is_file($envYolu)
    ? ['ok', '.env dosyası', 'Mevcut — veritabanı bilgileri koda gömülü değil.']
    : ['warn', '.env dosyası', 'Bulunamadı. Ayarlar config.php varsayılanlarından okunuyor.'];

// --- upload/ yazılabilir mi? ---
$kontroller[] = is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR)
    ? ['ok', 'upload/ klasörü', 'Mevcut ve yazılabilir — dosya yükleme çalışır.']
    : ['down', 'upload/ klasörü', 'Yok veya yazma izni kapalı. Profil fotoğrafı ve logo yüklenemez.'];

// --- upload/.htaccess (PHP çalıştırmayı kapatan koruma) ---
$kontroller[] = is_file(UPLOAD_DIR . '.htaccess')
    ? ['ok', 'upload/.htaccess', 'Mevcut — yüklenen klasörde PHP çalıştırma kapalı.']
    : ['down', 'upload/.htaccess', 'YOK. Bu dosya olmadan yüklenen bir betik sunucuda çalıştırılabilir.'];

// --- HTTPS ---
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

$kontroller[] = $https
    ? ['ok', 'HTTPS', 'Etkin — oturum çerezi "secure" olarak gönderiliyor.']
    : ['warn', 'HTTPS', 'Kapalı. Yerel geliştirmede normaldir; canlıda mutlaka SSL kullanın.'];

// --- Yönetici sayısı ---
$yoneticiSayisi = admin_count($db);
$kontroller[] = $yoneticiSayisi >= 2
    ? ['ok', 'Yönetici hesabı', $yoneticiSayisi . ' aktif yönetici var.']
    : ['warn', 'Yönetici hesabı', 'Yalnızca ' . $yoneticiSayisi . ' aktif yönetici var. Parolanızı unutursanız panele giremezsiniz.'];

// --- Zaman dilimi ---
$phpTz = date_default_timezone_get();
$kontroller[] = ['ok', 'Zaman dilimi', $phpTz . ' — sunucu saati: ' . date('d.m.Y H:i:s')];

// Sorunlu kontrolleri say (üstteki özet için).
$sorunSayisi = count(array_filter($kontroller, static fn($k) => $k[0] !== 'ok'));
?>

<div class="row g-3">

    <!-- ============================================================
         SAĞLIK KONTROLLERİ
         ============================================================ -->
    <div class="col-xl-7">
        <div class="adm-card <?= $sorunSayisi === 0 ? 'adm-card--ok' : 'adm-card--warn' ?>">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('shield') ?> Sağlık Kontrolü</h3>
                <span class="cy-badge <?= $sorunSayisi === 0 ? 'cy-badge--soft' : '' ?>"
                      <?= $sorunSayisi === 0 ? '' : 'style="background:#fef3c7;color:#92400e"' ?>>
                    <?= $sorunSayisi === 0
                        ? 'Her şey yolunda'
                        : $sorunSayisi . ' madde dikkat istiyor' ?>
                </span>
            </div>

            <div class="adm-card__body adm-card__body--flush">
                <ul class="adm-list">
                    <?php foreach ($kontroller as [$durum, $baslik, $aciklama]): ?>
                        <li class="adm-list__item">
                            <span class="adm-dot adm-dot--<?= e($durum) ?>"></span>
                            <div class="adm-list__body">
                                <div class="adm-list__title"><?= e($baslik) ?></div>
                                <div class="cy-muted small" style="white-space:normal"><?= e($aciklama) ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="adm-card__footer">
                Yeşil nokta: sorun yok &middot; Sarı: iyileştirilebilir &middot; Kırmızı: acil
            </div>
        </div>

        <!-- ---------- Veritabanı tabloları ---------- -->
        <div class="adm-card">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('database') ?> Veritabanı Tabloları</h3>
                <span class="cy-muted small">Toplam <?= e(format_bytes($toplamBoyut)) ?></span>
            </div>

            <div class="adm-card__body adm-card__body--flush">
                <?php if ($tablolar === []): ?>
                    <p class="cy-empty mb-0">Tablo bilgisi okunamadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table cy-table w-100 mb-0">
                            <thead>
                                <tr>
                                    <th>Tablo</th>
                                    <th class="text-end">Satır</th>
                                    <th class="text-end">Boyut</th>
                                    <th>Motor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tablolar as $t): ?>
                                    <tr>
                                        <td class="cy-name"><?= e((string) $t['ad']) ?></td>
                                        <td class="text-end cy-nowrap">≈ <?= (int) $t['satir'] ?></td>
                                        <td class="text-end cy-nowrap"><?= e(format_bytes((float) $t['boyut'])) ?></td>
                                        <td>
                                            <span class="cy-badge cy-badge--soft" style="font-size:.7rem">
                                                <?= e((string) $t['motor']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="adm-card__footer">
                Satır sayıları InnoDB'de tahminidir (≈); kesin sayım için tabloyu ayrıca sorgulayın.
            </div>
        </div>
    </div>


    <!-- ============================================================
         ORTAM BİLGİLERİ
         ============================================================ -->
    <div class="col-xl-5">

        <div class="adm-card adm-card--brand">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('server') ?> Sunucu</h3>
            </div>
            <div class="adm-card__body">
                <div class="adm-kv"><span class="adm-kv__k">PHP</span><span class="adm-kv__v"><?= e(PHP_VERSION) ?></span></div>
                <div class="adm-kv"><span class="adm-kv__k">Web sunucusu</span><span class="adm-kv__v"><?= e((string) ($_SERVER['SERVER_SOFTWARE'] ?? '-')) ?></span></div>
                <div class="adm-kv"><span class="adm-kv__k">İşletim sistemi</span><span class="adm-kv__v"><?= e(PHP_OS_FAMILY) ?></span></div>
                <div class="adm-kv"><span class="adm-kv__k">MySQL</span><span class="adm-kv__v"><?= e($mysqlSurum) ?></span></div>
                <div class="adm-kv"><span class="adm-kv__k">Bellek sınırı</span><span class="adm-kv__v"><?= e((string) ini_get('memory_limit')) ?></span></div>
                <div class="adm-kv"><span class="adm-kv__k">Azami çalışma süresi</span><span class="adm-kv__v"><?= e((string) ini_get('max_execution_time')) ?> sn</span></div>
                <div class="adm-kv"><span class="adm-kv__k">Zaman dilimi</span><span class="adm-kv__v"><?= e($phpTz) ?></span></div>
            </div>
        </div>

        <div class="adm-card">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('upload') ?> Dosya Yükleme</h3>
            </div>
            <div class="adm-card__body">
                <div class="adm-kv">
                    <span class="adm-kv__k">Şablon sınırı</span>
                    <span class="adm-kv__v"><?= e(format_bytes((float) UPLOAD_MAX_BYTES)) ?></span>
                </div>
                <div class="adm-kv">
                    <span class="adm-kv__k">php.ini · upload_max_filesize</span>
                    <span class="adm-kv__v"><?= e((string) ini_get('upload_max_filesize')) ?></span>
                </div>
                <div class="adm-kv">
                    <span class="adm-kv__k">php.ini · post_max_size</span>
                    <span class="adm-kv__v"><?= e((string) ini_get('post_max_size')) ?></span>
                </div>
                <div class="adm-kv">
                    <span class="adm-kv__k">upload/ içeriği</span>
                    <span class="adm-kv__v"><?= (int) $upDosyaSayisi ?> dosya · <?= e(format_bytes($upBoyut)) ?></span>
                </div>

                <?php
                /* Şablon sınırı php.ini sınırından büyükse, kullanıcı
                 * "2 MB'a kadar yükleyebilirsiniz" yazısını görür ama
                 * yükleme sunucuda reddedilir. Bunu açıkça söylüyoruz. */
                $iniSinir = min(ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size')));
                if ($iniSinir > 0 && UPLOAD_MAX_BYTES > $iniSinir):
                ?>
                    <div class="alert alert-warning mt-3 mb-0 small">
                        Şablon sınırı (<?= e(format_bytes((float) UPLOAD_MAX_BYTES)) ?>) php.ini sınırından
                        (<?= e(format_bytes($iniSinir)) ?>) büyük. Bu boyutta bir dosya sunucu tarafından
                        reddedilir. <code>php.ini</code> değerlerini yükseltin veya
                        <code>system/config.php</code> içindeki <code>UPLOAD_MAX_BYTES</code> değerini düşürün.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="adm-card">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('info') ?> Uygulama</h3>
            </div>
            <div class="adm-card__body">
                <div class="adm-kv"><span class="adm-kv__k">Uygulama adı</span><span class="adm-kv__v"><?= e(APP_NAME) ?></span></div>
                <div class="adm-kv"><span class="adm-kv__k">Şablon sürümü</span><span class="adm-kv__v"><?= e((string) setting('sistem_surum', '1.0.0')) ?></span></div>
                <div class="adm-kv"><span class="adm-kv__k">Veritabanı</span><span class="adm-kv__v"><?= e(DB_NAME) ?></span></div>
                <div class="adm-kv"><span class="adm-kv__k">Karakter seti</span><span class="adm-kv__v"><?= e(DB_CHARSET) ?></span></div>
                <div class="adm-kv">
                    <span class="adm-kv__k">Hata ayıklama</span>
                    <span class="adm-kv__v"><?= APP_DEBUG ? 'Açık' : 'Kapalı' ?></span>
                </div>
                <div class="adm-kv">
                    <span class="adm-kv__k">Bakım modu</span>
                    <span class="adm-kv__v"><?= setting_bool('sistem_bakim_modu') ? 'Açık' : 'Kapalı' ?></span>
                </div>
                <div class="adm-kv">
                    <span class="adm-kv__k">Yeni kayıtlar</span>
                    <span class="adm-kv__v"><?= setting_bool('sistem_kayit_acik') ? 'Açık' : 'Kapalı' ?></span>
                </div>
            </div>
        </div>

        <!-- ---------- Bağlantı testi ---------- -->
        <div class="adm-card">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('refresh') ?> Bağlantı Testi</h3>
            </div>
            <div class="adm-card__body">
                <p class="cy-muted small">
                    AJAX uç noktasının, CSRF korumasının ve veritabanı bağlantısının
                    birlikte çalıştığını tek tıkla doğrular.
                </p>
                <button type="button" class="btn cy-btn cy-btn--primary btn-sm" id="ping_test">
                    Testi Çalıştır
                </button>
                <pre class="mt-3 mb-0 p-2 d-none" id="ping_sonuc"
                     style="background:var(--cy-surface-soft);border:1px solid var(--cy-border);
                            border-radius:var(--cy-radius-sm);font-size:.8rem;white-space:pre-wrap"></pre>
            </div>
        </div>
    </div>
</div>

<?php
ob_start();
?>
<script>
$(function () {
    'use strict';

    $('#ping_test').on('click', function () {
        var $btn    = $(this).prop('disabled', true).text('Çalışıyor…');
        var $sonuc  = $('#ping_sonuc').removeClass('d-none').text('İstek gönderiliyor…');

        CY.post({ action: 'ping' })
            .done(function (res) {
                // .text() kullanıyoruz: yanıt HTML olarak yorumlanmasın.
                $sonuc.text(
                    '✔ ' + res.description + '\n' +
                    'PHP: ' + res.php + '\n' +
                    'Veritabanı: ' + res.database + '\n' +
                    'Sunucu saati: ' + res.server_at
                );
                CY.notify('Bağlantı testi başarılı.', 'success');
            })
            .fail(function (xhr) {
                $sonuc.text('✖ ' + CY.hataMesaji(xhr, 'Test başarısız.') + '\nHTTP durumu: ' + xhr.status);
                CY.notify('Bağlantı testi başarısız.', 'danger');
            })
            .always(function () {
                $btn.prop('disabled', false).text('Testi Çalıştır');
            });
    });
});
</script>
<?php
$sayfaScript = ob_get_clean();
require __DIR__ . '/_alt.php';
