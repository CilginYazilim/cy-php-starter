<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ – ORTAK ÜST ŞABLON (AdminLTE düzeni)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Her panel sayfası bu dosyayı dahil eder. Kenar çubuğu, üst çubuk,
 *  sayfa başlığı ve CSS bağlantıları TEK yerde durur.
 *
 *  KULLANIMI (panel sayfasının en üstünde):
 *      $sayfaBaslik   = 'Ayarlar';           // <title> + sayfa başlığı
 *      $sayfaAciklama = 'Site geneli...';    // başlığın altı (ops.)
 *      $aktifMenu     = 'ayarlar';           // menüde hangisi parlasın
 *      $gerekenRol    = 'admin';             // ops., varsayılan 'editor'
 *      $kirintilar    = ['Ayarlar'];         // ops., kırıntı yolu
 *      require __DIR__ . '/_ust.php';
 *
 *  DİKKAT: Bu dosya config.php'yi kendisi yükler ve yetki kontrolünü
 *  kendisi yapar. Panel sayfalarında ayrıca yapmanıza gerek yoktur.
 *
 *  MENÜYE YENİ SAYFA EKLEMEK: Burayı değil, system/panel.php
 *  içindeki panel_menu() fonksiyonunu düzenleyin.
 * =====================================================================
 */

declare(strict_types=1);

// dirname(__DIR__): "yonetim" klasörünün bir üstü = proje kökü.
require_once dirname(__DIR__) . '/system/config.php';
require_once dirname(__DIR__) . '/system/panel.php';

/* --- YETKİ KONTROLÜ ---
 * Panelin tamamı en az "editor" rolü ister. Tek tek sayfalar
 * $gerekenRol değişkeniyle bunu yükseltebilir (örn. 'admin'). */
$gerekenRol = $gerekenRol ?? 'editor';
require_role($gerekenRol, '../giris.php');

$aktifKullanici = auth_user($db);
$csrfToken      = csrf_token();
$sayfaBaslik    = $sayfaBaslik   ?? 'Yönetim';
$sayfaAciklama  = $sayfaAciklama ?? '';
$aktifMenu      = $aktifMenu     ?? '';
$kirintilar     = $kirintilar    ?? [];
$siteAdi        = (string) setting('site_adi', APP_NAME);
$temaRengi      = (string) setting('sistem_tema_rengi', '#0b5cb5');

/* --- Okunmamış mesaj sayısı ---
 * Hem sol menüdeki rozet hem üstteki zil bunu kullanır.
 * Yalnızca yöneticiler mesajları görebildiği için başkasına sorulmaz. */
$okunmamisMesaj = auth_at_least('admin') ? unread_message_count($db) : 0;

// Zil menüsünde gösterilecek son okunmamış mesajlar.
$sonMesajlar = [];
if ($okunmamisMesaj > 0) {
    $sonMesajlar = $db->query(
        'SELECT id, ad, konu, created_at
           FROM mesajlar
          WHERE okundu = 0
          ORDER BY created_at DESC
          LIMIT 5'
    )->fetchAll();
}

/* --- Kenar çubuğu durumu ---
 * Daraltılmış mı? Bu bilgiyi ÇEREZDEN okuyup body sınıfını sunucuda
 * basıyoruz. localStorage ile yapılsaydı sayfa önce geniş menüyle
 * çizilir, sonra JavaScript daraltırdı — gözle görülür bir sıçrama
 * olurdu. Çerez, HTML'in ilk baytından itibaren doğru olmasını sağlar. */
$menuDar = (($_COOKIE['cy_menu'] ?? '') === 'dar');

$avatarUrl = avatar_url($aktifKullanici, '../');
$menu      = panel_menu();
?>
<!DOCTYPE html>
<html lang="<?= e((string) setting('site_dil', 'tr')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= e($temaRengi) ?>">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e($sayfaBaslik) ?> | <?= e($siteAdi) ?> Yönetim</title>

    <link rel="icon" type="image/png" href="<?= e(site_logo_url('../')) ?>">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/cilginyazilim.css">
    <link rel="stylesheet" href="../assets/css/cy-admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Marka rengini ayarlardan uygula (CSS değişkenini ezerek) -->
    <style>:root { --cy-brand-600: <?= e($temaRengi) ?>; }</style>

    <script>
    /* KOYU TEMA – "yanıp sönme" (flash) önleyici.
     * BİLEREK <head> içinde ve satır içi: sayfa çizilmeden önce
     * çalışmazsa kullanıcı önce açık temayı görür, sonra ekran kararır. */
    (function () {
        try {
            var t = localStorage.getItem('cy-tema');
            if (t === 'dark' || t === 'light') {
                document.documentElement.setAttribute('data-cy-theme', t);
            }
        } catch (e) {}
    })();
    </script>
