<?php
/**
 * =====================================================================
 *  SeoController – sitemap.xml ve robots.txt
 * ---------------------------------------------------------------------
 *  İkisi de DİNAMİKTİR. Sebep: "SEO → İndeksleme" ayarı kapatıldığında
 *  statik dosyalar bunu bilemez ve arama motorları siteyi taramaya
 *  devam eder. Panelden kapatılan bir siteyi Google'ın da görmemesi
 *  gerekir.
 *
 *  Site haritası şu an SABİT sayfaları listeler. Kendi içeriğinizi
 *  (blog yazıları, ürünler…) eklemek için urls() metoduna kendi
 *  sorgunuzu ekleyin — modüller de buraya katkı yapabilir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Request;
use App\Core\Setting;
use App\Core\Url;
use App\Http\Controller;

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

        foreach ($this->urls() as $url) {
            echo "  <url>\n";
            echo '    <loc>' . e($url['loc']) . "</loc>\n";
            echo '    <changefreq>' . e($url['freq']) . "</changefreq>\n";
            echo '    <priority>' . e($url['oncelik']) . "</priority>\n";
            echo "  </url>\n";
        }

        echo '</urlset>';
    }

    /**
     * Site haritasına girecek adresler.
     *
     * @return array<int,array{loc:string,freq:string,oncelik:string}>
     */
    private function urls(): array
    {
        $sayfalar = [
            ['', 'weekly', '1.0'],
            ['hakkimizda', 'monthly', '0.7'],
            ['iletisim', 'monthly', '0.7'],
        ];

        // Giriş/kayıt sayfaları haritaya KONMAZ: arama sonuçlarında
        // görünmelerinin kimseye faydası yoktur.

        $urls = [];

        foreach ($sayfalar as [$yol, $freq, $oncelik]) {
            $urls[] = [
                'loc'     => Url::absolute($yol),
                'freq'    => $freq,
                'oncelik' => $oncelik,
            ];
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

        echo "\nSitemap: " . Url::absolute('sitemap.xml') . "\n";
    }
}
