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
    /** @var array<int,array{id:string,tur:string,baslik:string,metin:string,yol:string,baglanti:string,kapatilabilir:bool}>|null */
    private static ?array $cache = null;

    /**
     * BİLDİRİM BİÇİMİ: kısa başlık + tek cümle açıklama + (varsa)
     * düzeltmenin yapılacağı sayfaya bağlantı. Yalnızca BİLGİ türündekiler
     * kapatılabilir; uyarılar sorun çözülene kadar her sayfada kalır.
     * Kapatılan bildirim tarayıcı oturumu boyunca gizlenir (çerez:
     * cy_uyari_<id>, bkz. layouts/admin.php ve app.js).
     *
     * @return array<int,array{id:string,tur:string,baslik:string,metin:string,yol:string,baglanti:string,kapatilabilir:bool}>
     */
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
            $notices[] = self::notice('demo-hesabi', 'info', 'Demo hesabı',
                'Her şeyi gezip deneyebilirsiniz; hesap bilgileri, kullanıcılar, site ayarları, sistem işlemleri ve e-posta gönderimi bu hesapta kilitli.',
                kapatilabilir: true);
        }

        if (!Auth::can('system.view')) {
            return self::$cache = $notices;
        }

        if (Demo::enabled() && !$demoHesabi) {
            $notices[] = self::notice('demo-modu', 'warning', 'Demo modu açık',
                'Giriş ekranı örnek hesapları parolasıyla listeliyor. Bu bir demo sitesi değilse .env dosyasında APP_DEMO=false yapın.');
        }

        /* Demo modu kapalı ama parolası herkesçe bilinen (Demo1234!)
         * örnek hesaplar duruyor: yerel makine dışında bu bir açıktır —
         * örnek veride bir YÖNETİCİ hesabı da var. */
        if (!Demo::enabled() && !self::isLocalRequest() && Demo::existing(Database::connection()) !== []) {
            $notices[] = self::notice('ornek-hesaplar', 'danger', 'Örnek hesaplar duruyor',
                'Parolaları herkesçe bilinen ("' . Demo::PAROLA . '") hesapları silin: DELETE FROM kullanicilar WHERE eposta LIKE \'%.demo@ornek.com\';',
                'panel/kullanicilar', 'Kullanıcılar');
        }

        if (Config::isDebug() && !self::isLocalRequest()) {
            $notices[] = self::notice('hata-ayiklama', 'danger', 'Hata ayıklama açık',
                'Site yerel bir bilgisayarda çalışmıyor; bir hata olduğunda ziyaretçi dosya yollarını ve SQL sorgularını görür. .env dosyasında APP_ENV=production ve APP_DEBUG=false yapın.',
                'panel/sistem', 'Sistem sayfası');
        }

        $bekleyen = self::pendingMigrations();

        if ($bekleyen > 0) {
            $notices[] = self::notice('migration', 'warning', $bekleyen . ' migration bekliyor',
                'Güncellemeden sonra çalıştırılmazsa bazı özellikler eksik ya da hatalı çalışır.',
                'panel/sistem#migration', 'Şimdi çalıştır');
        }

        if (trim((string) Config::get('app.key', '')) === '') {
            $notices[] = self::notice('app-key', 'warning', 'APP_KEY boş',
                'Oturumlar kurulum klasörüne bağlı kalır (her dağıtımda herkes çıkış yapar). Üretmek için: php -r "echo bin2hex(random_bytes(32));"');
        }

        $kayit = Registration::closedReason();

        if ($kayit !== '') {
            $notices[] = self::notice('kayit-kapali', 'info', 'Üye kaydı kapalı', $kayit,
                'panel/ayarlar/eposta', 'E-posta ayarları', kapatilabilir: true);
        }

        return self::$cache = $notices;
    }

    /** @return array{id:string,tur:string,baslik:string,metin:string,yol:string,baglanti:string,kapatilabilir:bool} */
    private static function notice(
        string $id,
        string $tur,
        string $baslik,
        string $metin,
        string $yol = '',
        string $baglanti = '',
        bool $kapatilabilir = false,
    ): array {
        return compact('id', 'tur', 'baslik', 'metin', 'yol', 'baglanti', 'kapatilabilir');
    }

    /** Kullanıcı bu bildirimi bu tarayıcı oturumunda kapatmış mı? */
    public static function dismissed(array $notice): bool
    {
        return $notice['kapatilabilir'] && ($_COOKIE['cy_uyari_' . $notice['id']] ?? '') === '1';
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
