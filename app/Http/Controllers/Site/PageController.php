<?php
/**
 * =====================================================================
 *  Site\PageController – İçerik sayfalarının ön yüzü
 * ---------------------------------------------------------------------
 *  Tek bir rota tüm sayfaları karşılar: /hakkimizda, /gizlilik, /kvkk…
 *
 *  ÇAKIŞMA OLUR MU? Hayır. Router önce SABİT rotalara bakar; "{slug}"
 *  yalnızca hiçbiri eşleşmediğinde denenir. Yani "panel" ya da
 *  "giris" adresleri buraya hiç düşmez. Yayında olmayan ya da hiç
 *  var olmayan bir adres 404 verir — sessizce ana sayfaya atmak
 *  ziyaretçiye de arama motoruna da yalan söylemek olurdu.
 *
 *  SEO: Her sayfa WebPage + BreadcrumbList yapısal verisini (JSON-LD)
 *  taşır; kapak görseli varsa paylaşım görseli (og:image) odur.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Setting;
use App\Core\Url;
use App\Http\Controller;
use App\Http\Controllers\PageController as PanelPageController;
use App\Models\Page;
use App\Repositories\PageRepository;

final class PageController extends Controller
{
    public function show(Request $request, string $slug): void
    {
        /* Adresler küçük harflidir. "/Hakkimizda" eskiden de açılıyor ve
         * kendi canonical adresini basıyordu: arama motoru aynı içeriği
         * iki adreste görüyordu. Büyük harfli istek kalıcı olarak (301)
         * küçük harfli adrese yönlendirilir. (Rota yalnızca ASCII kabul eder.) */
        if (strtolower($slug) !== $slug) {
            Response::redirect(url(strtolower($slug)), 301);
        }

        $sayfa = (new PageRepository($this->db))->findPublished($slug);

        if ($sayfa === null) {
            throw HttpException::notFound($slug);
        }

        /* İletişim sayfası özel bir görünüm kullanır: içeriğin altında
         * form ve iletişim kartları da vardır. Yönetici sayfayı yine
         * panelden yazar; yalnızca çevresi farklıdır. */
        if ($sayfa->slug === 'iletisim') {
            (new ContactController())->show($request, $sayfa);

            return;
        }

        $this->render($sayfa);
    }

    /**
     * Taslak dahil herhangi bir sayfanın imzalı önizlemesi.
     * Bağlantıyı panel üretir (PageController::previewUrl); 30 dakika geçerlidir.
     */
    public function preview(Request $request, string $id): void
    {
        $son  = (int) $request->string('son');
        $imza = $request->string('imza');

        if (!PanelPageController::previewValid((int) $id, $son, $imza)) {
            throw HttpException::forbidden('Önizleme bağlantısının süresi dolmuş ya da bağlantı geçersiz. Panelden yeniden "Önizle"ye basın.');
        }

        $sayfa = (new PageRepository($this->db))->find((int) $id);

        if ($sayfa === null) {
            throw HttpException::notFound('onizleme/' . $id);
        }

        /* İmzalı adres başka bir siteye "Referer" olarak sızmasın,
         * önbelleğe ve arama motoruna girmesin. */
        if (!headers_sent()) {
            header('Referrer-Policy: no-referrer');
            header('X-Robots-Tag: noindex, nofollow');
            header('Cache-Control: private, no-store');
        }

        $this->render($sayfa, $son);
    }

    private function render(Page $sayfa, ?int $onizlemeBitis = null): void
    {
        $kapak = $sayfa->kapakUrl();

        $this->view('site/page', [
            'title'           => $sayfa->baslikSeo(),
            'ogAciklama'      => $sayfa->aciklamaSeo(),
            'paylasimGorseli' => $kapak,
            'sayfa'           => $sayfa,
            'kapak'           => $kapak,
            'onizlemeBitis'   => $onizlemeBitis,
            'noindex'         => $onizlemeBitis !== null,
            'jsonLd'          => $onizlemeBitis === null ? [self::structuredData($sayfa, $kapak)] : [],
            'iletisim'        => HomeController::contactCards(),
        ], 'layouts/site');
    }

    /**
     * WebPage + BreadcrumbList. Arama sonuçlarında "Ana Sayfa › Hakkımızda"
     * konum satırı ve sayfanın güncellenme tarihi buradan okunur.
     *
     * @return array<string,mixed>
     */
    public static function structuredData(Page $sayfa, string $kapak = ''): array
    {
        $adres    = Url::absolute($sayfa->slug);
        $anaSayfa = Url::absolute('');
        $tarih    = static fn (?string $t): ?string => $t === null || $t === '' ? null : date(DATE_ATOM, (int) strtotime($t));

        $webPage = array_filter([
            '@type'         => 'WebPage',
            '@id'           => $adres . '#sayfa',
            'url'           => $adres,
            'name'          => $sayfa->baslikSeo(),
            'description'   => $sayfa->aciklamaSeo(),
            'inLanguage'    => Setting::get('site_dil', 'tr'),
            'datePublished' => $tarih($sayfa->createdAt),
            'dateModified'  => $tarih($sayfa->updatedAt),
            'image'         => $kapak !== '' ? (str_starts_with($kapak, 'http') ? $kapak : Url::origin() . $kapak) : null,
            'isPartOf'      => ['@type' => 'WebSite', 'name' => Setting::get('site_adi', ''), 'url' => $anaSayfa],
            'breadcrumb'    => ['@id' => $adres . '#konum'],
        ], static fn ($v): bool => $v !== null && $v !== '');

        return [
            '@context' => 'https://schema.org',
            '@graph'   => [
                $webPage,
                [
                    '@type'           => 'BreadcrumbList',
                    '@id'             => $adres . '#konum',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => $anaSayfa],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $sayfa->baslik, 'item' => $adres],
                    ],
                ],
            ],
        ];
    }
}
