<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – ÖZET (Dashboard)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Yetki kontrolü ve sayfa iskeleti _ust.php içindedir.
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik = 'Özet';
$aktifMenu   = 'ozet';
$gerekenRol  = 'editor';

require __DIR__ . '/_ust.php';

/* --- Özet istatistikler --- */
$toplamKullanici = count_rows($db, 'kullanicilar');
$toplamAyar      = count_rows($db, 'ayarlar');
$yoneticiSayisi  = admin_count($db);

// Son giriş yapan 5 kullanıcı.
$sonGirisler = $db->query(
    'SELECT ad, soyad, kullanici_adi, rol, son_giris
       FROM kullanicilar
      WHERE son_giris IS NOT NULL
      ORDER BY son_giris DESC
      LIMIT 5'
)->fetchAll();
?>

<h2 class="h6 text-uppercase cy-muted mb-3">Hoş geldiniz</h2>
<p class="mb-4">
    <strong><?= e($aktifKullanici['ad'] . ' ' . $aktifKullanici['soyad']) ?></strong>,
    <?= e($siteAdi) ?> yönetim paneline giriş yaptınız.
    <?php if ($aktifKullanici['son_giris'] !== null): ?>
        <br><small class="cy-muted">
            Bir önceki girişiniz: <?= e(format_date($aktifKullanici['son_giris'])) ?>
            <?php if ($aktifKullanici['son_giris_ip'] !== ''): ?>
                (<?= e($aktifKullanici['son_giris_ip']) ?>)
            <?php endif; ?>
        </small>
    <?php endif; ?>
</p>

<!-- ---------- İstatistik kutuları ---------- -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="p-3 rounded" style="background:var(--cy-surface-soft);border:1px solid var(--cy-border)">
            <div class="cy-muted small text-uppercase">Kullanıcı</div>
            <div class="h3 mb-0"><?= (int) $toplamKullanici ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="p-3 rounded" style="background:var(--cy-surface-soft);border:1px solid var(--cy-border)">
            <div class="cy-muted small text-uppercase">Yönetici</div>
            <div class="h3 mb-0"><?= (int) $yoneticiSayisi ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="p-3 rounded" style="background:var(--cy-surface-soft);border:1px solid var(--cy-border)">
            <div class="cy-muted small text-uppercase">Ayar</div>
            <div class="h3 mb-0"><?= (int) $toplamAyar ?></div>
        </div>
    </div>
</div>

<!-- ---------- Son girişler ---------- -->
<h2 class="h6 text-uppercase cy-muted mb-3">Son Girişler</h2>

<?php if ($sonGirisler === []): ?>
    <p class="cy-empty">Henüz giriş kaydı yok.</p>
<?php else: ?>
    <div class="table-responsive mb-4">
        <table class="table cy-table w-100">
            <thead>
                <tr>
                    <th>Kullanıcı</th>
                    <th>Rol</th>
                    <th>Son Giriş</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sonGirisler as $satir): ?>
                    <tr>
                        <td class="cy-name">
                            <?= e($satir['ad'] . ' ' . $satir['soyad']) ?>
                            <small class="cy-muted">@<?= e($satir['kullanici_adi']) ?></small>
                        </td>
                        <td><span class="cy-badge cy-badge--soft"><?= e(auth_role_labels()[$satir['rol']] ?? $satir['rol']) ?></span></td>
                        <td class="cy-nowrap"><?= e(format_date($satir['son_giris'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ---------- Kurulum dosyası uyarısı ---------- -->
<?php if (is_file(dirname(__DIR__) . '/install.php')): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Güvenlik uyarısı:</strong> <code>install.php</code> dosyası hâlâ sunucuda.
        Kurulum tamamlandığına göre bu dosyayı silin — sunucuda kalırsa
        veritabanınızı sıfırlamak isteyen birine kapı açık kalır.
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_alt.php'; ?>
