<?php
/**
 * =====================================================================
 *  Demo – Herkese açık deneme kurulumları için demo modu
 * ---------------------------------------------------------------------
 *  .env içinde APP_DEMO=true iken:
 *
 *    1) Giriş ekranı örnek hesapları (Yönetici, Editör, Üye) parolasıyla
 *       birlikte listeler; bir satıra tıklamak doğrudan giriş yapar.
 *    2) O hesaplarla giriş yapan ziyaretçi, demoyu herkes için bozacak
 *       işleri YAPAMAZ: kendi hesabını ve parolasını değiştirmek,
 *       kullanıcı eklemek/düzenlemek/silmek, site ayarları, sistem
 *       işlemleri ve e-posta gönderimi kilitlidir.
 *
 *  NEDEN .env, NEDEN PANEL AYARI DEĞİL? Demo yöneticisi her yetkiye
 *  sahiptir (bkz. Role::can). Anahtar panelde olsaydı ilk ziyaretçi
 *  demo modunu kapatıp kilitleri kaldırabilirdi.
 *
 *  KİLİT YALNIZCA ÖRNEK HESAPLARA UYGULANIR. Kurulumda kendi
 *  parolanızla açtığınız yönetici hesabı kısıtlanmaz; demoyu o hesapla
 *  yönetirsiniz.
 *
 *  İçerik işleri (sayfa yazmak, mesajları okumak/silmek, Ornek
 *  modülüne kayıt eklemek) bilerek AÇIK kalır: demonun amacı bunları
 *  denetmektir. Demo modunda zamanlayıcı her 3 saatte bir "php cy
 *  demo:reset" çalıştırır ve örnek veriyi baştan kurar.
 *
 *  Örnek hesaplar ve diğer demo verisi App\Core\DemoData'dadır (TEK
 *  KAYNAK): sihirbaz, "php cy db:seed" ve demo:reset aynı veriyi üretir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use PDO;
use Throwable;

final class Demo
{
    /** Canlı demonun sıfırlanma aralığı (dakika) — zamanlayıcı ve panel bildirimi aynı değeri kullanır. */
    public const SIFIRLAMA_DAKIKA = 180;

    /** Bütün örnek hesapların ortak parolası. */
    public const PAROLA = 'Demo1234!';

    /**
     * Örnek hesaplar. Anahtar kullanıcı adıdır; DemoData bu listeden
     * kurar, giriş ekranı bu listeden gösterir — ikisi ayrışamaz.
     *
     * "herkese" → demo modunda giriş ekranında listelenir. Pasif, askıda
     * ve onay bekleyen hesaplar bilerek giriş yapamaz; yalnızca
     * geliştirme ortamında, durum kontrolünü denemek için gösterilir.
     * "gun" → kayıt tarihi (bugünden geriye), kontrol panelindeki 14
     * günlük grafik dolu görünsün diye.
     *
     * @var array<string,array{ad:string,soyad:string,eposta:string,rol:string,durum:string,etiket:string,variant:string,icon:string,herkese:bool,telefon:string,hakkinda:string,gun:int}>
     */
    public const HESAPLAR = [
        'ali.yonetici' => ['ad' => 'Ali', 'soyad' => 'Yılmaz', 'eposta' => 'ali.demo@ornek.com', 'rol' => 'admin', 'durum' => 'aktif',
            'etiket' => 'Yönetici', 'variant' => 'admin', 'icon' => 'shield', 'herkese' => true, 'gun' => 13,
            'telefon' => '+90 555 000 00 01', 'hakkinda' => 'Demo yönetici hesabı: her ekranı gezebilir; parola, kullanıcı ve ayar işlemleri demo modunda kilitli.'],
        'elif.editor'  => ['ad' => 'Elif', 'soyad' => 'Demir', 'eposta' => 'elif.demo@ornek.com', 'rol' => 'editor', 'durum' => 'aktif',
            'etiket' => 'Editör', 'variant' => 'editor', 'icon' => 'edit', 'herkese' => true, 'gun' => 11,
            'telefon' => '+90 555 000 00 02', 'hakkinda' => 'İçerik editörü: sayfaları yazar, mesajları yanıtlar, Örnek Modül\'de onay bekleyen kayıtları yayınlar.'],
        'mehmet.uye'   => ['ad' => 'Mehmet', 'soyad' => 'Kaya', 'eposta' => 'mehmet.demo@ornek.com', 'rol' => 'uye', 'durum' => 'aktif',
            'etiket' => 'Üye', 'variant' => 'member', 'icon' => 'user', 'herkese' => true, 'gun' => 8,
            'telefon' => '', 'hakkinda' => ''],
        'ayse.pasif'   => ['ad' => 'Ayşe', 'soyad' => 'Şahin', 'eposta' => 'ayse.demo@ornek.com', 'rol' => 'uye', 'durum' => 'pasif',
            'etiket' => 'Pasif Üye', 'variant' => 'passive', 'icon' => 'user', 'herkese' => false, 'gun' => 6,
            'telefon' => '', 'hakkinda' => ''],
        'can.askida'   => ['ad' => 'Can', 'soyad' => 'Yıldız', 'eposta' => 'can.demo@ornek.com', 'rol' => 'uye', 'durum' => 'askida',
            'etiket' => 'Askıda Üye', 'variant' => 'hold', 'icon' => 'user', 'herkese' => false, 'gun' => 4,
            'telefon' => '', 'hakkinda' => ''],
        'zeynep.onay'  => ['ad' => 'Zeynep', 'soyad' => 'Arslan', 'eposta' => 'zeynep.demo@ornek.com', 'rol' => 'uye', 'durum' => 'onay_bekliyor',
            'etiket' => 'Onay Bekleyen Üye', 'variant' => 'hold', 'icon' => 'mail', 'herkese' => false, 'gun' => 1,
            'telefon' => '', 'hakkinda' => ''],
    ];

    /**
     * Örnek hesaplara kapalı YAZMA uçları. Sonu "/" ile bitenler önek,
     * diğerleri tam yoldur.
     *
     * @var array<string,string> yol => kullanıcıya gösterilecek gerekçe
     */
    private const KILITLI = [
        'panel/hesabim/'          => 'Demo hesabının bilgileri, parolası, görseli ve API anahtarları değiştirilemez.',
        'panel/ayarlar/'          => 'Demo modunda site ayarları değiştirilemez.',
        'panel/sistem/'           => 'Demo modunda sistem işlemleri (modül açma/kapama, kuyruk, migration) kapalıdır.',
        'api/kullanicilar/save'   => 'Demo modunda kullanıcı eklenemez ve düzenlenemez.',
        'api/kullanicilar/delete' => 'Demo modunda kullanıcı silinemez.',
        'api/kullanicilar/status' => 'Demo modunda kullanıcı durumu değiştirilemez.',
        'api/eposta/gonder'       => 'Demo modunda e-posta gönderilemez.',
        'api/eposta/isle'         => 'Demo modunda e-posta gönderilemez.',
        'api/eposta/tekrar'       => 'Demo modunda e-posta gönderilemez.',
        'api/eposta/sinama'       => 'Demo modunda e-posta gönderilemez.',
        'api/eposta/baglanti'     => 'Demo modunda e-posta ayarları sınanamaz.',
        'api/eposta/sil'          => 'Demo modunda e-posta kayıtları silinemez.',
        'api/eposta/temizle'      => 'Demo modunda e-posta kayıtları silinemez.',
    ];

    public static function enabled(): bool
    {
        return (bool) Config::get('app.demo', false);
    }

    public static function isDemoUser(?User $user): bool
    {
        return $user !== null && isset(self::HESAPLAR[$user->kullaniciAdi]);
    }

    /**
     * Bu istek örnek hesaba kapalı mı? Kapalıysa gerekçeyi döndürür.
     * Okuma (GET/HEAD) hiçbir zaman kilitlenmez.
     */
    public static function blockReason(string $method, string $path): ?string
    {
        if (in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
            return null;
        }

        $path = trim($path, '/');

        foreach (self::KILITLI as $yol => $gerekce) {
            $eslesti = str_ends_with($yol, '/')
                ? str_starts_with($path . '/', $yol)
                : $path === $yol;

            if ($eslesti) {
                return $gerekce;
            }
        }

        return null;
    }

    /**
     * Giriş yapan örnek hesap bu adrese form gönderemiyorsa gerekçesi.
     * Görünümler düğmeyi baştan kilitli çizmek için kullanır; asıl
     * engel yine guard() içindedir.
     */
    public static function lockReason(string $path): ?string
    {
        if (!self::enabled() || !self::isDemoUser(Auth::user())) {
            return null;
        }

        return self::blockReason('POST', $path);
    }

    /**
     * "auth" ara katmanından çağrılır. Demo modu kapalıysa ya da giriş
     * yapan örnek hesap değilse hiçbir şey yapmaz.
     */
    public static function guard(Request $request): void
    {
        if (!self::enabled() || !self::isDemoUser(Auth::user())) {
            return;
        }

        $path   = Url::current();
        $reason = self::blockReason($request->method(), $path);

        if ($reason === null) {
            return;
        }

        // AJAX: app.js "description" alanını bildirim olarak gösterir.
        if ($request->isAjax()) {
            throw Exceptions\HttpException::forbidden($reason, context: ['demo' => Auth::user()?->kullaniciAdi]);
        }

        Flash::error($reason);
        Response::redirect(url(self::backPath($path)));
    }

    /** Kilitlenen formdan sonra dönülecek sayfa: "panel/ayarlar/genel" → "panel/ayarlar". */
    public static function backPath(string $path): string
    {
        $parcalar = explode('/', trim($path, '/'));

        return ($parcalar[0] ?? '') === 'panel' && isset($parcalar[1])
            ? 'panel/' . $parcalar[1]
            : 'panel';
    }

    /**
     * Giriş ekranında gösterilecek hesaplar: YALNIZCA demo modunda
     * (APP_DEMO=true) Yönetici, Editör ve Üye.
     *
     * Eskiden geliştirme ortamında da (APP_DEBUG=true) altı hesabın
     * hepsi parolasıyla listeleniyordu. Kurulum sihirbazının "geliştirme
     * modu" kutusu açık kalan bir site yayına alınınca giriş ekranı
     * "Demo1234!" öneriyordu — örnek veride bir YÖNETİCİ hesabı da var.
     * Geliştirmede artık yalnızca parolasız bir not çıkar
     * (bkz. sampleDataNote). Hesap ancak veritabanında HESAPLAR'daki
     * e-postasıyla GERÇEKTEN varsa listelenir.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function loginAccounts(PDO $db): array
    {
        if (!self::enabled()) {
            return [];
        }

        return self::visibleAccounts(true, self::existing($db));
    }

    /**
     * Geliştirme ortamında örnek veri yüklü mü? Giriş ekranı parolaları
     * DEĞİL, "demo hesaplar README'de" notunu gösterir.
     */
    public static function sampleDataNote(PDO $db): bool
    {
        /* Ad ve örnek E-POSTASI birlikte eşleşmeli: örnek veri
         * kaldırıldıktan sonra adresini değiştirip kalan bir hesap
         * (artık gerçek hesap) notu açık tutmasın. */
        return !self::enabled()
            && Config::isDebug()
            && !Config::isProduction()
            && self::visibleAccounts(false, self::existing($db)) !== [];
    }

    /**
     * @param array<string,string> $mevcut kullanıcı adı => e-posta (veritabanındaki)
     * @return array<int,array<string,mixed>>
     */
    public static function visibleAccounts(bool $demo, array $mevcut): array
    {
        $liste = [];

        foreach (self::HESAPLAR as $kadi => $hesap) {
            if ($demo && !$hesap['herkese']) {
                continue;
            }
            if (($mevcut[$kadi] ?? null) !== $hesap['eposta']) {
                continue;
            }

            $liste[] = ['kullanici_adi' => $kadi, 'parola' => self::PAROLA] + $hesap;
        }

        return $liste;
    }

    /**
     * Veritabanında duran örnek hesaplar (kullanıcı adı => e-posta).
     * Panel uyarısı da bunu kullanır: demo modu kapalı bir sitede
     * parolası herkesçe bilinen hesap kalmamalı.
     *
     * @return array<string,string>
     */
    public static function existing(PDO $db): array
    {
        $adlar = array_keys(self::HESAPLAR);

        try {
            $stmt = $db->prepare(
                'SELECT kullanici_adi, eposta FROM kullanicilar WHERE kullanici_adi IN ('
                . implode(', ', array_fill(0, count($adlar), '?')) . ')'
            );
            $stmt->execute($adlar);

            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable) {
            return [];
        }
    }
}
