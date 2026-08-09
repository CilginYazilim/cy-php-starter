<?php
/**
 * =====================================================================
 *  MessageApiController – Mesaj işlemlerinin AJAX uç noktaları
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Models\Message;

final class MessageApiController extends Controller
{
    public function list(Request $request): void
    {
        $order = $request->raw('order', []);

        $result = $this->messages()->paginate([
            'search'       => $this->searchTerm($request),
            'filter'       => $request->input('filter'),
            'order_column' => (int) ($order[0]['column'] ?? 4),
            'order_dir'    => (string) ($order[0]['dir'] ?? 'desc'),
            'start'        => (int) $request->raw('start', 0),
            'length'       => (int) $request->raw('length', 10),
        ]);

        $rows = [];

        foreach ($result['rows'] as $message) {
            $rows[] = $this->toTableRow($message);
        }

        Response::json([
            'draw'            => (int) $request->raw('draw', 1),
            'recordsTotal'    => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data'            => $rows,
            'unread'          => $result['unread'],
        ]);
    }

    private function searchTerm(Request $request): string
    {
        $search = $request->raw('search', []);

        return is_array($search) ? trim((string) ($search['value'] ?? '')) : '';
    }

    /** @return array<int,string> */
    private function toTableRow(Message $message): array
    {
        $konu = $message->konu !== '' ? $message->konu : '(konusuz)';

        return [
            '<input type="checkbox" class="form-check-input js-select-row" value="' . $message->id . '" aria-label="Mesaj ' . $message->id . ' seç">',
            '<span class="cy-user-cell__name">' . e($message->ad) . '</span><span class="cy-user-cell__meta">' . e($message->eposta) . '</span>',
            '<span class="' . (!$message->okundu ? 'fw-bold' : '') . '">' . e($konu) . '</span>'
                . '<br><span class="cy-cell-muted" style="font-size:.75rem">' . e($message->preview()) . '…</span>',
            $message->okundu
                ? '<span class="cy-status is-active"><span class="cy-status__dot"></span>Okundu</span>'
                : '<span class="cy-status is-hold"><span class="cy-status__dot"></span>Yeni</span>',
            '<span class="cy-nowrap cy-cell-muted" title="' . e(Message::formatDate($message->createdAt)) . '">'
                . e(human_date($message->createdAt)) . '</span>',
            '<div class="cy-actions">'
                . '<button type="button" class="cy-btn-icon cy-btn-icon--view js-view" data-id="' . $message->id . '" title="Görüntüle">' . icon('eye', 'cy-icon cy-icon--sm') . '</button>'
                . '<button type="button" class="cy-btn-icon cy-btn-icon--edit js-toggle-read" data-id="' . $message->id . '" data-next="' . ($message->okundu ? '0' : '1') . '"'
                . ' title="' . ($message->okundu ? 'Okunmadı işaretle' : 'Okundu işaretle') . '">' . icon($message->okundu ? 'x-circle' : 'check', 'cy-icon cy-icon--sm') . '</button>'
                . '<button type="button" class="cy-btn-icon cy-btn-icon--delete js-delete" data-id="' . $message->id . '" data-label="' . e($konu) . '" title="Sil">' . icon('trash', 'cy-icon cy-icon--sm') . '</button>'
                . '</div>',
        ];
    }

    public function fetch(Request $request): void
    {
        $message = $this->requireMessage($request);

        // Görüntülenen mesaj otomatik olarak okundu sayılır.
        if (!$message->okundu) {
            $this->messages()->markRead($message->id, true);
        }

        Response::json([
            'success'  => true,
            'id'       => $message->id,
            'ad'       => $message->ad,
            'eposta'   => $message->eposta,
            'konu'     => $message->konu,
            'mesaj'    => $message->mesaj,
            'ip'       => $message->ip,
            'tarayici' => $message->tarayici,
            'uye'      => $message->gonderenKullaniciAdi !== null ? '@' . $message->gonderenKullaniciAdi : '',
            'tarih'    => Message::formatDate($message->createdAt),
            'unread'   => $this->messages()->countUnread(),
        ]);
    }

    public function markRead(Request $request): void
    {
        $id = $request->int('id', 1);

        if ($id === null) {
            Response::error('Geçersiz mesaj numarası.', 400);
        }

        $read = $request->bool('deger') || $request->input('deger') === '1';

        $this->messages()->markRead($id, $read);

        if (!$this->messages()->exists($id)) {
            Response::error('Mesaj bulunamadı.', 404);
        }

        Response::success($read ? 'Okundu işaretlendi.' : 'Okunmadı işaretlendi.', [
            'id'     => $id,
            'unread' => $this->messages()->countUnread(),
        ]);
    }

    public function delete(Request $request): void
    {
        $message = $this->requireMessage($request);

        $this->messages()->delete($message->id);

        Response::success('Mesaj silindi.', ['id' => $message->id, 'unread' => $this->messages()->countUnread()]);
    }

    public function bulk(Request $request): void
    {
        $action = $request->input('islem');

        if (!in_array($action, ['okundu', 'okunmadi', 'sil'], true)) {
            Response::error('Geçersiz toplu işlem.', 422);
        }

        $raw = (array) $request->raw('ids', []);
        $ids = [];

        foreach ($raw as $value) {
            $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($int !== false) {
                $ids[] = (int) $int;
            }
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            Response::error('Hiç mesaj seçilmedi.', 422);
        }

        if (count($ids) > 500) {
            Response::error('Tek seferde en fazla 500 mesaj işlenebilir.', 422);
        }

        $affected = $this->messages()->bulk($ids, $action);

        $message = $action === 'sil'
            ? $affected . ' mesaj silindi.'
            : $affected . ' mesaj güncellendi.';

        Response::success($message, ['unread' => $this->messages()->countUnread()]);
    }

    private function requireMessage(Request $request): Message
    {
        $id = $request->int('id', 1);

        if ($id === null) {
            Response::error('Geçersiz mesaj numarası.', 400);
        }

        $message = $this->messages()->find($id);

        if ($message === null) {
            Response::error('Mesaj bulunamadı.', 404);
        }

        return $message;
    }
}