</head>

<body class="cy-app adm<?= $menuDar ? ' adm--mini' : '' ?>">

<!-- Klavye kullanıcıları menüyü atlayıp içeriğe geçebilsin -->
<a href="#adm_icerik" class="visually-hidden-focusable btn cy-btn cy-btn--primary m-2">İçeriğe geç</a>

<!-- ==================================================================
     KENAR ÇUBUĞU
     ================================================================== -->
<aside class="adm-sidebar" id="adm_sidebar">

    <a class="adm-sidebar__brand" href="index.php">
        <span class="adm-sidebar__logo">
            <img src="<?= e(site_logo_url('../')) ?>" alt="">
        </span>
        <span class="adm-sidebar__name">
            <?= e($siteAdi) ?>
            <span class="adm-sidebar__tag">Yönetim Paneli</span>
        </span>
    </a>

    <div class="adm-sidebar__user">
        <?php if ($avatarUrl !== ''): ?>
            <img src="<?= e($avatarUrl) ?>" class="cy-avatar" alt="">
        <?php else: ?>
            <span class="cy-avatar cy-avatar--initial"><?= e(user_initials($aktifKullanici)) ?></span>
        <?php endif; ?>
        <div class="overflow-hidden">
            <span class="adm-sidebar__user-name d-block">
                <?= e($aktifKullanici['ad'] . ' ' . $aktifKullanici['soyad']) ?>
            </span>
            <span class="adm-sidebar__user-role">
                <?= e(auth_role_labels()[$aktifKullanici['rol']] ?? $aktifKullanici['rol']) ?>
            </span>
        </div>
    </div>

    <nav aria-label="Panel menüsü">
        <ul class="adm-menu">
            <?php foreach ($menu as $item): ?>
                <?php
                // Rolü yetmeyen öğeler HİÇ BASILMAZ (gizlemek yetmez).
                if (!auth_at_least($item['rol'])) { continue; }
                ?>

                <?php if ($item['tip'] === 'baslik'): ?>
                    <li class="adm-menu__header"><?= e($item['metin']) ?></li>
                    <?php continue; ?>
                <?php endif; ?>

                <?php
                $aktif = ($aktifMenu === $item['anahtar']);
                $rozet = ($item['rozet'] ?? '') === 'mesaj' ? $okunmamisMesaj : 0;
                ?>
                <li>
                    <a href="<?= e($item['url']) ?>"
                       class="adm-menu__link<?= $aktif ? ' adm-menu__link--aktif' : '' ?>"
                       data-baslik="<?= e($item['baslik']) ?>"
                       <?= $aktif ? 'aria-current="page"' : '' ?>>
                        <span class="adm-menu__icon"><?= panel_icon($item['ikon']) ?></span>
                        <span class="adm-menu__text"><?= e($item['baslik']) ?></span>
                        <?php if (($item['rozet'] ?? '') !== ''): ?>
                            <!-- Rozet 0 olsa bile DOM'da durur (gizli):
                                 mesajlar sayfası sayacı sayfa yenilemeden
                                 güncelleyebilsin diye. -->
                            <span class="adm-menu__badge"<?= $rozet > 0 ? '' : ' style="display:none"' ?>>
                                <?= $rozet > 99 ? '99+' : $rozet ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>

            <li class="adm-menu__header">Oturum</li>
            <li>
                <!-- Çıkış POST ile yapılır: <img src="cikis.php"> gibi bir
                     etiket bile GET isteğiyle sizi habersiz çıkarabilirdi. -->
                <form method="post" action="../cikis.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="adm-menu__link w-100 border-0 bg-transparent"
                            data-baslik="Çıkış Yap">
                        <span class="adm-menu__icon"><?= panel_icon('logout') ?></span>
                        <span class="adm-menu__text">Çıkış Yap</span>
                    </button>
                </form>
            </li>
        </ul>
    </nav>
</aside>

<!-- Mobilde menü açıkken arkayı karartan perde -->
<div class="adm-backdrop" id="adm_backdrop"></div>


<!-- ==================================================================
     ÜST ÇUBUK
     ================================================================== -->
