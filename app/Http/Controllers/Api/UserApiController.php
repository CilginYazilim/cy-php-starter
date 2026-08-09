<?php
/**
 * =====================================================================
 *  UserApiController – Kullanıcı CRUD işlemlerinin AJAX uç noktaları
 * ---------------------------------------------------------------------
 *  YETKİ KURALLARI (rol tablosuna EK olarak uygulanan iş kuralları):
 *    1. Kimse kendi hesabını silemez veya durumunu değiştiremez
 *    2. Kimse kendi rolünü değiştiremez
 *    3. Sistemdeki SON aktif yönetici silinemez / rolü indirilemez / pasife alınamaz
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Core\Validator;
use App\Http\Controller;
use App\Models\Role;
use App\Models\User;
use RuntimeException;

final class UserApiController extends Controller
{
    /* =================================================================
     *  1) LİSTELEME (DataTables sunucu taraflı protokolü)
     * ============================================================== */
    public function list(Request $request): void
    {
        $order = $request->raw('order', []);

        $result = $this->users()->paginate([
            'search'       => $this->searchTerm($request),
            'role'         => $request->input('filter_role'),
            'status'       => $request->input('filter_status'),
            'order_column' => (int) ($order[0]['column'] ?? 0),
            'order_dir'    => (string) ($order[0]['dir'] ?? 'desc'),
            'start'        => (int) $request->raw('start', 0),
            'length'       => (int) $request->raw('length', 10),
        ]);

        $rows = [];

        foreach ($result['rows'] as $user) {
            $rows[] = $this->toTableRow($user);
        }

        Response::json([
            'draw'            => (int) $request->raw('draw', 1),
            'recordsTotal'    => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data'            => $rows,
        ]);
    }

    private function searchTerm(Request $request): string
    {
        $search = $request->raw('search', []);

        return is_array($search) ? trim((string) ($search['value'] ?? '')) : '';
    }

    /** @return array<int,string|int> */
    private function toTableRow(User $user): array
    {
        $current = Auth::user();
        $isSelf  = $current !== null && $current->id === $user->id;

        $avatar = $user->avatarUrl() !== ''
            ? '<img src="' . e($user->avatarUrl()) . '" class="cy-avatar" alt="" loading="lazy">'
            : '<span class="cy-avatar cy-avatar--initial">' . e($user->initials()) . '</span>';

        $nameCell =
            '<div class="cy-user-cell">'
            . '<span class="cy-user-cell__name">' . e($user->fullName()) . '</span>'
            . '<span class="cy-user-cell__meta">@' . e($user->kullaniciAdi) . ' · ' . e($user->eposta) . '</span>'
            . ($isSelf ? '<span class="cy-tag cy-tag--self">siz</span>' : '')
            . '</div>';

        $roleBadge = '<span class="cy-role cy-role--' . e(Role::variant($user->rol)) . '">' . e($user->roleLabel()) . '</span>';

        $statusMap = ['aktif' => 'is-active', 'pasif' => 'is-passive', 'askida' => 'is-hold'];
        $statusClass = $statusMap[$user->durum] ?? 'is-passive';

        if (Auth::can('users.status') && !$isSelf) {
            $next = $user->durum === 'aktif' ? 'pasif' : 'aktif';
            $statusBadge = '<button type="button" class="cy-status ' . $statusClass . ' js-toggle-status"'
                . ' data-id="' . $user->id . '" data-next="' . e($next) . '" title="Durumu değiştir">'
                . '<span class="cy-status__dot"></span>' . e($user->statusLabel()) . '</button>';
        } else {
            $statusBadge = '<span class="cy-status ' . $statusClass . '"><span class="cy-status__dot"></span>' . e($user->statusLabel()) . '</span>';
        }

        $actions = '<div class="cy-actions">'
            . '<button type="button" class="cy-btn-icon cy-btn-icon--view js-view" data-id="' . $user->id . '"'
            . ' title="Detayı gör" aria-label="Detayı gör">' . icon('eye', 'cy-icon cy-icon--sm') . '</button>';

        if (Auth::can('users.update')) {
            $actions .= '<button type="button" class="cy-btn-icon cy-btn-icon--edit js-edit" data-id="' . $user->id . '"'
                . ' title="Düzenle" aria-label="Düzenle">' . icon('edit', 'cy-icon cy-icon--sm') . '</button>';
        }

        if (Auth::can('users.delete') && !$isSelf) {
            $actions .= '<button type="button" class="cy-btn-icon cy-btn-icon--delete js-delete" data-id="' . $user->id . '"'
                . ' data-label="' . e($user->fullName()) . '"'
                . ' title="Sil" aria-label="Sil">' . icon('trash', 'cy-icon cy-icon--sm') . '</button>';
        }

        $actions .= '</div>';

        return [
            $user->id,
            $avatar,
            $nameCell,
            '<span class="cy-cell-muted">' . e($user->eposta) . '</span>',
            $roleBadge,
            $statusBadge,
            '<span class="cy-nowrap cy-cell-muted">' . e(User::formatDate($user->createdAt)) . '</span>',
            $actions,
        ];
    }

    /* =================================================================
     *  2) TEK KAYIT GETİRME
     * ============================================================== */
    public function fetch(Request $request): void
    {
        $user = $this->requireUser($request);

        Response::json([
            'success' => true,
            'data'    => array_merge($user->toArray(), [
                'can_edit'   => Auth::can('users.update'),
                'can_delete' => Auth::can('users.delete') && !Auth::isSelf($user->id),
            ]),
        ]);
    }

    /* =================================================================
     *  3) EKLEME / GÜNCELLEME
     * ============================================================== */
    public function save(Request $request): void
    {
        $action = $request->input('action') === 'edit' ? 'edit' : 'add';
        $isEdit = $action === 'edit';

        if (!Auth::can($isEdit ? 'users.update' : 'users.create')) {
            Response::error('Bu işlem için yetkiniz bulunmuyor.', 403);
        }

        $target = $isEdit ? $this->requireUser($request, 'id') : null;

        $validator = new Validator($_POST);
        $validator->name('ad', 'Ad')
                  ->name('soyad', 'Soyad')
                  ->username('kullanici_adi')
                  ->email('eposta')
                  ->phone('telefon')
                  ->text('hakkinda', 'Hakkında', 0, 1000)
                  ->password('sifre', !$isEdit);

        if ($validator->passes() && $this->users()->fieldTaken('eposta', (string) $validator->validated()['eposta'], $target?->id)) {
            $validator->addError('eposta', 'Bu e-posta adresi başka bir hesapta kayıtlı.');
        }
        if ($validator->passes() && $this->users()->fieldTaken('kullanici_adi', (string) $validator->validated()['kullanici_adi'], $target?->id)) {
            $validator->addError('kullanici_adi', 'Bu kullanıcı adı başka bir hesapta kayıtlı.');
        }

        $role = $isEdit ? $target->rol : Role::MEMBER;

        if (Auth::can('users.role') && array_key_exists('rol', $_POST)) {
            $validator->in('rol', Role::all(), 'Rol');

            if (!isset($validator->errors()['rol'])) {
                $role = (string) $validator->validated()['rol'];
            }
        }

        if ($isEdit && Auth::isSelf($target->id) && $role !== $target->rol) {
            $validator->addError('rol', 'Kendi rolünüzü değiştiremezsiniz.');
        }

        $status = $isEdit ? $target->durum : 'aktif';

        if (Auth::can('users.status') && array_key_exists('durum', $_POST)) {
            $validator->in('durum', ['aktif', 'pasif', 'askida'], 'Durum');

            if (!isset($validator->errors()['durum'])) {
                $status = (string) $validator->validated()['durum'];
            }
        }

        if ($isEdit && Auth::isSelf($target->id) && $status !== 'aktif') {
            $validator->addError('durum', 'Kendi hesabınızı pasife alamazsınız.');
        }

        // Son aktif yöneticinin yetkisi/durumu elinden alınamaz.
        if ($isEdit && $target->isAdmin() && $target->isActive()
            && ($role !== Role::ADMIN || $status !== 'aktif')
            && !$this->users()->otherActiveAdminExists($target->id)) {

            $validator->addError('rol', 'Sistemdeki son aktif yönetici bu şekilde değiştirilemez.');
        }

        $newAvatar = null;

        if ($request->hasFile('avatar')) {
            try {
                if ($validator->passes()) {
                    $newAvatar = Uploader::image((array) $request->file('avatar'));
                } else {
                    Uploader::validate((array) $request->file('avatar'));
                }
            } catch (RuntimeException $e) {
                $validator->addError('avatar', $e->getMessage());
            }
        }

        if ($validator->fails()) {
            if ($newAvatar !== null) {
                Uploader::delete($newAvatar);
            }

            Response::error('Lütfen formdaki hataları düzeltin.', 422, ['errors' => $validator->errors()]);
        }

        $data = $validator->validated();

        $payload = [
            'ad'            => $data['ad'],
            'soyad'         => $data['soyad'],
            'kullanici_adi' => $data['kullanici_adi'],
            'eposta'        => $data['eposta'],
            'telefon'       => $data['telefon'] ?? '',
            'hakkinda'      => $data['hakkinda'] ?? '',
            'rol'           => $role,
            'durum'         => $status,
        ];

        if ($newAvatar !== null) {
            $payload['avatar'] = $newAvatar;
        }

        if ($isEdit) {
            if (!empty($data['sifre'])) {
                $payload['sifre'] = $data['sifre'];
            }

            $this->users()->update($target->id, $payload);

            if ($newAvatar !== null && $target->avatar !== '') {
                Uploader::delete($target->avatar);
            }

            Response::success('Kullanıcı başarıyla güncellendi.', ['id' => $target->id]);
        }

        $payload['sifre'] = (string) $data['sifre'];
        $newId = $this->users()->create($payload);

        Response::success('Kullanıcı başarıyla eklendi.', ['id' => $newId]);
    }

    /* =================================================================
     *  4) SİLME
     * ============================================================== */
    public function delete(Request $request): void
    {
        $target = $this->requireUser($request);

        if (Auth::isSelf($target->id)) {
            Response::error('Kendi hesabınızı silemezsiniz.', 403);
        }

        if ($target->isAdmin() && !$this->users()->otherActiveAdminExists($target->id)) {
            Response::error('Sistemdeki son yönetici silinemez.', 409);
        }

        if (!$this->users()->delete($target->id)) {
            Response::error('Silinecek kayıt bulunamadı.', 404);
        }

        if ($target->avatar !== '') {
            Uploader::delete($target->avatar);
        }

        Response::success('Kullanıcı başarıyla silindi.', ['id' => $target->id]);
    }

    /* =================================================================
     *  5) DURUM DEĞİŞTİRME
     * ============================================================== */
    public function status(Request $request): void
    {
        $target = $this->requireUser($request);

        if (Auth::isSelf($target->id)) {
            Response::error('Kendi hesabınızın durumunu değiştiremezsiniz.', 403);
        }

        $newStatus = $target->durum === 'aktif' ? 'pasif' : 'aktif';

        if ($newStatus !== 'aktif' && $target->isAdmin() && !$this->users()->otherActiveAdminExists($target->id)) {
            Response::error('Sistemdeki son aktif yönetici pasifleştirilemez.', 409);
        }

        $this->users()->update($target->id, ['durum' => $newStatus]);

        Response::success(
            $newStatus === 'aktif' ? 'Hesap aktifleştirildi.' : 'Hesap pasifleştirildi.',
            ['id' => $target->id, 'durum' => $newStatus]
        );
    }

    private function requireUser(Request $request, string $field = 'id'): User
    {
        $id = $request->int($field, 1);

        if ($id === null) {
            Response::error('Geçersiz kayıt numarası.', 400);
        }

        $user = $this->users()->find($id);

        if ($user === null) {
            Response::error('Kayıt bulunamadı.', 404);
        }

        return $user;
    }
}
