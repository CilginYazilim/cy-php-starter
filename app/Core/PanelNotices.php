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
     * Kapatılan bildirim OTURUM boyunca gizlenir: kimlik sunucu tarafında
     * oturuma yazılır (bkz. dismiss), sayfa yüklenirken hiç basılmaz.
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
                'Her şeyi gezip deneyebilirsiniz; hesap bilgileri, kullanıcılar, site ayarları, sistem işlemleri ve e-posta gönderimi bu hesapta kilitli.'
                . self::nextResetText(),
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
                'Parolaları herkesçe bilinen ("' . Demo::PAROLA . '") hesaplar var. Örnek veriyi kaldırın; gerçek hesaplara dokunulmaz.',
                'panel/sistem#kurulum', 'Örnek veriyi kaldır');
        }

        /* Bakım modu unutulursa site günlerce kapalı kalır; panele giren
         * yönetici ve editör siteyi normal gördüğü için fark etmez. */
        if (Setting::bool('sistem_bakim_modu', false)) {
            $notices[] = self::notice('bakim-modu', 'warning', 'Bakım modu açık',
                'Ziyaretçiler bakım sayfasını görüyor; siteyi yalnızca yöneticiler ve editörler gezebiliyor.',
                $demoHesabi ? '' : 'panel/ayarlar/sistem', $demoHesabi ? '' : 'Bakım modunu kapat');
        }

        if (Config::isDebug() && !self::isLocalRequest()) {
            $notices[] = self::notice('hata-ayiklama', 'danger', 'Hata ayıklama açık',
                'Site yerel bir bilgisayarda çalışmıyor; bir hata olduğunda ziyaretçi dosya yollarını ve SQL sorgularını görür. .env dosyasında APP_ENV=production ve APP_DEBUG=false yapın.',
                'panel/sistem', 'Sistem sayfası');
        }

        $bekleyen = self::pendingMigrations();

        /* Demo hesabı bu işlemleri yapamaz (kilitli): ona "Şimdi
         * çalıştır" bağlantısı göstermek yalnızca hata mesajına götürürdü. */
        $kilitli = $demoHesabi;

        if ($bekleyen > 0) {
            $notices[] = self::notice('migration', 'warning', $bekleyen . ' migration bekliyor',
                'Güncellemeden sonra çalıştırılmazsa bazı özellikler eksik ya da hatalı çalışır.',
                $kilitli ? '' : 'panel/sistem#migration', $kilitli ? '' : 'Şimdi çalıştır');
        }

        /* Kurulum kilitli olsa da klasörün sunucuda durması gereksiz bir
         * risktir; eskiden yalnızca Sistem sayfasındaki denetimde görünüyordu. */
        if (is_dir(CY_BASE . DIRECTORY_SEPARATOR . 'kurulum')) {
            $notices[] = self::notice('kurulum-klasoru', 'warning', 'Kurulum klasörü duruyor',
                'Kurulum tamamlandı ama kurulum/ klasörü hâlâ sunucuda. Sihirbaz kilitli olsa da klasörü silmek en güvenlisidir.',
                $kilitli ? '' : 'panel/sistem#kurulum', $kilitli ? '' : 'Şimdi sil');
        }

        if (trim((string) Config::get('app.key', '')) === '') {
            $notices[] = self::notice('app-key', 'warning', 'APP_KEY boş',
                'Oturumlar kurulum klasörüne bağlı kalır (her dağıtımda herkes çıkış yapar). Üretmek için: php -r "echo bin2hex(random_bytes(32));"');
        }

        /* İletişim formu ve üye bildirimleri bu adrese gider. Sihirbaz
         * yöneticinin adresini yazar; SQL dosyasıyla kurulan sitede boştur
         * ve mesajlar sessizce kaybolurdu. */
        if (trim(Setting::get('iletisim_eposta')) === '') {
            $gonderen = trim(Setting::get('mail_gonderen'));
            $notices[] = self::notice('iletisim-eposta', 'info', 'İletişim e-postası tanımlı değil',
                ($gonderen !== ''
                    ? 'Form mesajlarının ve üye bildirimlerinin bildirimleri şimdilik gönderen adresine (' . $gonderen . ') gidiyor. '
                    : 'Form mesajlarının ve üye bildirimlerinin bildirimleri hiçbir yere gitmiyor. ')
                . 'Okuduğunuz bir adresi Ayarlar → İletişim\'den girin.',
                $demoHesabi ? '' : 'panel/ayarlar/iletisim', $demoHesabi ? '' : 'İletişim ayarları');
        }

        $kayit = Registration::closedReason();

        if ($kayit !== '') {
            $notices[] = self::notice('kayit-kapali', 'info', Registration::developmentPreview() ? 'Doğrulama mektupları gönderilmiyor' : 'Üye kaydı kapalı', $kayit,
                'panel/ayarlar/eposta', 'E-posta ayarları', kapatilabilir: true);
        }

        /* Sayaçlar bilgi amaçlıdır: tablo yoksa (migration bekliyor) ya da
         * sorgu hata verirse panel açılmaya devam eder. İkisi de indeksli
         * sütuna bakar (kullanicilar.durum, mail_kayitlari.durum). */
        try {
            $db = Database::connection();

            /* Örnek verideki onay bekleyen hesap (zeynep.onay) ve başarısız
             * mektup sayılmaz: taze kurulum ilk açılışta "bir şey bozuk"
             * gibi görünüyordu. */
            $bekleyenHesap = (new \App\Repositories\UserRepository($db))->countByStatus('onay_bekliyor', ornekHaric: true);

            if ($bekleyenHesap > 0) {
                $notices[] = self::notice('onay-bekleyen', 'info', $bekleyenHesap . ' hesap e-posta doğrulaması bekliyor',
                    'Doğrulama bağlantısına tıklamayan hesaplar giriş yapamaz. Gerekirse kullanıcı ekranından durumlarını değiştirebilirsiniz.',
                    'panel/kullanicilar', 'Kullanıcılar', kapatilabilir: true);
            }

            $basarisiz = (new \App\Repositories\MailRepository($db))->countFailed(ornekHaric: true);

            if ($basarisiz > 0) {
                $notices[] = self::notice('eposta-basarisiz', 'info', $basarisiz . ' e-posta gönderilemedi',
                    'Geçmiş sekmesinde hatanın nedenini görüp mektubu yeniden kuyruğa alabilirsiniz.',
                    'panel/eposta?sekme=gecmis&durum=basarisiz', 'E-posta geçmişi', kapatilabilir: true);
            }
        } catch (Throwable) {
            // Bildirim yoksa panel yine açılır.
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

    /**
     * Bildirim bağlantısının adresi. "yol" sorgu (?sekme=gecmis) ve çapa
     * (#kurulum) taşıyabilir; url()'e bütün olarak verilirse "güzel adres"
     * kapalıyken (index.php?r=…) ikisi de r parametresinin içine
     * kodlanıp bozuluyordu.
     */
    public static function href(array $notice): string
    {
        [$yol, $capa]  = array_pad(explode('#', (string) $notice['yol'], 2), 2, '');
        [$yol, $sorgu] = array_pad(explode('?', $yol, 2), 2, '');
        parse_str($sorgu, $parametreler);

        /** @var array<string,string|int> $parametreler */
        return url($yol, $parametreler) . ($capa !== '' ? '#' . rawurlencode($capa) : '');
    }

    /** Kullanıcı bu bildirimi bu oturumda kapatmış mı? */
    public static function dismissed(array $notice): bool
    {
        $kapali = Session::get('_kapali_bildirimler', []);

        return $notice['kapatilabilir'] && is_array($kapali) && isset($kapali[$notice['id']]);
    }

    /**
     * Bildirimi bu oturum için kapatır. Yalnızca KAPATILABİLİR (bilgi)
     * bildirimleri: uyarı kimliği gönderilse bile yok sayılır.
     */
    public static function dismiss(string $id): bool
    {
        foreach (self::forCurrentUser() as $notice) {
            if ($notice['id'] === $id && $notice['kapatilabilir']) {
                $kapali       = Session::get('_kapali_bildirimler', []);
                $kapali       = is_array($kapali) ? $kapali : [];
                $kapali[$id]  = true;
                Session::set('_kapali_bildirimler', $kapali);

                return true;
            }
        }

        return false;
    }

    /** " Bu demo her 3 saatte bir sıfırlanır · sonraki: 14:00" ya da boş. */
    private static function nextResetText(): string
    {
        $son = Setting::get('demo_son_sifirlama');
        $zaman = $son !== '' ? strtotime($son) : false;

        if ($zaman === false) {
            return '';
        }

        $sonraki = $zaman + Demo::SIFIRLAMA_DAKIKA * 60;

        // Zamanlayıcı biraz gecikebilir; geçmiş bir saat gösterme.
        while ($sonraki <= time()) {
            $sonraki += Demo::SIFIRLAMA_DAKIKA * 60;
        }

        return sprintf(' Bu demo her %d saatte bir sıfırlanır · sonraki: %s.', intdiv(Demo::SIFIRLAMA_DAKIKA, 60), date('H:i', $sonraki));
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
