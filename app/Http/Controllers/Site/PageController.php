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
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Http\Controller;
use App\Repositories\PageRepository;

final class PageController extends Controller
{
    public function show(Request $request, string $slug): void
    {
        $sayfa = (new PageRepository($this->db))->findPublished(mb_strtolower($slug));

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

        $this->view('site/page', [
            'title'      => $sayfa->baslikSeo(),
            'ogAciklama' => $sayfa->aciklamaSeo(),
            'sayfa'      => $sayfa,
            'iletisim'   => HomeController::contactCards(),
        ], 'layouts/site');
    }
}
