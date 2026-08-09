<?php
/**
 * =====================================================================
 *  ÇIKIŞ
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  GÜVENLİK: Çıkış işlemi de CSRF korumalıdır.
 *
 *  "Bir kullanıcıyı çıkış yaptırmak zararsız, neden koruyalım?"
 *  Zararsız değil: kötü niyetli bir site <img src=".../cikis.php">
 *  koyarak sizi sürekli oturumdan düşürebilir. Bu yüzden çıkış
 *  POST + token ile yapılır.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/system/config.php';

$token = $_POST['csrf_token'] ?? $_GET['token'] ?? '';

if (is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
    auth_logout();
    header('Location: giris.php?cikis=tamam');
    exit;
}

// Token geçersizse hiçbir şey yapmadan ana sayfaya döneriz.
header('Location: index.php');
exit;
