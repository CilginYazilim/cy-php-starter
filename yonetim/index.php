<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – KONTROL PANELİ (Dashboard)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Panele giren kişinin ilk gördüğü ekran. Amacı "her şeyi göstermek"
 *  değil, BİR BAKIŞTA durumu anlatmaktır:
 *      • sayılar        → kaç kullanıcı, kaç mesaj
 *      • eğilim         → son 14 gün ne oldu
 *      • dikkat isteyen → okunmamış mesaj, güvenlik uyarısı
 *
 *  Yetki kontrolü ve sayfa iskeleti _ust.php içindedir.
 * =====================================================================
 */

declare(strict_types=1);

$sayfaBaslik   = 'Kontrol Paneli';
$sayfaAciklama = 'Sitenizin güncel durumu ve son hareketler.';
$aktifMenu     = 'ozet';
$gerekenRol    = 'editor';

require __DIR__ . '/_ust.php';

/* ---------------------------------------------------------------------
 *  VERİ TOPLAMA
 * ------------------------------------------------------------------ */
$sayilar  = panel_stats($db);
$yonetici = auth_at_least('admin');

// Grafik serileri: son 14 günün günlük dağılımı.
$kayitSerisi = panel_daily_counts($db, 'kullanicilar', 14);
$mesajSerisi = $yonetici ? panel_daily_counts($db, 'mesajlar', 14) : [];

// Son giriş yapan kullanıcılar.
$sonGirisler = $db->query(
    'SELECT id, ad, soyad, kullanici_adi, avatar, rol, son_giris, son_giris_ip
       FROM kullanicilar
      WHERE son_giris IS NOT NULL
      ORDER BY son_giris DESC
      LIMIT 6'
)->fetchAll();

// Son kayıt olan kullanıcılar.
$sonKayitlar = $db->query(
    'SELECT id, ad, soyad, eposta, avatar, rol, durum, created_at
       FROM kullanicilar
      ORDER BY created_at DESC
      LIMIT 5'
)->fetchAll();

// Son gelen mesajlar (yalnızca yöneticiye).
$sonMesajListesi = [];
if ($yonetici && table_exists($db, 'mesajlar')) {
    $sonMesajListesi = $db->query(
        'SELECT id, ad, eposta, konu, mesaj, okundu, created_at
           FROM mesajlar
          ORDER BY created_at DESC
          LIMIT 6'
    )->fetchAll();
}

/* ---------------------------------------------------------------------
 *  DURUM UYARILARI
 * ---------------------------------------------------------------------
 *  Sunucuyu güvensiz bırakan tipik hataları panelin ilk ekranında
 *  söylemek, README'ye yazmaktan çok daha etkilidir.
 * ------------------------------------------------------------------ */
$uyarilar = [];

if (is_dir(dirname(__DIR__) . '/kurulum')) {
    $uyarilar[] = [
        'tur'   => 'danger',
        'ikon'  => 'alert',
        'baslik' => 'Kurulum klasörü hâlâ sunucuda',
        'metin' => 'Kurulum bittiğine göre "kurulum/" klasörünü silin. Sunucuda kalırsa veritabanınızı sıfırlamak isteyen birine kapı açık kalır.',
    ];
}

if (APP_DEBUG) {
    $uyarilar[] = [
        'tur'   => 'warning',
        'ikon'  => 'info',
        'baslik' => 'Hata ayıklama modu açık',
        'metin' => 'Siteyi yayına aldığınızda ".env" dosyasında APP_DEBUG=false yapın; aksi halde hata mesajları ziyaretçilere de görünür.',
    ];
}

if (setting_bool('sistem_bakim_modu')) {
    $uyarilar[] = [
        'tur'   => 'warning',
        'ikon'  => 'alert',
        'baslik' => 'Bakım modu açık',
        'metin' => 'Site şu anda ziyaretçilere kapalı; sadece editör ve yöneticiler görebiliyor.',
    ];
}

