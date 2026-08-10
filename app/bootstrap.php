<?php
/**
 * =====================================================================
 *  ÖNYÜKLEME (bootstrap) – Web ve CLI için ORTAK başlangıç
 * ---------------------------------------------------------------------
 *  İki giriş noktamız var:
 *
 *      index.php   → tarayıcıdan gelen istekler
 *      cy          → komut satırı (php cy migrate)
 *
 *  İkisinin de ihtiyacı olan şeyler burada toplanır: sınıf yükleyici,
 *  .env, yapılandırma, saat dilimi, hata yönetimi ve yardımcılar.
 *  Oturum, güvenlik başlıkları ve görünüm paylaşımı gibi WEB'e özgü
 *  işler index.php'de kalır; CLI'da onlara gerek yoktur.
 *
 *  Bu dosya CY_BASE sabitinin TANIMLANMIŞ olmasını bekler.
 * =====================================================================
 */

declare(strict_types=1);

if (!defined('CY_BASE')) {
    exit('bootstrap.php doğrudan çağrılamaz: CY_BASE tanımlı değil.');
}

require_once CY_BASE . '/app/Core/Env.php';
require_once CY_BASE . '/app/Core/Autoloader.php';

App\Core\Autoloader::register('App', CY_BASE . '/app');

App\Core\Env::load(CY_BASE . '/.env');

/* config/ klasöründeki her dosya bir üst anahtar olur:
 * config/db.php → Config::get('db.host'). Yayında önbelleğe
 * alınmışsa (php cy config:cache) tek dosya okunur. */
App\Core\Config::load(CY_BASE . '/config');

date_default_timezone_set((string) App\Core\Config::get('app.timezone', 'Europe/Istanbul'));

/* ---------------------------------------------------------------------
 *  MERKEZİ HATA YÖNETİMİ
 * ---------------------------------------------------------------------
 *  Buradan SONRAKİ her satır güvendedir: yakalanmamış istisnalar, PHP
 *  uyarıları ve ölümcül hatalar tek bir yerden loglanır ve ortama
 *  uygun biçimde (tarayıcıda sayfa, terminalde metin) sonuçlanır.
 * ------------------------------------------------------------------ */
App\Core\ErrorHandler::register(App\Core\Config::isDebug());

/* Yayın ortamında hata ayıklama açık unutulmuşsa güvenlik kanalına
 * kaydeder. Sessizce kapatmıyoruz — geliştirici bilerek açmış
 * olabilir — ama iz bırakmadan da geçmiyoruz. */
App\Core\Config::warnIfUnsafe();

require_once CY_BASE . '/app/Support/helpers.php';

/* ---------------------------------------------------------------------
 *  OLAY DİNLEYİCİLERİ
 * ---------------------------------------------------------------------
 *  "Ne olduğunda ne yapılır" tablosu. Web ve CLI için ORTAK yüklenir:
 *  komut satırından oluşturulan bir kullanıcı da karşılama mektubunu
 *  almalıdır.
 * ------------------------------------------------------------------ */
require_once CY_BASE . '/routes/events.php';

/* Zamanlanmış görevler yalnızca komut satırında gerekir; web
 * isteğinde yüklemek gereksiz iştir. */
if (PHP_SAPI === 'cli') {
    require_once CY_BASE . '/routes/schedule.php';
}
