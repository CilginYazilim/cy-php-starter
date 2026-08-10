<?php
/**
 * =====================================================================
 *  SeoController – sitemap.xml ve robots.txt
 * ---------------------------------------------------------------------
 *  İKİSİ DE DİNAMİKTİR ve bu bilinçlidir:
 *
 *   · "SEO → İndeksleme" kapatıldığında statik bir dosya bunu bilemez
 *     ve arama motorları siteyi taramaya devam eder.
 *   · Panelden yayınlanan her sayfa site haritasına KENDİLİĞİNDEN
 *     girer, taslağa alındığında düşer. Elle dosya güncellemek
 *     unutulan işlerin başında gelir.
 *
 *  DİKKAT: Sunucuda gerçek bir sitemap.xml / robots.txt DOSYASI varsa
 *  web sunucusu onu önceliklendirir ve buradaki hiçbir ayar okunmaz.
 *  Panelden yönetmek istiyorsanız o dosyaları silin.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Request;
use App\Core\Setting;
use App\Core\Url;
use App\Http\Controller;
use App\Repositories\PageRepository;

final class SeoController extends Controller
{
    public function sitemap(Request $request): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        /* Site haritası kapalıysa ya da site aramaya kapalıysa BOŞ bir
         * harita basıyoruz. 404 vermek yerine boş dönmek, Search
         * Console'da "dosya bulunamadı" hatası üretmeden "şu an
         * taranacak sayfa yok" demenin doğru yoludur. */
        if (Setting::bool('seo_sitemap_aktif', true) && Setting::bool('seo_indeksleme', true)) {
            foreach ($this->urls() as $url) {
                echo "  <url>\n";
                echo '    <loc>' . e($url['loc']) . "</loc>\n";

                if ($url['tarih'] !== '') {
                    echo '    <lastmod>' . e($url['tarih']) . "</lastmod>\n";
                }

                echo '    <changefreq>' . e($url['freq']) . "</changefreq>\n";
                echo '    <priority>' . e($url['oncelik']) . "</priority>\n";
                echo "  </url>\n";
            }
        }

        echo '</urlset>';
    }

    /**
     * Site haritasına girecek adresler.
     *
     * Ana sayfa + YAYINDAKİ tüm içerik sayfaları. Giriş/kayıt
     * sayfaları KONMAZ: arama sonuçlarında görünmelerinin kimseye
     * faydası yoktur.
     *
     * @return array<int,array{loc:string,freq:string,oncelik:string,tarih:string}>
     */
    private function urls(): array
    {
        $urls = [[
            'loc'     => Url::absolute(''),
            'freq'    => 'weekly',
            'oncelik' => '1.0',
            'tarih'   => '',
        ]];

        try {
            foreach ((new PageRepository($this->db))->sitemap() as $sayfa) {
                $urls[] = [
                    'loc'     => Url::absolute($sayfa['slug']),
                    'freq'    => 'monthly',
                    'oncelik' => '0.7',
                    'tarih'   => $sayfa['updated'] !== '' ? date('Y-m-d', strtotime($sayfa['updated'])) : '',
                ];
            }
        } catch (\Throwable) {
            // Tablo yoksa harita yalnızca ana sayfayı içerir.
        }

        return $urls;
    }

    public function robots(Request $request): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
        }

        $indeksle = Setting::bool('seo_indeksleme', true);

        echo "# Bu dosya PANELDEN üretilir: Ayarlar → SEO\n";
        echo "User-agent: *\n";

        if (!$indeksle) {
            /* İndeksleme kapalı: her şeyi engelle ve site haritası
               verme. Panelden "kapalı" diyen biri, sitenin arama
               sonuçlarında çıkmasını istemiyordur. */
            echo "Disallow: /\n";

            return;
        }

        // Panel ve teknik klasörler asla taranmamalı.
        foreach (['/panel', '/giris', '/kayit', '/kurulum', '/api/', '/upload/'] as $yol) {
            echo 'Disallow: ' . Url::base() . $yol . "\n";
        }

        /* Yöneticinin eklediği kurallar (Ayarlar → SEO → robots.txt Ek
         * Kuralları). Satır satır basılır; içeriğe karışmıyoruz ama
         * satır sonlarını normalleştiriyoruz — Windows'ta yapıştırılan
         * metnin "\r" karakterleri bazı tarayıcıları şaşırtıyordu. */
        $ek = trim(Setting::get('seo_robots_ek'));

        if ($ek !== '') {
            echo "\n# --- Panelden eklenen kurallar ---\n";

            foreach (preg_split('/\R/', $ek) ?: [] as $satir) {
                echo rtrim($satir) . "\n";
            }
        }

        if (Setting::bool('seo_sitemap_aktif', true)) {
            echo "\nSitemap: " . Url::absolute('sitemap.xml') . "\n";
        }
    }
}
