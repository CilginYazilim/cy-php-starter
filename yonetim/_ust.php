<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – ORTAK ÜST ŞABLON
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Her panel sayfası bu dosyayı dahil eder; böylece menü, başlık ve
 *  CSS bağlantıları TEK yerde durur. Bir menü öğesi eklemek
 *  istediğinizde sadece burayı düzenlemeniz yeterli.
 *
 *  KULLANIMI (panel sayfasının en üstünde):
 *      $sayfaBaslik = 'Ayarlar';
 *      $aktifMenu   = 'ayarlar';
 *      $gerekenRol  = 'admin';          // opsiyonel, varsayılan 'editor'
 *      require __DIR__ . '/_ust.php';
 *
 *  DİKKAT: Bu dosya config.php'yi kendisi yükler ve yetki kontrolünü
 *  kendisi yapar. Panel sayfalarında ayrıca yapmanıza gerek yoktur.
 * =====================================================================
 */

declare(strict_types=1);

// dirname(__DIR__): "yonetim" klasörünün bir üstü = proje kökü.
require_once dirname(__DIR__) . '/system/config.php';

/* --- YETKİ KONTROLÜ ---
 * Panelin tamamı en az "editor" rolü ister. Tek tek sayfalar
 * $gerekenRol değişkeniyle bunu yükseltebilir (örn. 'admin'). */
$gerekenRol = $gerekenRol ?? 'editor';
require_role($gerekenRol, '../giris.php');

$aktifKullanici = auth_user($db);
$csrfToken      = csrf_token();
$sayfaBaslik    = $sayfaBaslik ?? 'Yönetim';
$aktifMenu      = $aktifMenu ?? '';
$siteAdi        = (string) setting('site_adi', APP_NAME);

/**
 * Menü tanımı.
 * 'rol' → o öğeyi görebilmek için gereken EN AZ rol.
 */
$menu = [
    ['anahtar' => 'ozet',         'baslik' => 'Özet',         'url' => 'index.php',        'rol' => 'editor'],
    ['anahtar' => 'ayarlar',      'baslik' => 'Ayarlar',      'url' => 'ayarlar.php',      'rol' => 'admin'],
    ['anahtar' => 'kullanicilar', 'baslik' => 'Kullanıcılar', 'url' => 'kullanicilar.php', 'rol' => 'admin'],
    ['anahtar' => 'profil',       'baslik' => 'Profilim',     'url' => 'profil.php',       'rol' => 'editor'],
];
?>
<!DOCTYPE html>
<html lang="<?= e((string) setting('site_dil', 'tr')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e($sayfaBaslik) ?> | <?= e($siteAdi) ?></title>

    <link rel="icon" type="image/png" href="<?= e(site_logo_url('../')) ?>">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/cilginyazilim.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="cy-app">
    <div class="cy-topbar"></div>

    <div class="container py-2 py-lg-2">

        <div class="cy-card">

            <!-- ---------- Panel Başlığı ---------- -->
            <div class="cy-card__header">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                    <a class="cy-brand" href="../index.php">
                        <span class="cy-brand__mark">
                            <img src="<?= e(site_logo_url('../')) ?>" alt="<?= e($siteAdi) ?>">
                        </span>
                        <div>
                            <h1 class="cy-brand__title"><?= e($siteAdi) ?></h1>
                            <p class="cy-brand__subtitle">Yönetim Paneli &middot; <?= e($sayfaBaslik) ?></p>
                        </div>
                    </a>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="cy-badge cy-badge--glass">
                            <?= e($aktifKullanici['ad'] . ' ' . $aktifKullanici['soyad']) ?>
                            &middot;
                            <?= e(auth_role_labels()[$aktifKullanici['rol']] ?? $aktifKullanici['rol']) ?>
                        </span>

                        <!-- Çıkış POST ile yapılır (CSRF koruması) -->
                        <form method="post" action="../cikis.php" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <button type="submit" class="btn cy-btn cy-btn--glass btn-sm">Çıkış</button>
                        </form>
                    </div>
                </div>

                <!-- ---------- Menü ---------- -->
                <nav class="mt-3 d-flex flex-wrap gap-2">
                    <?php foreach ($menu as $item): ?>
                        <?php if (!auth_at_least($item['rol'])) { continue; } ?>
                        <a href="<?= e($item['url']) ?>"
                           class="btn cy-btn btn-sm <?= $aktifMenu === $item['anahtar'] ? 'cy-btn--onbrand' : 'cy-btn--glass' ?>">
                            <?= e($item['baslik']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- ---------- Sayfa İçeriği (panel sayfası buradan devam eder) ---------- -->
            <div class="cy-card__body">