if ($sayilar['kullanici_yonetici'] <= 1) {
    $uyarilar[] = [
        'tur'   => 'info',
        'ikon'  => 'shield',
        'baslik' => 'Tek yönetici hesabı var',
        'metin' => 'Bu hesabın parolasını unutursanız panele giremezsiniz. Yedek bir yönetici hesabı açmanız önerilir.',
    ];
}

/**
 * Küçük bir sütun grafiği çizer.
 *
 * Yükseklikler EN BÜYÜK DEĞERE göre oranlanır; böylece sayılar
 * 3 de olsa 3000 de olsa grafik aynı güzellikte görünür.
 *
 * @param array<string,int> $seri 'Y-m-d' => adet
 */
function grafik_ciz(array $seri, string $etiket): void
{
    if ($seri === []) {
        echo '<p class="cy-empty mb-0">Gösterilecek veri yok.</p>';
        return;
    }

    // max(...,1): tüm değerler 0 ise sıfıra bölme hatası olmasın.
    $enBuyuk = max(1, max($seri));

    echo '<div class="adm-chart">';
    foreach ($seri as $gun => $adet) {
        $yukseklik = (int) round(($adet / $enBuyuk) * 100);
        $bilgi     = date('d.m.Y', strtotime($gun)) . ' · ' . $adet . ' ' . $etiket;

        echo '<div class="adm-chart__col" data-bilgi="' . e($bilgi) . '">'
            . '<div class="adm-chart__bar" style="height:' . max(2, $yukseklik) . '%"></div>'
            . '</div>';
    }
    echo '</div>';

    // Alt eksen: kalabalık olmasın diye sadece her 2. günün numarası.
    echo '<div class="adm-chart__axis">';
    $i = 0;
    foreach (array_keys($seri) as $gun) {
        echo '<span>' . ($i % 2 === 0 ? date('d', strtotime($gun)) : '') . '</span>';
        $i++;
    }
    echo '</div>';
}
?>

<!-- ================================================================
     DURUM UYARILARI
     ================================================================ -->
<?php foreach ($uyarilar as $uyari): ?>
    <div class="alert alert-<?= e($uyari['tur']) ?> d-flex gap-3 align-items-start" role="alert">
        <span class="flex-shrink-0 mt-1"><?= panel_icon($uyari['ikon']) ?></span>
        <span>
            <strong><?= e($uyari['baslik']) ?></strong><br>
            <?= e($uyari['metin']) ?>
        </span>
    </div>
<?php endforeach; ?>


<!-- ================================================================
     İSTATİSTİK KUTULARI
     ================================================================ -->
<div class="row g-3 mb-1">
    <div class="col-6 col-xl-3">
        <div class="small-box small-box--brand">
            <div class="small-box__inner">
                <div class="small-box__value"><?= (int) $sayilar['kullanici_toplam'] ?></div>
                <div class="small-box__label">Toplam Kullanıcı</div>
            </div>
            <span class="small-box__icon"><?= panel_icon('users') ?></span>
            <?php if ($yonetici): ?>
                <a href="kullanicilar.php" class="small-box__link">
                    Yönet <?= panel_icon('arrow') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="small-box small-box--ok">
            <div class="small-box__inner">
                <div class="small-box__value"><?= (int) $sayilar['kullanici_aktif_7'] ?></div>
                <div class="small-box__label">Son 7 Günde Giriş</div>
            </div>
            <span class="small-box__icon"><?= panel_icon('clock') ?></span>
            <span class="small-box__link">
                Son 30 günde <?= (int) $sayilar['kullanici_yeni_30'] ?> yeni kayıt
            </span>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="small-box <?= $sayilar['mesaj_okunmamis'] > 0 ? 'small-box--danger' : 'small-box--info' ?>">
            <div class="small-box__inner">
                <div class="small-box__value"><?= (int) $sayilar['mesaj_okunmamis'] ?></div>
                <div class="small-box__label">Okunmamış Mesaj</div>
            </div>
            <span class="small-box__icon"><?= panel_icon('mail') ?></span>
            <?php if ($yonetici): ?>
                <a href="mesajlar.php" class="small-box__link">
                    Toplam <?= (int) $sayilar['mesaj_toplam'] ?> mesaj <?= panel_icon('arrow') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="small-box small-box--warn">
            <div class="small-box__inner">
                <div class="small-box__value"><?= (int) $sayilar['kullanici_yonetici'] ?></div>
                <div class="small-box__label">Yönetici Hesabı</div>
            </div>
            <span class="small-box__icon"><?= panel_icon('shield') ?></span>
            <span class="small-box__link">
                <?= (int) $sayilar['kullanici_editor'] ?> editör ·
                <?= (int) $sayilar['kullanici_pasif'] ?> pasif hesap
            </span>
        </div>
    </div>
