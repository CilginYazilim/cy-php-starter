<?php
/**
 * =====================================================================
 *  Mailer – E-posta katmanının ön kapısı
 * ---------------------------------------------------------------------
 *  Uygulamanın geri kalanı SADECE bu sınıfı tanır:
 *
 *      Mailer::send($mailable);          → şimdi gönder (ve kaydet)
 *      Mailer::queue($mailable);         → kuyruğa koy (arka planda)
 *      Mailer::verify();                 → ayarları sına
 *      Mailer::enabled();                → posta yapılandırılmış mı?
 *
 *  Hangi sürücünün (SMTP / PHP mail() / kayıt) kullanılacağı panelden
 *  "Site Ayarları → E-posta" sekmesinden seçilir; kod değişmez.
 *
 *  GÖNDERİM ASLA SAYFAYI ÇÖKERTMEZ: send() istisna fırlatmaz, false
 *  döner ve hatayı "mail_kayitlari" tablosuna yazar. Ziyaretçi
 *  iletişim formunu doldurduğunda posta sunucusu kapalı olsa bile
 *  mesajı veritabanına düşer ve kullanıcı teşekkür mesajını görür.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\Config;
use App\Core\Database;
use App\Core\Log\Logger;
use App\Core\Setting;
use App\Models\MailLog;
use App\Repositories\MailRepository;
use Throwable;

final class Mailer
{
    private static ?Transport $transport = null;

    /** Son hatanın metni (arayüzde göstermek için). */
    private static string $lastError = '';

    /** Takılı satırlar en son ne zaman serbest bırakıldı (süreç içinde). */
    private static int $releasedAt = 0;

    /* =================================================================
     *  YAPILANDIRMA
     * ============================================================== */

    /** @return array<string,string> */
    public static function config(): array
    {
        return [
            'surucu'     => Setting::get('mail_surucu', 'kayit'),
            'host'       => Setting::get('mail_host'),
            'port'       => Setting::get('mail_port', '587'),
            'guvenlik'   => Setting::get('mail_guvenlik', 'tls'),
            'kullanici'  => Setting::get('mail_kullanici'),
            'sifre'      => Setting::get('mail_sifre'),
            'gonderen'   => self::senderAddress(),
            'gonderenAd' => Setting::get('mail_gonderen_adi', Setting::get('site_adi', 'Site')),
        ];
    }

    /**
     * Gönderen adresi: önce özel ayar, yoksa iletişim e-postası,
     * o da yoksa alan adından türetilmiş "noreply@..." adresi.
     */
    public static function senderAddress(): string
    {
        $address = Setting::get('mail_gonderen');

        if ($address === '') {
            $address = Setting::get('iletisim_eposta');
        }

        if ($address === '') {
            $host = parse_url(Config::get('app.url', ''), PHP_URL_HOST);
            $host = is_string($host) && $host !== '' ? $host : 'localhost';

            $address = 'noreply@' . preg_replace('/^www\./', '', $host);
        }

        return $address;
    }

    /** Yönetici bildirimlerinin gideceği adres. */
    public static function adminAddress(): string
    {
        $address = Setting::get('iletisim_eposta');

        return $address !== '' ? $address : Setting::get('mail_gonderen');
    }

    /**
     * Posta gönderimi kullanılabilir durumda mı?
     * SMTP seçilmiş ama sunucu adı boşsa "hayır" deriz — böylece
     * yarım yapılandırmayla her seferinde hata üretmeyiz.
     */
    public static function enabled(): bool
    {
        $config = self::config();

        if ($config['surucu'] === 'smtp' && $config['host'] === '') {
            return false;
        }

        return true;
    }

    /**
     * Mektup GERÇEKTEN alıcıya ulaşabilir mi?
     *
     * enabled() "gönderim denenebilir mi" sorusunu cevaplar ve "Kayıt"
     * sürücüsünde de true döner: mektup storage/mail/ klasörüne yazılır,
     * hata oluşmaz. Ama kimseye ulaşmaz. Doğrulama ve parola sıfırlama
     * gibi kullanıcının mektubu ALMASINA dayanan akışlar bunu sormalı;
     * eskiden kayıt formu "bağlantı gönderdik" deyip hesabı sonsuza dek
     * "onay bekliyor"da bırakıyordu.
     */
    public static function canDeliver(): bool
    {
        return self::config()['surucu'] !== 'kayit' && self::enabled();
    }

    public static function transport(): Transport
    {
        if (self::$transport !== null) {
            return self::$transport;
        }

        $config = self::config();

        self::$transport = match ($config['surucu']) {
            'smtp' => new SmtpTransport(
                host:       $config['host'],
                port:       (int) ($config['port'] !== '' ? $config['port'] : 587),
                username:   $config['kullanici'],
                password:   $config['sifre'],
                encryption: in_array($config['guvenlik'], ['ssl', 'tls', 'yok'], true) ? $config['guvenlik'] : 'tls',
                heloName:   self::heloName(),
            ),
            'php'  => new NativeTransport(),
            default => new LogTransport(CY_BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mail'),
        };

        return self::$transport;
    }

    /** Testlerde ya da özel bir sağlayıcıda taşıyıcıyı elle değiştirmek için. */
    public static function useTransport(?Transport $transport): void
    {
        self::$transport = $transport;
    }

    /**
     * Göreli bir yolu MUTLAK adrese çevirir.
     *
     * E-postada "assets/images/logo.png" hiçbir şey ifade etmez;
     * mektup Gmail'de açıldığında tarayıcı o dosyayı gmail.com'da
     * arar. Bu yüzden her adres site köküyle birleştirilir.
     *
     * BAŞTAKİ BÖLÜ ÖNEMLİDİR. "/proje/assets/logo.png" gibi bölüyle
     * başlayan bir yol SUNUCU KÖKÜNE göredir ve alt klasör adını
     * zaten içerir; onu site_url ("http://sunucu/proje") ile
     * birleştirmek klasör adını iki kez yazar:
     *     http://sunucu/proje/proje/assets/logo.png
     * Böyle yollar yalnızca şema + alan adıyla tamamlanır. Bu tam
     * olarak Setting::logoUrl()'ün ürettiği biçimdir; hatanın belirtisi
     * "mektuptaki logo kırık görünüyor" idi.
     */
    public static function absolute(string $path = ''): string
    {
        if ($path !== '' && preg_match('#^(https?:)?//#i', $path) === 1) {
            return $path;
        }

        $base = Setting::get('site_url');

        if ($base === '') {
            $base = (string) Config::get('app.url', '');
        }

        $base = rtrim($base, '/');

        if ($base === '') {
            // Adres hiç bilinmiyorsa göreli yolu olduğu gibi bırakırız;
            // mektup yine gider, yalnızca logo görünmeyebilir.
            return $path;
        }

        if (str_starts_with($path, '/')) {
            $parts = parse_url($base);

            if (!empty($parts['host'])) {
                $base = ($parts['scheme'] ?? 'http') . '://' . $parts['host']
                    . (isset($parts['port']) ? ':' . $parts['port'] : '');
            }
        }

        return $path === '' ? $base . '/' : $base . '/' . ltrim($path, '/');
    }

    private static function heloName(): string
    {
        $host = parse_url(Config::get('app.url', ''), PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        return (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    }

    /* =================================================================
     *  GÖNDERİM
     * ============================================================== */

    /**
     * Mektubu HEMEN gönderir ve sonucu "mail_kayitlari" tablosuna yazar.
     *
     * @param bool $rethrow true verilirse hata yutulmaz, fırlatılır
     *                      (ayarlar sayfasındaki sınama bunu kullanır).
     */
    public static function send(Mailable $mail, bool $rethrow = false): bool
    {
        self::$lastError = '';

        self::applyDefaults($mail);

        /* Kayıt doğrudan "gonderiliyor" olarak açılır. Eskiden
         * "kuyrukta" yazılıyordu: gönderim sürerken çalışan bir kuyruk
         * işçisi aynı satırı alıp mektubu İKİNCİ KEZ gönderebiliyordu. */
        $logId = self::log()?->record($mail, MailLog::DURUM_GONDERILIYOR);

        try {
            if (!self::enabled()) {
                throw new MailException('E-posta ayarları eksik: SMTP sunucu adresi girilmemiş.');
            }

            self::transport()->send($mail);
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();

            if ($logId !== null) {
                self::markQuietly(fn (MailRepository $r) => $r->markFailed($logId, $e->getMessage()));
            }

            // Veritabanı kaydı panelde görünür; dosya log'u ise
            // veritabanına hiç ulaşılamadığı durumda tek izdir.
            Logger::error('Mektup gönderilemedi: ' . $e->getMessage(), [
                'alici'  => $mail->recipients()[0][0] ?? '',
                'konu'   => $mail->getSubject(),
                'surucu' => self::config()['surucu'],
            ], 'mail');

            if ($rethrow) {
                throw $e instanceof MailException ? $e : new MailException($e->getMessage(), 0, $e);
            }

            return false;
        }

        /* GÖNDERİM BAŞARILI. Durumu yazarken bir veritabanı hatası olsa
         * bile mektup "başarısız" sayılmaz: ulaştığı kesindir. Eskiden
         * markSent() aynı try içindeydi; bir kilitlenme (deadlock)
         * gönderilmiş mektubu "başarısız" gösteriyor, yönetici yeniden
         * kuyruğa alınca alıcıya ikinci kopya gidiyordu. */
        if ($logId !== null) {
            self::markQuietly(fn (MailRepository $r) => $r->markSent($logId));
        }

        return true;
    }

    /**
     * Takılı satırları serbest bırakır — süreç içinde en fazla dakikada
     * bir (uzun çalışan "php cy mail:work" da arada bir temizlesin diye).
     */
    private static function releaseStuckOnce(MailRepository $repository): void
    {
        if (self::$releasedAt > time() - 60) {
            return;
        }

        self::$releasedAt = time();

        try {
            $adet = $repository->releaseStuck();

            if ($adet > 0) {
                Logger::warning('Yarıda kalan mektuplar yeniden kuyruğa alındı', ['adet' => $adet], 'mail');
            }
        } catch (Throwable $e) {
            Logger::error('Takılı mektuplar serbest bırakılamadı: ' . $e->getMessage(), [], 'mail');
        }
    }

    /**
     * Kayıt durumunu yazar; kilitlenmede yeniden dener, yine olmazsa
     * yalnızca log'a yazar (gönderimin sonucunu DEĞİŞTİRMEZ).
     *
     * @param callable(MailRepository):void $write
     */
    private static function markQuietly(callable $write): void
    {
        $repository = self::log();

        if ($repository === null) {
            return;
        }

        try {
            Database::retry(static fn () => $write($repository));
        } catch (Throwable $e) {
            Logger::critical('Mektup kaydının durumu yazılamadı: ' . $e->getMessage(), [], 'mail');
        }
    }

    /**
     * Mektubu KUYRUĞA koyar; gönderim panelden ya da zamanlanmış
     * görevden (cron) parti parti yapılır. Toplu duyurularda
     * kullanın: 500 kişiye tek istekte mektup göndermek isteği
     * zaman aşımına uğratır.
     *
     * @return int Kuyruk kaydının numarası
     */
    public static function queue(Mailable $mail, string $batchId = ''): int
    {
        self::applyDefaults($mail);

        $repository = self::log();

        if ($repository === null) {
            throw new MailException('Veritabanına ulaşılamadığı için mektup kuyruğa alınamadı.');
        }

        return $repository->record($mail, 'kuyrukta', $batchId);
    }

    /**
     * Kuyruktaki mektuplardan en fazla $limit tanesini gönderir.
     *
     * @return array{gonderildi:int,basarisiz:int,kalan:int}
     */
    public static function processQueue(int $limit = 10): array
    {
        $repository = self::log();

        if ($repository === null) {
            return ['gonderildi' => 0, 'basarisiz' => 0, 'kalan' => 0];
        }

        $sent            = 0;
        $failed          = 0;
        self::$lastError = '';

        /* Takılı kalmış satırları süreç başına BİR KEZ serbest bırakırız.
         * Eskiden her ayırmada çalışan aralık UPDATE'i, aynı anda çalışan
         * işçilerin ayırma sorgularıyla çakışıp kilitlenme üretiyordu. */
        self::releaseStuckOnce($repository);

        /* Satırlar TEK TEK, gönderimden hemen önce ayrılır (claimNext).
         * İşçi çökerse yalnızca o an elindeki satır takılır. */
        while ($sent + $failed < max(1, $limit)) {
            $row = $repository->claimNext();

            if ($row === null) {
                break;
            }

            $id   = (int) $row['id'];
            $mail = $repository->toMailable($row);

            self::applyDefaults($mail);

            try {
                if (!self::enabled()) {
                    throw new MailException('E-posta ayarları eksik.');
                }

                self::transport()->send($mail);
            } catch (Throwable $e) {
                // Son hatayı saklıyoruz: arayüz "3 mektup başarısız"
                // demekle yetinmesin, SEBEBİNİ de gösterebilsin.
                self::$lastError = $e->getMessage();

                self::markQuietly(fn (MailRepository $r) => $r->markFailed($id, $e->getMessage()));
                $failed++;

                continue;
            }

            // Gönderildi; durum yazılamasa bile "başarısız" sayılmaz.
            self::markQuietly(fn (MailRepository $r) => $r->markSent($id));
            $sent++;
        }

        return [
            'gonderildi' => $sent,
            'basarisiz'  => $failed,
            'kalan'      => $repository->countPending(),
        ];
    }

    /**
     * Ayarları sınar. Başarısızsa MailException fırlatır.
     */
    public static function verify(): void
    {
        self::$transport = null; // ayarlar yeni kaydedilmiş olabilir

        self::transport()->verify();
    }

    public static function lastError(): string
    {
        return self::$lastError;
    }

    /* =================================================================
     *  İÇ YARDIMCILAR
     * ============================================================== */

    /** Gönderen ve izleme başlıkları verilmediyse tamamlar. */
    private static function applyDefaults(Mailable $mail): void
    {
        if ($mail->sender() === null) {
            $config = self::config();
            $mail->from($config['gonderen'], $config['gonderenAd']);
        }

        // Otomatik yanıt döngülerini (tatil mesajı ↔ bildirim) keser.
        if (!array_key_exists('Auto-Submitted', $mail->extraHeaders())) {
            $mail->header('Auto-Submitted', 'auto-generated');
        }
    }

    private static function log(): ?MailRepository
    {
        try {
            return new MailRepository(Database::connection());
        } catch (Throwable) {
            // Kurulum tamamlanmamış olabilir; kayıt tutamamak
            // gönderimi engellememelidir.
            return null;
        }
    }
}
