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
    public function manifest(Request $request): void
    {
        $ad   = Setting::get('site_adi', 'Uygulama');
        $renk = Theme::brand();
        $logo = Setting::logoUrl();

        $manifest = [
            'name'             => $ad,
            'short_name'       => mb_substr($ad, 0, 12, 'UTF-8'),
            'description'      => Setting::get('site_aciklama', ''),
            'lang'             => Setting::get('site_dil', 'tr'),
            'dir'              => 'ltr',
            'start_url'        => Url::to(''),
            'scope'            => Url::base() . '/',
            'display'          => 'standalone',
            'orientation'      => 'any',
            'background_color' => '#ffffff',
            'theme_color'      => $renk,
            'icons'            => [
                ['src' => $logo, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $logo, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $logo, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        if (!headers_sent()) {
            header('Content-Type: application/manifest+json; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
        }

        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** Ağ yokken servis çalışanının gösterdiği sayfa. */
    public function offline(Request $request): void
    {
        $this->view('site/offline', ['title' => 'Bağlantı yok'], 'layouts/plain');
    }
}
