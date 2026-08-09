<?php
/**
 * =====================================================================
 *  SystemController – Sistem bilgileri ve güvenlik durumu
 * ---------------------------------------------------------------------
 *  Yalnızca yöneticiye açıktır. Burada ayar DEĞİŞTİRİLMEZ; sunucunun
 *  ve uygulamanın mevcut durumu raporlanır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;
use App\Http\Controller;

final class SystemController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('system/index', [
            'title'    => 'Sistem Bilgisi',
            'subtitle' => 'Kurulum bilgileri ve güvenlik denetim listesi.',
            'info'     => $this->systemInfo(),
            'checks'   => $this->securityChecks(),
        ]);
    }

    /** @return array<string,string> */
    private function systemInfo(): array
    {
        return [
            'Uygulama'       => (string) Config::get('app.name'),
            'PHP sürümü'     => PHP_VERSION,
            'Veritabanı'     => $this->serverVersion(),
            'Sunucu'         => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'bilinmiyor'),
            'Zaman dilimi'   => date_default_timezone_get(),
            'Sunucu saati'   => date('d.m.Y H:i:s'),
            'Yükleme limiti' => ini_get('upload_max_filesize') . ' / POST ' . ini_get('post_max_size'),
            'GD eklentisi'   => function_exists('imagecreatetruecolor') ? 'Var' : 'Yok',
        ];
    }

    private function serverVersion(): string
    {
        try {
            return 'MySQL ' . (string) $this->db->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable) {
            return 'bilinmiyor';
        }
    }

    /** @return array<int,array{label:string,ok:bool,detail:string}> */
    private function securityChecks(): array
    {
        $debug = (bool) Config::get('app.debug');

        return [
            [
                'label'  => 'Hata ayıklama modu kapalı',
                'ok'     => !$debug,
                'detail' => $debug
                    ? 'APP_DEBUG=true. Canlı sunucuda .env dosyasında false yapın.'
                    : 'Hatalar kullanıcıya gösterilmiyor, yalnızca log dosyasına yazılıyor.',
            ],
            [
                'label'  => 'HTTPS kullanımı',
                'ok'     => Session::isHttps(),
                'detail' => Session::isHttps()
                    ? 'Bağlantı şifreli. Oturum çerezi "secure" bayrağıyla gönderiliyor.'
                    : 'Yerel geliştirmede normaldir. Canlıda SSL sertifikası şarttır.',
            ],
            [
                'label'  => '.env dosyası kullanılıyor',
                'ok'     => is_file(CY_BASE . '/.env'),
                'detail' => is_file(CY_BASE . '/.env')
                    ? 'Veritabanı bilgileri kod dışında tutuluyor.'
                    : '.env.example dosyasını .env olarak kopyalayın.',
            ],
            [
                'label'  => 'kurulum klasörü kaldırıldı',
                'ok'     => !is_dir(CY_BASE . '/kurulum'),
                'detail' => is_dir(CY_BASE . '/kurulum')
                    ? '"kurulum/" klasörü hâlâ sunucuda. Kurulum bittiyse silin.'
                    : 'Kurulum klasörü temizlenmiş.',
            ],
            [
                'label'  => 'Yükleme klasöründe PHP kapalı',
                'ok'     => is_file(CY_BASE . '/upload/.htaccess'),
                'detail' => 'upload/.htaccess, yüklenen bir dosyanın çalıştırılmasını engeller.',
            ],
            [
                'label'  => 'Uygulama kodu web erişimine kapalı',
                'ok'     => is_file(CY_BASE . '/app/.htaccess'),
                'detail' => 'app/, config/ ve views/ klasörleri tarayıcıdan doğrudan okunamaz.',
            ],
            [
                'label'  => 'Content-Security-Policy aktif',
                'ok'     => (bool) Config::get('security.csp_enabled'),
                'detail' => 'XSS\'e karşı ikinci savunma hattı.',
            ],
            [
                'label'  => 'Kaba kuvvet koruması aktif',
                'ok'     => (int) Config::get('security.login_max_attempts') > 0,
                'detail' => sprintf(
                    '%d hatalı denemeden sonra hesap %d dakika kilitlenir.',
                    (int) Config::get('security.login_max_attempts'),
                    (int) ((int) Config::get('security.login_lockout') / 60)
                ),
            ],
            [
                'label'  => 'Parolalar özetlenerek saklanıyor',
                'ok'     => true,
                'detail' => 'password_hash() / PASSWORD_DEFAULT (bcrypt). Parolalar geri çevrilemez.',
            ],
        ];
    }
}
