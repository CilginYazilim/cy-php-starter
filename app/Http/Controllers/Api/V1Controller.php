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
 *  TARİHLER ISO 8601 biçimindedir ("2026-10-06T14:05:00+03:00").
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

        // Dış istemciye gereken alanlar; panelin iç alanları değil.
        $items = array_map(static fn ($user): array => [
            'id'            => $user->id,
            'ad'            => $user->ad,
            'soyad'         => $user->soyad,
            'kullanici_adi' => $user->kullaniciAdi,
            'eposta'        => $user->eposta,
            'rol'           => $user->rol,
            'durum'         => $user->durum,
            'created_at'    => self::iso($user->createdAt),
        ], $result['rows']);

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
            'guncelleme' => self::iso($page->updatedAt),
        ]);
    }

    /**
     * Veritabanı tarihini ISO 8601'e çevirir: "2026-10-06T14:05:00+03:00".
     *
     * API'de tarih, panelde gösterilen "06.10.2026 14:05" biçiminde
     * dönüyordu: saat dilimi yoktu ve her istemci Türkçe biçimi elle
     * ayrıştırmak zorundaydı. Veritabanı bağlantısının saat dilimi
     * PHP'ninkiyle eşitlendiği için (bkz. Database::syncTimezone) değer
     * PHP'nin saat diliminde okunur.
     */
    private static function iso(?string $value): ?string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format(\DateTimeInterface::ATOM);
        } catch (\Exception) {
            return null;
        }
    }
}
