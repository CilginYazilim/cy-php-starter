<?php
/**
 * =====================================================================
 *  HomeController – Ana sayfa (ön yüz)
 * ---------------------------------------------------------------------
 *  ÖN YÜZ TAMAMEN PANELDEN BESLENİR. Sayfadaki metinler koda gömülü
 *  değildir: başlık, düğmeler, özellik kartları, adımlar ve sık sorulan
 *  sorular Panel → Ayarlar → Ana Sayfa'dan; tanıtım metni Hakkımızda
 *  sayfasından; iletişim bilgileri İletişim ayarlarından gelir.
 *
 *  Hangi bölümlerin görüneceği de ayardır (anasayfa_bolumler). İçeriği
 *  boş bir bölüm hiç basılmaz: yarım dolu bir sayfa, boş bir sayfadan
 *  daha kötü görünür. Nötr kurulumda karşılama, hakkımızda ve iletişim
 *  görünür; örnek veriyle kurulumda CY PHP Starter vitrini açılır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Config;
use App\Core\Demo;
use App\Core\Request;
use App\Core\Setting;
use App\Core\Url;
use App\Http\Controller;
use App\Models\Role;
use App\Repositories\PageRepository;
use App\Support\Surum16;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $bolumler = self::sections();

        $this->view('site/home', [
            'title'      => '',
            'bolumler'   => $bolumler,
            'hero'       => self::hero(),
            'ozellikler' => self::listSetting('anasayfa_ozellikler'),
            'adimlar'    => self::listSetting('anasayfa_adimlar'),
            'sss'        => self::listSetting('anasayfa_sss'),
            'roller'     => in_array('roller', $bolumler, true) ? self::roleCards() : [],
            'cta'        => [
                'baslik'   => Setting::get('anasayfa_cta_baslik'),
                'metin'    => Setting::get('anasayfa_cta_metin'),
                'dugmeler' => array_map(
                    static fn (array $d): array => ['metin' => (string) ($d['metin'] ?? ''), 'adres' => self::link((string) ($d['adres'] ?? ''))],
                    self::listSetting('anasayfa_cta_dugmeler')
                ),
            ],
            'iletisim'   => self::contactCards(),
            'hakkinda'   => in_array('hakkimizda', $bolumler, true)
                ? (new PageRepository($this->db))->findPublished('hakkimizda')
                : null,
            'jsonLd'     => self::structuredData(),
            'scripts'    => ['home.js'],
        ], 'layouts/site');
    }

    /* =================================================================
     *  AYARLARDAN OKUMA
     * ============================================================== */

    /** @return array<int,string> Görünecek bölümler, sayfadaki sırayla. */
    public static function sections(): array
    {
        $secili = json_decode(Setting::get('anasayfa_bolumler', '[]'), true);
        $secili = is_array($secili) ? $secili : ['hero'];

        return array_values(array_filter(
            array_keys(Surum16::ANASAYFA_BOLUMLERI),
            static fn (string $b): bool => in_array($b, $secili, true)
        ));
    }

    /** @return array<int,array<string,string>> "liste" ayarının satırları */
    public static function listSetting(string $key): array
    {
        $satirlar = json_decode(Setting::get($key, '[]'), true);

        return is_array($satirlar)
            ? array_values(array_filter($satirlar, 'is_array'))
            : [];
    }

    /**
     * Karşılama bölümü. Boş alanlar site ayarlarına düşer: başlık → site
     * adı, metin → slogan ya da açıklama.
     *
     * @return array<string,mixed>
     */
    private static function hero(): array
    {
        $metin = Setting::get('anasayfa_metin');

        if ($metin === '') {
            $metin = Setting::get('site_slogan') !== '' ? Setting::get('site_slogan') : Setting::get('site_aciklama');
        }

        $dugmeler = [];

        foreach (['birincil', 'ikincil'] as $tur) {
            $yazi  = Setting::get('anasayfa_' . $tur . '_metin');
            $adres = Setting::get('anasayfa_' . $tur . '_adres');

            if ($yazi !== '' && $adres !== '') {
                $dugmeler[] = ['metin' => $yazi, 'adres' => self::link($adres), 'dis' => self::isExternal($adres), 'tur' => $tur];
            }
        }

        return [
            'rozet'    => self::placeholders(Setting::get('anasayfa_rozet')),
            'baslik'   => Setting::get('anasayfa_baslik') !== '' ? Setting::get('anasayfa_baslik') : Setting::get('site_adi', (string) Config::get('app.name')),
            'metin'    => $metin,
            'dugmeler' => $dugmeler,
            'guven'    => self::placeholders(Setting::get('anasayfa_guven')),
            'gorsel'   => Setting::get('anasayfa_gorsel'),
        ];
    }

    /**
     * {surum} {php} {komut} {migration} {test} yer tutucuları. Elle
     * yazılan "20 konsol komutu" gibi sayılar zamanla yanlış kalıyordu;
     * bunlar koddan sayılır.
     */
    public static function placeholders(string $metin): string
    {
        if (!str_contains($metin, '{')) {
            return $metin;
        }

        return strtr($metin, [
            '{surum}'     => (string) Config::get('app.version', ''),
            '{php}'       => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            '{komut}'     => (string) \App\Core\Console\Kernel::commandCount(),
            '{migration}' => (string) count(glob(CY_BASE . '/database/migrations/*.php') ?: []),
            '{test}'      => (string) Config::get('app.test_sayisi', 0),
        ]);
    }

    /** Ayardaki adres: site içi yol → tam adres; http(s) olduğu gibi. */
    public static function link(string $adres): string
    {
        return self::isExternal($adres) ? $adres : url(ltrim($adres, '/'));
    }

    private static function isExternal(string $adres): bool
    {
        return preg_match('#^https?://#i', $adres) === 1;
    }

    /* =================================================================
     *  "ROLLERİ DENEYİN" (yalnızca demo modunda)
     * ============================================================== */

    /** Yetki kodlarının okunur karşılıkları — kart başına en fazla 4. */
    private const YETKI_ETIKETLERI = [
        'users.view'      => 'Kullanıcıları yönetir',
        'settings.manage' => 'Site ayarlarını değiştirir',
        'system.manage'   => 'Modülleri açıp kapatır',
        'mail.send'       => 'Toplu e-posta gönderir',
        'pages.manage'    => 'Sayfa yazar ve yayınlar',
        'messages.manage' => 'İletişim mesajlarını yanıtlar',
        'mail.view'       => 'E-posta geçmişini görür',
        'ornek.publish'   => 'Onay bekleyen kayıtları yayınlar',
        'ornek.create'    => 'Örnek Modül\'e kayıt yazar',
        'profile.update'  => 'Kendi profilini düzenler',
        'profile.api'     => 'API anahtarı üretir',
    ];

    /** @return array<int,array{rol:string,etiket:string,variant:string,kullanici:string,yapabilir:array<int,string>}> */
    private static function roleCards(): array
    {
        if (!Demo::enabled()) {
            return [];
        }

        $kartlar = [];

        foreach (Demo::HESAPLAR as $kadi => $hesap) {
            if (!$hesap['herkese']) {
                continue;
            }

            $yapabilir = [];

            foreach (self::YETKI_ETIKETLERI as $yetki => $etiket) {
                if (Role::can($hesap['rol'], $yetki) && count($yapabilir) < 4) {
                    $yapabilir[] = $etiket;
                }
            }

            $kartlar[] = [
                'rol'       => $hesap['rol'],
                'etiket'    => Role::label($hesap['rol']),
                'variant'   => $hesap['variant'],
                'kullanici' => $kadi,
                'yapabilir' => $yapabilir,
            ];
        }

        return $kartlar;
    }

    /* =================================================================
     *  YAPISAL VERİ (JSON-LD)
     * ============================================================== */

    /**
     * Organization + WebSite; şablon vitriniyse SoftwareApplication.
     * Arama motorları marka adını, logoyu ve sosyal hesapları buradan
     * okur. Sosyal hesaplar ayardan gelir; boşlar atlanır.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function structuredData(): array
    {
        $kok   = Url::absolute('');
        $ad    = Setting::get('site_adi', (string) Config::get('app.name'));
        $logo  = Setting::logoUrl();
        $logo  = str_starts_with($logo, 'http') ? $logo : Url::origin() . '/' . ltrim($logo, '/');

        $sameAs = [];
        foreach (['sosyal_x', 'sosyal_facebook', 'sosyal_instagram', 'sosyal_linkedin', 'sosyal_youtube', 'sosyal_github', 'sosyal_pinterest'] as $anahtar) {
            if (($deger = Setting::get($anahtar)) !== '') {
                $sameAs[] = $deger;
            }
        }

        $veri = [
            array_filter([
                '@context' => 'https://schema.org',
                '@type'    => 'Organization',
                'name'     => site_brand(),
                'url'      => $kok,
                'logo'     => $logo,
                'email'    => Setting::get('iletisim_eposta') ?: null,
                'sameAs'   => $sameAs ?: null,
            ]),
            [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => $ad,
                'url'      => $kok,
                'inLanguage' => Setting::get('site_dil', 'tr'),
            ],
        ];

        if (Setting::bool('vitrin_tanitim_goster', false)) {
            $veri[] = [
                '@context'            => 'https://schema.org',
                '@type'               => 'SoftwareApplication',
                'name'                => 'CY PHP Starter',
                'description'         => Setting::get('site_aciklama'),
                'applicationCategory' => 'DeveloperApplication',
                'operatingSystem'     => 'Any',
                'softwareVersion'     => (string) Config::get('app.version'),
                'license'             => 'https://opensource.org/licenses/MIT',
                'url'                 => 'https://cilginyazilim.com/kutuphane/php-baslangic-sablonu',
                'codeRepository'      => 'https://github.com/CilginYazilim/cy-php-starter',
                'offers'              => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'TRY'],
                'author'              => ['@type' => 'Organization', 'name' => 'ÇILGIN Yazılım', 'url' => 'https://cilginyazilim.com'],
            ];
        }

        return $veri;
    }

    /* =================================================================
     *  İLETİŞİM KARTLARI (ana sayfa, Hakkımızda ve İletişim ortak)
     * ============================================================== */

    /**
     * İletişim kartları — YALNIZCA dolu olanlar döner.
     *
     * @return array<int,array{ikon:string,etiket:string,deger:string,link:string}>
     */
    public static function contactCards(): array
    {
        $kartlar = [];

        $eposta = Setting::get('iletisim_eposta');

        if ($eposta !== '') {
            $kartlar[] = ['ikon' => 'mail', 'etiket' => 'E-posta', 'deger' => $eposta, 'link' => 'mailto:' . $eposta];
        }

        $telefon = Setting::get('iletisim_telefon');

        if ($telefon !== '') {
            // tel: bağlantısında boşluk ve parantez sorun çıkarır.
            $kartlar[] = ['ikon' => 'phone', 'etiket' => 'Telefon', 'deger' => $telefon, 'link' => 'tel:' . self::digits($telefon)];
        }

        if (self::whatsappNumber() !== '') {
            $kartlar[] = ['ikon' => 'whatsapp', 'etiket' => 'WhatsApp', 'deger' => Setting::get('iletisim_whatsapp'), 'link' => self::whatsappLink()];
        }

        $saatler = Setting::get('iletisim_saatler');

        if ($saatler !== '') {
            $kartlar[] = ['ikon' => 'clock', 'etiket' => 'Çalışma Saatleri', 'deger' => $saatler, 'link' => ''];
        }

        $adres = Setting::get('iletisim_adres');

        if ($adres !== '') {
            $kartlar[] = ['ikon' => 'map', 'etiket' => 'Adres', 'deger' => $adres, 'link' => Setting::get('iletisim_harita')];
        }

        return $kartlar;
    }

    /**
     * WhatsApp numarasının yalnızca rakamları. wa.me "+", boşluk ve
     * parantez KABUL ETMEZ; ülke koduyla bitişik rakam ister.
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

        return 'https://wa.me/' . $numara . ($mesaj !== '' ? '?text=' . rawurlencode($mesaj) : '');
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
