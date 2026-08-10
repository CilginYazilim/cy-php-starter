<?php
/**
 * =====================================================================
 *  HomeController – Ana sayfa ve Hakkımızda (ön yüz)
 * ---------------------------------------------------------------------
 *  ÖN YÜZ TAMAMEN AYARLARDAN BESLENİR. Sayfadaki hiçbir metin koda
 *  gömülü değildir: site adı, slogan, hakkımızda yazısı, iletişim
 *  bilgileri ve sosyal bağlantılar "ayarlar" tablosundan gelir.
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

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('site/home', [
            'title'       => '',
            'ozellikler'  => $this->features(),
            'iletisim'    => $this->contactCards(),
            'istatistik'  => $this->highlights(),
        ], 'layouts/site');
    }

    public function about(Request $request): void
    {
        $this->view('site/about', [
            'title'      => 'Hakkımızda',
            'iletisim'   => $this->contactCards(),
            'ozellikler' => $this->features(),
        ], 'layouts/site');
    }

    /* =================================================================
     *  İÇERİK PARÇALARI
     * ============================================================== */

    /**
     * Öne çıkan özellikler.
     *
     * Şablonun kendi tanıtımıdır; kendi projenizde bu diziyi kendi
     * hizmetlerinizle değiştirin ya da bir modülden besleyin.
     *
     * @return array<int,array{ikon:string,renk:string,baslik:string,metin:string}>
     */
    private function features(): array
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
                'ikon'   => 'server',
                'renk'   => 'brand',
                'baslik' => 'Modüler',
                'metin'  => 'Tek komutla modül üretin; açıp kapatın, çekirdeğe hiç dokunmayın.',
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
    private function contactCards(): array
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
                'link'   => 'tel:' . preg_replace('/[^0-9+]/', '', $telefon),
            ];
        }

        $adres = Setting::get('iletisim_adres');

        if ($adres !== '') {
            $kartlar[] = [
                'ikon'   => 'globe',
                'etiket' => 'Adres',
                'deger'  => $adres,
                'link'   => '',
            ];
        }

        return $kartlar;
    }

    /**
     * Ana sayfadaki rakamlar.
     *
     * @return array<int,array{deger:string,etiket:string}>
     */
    private function highlights(): array
    {
        return [
            ['deger' => '0',   'etiket' => 'Bağımlılık'],
            ['deger' => '20',  'etiket' => 'Konsol komutu'],
            ['deger' => '8.1+', 'etiket' => 'PHP sürümü'],
            ['deger' => 'MIT', 'etiket' => 'Lisans'],
        ];
    }
}
