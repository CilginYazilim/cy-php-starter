<?php
/**
 * =====================================================================
 *  PanelNotices – Panelin her sayfasında görünen yönetici uyarıları
 * ---------------------------------------------------------------------
 *  Sistem sayfası ayrıntılı denetim yapar ama oraya bakmayan yönetici
 *  kritik bir durumu fark etmiyordu. Örnek: 1.2.1'den yükseltilen
 *  kurulumda .env hâlâ APP_DEBUG=true yazıyordu; yayındaki sitede bir
 *  hata, dosya yollarını ve SQL'i ziyaretçiye gösteriyordu.
 *
 *  "system.view" yetkisi olana gösterilir (demo hesabı bildirimi
 *  hariç: o, demo hesabıyla giren her role görünür); her istekte en
 *  fazla bir kez hesaplanır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Modules\Modules;
use Throwable;

final class PanelNotices
{
    /** @var array<int,array{tur:string,metin:string,yol:string,baglanti:string}>|null */
    private static ?array $cache = null;

    /** @return array<int,array{tur:string,metin:string,yol:string,baglanti:string}> */
    public static function forCurrentUser(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $notices = [];

        /* Demo hesabıyla giren HER ROL neyin kilitli olduğunu görür;
         * aksi hâlde "Kaydet" düğmesi hata verince sebebi anlaşılmaz. */
        $demoHesabi = Demo::enabled() && Demo::isDemoUser(Auth::user());

        if ($demoHesabi) {
            $notices[] = [
                'tur'      => 'info',
                'metin'    => 'Demo hesabıyla giriş yaptınız. Her şeyi gezip deneyebilirsiniz; hesap bilgileri, kullanıcılar, site ayarları, sistem işlemleri ve e-posta gönderimi bu hesapta kilitlidir.',
                'yol'      => '',
                'baglanti' => '',
            ];
        }

        if (!Auth::can('system.view')) {
            return self::$cache = $notices;
        }

        if (Demo::enabled() && !$demoHesabi) {
            $notices[] = [
                'tur'      => 'warning',
                'metin'    => 'Demo modu AÇIK: giriş ekranı örnek hesapları parolasıyla listeliyor. Bu bir demo sitesi değilse .env dosyasında APP_DEMO=false yapın.',
                'yol'      => '',
                'baglanti' => '',
            ];
        }

        /* Demo modu kapalı ama parolası herkesçe bilinen (Demo1234!)
         * örnek hesaplar duruyor: yerel makine dışında bu bir açıktır —
         * örnek veride bir YÖNETİCİ hesabı da var. */
        if (!Demo::enabled() && !self::isLocalRequest() && Demo::existing(Database::connection()) !== []) {
            $notices[] = [
                'tur'      => 'danger',
                'metin'    => 'Örnek hesaplar (parolaları herkesçe bilinen "' . Demo::PAROLA . '") hâlâ duruyor. Silin: DELETE FROM kullanicilar WHERE eposta LIKE \'%.demo@ornek.com\';',
                'yol'      => 'panel/kullanicilar',
                'baglanti' => 'Kullanıcılar',
            ];
        }

        if (Config::isDebug() && !self::isLocalRequest()) {
            $notices[] = [
                'tur'      => 'danger',
                'metin'    => 'Hata ayıklama modu AÇIK ve site yerel bir bilgisayarda çalışmıyor: bir hata olduğunda ziyaretçi dosya yollarını ve SQL sorgularını görür. .env dosyasında APP_ENV=production ve APP_DEBUG=false yapın.',
                'yol'      => 'panel/sistem',
                'baglanti' => 'Sistem sayfası',
            ];
        }

        $bekleyen = self::pendingMigrations();

        if ($bekleyen > 0) {
            $notices[] = [
                'tur'      => 'warning',
                'metin'    => $bekleyen . ' migration bekliyor. Güncellemeden sonra çalıştırılmazsa bazı özellikler eksik ya da hatalı çalışır.',
                'yol'      => 'panel/sistem#migration',
                'baglanti' => 'Şimdi çalıştır',
            ];
        }

        if (trim((string) Config::get('app.key', '')) === '') {
            $notices[] = [
                'tur'      => 'warning',
                'metin'    => '.env dosyasında APP_KEY boş. Oturumlar kurulum klasörüne bağlı kalır (her dağıtımda herkes çıkış yapar). Üretmek için: php -r "echo bin2hex(random_bytes(32));"',
                'yol'      => '',
                'baglanti' => '',
            ];
        }

        $kayit = Registration::closedReason();

        if ($kayit !== '') {
            $notices[] = [
                'tur'      => 'info',
                'metin'    => $kayit,
                'yol'      => 'panel/ayarlar/eposta',
                'baglanti' => 'E-posta ayarları',
            ];
        }

        return self::$cache = $notices;
    }

    /**
     * Site yerel bir geliştirme adresinden mi açılmış?
     *
     * Karar ALAN ADINA göre verilir (localhost, *.test, *.local…).
     * İstemcinin IP'sine BAKILMAZ: aynı makinedeki bir ters vekilin
     * (nginx → Apache/PHP-FPM) arkasındaki yayın sunucusunda her istek
     * 127.0.0.1'den gelir; IP'ye bakılsaydı uyarı yayında hiç çıkmazdı.
     *
     * Yalnızca bir UYARI'yı göstermek için kullanılır, güvenlik kararı
     * vermez (Host başlığı istemcinin elindedir).
     */
    public static function isLocalRequest(): bool
    {
        $host = strtolower((string) preg_replace('/:\d+\z/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $host = trim($host, '[]');

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        foreach (['.localhost', '.test', '.local'] as $sonek) {
            if (str_ends_with($host, $sonek)) {
                return true;
            }
        }

        return false;
    }

    /** Bekleyen migration sayısı; okunamazsa 0. */
    public static function pendingMigrations(): int
    {
        try {
            return count(Modules::migrator(Database::connection(), (string) Config::get('db.migrations'))->pending());
        } catch (Throwable) {
            return 0;
        }
    }
}
