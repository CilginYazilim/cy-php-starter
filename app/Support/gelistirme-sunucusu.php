<?php
/**
 * =====================================================================
 *  PHP'nin yerleşik sunucusu için yönlendirici (XAMPP'siz geliştirme)
 * ---------------------------------------------------------------------
 *      php cy serve                     → http://127.0.0.1:8000
 *      php -S 127.0.0.1:8000 app/Support/gelistirme-sunucusu.php
 *
 *  Yerleşik sunucu .htaccess OKUMAZ. Apache'nin kök .htaccess'inde
 *  yaptığı iki işi burada yaparız:
 *    1) Gizli dosyalar (.env, .git), uygulama klasörleri (app/, config/,
 *       storage/ …), .sql/.md/.log dosyaları ve "cy" betiği servis
 *       edilmez; upload/ içinde betik çalışmaz.
 *    2) Gerçek dosya değilse istek index.php'ye gider (temiz adresler).
 *
 *  YALNIZCA GELİŞTİRME VE CI İÇİNDİR. Yerleşik sunucu tek iş parçacıklı
 *  çalışır; yayında Apache ya da Nginx kullanın. Bu dosya app/ altında
 *  olduğu için Apache'de web'den hiç açılmaz.
 * =====================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$kok = dirname(__DIR__, 2);
$yol = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
$yol = '/' . ltrim((string) preg_replace('#/{2,}#', '/', $yol), '/');

$yasak = '#(^|/)\.(?!well-known(/|$))'                                         // .env, .git/ …
       . '|^/(app|config|database|modules|routes|storage|tests|docs|views|vendor)(/|$)'
       . '|^/cy$|composer\.(json|lock)$'
       . '|\.(sql|md|log|ini|sh|bak|swp|dist|stub)$#i';

if (preg_match($yasak, $yol) === 1
    || preg_match('#^/upload/.*\.(php\d?|phtml|phar|phps|pht)$#i', $yol) === 1) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Erişim engellendi.';

    return true;
}

$hedef = $kok . str_replace('/', DIRECTORY_SEPARATOR, $yol);

// Kurulum sihirbazı kendi başına çalışır; gerçek dosyaları sunucu kendisi verir.
if ($yol !== '/' && (is_file($hedef) || str_starts_with($yol, '/kurulum'))) {
    return false;
}

// Geri kalan her şey uygulamanın ön denetleyicisine.
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $kok . DIRECTORY_SEPARATOR . 'index.php';

chdir($kok);

require $kok . DIRECTORY_SEPARATOR . 'index.php';