</div>


<div class="row g-3">

    <!-- ============================================================
         SOL SÜTUN – GRAFİK + SON GİRİŞLER
         ============================================================ -->
    <div class="col-xl-8">

        <!-- ---------- Hareket grafiği ---------- -->
        <div class="adm-card adm-card--brand">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('chart') ?> Son 14 Gün</h3>

                <?php if ($yonetici && $mesajSerisi !== []): ?>
                    <ul class="nav nav-pills nav-sm" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active btn-sm" data-bs-toggle="tab"
                                    data-bs-target="#grafik_kayit" type="button" role="tab">Kayıtlar</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link btn-sm" data-bs-toggle="tab"
                                    data-bs-target="#grafik_mesaj" type="button" role="tab">Mesajlar</button>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="adm-card__body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="grafik_kayit" role="tabpanel">
                        <div class="cy-muted small mb-2">
                            Bu dönemde <strong><?= array_sum($kayitSerisi) ?></strong> yeni kullanıcı kaydı oluştu.
                        </div>
                        <?php grafik_ciz($kayitSerisi, 'kayıt'); ?>
                    </div>

                    <?php if ($yonetici && $mesajSerisi !== []): ?>
                        <div class="tab-pane fade" id="grafik_mesaj" role="tabpanel">
                            <div class="cy-muted small mb-2">
                                Bu dönemde <strong><?= array_sum($mesajSerisi) ?></strong> mesaj alındı.
                            </div>
                            <?php grafik_ciz($mesajSerisi, 'mesaj'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ---------- Son girişler ---------- -->
        <div class="adm-card">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('clock') ?> Son Girişler</h3>
                <?php if ($yonetici): ?>
                    <a href="kullanicilar.php" class="btn cy-btn btn-sm btn-outline-secondary">Tümü</a>
                <?php endif; ?>
            </div>

            <div class="adm-card__body adm-card__body--flush">
                <?php if ($sonGirisler === []): ?>
                    <p class="cy-empty mb-0">Henüz giriş kaydı yok.</p>
                <?php else: ?>
                    <ul class="adm-list">
                        <?php foreach ($sonGirisler as $satir): ?>
                            <?php $av = avatar_url($satir, '../'); ?>
                            <li class="adm-list__item">
                                <?php if ($av !== ''): ?>
                                    <img src="<?= e($av) ?>" class="cy-avatar" alt="" style="width:36px;height:36px">
                                <?php else: ?>
                                    <span class="cy-avatar cy-avatar--initial" style="width:36px;height:36px;font-size:.85rem">
                                        <?= e(user_initials($satir)) ?>
                                    </span>
                                <?php endif; ?>

                                <div class="adm-list__body">
                                    <div class="adm-list__title">
                                        <?= e($satir['ad'] . ' ' . $satir['soyad']) ?>
                                        <span class="cy-badge cy-badge--soft ms-1" style="font-size:.68rem;padding:.2rem .5rem">
                                            <?= e(auth_role_labels()[$satir['rol']] ?? $satir['rol']) ?>
                                        </span>
                                    </div>
                                    <div class="adm-list__meta">
                                        @<?= e($satir['kullanici_adi']) ?>
                                        <?php if ($yonetici && $satir['son_giris_ip'] !== ''): ?>
                                            &middot; <?= e($satir['son_giris_ip']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <span class="adm-list__time" title="<?= e(format_date($satir['son_giris'])) ?>">
                                    <?= e(time_ago($satir['son_giris'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- ============================================================
         SAĞ SÜTUN – MESAJLAR + YENİ KAYITLAR + HIZLI ERİŞİM
         ============================================================ -->
    <div class="col-xl-4">

        <?php if ($yonetici): ?>
            <!-- ---------- Son mesajlar ---------- -->
            <div class="adm-card <?= $sayilar['mesaj_okunmamis'] > 0 ? 'adm-card--danger' : '' ?>">
                <div class="adm-card__header">
                    <h3 class="adm-card__title"><?= panel_icon('mail') ?> Son Mesajlar</h3>
                    <a href="mesajlar.php" class="btn cy-btn btn-sm btn-outline-secondary">Tümü</a>
                </div>

                <div class="adm-card__body adm-card__body--flush">
                    <?php if ($sonMesajListesi === []): ?>
                        <p class="cy-empty mb-0">Henüz mesaj yok.</p>
                    <?php else: ?>
                        <ul class="adm-list">
                            <?php foreach ($sonMesajListesi as $mesaj): ?>
                                <li class="adm-list__item<?= ((int) $mesaj['okundu']) === 0 ? ' adm-list__item--yeni' : '' ?>">
                                    <div class="adm-list__body">
                                        <div class="adm-list__title">
                                            <a href="mesajlar.php?id=<?= (int) $mesaj['id'] ?>"
                                               class="text-decoration-none text-reset">
                                                <?= e($mesaj['konu'] !== '' ? $mesaj['konu'] : '(konusuz)') ?>
                                            </a>
                                        </div>
                                        <div class="adm-list__meta">
                                            <?= e($mesaj['ad']) ?> &middot;
                                            <?= e(mb_substr(trim((string) $mesaj['mesaj']), 0, 60, 'UTF-8')) ?>…
                                        </div>
                                    </div>
                                    <span class="adm-list__time" title="<?= e(format_date($mesaj['created_at'])) ?>">
                                        <?= e(time_ago($mesaj['created_at'])) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ---------- Yeni kayıtlar ---------- -->
        <div class="adm-card">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('user') ?> Yeni Kayıtlar</h3>
            </div>
            <div class="adm-card__body adm-card__body--flush">
                <?php if ($sonKayitlar === []): ?>
                    <p class="cy-empty mb-0">Kayıtlı kullanıcı yok.</p>
                <?php else: ?>
                    <ul class="adm-list">
                        <?php foreach ($sonKayitlar as $satir): ?>
                            <li class="adm-list__item">
                                <div class="adm-list__body">
                                    <div class="adm-list__title"><?= e($satir['ad'] . ' ' . $satir['soyad']) ?></div>
                                    <div class="adm-list__meta"><?= e($satir['eposta']) ?></div>
                                </div>
                                <span class="adm-list__time" title="<?= e(format_date($satir['created_at'])) ?>">
                                    <?= e(time_ago($satir['created_at'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- ---------- Hızlı erişim ---------- -->
        <div class="adm-card">
            <div class="adm-card__header">
                <h3 class="adm-card__title"><?= panel_icon('arrow') ?> Hızlı Erişim</h3>
            </div>
            <div class="adm-card__body d-grid gap-2">
                <?php if ($yonetici): ?>
                    <a href="kullanicilar.php" class="btn cy-btn cy-btn--primary btn-sm text-start">
                        Yeni kullanıcı ekle
                    </a>
                    <a href="ayarlar.php" class="btn cy-btn btn-outline-secondary btn-sm text-start">
                        Site ayarlarını düzenle
                    </a>
                    <a href="sistem.php" class="btn cy-btn btn-outline-secondary btn-sm text-start">
                        Sistem bilgisi ve sağlık kontrolü
                    </a>
                <?php endif; ?>
                <a href="profil.php" class="btn cy-btn btn-outline-secondary btn-sm text-start">
                    Profilimi güncelle
                </a>
                <a href="../index.php" target="_blank" rel="noopener"
                   class="btn cy-btn btn-outline-secondary btn-sm text-start">
                    Siteyi yeni sekmede aç
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_alt.php'; ?>
