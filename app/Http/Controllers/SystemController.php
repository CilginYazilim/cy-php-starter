<?php
/**
 * =====================================================================
 *  SystemController – Sistem bilgisi, sağlık ve güvenlik denetimi
 * ---------------------------------------------------------------------
 *  Yalnızca yöneticiye açıktır. Burada ayar DEĞİŞTİRİLMEZ; sunucunun
 *  ve uygulamanın mevcut durumu raporlanır.
 *
 *  SAYFANIN AMACI "canlıya çıkmaya hazır mıyım?" sorusunu tek ekranda
 *  yanıtlamaktır. Bu yüzden her denetim, sorunu NASIL ÇÖZECEĞİNİZİ de
 *  söyler — "kırmızı yanıyor" demek tek başına işe yaramaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Cache\Cache;
use App\Core\Config;
use App\Core\Database\Migrator;
use App\Core\Flash;
use App\Core\Log\Logger;
use App\Core\Mail\Mailer;
use App\Core\Modules\Modules;
use App\Core\Queue\Queue;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Setting;
use App\Core\Storage\Storage;
use App\Http\Controller;
use App\Repositories\PageRepository;
use Throwable;

final class SystemController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('system/index', [
            'title'      => 'Sistem Bilgisi',
            'subtitle'   => 'Kurulum durumu, sağlık kontrolleri ve canlıya çıkış listesi.',
            'ozet'       => $this->summary(),
            'uygulama'   => $this->appInfo(),
            'sunucu'     => $this->serverInfo(),
            'altyapi'    => $this->infrastructure(),
            'klasorler'  => $this->directories(),
            'eklentiler' => $this->extensions(),
            'checks'     => $this->securityChecks(),
            'ayarChecks' => $this->settingChecks(),
            'moduller'   => Modules::all(),
            'basarisizIsler' => $this->safe(static fn (): array => Queue::failed(20), []),
        ]);
    }

    /* =================================================================
     *  KUYRUK (İSLER TABLOSU) — YENİDEN DENE / TEMİZLE
     * ============================================================== */

    public function queueRetry(Request $request): void
    {
        $sayi = $this->safe(static fn (): int => Queue::retryFailed(), 0);

        Logger::info('Başarısız işler yeniden kuyruğa alındı', ['adet' => $sayi], 'app');
        Flash::success($sayi . ' iş yeniden kuyruğa alındı.');
        Response::redirect(url('panel/sistem'));
    }

    public function queuePurge(Request $request): void
    {
        $sayi = $this->safe(static fn (): int => Queue::purgeFailed(), 0);

        Logger::info('Başarısız işler temizlendi', ['adet' => $sayi], 'app');
        Flash::success($sayi . ' başarısız iş kalıcı olarak silindi.');
        Response::redirect(url('panel/sistem'));
    }

    /* =================================================================
     *  ÜST ÖZET KARTLARI
     * ============================================================== */

    /** @return array<int,array{etiket:string,deger:string,ipucu:string,ikon:string,renk:string}> */
    private function summary(): array
    {
        $checks   = $this->securityChecks();
        $failing  = count(array_filter($checks, static fn (array $c): bool => !$c['ok']));
        $modules  = Modules::all();
        $enabled  = count(array_filter($modules, static fn ($m): bool => $m->aktif));

        $queue = $this->safe(static fn (): array => Queue::stats(), ['bekleyen' => 0, 'basarisiz' => 0]);
        $logs  = $this->logSize();

        return [
            [
                'etiket' => 'Güvenlik denetimi',
                'deger'  => ($failing === 0 ? 'Temiz' : $failing . ' uyarı'),
                'ipucu'  => count($checks) . ' kontrolden geçti',
                'ikon'   => 'shield',
                'renk'   => $failing === 0 ? 'success' : 'warning',
            ],
            [
                'etiket' => 'Kuyruk',
                'deger'  => (string) $queue['bekleyen'],
                'ipucu'  => $queue['basarisiz'] > 0 ? $queue['basarisiz'] . ' başarısız iş' : 'bekleyen iş',
                'ikon'   => 'clock',
                'renk'   => $queue['basarisiz'] > 0 ? 'danger' : 'brand',
            ],
            [
                'etiket' => 'Modüller',
                'deger'  => $enabled . ' / ' . count($modules),
                'ipucu'  => 'açık / toplam',
                'ikon'   => 'server',
                'renk'   => 'brand',
            ],
            [
                'etiket' => 'Günlük dosyaları',
                'deger'  => Storage::humanSize($logs['boyut']),
                'ipucu'  => $logs['adet'] . ' dosya',
                'ikon'   => 'activity',
                'renk'   => $logs['boyut'] > 50 * 1024 * 1024 ? 'warning' : 'brand',
            ],
        ];
    }

    /* =================================================================
     *  BİLGİ TABLOLARI
     * ============================================================== */

    /** @return array<string,string> */
    private function appInfo(): array
    {
        $env = Config::environment();

        return [
            'Uygulama'       => (string) Config::get('app.name'),
            // Sürüm KODDAN okunur (config/app.php). Veritabanındaki eski
            // "sistem_surum" ayarı kaldırıldı: şablonu güncelleyen kişi
            // migration çalıştırana kadar burada eski numara duruyordu.
            'Sürüm'          => (string) Config::get('app.version', '1.0.0'),
            'Ortam'          => $env === 'production' ? 'Yayın (production)' : 'Geliştirme (' . $env . ')',
            'Hata ayıklama'  => Config::isDebug() ? 'AÇIK' : 'Kapalı',
            'Adres biçimi'   => Config::get('app.pretty_urls', true) ? 'Temiz adres (SEO uyumlu)' : 'index.php?r=…',
            // Site adresi ARTIK YALNIZCA .env'de (APP_URL). Panelde de
            // duran ikinci bir kopya, hangisinin geçerli olduğunu
            // belirsizleştiriyordu; ayar satırı kaldırıldı.
            'Site adresi'    => (string) Config::get('app.url', '—'),
            'Zaman dilimi'   => date_default_timezone_get(),
            'Sunucu saati'   => date('d.m.Y H:i:s'),
            'Yapılandırma'   => Config::isCached() ? 'Önbellekten okunuyor' : 'Dosyalardan okunuyor',
        ];
    }

    /** @return array<string,string> */
    private function serverInfo(): array
    {
        return [
            'PHP sürümü'      => PHP_VERSION,
            'Veritabanı'      => $this->serverVersion(),
            'Web sunucusu'    => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'bilinmiyor'),
            'İşletim sistemi' => PHP_OS_FAMILY,
            'Bellek limiti'   => (string) ini_get('memory_limit'),
            'Azami süre'      => ini_get('max_execution_time') . ' sn',
            'Yükleme limiti'  => ini_get('upload_max_filesize') . ' (POST: ' . ini_get('post_max_size') . ')',
            'Disk boş alan'   => $this->freeSpace(),
        ];
    }

    /** @return array<string,string> */
    private function infrastructure(): array
    {
        $queue = $this->safe(static fn (): array => Queue::stats(), ['bekleyen' => 0, 'calisan' => 0, 'basarisiz' => 0]);

        return [
            'Önbellek sürücüsü' => Cache::store()->name(),
            'E-posta yöntemi'   => match (Setting::get('mail_surucu', 'kayit')) {
                'smtp'  => 'SMTP (' . Setting::get('mail_host', '—') . ')',
                'php'   => 'PHP mail()',
                default => 'Kayıt — mektuplar diske yazılır, GÖNDERİLMEZ',
            },
            'Gönderen adresi'   => Mailer::senderAddress(),
            'Kuyruk'            => sprintf(
                '%d bekliyor · %d çalışıyor · %d başarısız',
                $queue['bekleyen'],
                $queue['calisan'] ?? 0,
                $queue['basarisiz']
            ),
            'Migration'         => $this->migrationSummary(),
            'Günlük seviyesi'   => (string) Config::get('log.level', 'debug')
                                  . ' · ' . Config::get('log.days', 30) . ' gün saklanır',
        ];
    }

    /**
     * Yazma izni gereken klasörler.
     *
     * @return array<int,array{ad:string,yol:string,ok:bool}>
     */
    private function directories(): array
    {
        $paths = [
            'Günlükler'        => 'storage/logs',
            'Önbellek'         => 'storage/cache',
            'Özel dosyalar'    => 'storage/files',
            'E-posta çıktısı'  => 'storage/mail',
            'Yüklemeler'       => 'upload',
        ];

        $rows = [];

        foreach ($paths as $ad => $relative) {
            $full = CY_BASE . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            $rows[] = [
                'ad'  => $ad,
                'yol' => $relative,
                'ok'  => is_dir($full) && is_writable($full),
            ];
        }

        return $rows;
    }

    /**
     * Kritik PHP eklentileri.
     *
     * @return array<int,array{ad:string,ok:bool,not:string}>
     */
    private function extensions(): array
    {
        $list = [
            ['pdo_mysql', true,  'Veritabanı bağlantısı — ZORUNLU'],
            ['mbstring',  true,  'Türkçe karakter işlemleri — ZORUNLU'],
            ['json',      true,  'Yapılandırma ve API — ZORUNLU'],
            ['openssl',   true,  'SMTP şifreleme ve güvenli anahtar üretimi'],
            ['gd',        false, 'Görsel yeniden üretme (avatar/logo)'],
            ['fileinfo',  false, 'Yüklenen dosyanın gerçek türünü tespit'],
            ['zip',       false, 'Arşiv işlemleri (opsiyonel)'],
            ['curl',      false, 'Dış servis çağrıları (opsiyonel)'],
        ];

        $rows = [];

        foreach ($list as [$ad, $zorunlu, $not]) {
            $var = extension_loaded($ad);

            $rows[] = [
                'ad'  => $ad,
                'ok'  => $var || !$zorunlu,
                'not' => $var ? $not : ($zorunlu ? 'EKSİK — ' . $not : 'Yok — ' . $not),
            ];
        }

        return $rows;
    }

    /* =================================================================
     *  GÜVENLİK DENETİMİ
     * ============================================================== */

    /** @return array<int,array{label:string,ok:bool,detail:string}> */
    private function securityChecks(): array
    {
        $debug      = Config::isDebug();
        $production = Config::isProduction();
        $https      = Session::isHttps();

        return [
            [
                'label'  => 'Hata ayıklama modu kapalı',
                'ok'     => !$debug,
                'detail' => $debug
                    ? 'APP_DEBUG=true. Ziyaretçiler kaynak kodunuzu ve dosya yollarınızı görebilir. Canlıda .env dosyasında false yapın.'
                    : 'Hatalar kullanıcıya gösterilmiyor, yalnızca storage/logs klasörüne yazılıyor.',
            ],
            [
                'label'  => 'Ortam doğru işaretlenmiş',
                'ok'     => !$production || !$debug,
                'detail' => $production && $debug
                    ? 'APP_ENV=production ama APP_DEBUG=true. Bu çelişki her istekte güvenlik günlüğüne yazılıyor.'
                    : 'APP_ENV=' . Config::environment() . ' ayarı hata ayıklama durumuyla tutarlı.',
            ],
            [
                'label'  => 'HTTPS kullanımı',
                'ok'     => $https,
                'detail' => $https
                    ? 'Bağlantı şifreli. Oturum çerezi "secure" bayrağıyla gönderiliyor.'
                    : 'Yerel geliştirmede normaldir. Canlıda SSL sertifikası şarttır — aksi halde parolalar açık gider.',
            ],
            [
                'label'  => '.env dosyası kullanılıyor',
                'ok'     => is_file(CY_BASE . '/.env'),
                'detail' => is_file(CY_BASE . '/.env')
                    ? 'Veritabanı bilgileri kod dışında tutuluyor ve depoya gönderilmiyor.'
                    : '.env.example dosyasını .env olarak kopyalayın.',
            ],
            [
                'label'  => 'kurulum klasörü kaldırıldı',
                'ok'     => !is_dir(CY_BASE . '/kurulum'),
                'detail' => is_dir(CY_BASE . '/kurulum')
                    ? '"kurulum/" klasörü hâlâ sunucuda. Kurulum bittiyse son adımdaki düğmeyle silin.'
                    : 'Kurulum sihirbazı temizlenmiş.',
            ],
            [
                'label'  => 'Yükleme klasöründe PHP kapalı',
                'ok'     => is_file(CY_BASE . '/upload/.htaccess'),
                'detail' => 'upload/.htaccess, yüklenen bir dosyanın sunucuda çalıştırılmasını engeller.',
            ],
            [
                'label'  => 'Uygulama kodu web erişimine kapalı',
                'ok'     => is_file(CY_BASE . '/app/.htaccess') && is_file(CY_BASE . '/storage/.htaccess'),
                'detail' => 'app/, config/, storage/ ve views/ klasörleri tarayıcıdan doğrudan okunamaz.',
            ],
            [
                'label'  => 'Content-Security-Policy aktif',
                'ok'     => (bool) Config::get('security.csp_enabled'),
                'detail' => 'Satır içi JavaScript engellenir — XSS\'e karşı ikinci savunma hattı.',
            ],
            [
                'label'  => 'Kaba kuvvet koruması aktif',
                'ok'     => (int) Config::get('security.login_max_attempts') > 0,
                'detail' => sprintf(
                    '%d hatalı denemeden sonra giriş %d dakika kilitlenir.',
                    (int) Config::get('security.login_max_attempts'),
                    (int) ((int) Config::get('security.login_lockout') / 60)
                ),
            ],
            [
                'label'  => 'Parolalar özetlenerek saklanıyor',
                'ok'     => true,
                'detail' => 'password_hash() / PASSWORD_DEFAULT (bcrypt). Parolalar geri çevrilemez.',
            ],
            [
                'label'  => 'Yazma izinleri yeterli',
                'ok'     => array_reduce(
                    $this->directories(),
                    static fn (bool $carry, array $d): bool => $carry && $d['ok'],
                    true
                ),
                'detail' => 'storage/ ve upload/ klasörleri yazılabilir olmalıdır; aksi halde günlük, önbellek ve yüklemeler çalışmaz.',
            ],
        ];
    }

    /**
     * YAPILANDIRMA DENETİMİ – "yayına çıkmadan önce bunları doldurun".
     *
     * Güvenlik denetiminden AYRI tutuluyor çünkü farklı bir soruya
     * cevap veriyor: orası "sunucu güvenli mi?", burası "site
     * kullanılabilir durumda mı?" diye sorar. İkisi tek listede
     * karışınca, SMTP'nin kurulmamış olması bir güvenlik açığıymış
     * gibi görünüyor, gerçek açıklar ise listenin içinde kayboluyordu.
     *
     * Her madde sorunun NEREDE çözüleceğini de yazar; "yol" alanı
     * doluysa görünümde tıklanabilir bir bağlantı olur.
     *
     * @return array<int,array{label:string,ok:bool,detail:string,yol:string,baglanti:string}>
     */
    private function settingChecks(): array
    {
        $surucu   = Setting::get('mail_surucu', 'kayit');
        $iletisim = Setting::get('iletisim_eposta');
        $aciklama = Setting::get('site_aciklama');
        $logo     = Setting::get('site_logo');
        $favicon  = Setting::get('site_favicon');
        $indeksle = Setting::bool('seo_indeksleme', true);
        $bakim    = Setting::bool('sistem_bakim_modu', false);

        $yayindaSayfa = 0;

        try {
            $yayindaSayfa = (new PageRepository($this->db))->stats()['yayin'];
        } catch (\Throwable) {
            $yayindaSayfa = 0;
        }

        return [
            [
                'label'    => 'E-posta gönderimi yapılandırıldı',
                'ok'       => $surucu !== 'kayit',
                'detail'   => $surucu === 'kayit'
                    ? 'Gönderim yöntemi hâlâ "kayıt": mektuplar storage/mail/ klasörüne .eml olarak yazılıyor, KİMSEYE ULAŞMIYOR. İletişim formu bildirimleri, hoş geldiniz mektupları ve parola bilgilendirmeleri gitmiyor demektir.'
                    : 'Yöntem: ' . ($surucu === 'smtp' ? 'SMTP · ' . Setting::get('mail_host', '—') : 'PHP mail()') . '.',
                'yol'      => 'panel/ayarlar/eposta',
                'baglanti' => 'SMTP ayarlarını aç',
            ],
            [
                'label'    => 'İletişim e-postası tanımlı',
                'ok'       => $iletisim !== '',
                'detail'   => $iletisim !== ''
                    ? 'Form bildirimleri ' . $iletisim . ' adresine gidiyor.'
                    : 'İletişim formundan gelen mesajların bildirimi hiçbir adrese gönderilemiyor.',
                'yol'      => 'panel/ayarlar/iletisim',
                'baglanti' => 'İletişim ayarlarını aç',
            ],
            [
                'label'    => 'Site açıklaması yazılmış',
                'ok'       => mb_strlen($aciklama) >= 40,
                'detail'   => mb_strlen($aciklama) >= 40
                    ? 'Arama sonuçlarında ve paylaşımlarda bu metin görünüyor.'
                    : 'Açıklama boş ya da çok kısa (' . mb_strlen($aciklama) . ' karakter). Arama motorları için 120–160 karakter idealdir.',
                'yol'      => 'panel/ayarlar/genel',
                'baglanti' => 'Genel ayarları aç',
            ],
            [
                'label'    => 'Logo ve favicon yüklendi',
                'ok'       => $logo !== '' && $favicon !== '',
                'detail'   => $logo !== '' && $favicon !== ''
                    ? 'Kendi görselleriniz kullanılıyor.'
                    : 'Hâlâ şablonun varsayılan görselleri kullanılıyor'
                        . ($logo === '' ? ' (logo)' : '')
                        . ($favicon === '' ? ' (favicon)' : '') . '.',
                'yol'      => 'panel/ayarlar/genel',
                'baglanti' => 'Logo ve favicon',
            ],
            [
                'label'    => 'Yayında içerik sayfası var',
                'ok'       => $yayindaSayfa > 0,
                'detail'   => $yayindaSayfa > 0
                    ? $yayindaSayfa . ' sayfa yayında ve site haritasında.'
                    : 'Hiçbir sayfa yayında değil; menüde yalnızca ana sayfa görünür.',
                'yol'      => 'panel/sayfalar',
                'baglanti' => 'Sayfaları aç',
            ],
            [
                'label'    => 'Arama motorlarına açık',
                'ok'       => $indeksle,
                'detail'   => $indeksle
                    ? 'robots.txt tarama izni veriyor, sayfalarda noindex yok.'
                    : 'İndeksleme KAPALI: robots.txt tüm siteyi engelliyor ve her sayfaya noindex ekleniyor. Yayına çıkarken açın.',
                'yol'      => 'panel/ayarlar/seo',
                'baglanti' => 'SEO ayarlarını aç',
            ],
            [
                'label'    => 'Bakım modu kapalı',
                'ok'       => !$bakim,
                'detail'   => $bakim
                    ? 'Site ziyaretçilere kapalı; yalnızca panele girebilenler içeriği görüyor.'
                    : 'Site herkese açık.',
                'yol'      => 'panel/ayarlar/sistem',
                'baglanti' => 'Sistem ayarlarını aç',
            ],
        ];
    }

    /* =================================================================
     *  YARDIMCILAR
     * ============================================================== */

    private function migrationSummary(): string
    {
        try {
            $migrator = new Migrator(
                $this->db,
                (string) Config::get('db.migrations'),
                Modules::migrationPaths()
            );

            $bekleyen = count($migrator->pending());
            $toplam   = count($migrator->available());

            return $bekleyen === 0
                ? $toplam . ' migration uygulandı — güncel'
                : $bekleyen . ' migration BEKLİYOR (php cy migrate)';
        } catch (Throwable) {
            return 'okunamadı';
        }
    }

    /** @return array{adet:int,boyut:int} */
    private function logSize(): array
    {
        $files = Logger::files();

        return [
            'adet'  => count($files),
            'boyut' => array_sum(array_column($files, 'boyut')),
        ];
    }

    private function freeSpace(): string
    {
        $free = @disk_free_space(CY_BASE);

        return $free === false ? 'bilinmiyor' : Storage::humanSize((int) $free);
    }

    private function serverVersion(): string
    {
        try {
            return 'MySQL/MariaDB ' . (string) $this->db->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (Throwable) {
            return 'bilinmiyor';
        }
    }

    /**
     * Bir okuma başarısız olursa sayfa çökmesin.
     *
     * @template T
     * @param callable():T $callback
     * @param T $default
     * @return T
     */
    private function safe(callable $callback, mixed $default): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $default;
        }
    }
}
