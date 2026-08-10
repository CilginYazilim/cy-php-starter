<?php
/**
 * =====================================================================
 *  HomeController – Ana sayfa (ön yüz)
 * ---------------------------------------------------------------------
 *  ÖN YÜZ TAMAMEN PANELDEN BESLENİR. Sayfadaki hiçbir metin koda
 *  gömülü değildir: site adı, slogan, iletişim bilgileri ve sosyal
 *  bağlantılar "ayarlar" tablosundan, tanıtım metni ise "sayfalar"
 *  tablosundaki Hakkımızda sayfasından gelir.
 *
 *  Sebep: bu bir ŞABLON. Yeni bir projede ana sayfayı düzenlemek için
 *  PHP dosyası açmak zorunda kalmamalısınız — panelden yazın, yeter.
 *  Bir ayar boşsa o bölüm hiç basılmaz, yarım görünmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Request;
use App\Core\Setting;
use App\Http\Controller;
use App\Repositories\PageRepository;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('site/home', [
            'title'      => '',
            'ozellikler' => self::features(),
            'iletisim'   => self::contactCards(),
            'istatistik' => $this->highlights(),
            'hakkinda'   => (new PageRepository($this->db))->findPublished('hakkimizda'),
        ], 'layouts/site');
    }

    /* =================================================================
     *  İÇERİK PARÇALARI
     * -----------------------------------------------------------------
     *  Statik: aynı kartları Hakkımızda ve İletişim sayfaları da
     *  kullanıyor. Üç denetleyicide üç kopya tutmaktansa tek kaynak.
     * ============================================================== */

    /**
     * Öne çıkan özellikler.
     *
     * Şablonun kendi tanıtımıdır; kendi projenizde bu diziyi kendi
     * hizmetlerinizle değiştirin ya da bir modülden besleyin.
     *
     * @return array<int,array{ikon:string,renk:string,baslik:string,metin:string}>
     */
    public static function features(): array
    {
        return [
            [
                'ikon'   => 'shield',
                'renk'   => 'brand',
                'baslik' => 'Güvenlik',
                'metin'  => 'CSRF, XSS, SQL enjeksiyonu ve kaba kuvvet korumaları kutudan çıktığı gibi aktif.',
            ],
            [
                'ikon'   => 'users',
                'renk'   => 'success',
                'baslik' => 'Rol ve Yetki',
                'metin'  => 'Yönetici, editör ve üye rolleri; yetkiler tek dosyadan yönetilir.',
            ],
            [
                'ikon'   => 'send',
                'renk'   => 'warning',
                'baslik' => 'E-posta',
                'metin'  => 'SMTP, kuyruk ve toplu gönderim hazır; giden her mektup kayıt altında.',
            ],
            [
                'ikon'   => 'edit',
                'renk'   => 'brand',
                'baslik' => 'İçerik Yönetimi',
                'metin'  => 'Sayfalarınızı zengin metin editörüyle yazın; adres ve SEO alanları otomatik.',
            ],
            [
                'ikon'   => 'activity',
                'renk'   => 'success',
                'baslik' => 'İzlenebilir',
                'metin'  => 'Kanal bazlı günlükler, sistem sağlığı ve güvenlik denetimi tek ekranda.',
            ],
            [
                'ikon'   => 'globe',
                'renk'   => 'warning',
                'baslik' => 'SEO ve PWA',
                'metin'  => 'Temiz adresler, site haritası ve telefona kurulabilir uygulama modu.',
            ],
        ];
    }

    /**
     * İletişim kartları — YALNIZCA dolu olanlar döner.
     *
     * Boş bir ayarı "—" ile göstermek, sitenin yarım kaldığı izlenimi
     * verir. Tanımlı değilse hiç basmıyoruz.
     *
     * @return array<int,array{ikon:string,etiket:string,deger:string,link:string}>
     */
    public static function contactCards(): array
    {
        $kartlar = [];

        $eposta = Setting::get('iletisim_eposta');

        if ($eposta !== '') {
            $kartlar[] = [
                'ikon'   => 'mail',
                'etiket' => 'E-posta',
                'deger'  => $eposta,
                'link'   => 'mailto:' . $eposta,
            ];
        }

        $telefon = Setting::get('iletisim_telefon');

        if ($telefon !== '') {
            $kartlar[] = [
                'ikon'   => 'phone',
                'etiket' => 'Telefon',
                'deger'  => $telefon,
                // tel: bağlantısında boşluk ve parantez sorun çıkarır.
                'link'   => 'tel:' . self::digits($telefon),
            ];
        }

        if (self::whatsappNumber() !== '') {
            $kartlar[] = [
                'ikon'   => 'whatsapp',
                'etiket' => 'WhatsApp',
                'deger'  => Setting::get('iletisim_whatsapp'),
                'link'   => self::whatsappLink(),
            ];
        }

        $saatler = Setting::get('iletisim_saatler');

        if ($saatler !== '') {
            $kartlar[] = [
                'ikon'   => 'clock',
                'etiket' => 'Çalışma Saatleri',
                'deger'  => $saatler,
                'link'   => '',
            ];
        }

        $adres = Setting::get('iletisim_adres');

        if ($adres !== '') {
            $kartlar[] = [
                'ikon'   => 'map',
                'etiket' => 'Adres',
                'deger'  => $adres,
                'link'   => Setting::get('iletisim_harita'),
            ];
        }

        return $kartlar;
    }

    /**
     * WhatsApp numarasının yalnızca rakamları.
     *
     * wa.me adresi "+", boşluk ve parantez KABUL ETMEZ; ülke koduyla
     * bitişik rakam ister (905415090583). Yönetici numarayı okunur
     * biçimde yazsın diye dönüşümü burada yapıyoruz.
     */
    public static function whatsappNumber(): string
    {
        return self::digits(Setting::get('iletisim_whatsapp'));
    }

    /** Hazır mesajı da taşıyan tam WhatsApp sohbet adresi. */
    public static function whatsappLink(): string
    {
        $numara = self::whatsappNumber();

        if ($numara === '') {
            return '';
        }

        $mesaj = trim(Setting::get('iletisim_whatsapp_mesaj'));

        return 'https://wa.me/' . $numara
            . ($mesaj !== '' ? '?text=' . rawurlencode($mesaj) : '');
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Ana sayfadaki rakamlar.
     *
     * @return array<int,array{deger:string,etiket:string}>
     */
    private function highlights(): array
    {
        return [
            ['deger' => '0',    'etiket' => 'Bağımlılık'],
            ['deger' => '20',   'etiket' => 'Konsol komutu'],
            ['deger' => '8.1+', 'etiket' => 'PHP sürümü'],
            ['deger' => 'MIT',  'etiket' => 'Lisans'],
        ];
    }
}