<header class="adm-navbar">
    <button type="button" class="adm-navbar__btn" id="adm_menu_toggle"
            aria-label="Menüyü aç/kapat" title="Menüyü aç/kapat">
        <?= panel_icon('menu') ?>
    </button>

    <h1 class="adm-navbar__title d-none d-sm-block"><?= e($sayfaBaslik) ?></h1>

    <span class="adm-navbar__spacer"></span>

    <!-- Siteyi yeni sekmede aç -->
    <a href="../index.php" target="_blank" rel="noopener"
       class="adm-navbar__btn" title="Siteyi yeni sekmede aç">
        <?= panel_icon('globe') ?>
    </a>

    <?php if (auth_at_least('admin')): ?>
        <!-- Bildirim zili: okunmamış mesajlar -->
        <div class="dropdown">
            <button type="button" class="adm-navbar__btn" data-bs-toggle="dropdown"
                    aria-expanded="false" title="Okunmamış mesajlar">
                <?= panel_icon('bell') ?>
                <span class="adm-navbar__count"<?= $okunmamisMesaj > 0 ? '' : ' style="display:none"' ?>>
                    <?= $okunmamisMesaj > 99 ? '99+' : $okunmamisMesaj ?>
                </span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end" style="min-width:290px">
                <li class="dropdown-header">
                    <?= $okunmamisMesaj > 0
                        ? $okunmamisMesaj . ' okunmamış mesaj'
                        : 'Okunmamış mesaj yok' ?>
                </li>
                <li><hr class="dropdown-divider"></li>

                <?php foreach ($sonMesajlar as $m): ?>
                    <li>
                        <a class="dropdown-item" href="mesajlar.php?id=<?= (int) $m['id'] ?>">
                            <span class="d-block fw-semibold adm-truncate">
                                <?= e($m['konu'] !== '' ? $m['konu'] : '(konusuz)') ?>
                            </span>
                            <small class="cy-muted">
                                <?= e($m['ad']) ?> &middot; <?= e(time_ago($m['created_at'])) ?>
                            </small>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php if ($sonMesajlar !== []): ?>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li><a class="dropdown-item text-center fw-semibold" href="mesajlar.php">Tüm mesajlar</a></li>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Açık / koyu tema -->
    <button type="button" class="adm-navbar__btn" id="adm_tema"
            title="Açık / koyu tema" aria-label="Açık veya koyu temaya geç">
        <?= panel_icon('moon') ?>
    </button>

    <!-- Kullanıcı menüsü -->
    <div class="dropdown">
        <button class="cy-usermenu" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <?php if ($avatarUrl !== ''): ?>
                <img src="<?= e($avatarUrl) ?>" class="cy-avatar" alt="">
            <?php else: ?>
                <span class="cy-avatar cy-avatar--initial"><?= e(user_initials($aktifKullanici)) ?></span>
            <?php endif; ?>
            <span class="d-none d-md-inline"><?= e($aktifKullanici['ad']) ?></span>
        </button>

        <ul class="dropdown-menu dropdown-menu-end">
            <li class="dropdown-header">
                <?= e($aktifKullanici['ad'] . ' ' . $aktifKullanici['soyad']) ?><br>
                <?= e(auth_role_labels()[$aktifKullanici['rol']] ?? $aktifKullanici['rol']) ?>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="profil.php">Profilim</a></li>
            <li><a class="dropdown-item" href="../hesabim.php">Site Hesabım</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="post" action="../cikis.php" class="px-1">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="dropdown-item">Çıkış Yap</button>
                </form>
            </li>
        </ul>
    </div>
</header>


<!-- ==================================================================
     İÇERİK
     ================================================================== -->
<main class="adm-content" id="adm_icerik">

    <div class="adm-head">
        <div>
            <h2 class="adm-head__title"><?= e($sayfaBaslik) ?></h2>
            <?php if ($sayfaAciklama !== ''): ?>
                <p class="adm-head__sub"><?= e($sayfaAciklama) ?></p>
            <?php endif; ?>
        </div>

        <nav aria-label="Kırıntı yolu">
            <ul class="adm-breadcrumb">
                <li><a href="index.php">Panel</a></li>
                <?php foreach ($kirintilar as $kirinti): ?>
                    <li><?= e((string) $kirinti) ?></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>

    <?php
    /* Bir önceki istekten devredilen bildirim (POST → Redirect → GET).
     * Sayfaların ayrıca bir şey yapmasına gerek yoktur. */
    $panelFlash = flash_get();
    if ($panelFlash !== null): ?>
        <div class="alert alert-<?= e($panelFlash['tur']) ?>" role="alert">
            <?= e($panelFlash['metin']) ?>
        </div>
    <?php endif; ?>

    <?php
    /* Sayfa içeriği buradan devam eder.
     * Sayfanın sonunda _alt.php dahil edilerek kapatılır. */
    ?>
