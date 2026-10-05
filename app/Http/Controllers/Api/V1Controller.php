<?php
/**
 * =====================================================================
 *  V1Controller – Dış istemciler için örnek REST uçları (/api/v1/…)
 * ---------------------------------------------------------------------
 *  Bu uçlar "Authorization: Bearer cy_…" anahtarıyla çağrılır. Anahtar
 *  Panel → Hesabım → API Anahtarları ekranından ya da komut satırından
 *  üretilir:
 *
 *      php cy api:token admin "Mobil uygulama" --gun=90
 *
 *      curl -H "Authorization: Bearer cy_…" https://site.com/api/v1/ben
 *
 *  NEDEN ÖRNEK UÇLAR? README API'yi "hazır" diye anlatıyordu ama
 *  "api" ara katmanını kullanan tek bir rota ya da anahtar üreten bir
 *  ekran yoktu; altyapının çalıştığını görmenin yolu yoktu. Bu dosya
 *  hem çalışan bir başlangıç hem de kendi uçlarınız için kalıptır.
 *
 *  YANIT ZARFI her uçta aynıdır (bkz. App\Core\Api\ApiResponse):
 *      { "success": true, "data": …, "meta": {…} }
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Api\ApiResponse;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Http\Controller;
use App\Models\Page;
use App\Repositories\PageRepository;

final class V1Controller extends Controller
{
    /** GET api/v1/ben — anahtarın sahibi kim? (bağlantı sınaması için ideal) */
    public function ben(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            ApiResponse::unauthorized();
        }

        ApiResponse::success([
            'id'            => $user->id,
            'ad_soyad'      => $user->fullName(),
            'kullanici_adi' => $user->kullaniciAdi,
            'eposta'        => $user->eposta,
            'rol'           => $user->rol,
        ]);
    }

    /** GET api/v1/kullanicilar?sayfa=1&boyut=25&ara=… (users.view) */
    public function kullanicilar(Request $request): void
    {
        $perPage = $request->int('boyut', 1, (int) Config::get('api.max_per_page', 100))
            ?? (int) Config::get('api.per_page', 25);
        $page    = $request->int('sayfa', 1, 100000) ?? 1;

        $result = $this->users()->paginate([
            'start'        => ($page - 1) * $perPage,
            'length'       => $perPage,
            'search'       => $request->input('ara'),
            'order_column' => 0,
            'order_dir'    => 'asc',
        ]);

        $items = array_map(static function ($user): array {
            $row = $user->toArray();

            // Dış istemciye gereken alanlar; panelin iç alanları değil.
            return array_intersect_key($row, array_flip([
                'id', 'ad', 'soyad', 'kullanici_adi', 'eposta', 'rol', 'durum', 'created_at',
            ]));
        }, $result['rows']);

        ApiResponse::paginated($items, $result['filtered'], $page, $perPage);
    }

    /** GET api/v1/sayfalar — yayındaki içerik sayfaları (anahtarsız, hız sınırlı) */
    public function sayfalar(Request $request): void
    {
        $pages = array_values(array_filter(
            (new PageRepository($this->db))->all(),
            static fn (Page $page): bool => $page->yayinda()
        ));

        ApiResponse::success(array_map(static fn (Page $page): array => [
            'baslik' => $page->baslik,
            'slug'   => $page->slug,
            'ozet'   => $page->ozet,
            'adres'  => \App\Core\Url::absolute($page->slug),
        ], $pages));
    }

    /** GET api/v1/sayfalar/{slug} — tek sayfanın içeriği */
    public function sayfa(Request $request, string $slug): void
    {
        $page = (new PageRepository($this->db))->findPublished($slug);

        if ($page === null) {
            ApiResponse::notFound('Sayfa bulunamadı.');
        }

        ApiResponse::success([
            'baslik'     => $page->baslik,
            'slug'       => $page->slug,
            'ozet'       => $page->ozet,
            'icerik'     => $page->icerik,
            'guncelleme' => $page->updatedAt,
        ]);
    }
}
