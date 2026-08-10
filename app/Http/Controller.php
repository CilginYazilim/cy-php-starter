<?php
/**
 * =====================================================================
 *  Controller – Tüm denetleyicilerin ortak atası
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Setting;
use App\Core\View;
use App\Repositories\MailRepository;
use App\Repositories\MessageRepository;
use App\Repositories\UserRepository;
use PDO;

abstract class Controller
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /* =================================================================
     *  DATATABLES SUNUCU TARAFLI PROTOKOLÜ
     * -----------------------------------------------------------------
     *  Kullanıcılar, Mesajlar ve E-posta listeleri aynı isteği
     *  gönderir: arama metni, sıralama sütunu/yönü, başlangıç ve
     *  uzunluk. Üç denetleyicide birebir aynı satırlar duruyordu.
     * ============================================================== */

    /**
     * İsteğin ortak liste parametrelerini çözer.
     *
     * Dönen dizi doğrudan bir Repository::paginate() çağrısına
     * verilebilir; denetleyici üstüne yalnızca kendi filtrelerini
     * ekler (rol, durum, tür…).
     *
     * @return array{search:string,order_column:int,order_dir:string,start:int,length:int}
     */
    protected function tableQuery(Request $request, int $defaultOrderColumn = 0): array
    {
        $order  = $request->raw('order', []);
        $search = $request->raw('search', []);

        return [
            'search'       => is_array($search) ? trim((string) ($search['value'] ?? '')) : '',
            'order_column' => (int) ($order[0]['column'] ?? $defaultOrderColumn),
            'order_dir'    => (string) ($order[0]['dir'] ?? 'desc'),
            'start'        => max(0, (int) $request->raw('start', 0)),
            'length'       => $this->tableLength($request),
        ];
    }

    /**
     * Sayfa başına kayıt sayısı.
     *
     * İstek bir değer göndermezse yöneticinin panelden seçtiği
     * varsayılan kullanılır (Ayarlar → Sistem → Sayfa Başına Kayıt).
     * Üst sınırı Repository koyar; burada yalnızca anlamsız
     * değerleri (0, negatif) varsayılana çeviriyoruz.
     */
    protected function tableLength(Request $request): int
    {
        $default = (int) Setting::get('sistem_sayfa_basina', '10');
        $default = $default > 0 ? $default : 10;

        $length = (int) $request->raw('length', $default);

        return $length > 0 ? $length : $default;
    }

    /**
     * DataTables'ın beklediği yanıt zarfı.
     *
     * @param array{total:int,filtered:int} $result Repository sonucu
     * @param array<int,array<int,string|int>> $rows Basılmaya hazır satırlar
     * @param array<string,mixed> $extra Sayfaya özgü ek alanlar (unread, bekleyen…)
     */
    protected function tableJson(Request $request, array $result, array $rows, array $extra = []): never
    {
        Response::json(array_merge([
            'draw'            => (int) $request->raw('draw', 1),
            'recordsTotal'    => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data'            => $rows,
        ], $extra));
    }

    protected function users(): UserRepository
    {
        return new UserRepository($this->db);
    }

    protected function messages(): MessageRepository
    {
        return new MessageRepository($this->db);
    }

    protected function mails(): MailRepository
    {
        return new MailRepository($this->db);
    }

    /** @param array<string,mixed> $data */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        View::render($view, $data, $layout);
    }
}
