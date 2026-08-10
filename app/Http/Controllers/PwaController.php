<?php
/**
 * =====================================================================
 *  PwaController – Uygulama künyesi (manifest) ve çevrimdışı sayfa
 * ---------------------------------------------------------------------
 *  MANIFEST NEDEN DİNAMİK?
 *  Site adı, renk ve logo panelden değiştirilebiliyor. Statik bir
 *  .webmanifest dosyası bunları bilemez; ayarlar değiştiğinde elle
 *  düzenlemek gerekirdi. Bu yüzden manifest'i PHP üretir.
 *
 *  Servis çalışanı (sw.js) ise KÖKTE STATİK bir dosyadır: bir servis
 *  çalışanı yalnızca bulunduğu klasör ve altını kontrol edebilir
 *  ("scope"). PHP üzerinden servis edilseydi kapsamı doğru
 *  kurulamazdı.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Setting;
use App\Core\Theme;
use App\Core\Url;
use App\Http\Controller;

final class PwaController extends Controller
{
    /** Künyede geçerli sayılan değerler; ayar bozuksa bunlara düşülür. */
    private const GORUNUMLER = ['standalone', 'fullscreen', 'minimal-ui', 'browser'];
    private const YONLER     = ['any', 'portrait', 'landscape'];

    public function manifest(Request $request): void
    {
        /* HER ALANIN BİR YEDEĞİ VAR: PWA ayarları boş bırakıldığında
         * künye eksik kalmaz, site ayarlarından beslenir. Yönetici
         * yalnızca farklı olmasını istediği alanı doldurur. */
        $ad      = Setting::get('pwa_ad', Setting::get('site_adi', 'Uygulama'));
        $kisaAd  = Setting::get('pwa_kisa_ad', mb_substr($ad, 0, 12, 'UTF-8'));
        $simge   = Setting::pwaIconUrl();

        $manifest = [
            'name'             => $ad,
            'short_name'       => $kisaAd,
            'description'      => Setting::get('pwa_aciklama', Setting::get('site_aciklama', '')),
            'lang'             => Setting::get('site_dil', 'tr'),
            'dir'              => 'ltr',
            'start_url'        => Url::to(Setting::get('pwa_baslangic', '')),
            'scope'            => Url::base() . '/',
            'display'          => $this->secim('pwa_gorunum', self::GORUNUMLER, 'standalone'),
            'orientation'      => $this->secim('pwa_yon', self::YONLER, 'any'),
            'background_color' => $this->renk('pwa_arka_renk', '#ffffff'),
            'theme_color'      => Theme::brand(),
            'icons'            => [
                ['src' => $simge, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $simge, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $simge, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        if (!headers_sent()) {
            header('Content-Type: application/manifest+json; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
        }

        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Listedeki bir değer mi? Değilse varsayılan.
     *
     * Ayar satırı "secim" tipinde olduğu için form yalnızca geçerli
     * değer gönderebilir; ama ayarlar tablosuna elle (SQL ile) de
     * yazılabiliyor. Geçersiz bir "display" değeri künyeyi tamamen
     * geçersiz kılar ve kurulum düğmesi hiç görünmez.
     *
     * @param array<int,string> $izinli
     */
    private function secim(string $anahtar, array $izinli, string $varsayilan): string
    {
        $deger = Setting::get($anahtar, $varsayilan);

        return in_array($deger, $izinli, true) ? $deger : $varsayilan;
    }

    /** "#rrggbb" biçiminde değilse varsayılan renk. */
    private function renk(string $anahtar, string $varsayilan): string
    {
        $deger = Setting::get($anahtar, $varsayilan);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $deger) === 1 ? $deger : $varsayilan;
    }

    /** Ağ yokken servis çalışanının gösterdiği sayfa. */
    public function offline(Request $request): void
    {
        $this->view('site/offline', ['title' => 'Bağlantı yok'], 'layouts/plain');
    }
}
