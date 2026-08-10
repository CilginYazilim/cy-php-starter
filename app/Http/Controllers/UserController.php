<?php
/**
 * =====================================================================
 *  UserController – Kullanıcı yönetimi SAYFASI (AJAX işlemleri Api/'de)
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Http\Controller;
use App\Models\Role;

final class UserController extends Controller
{
    public function index(Request $request): void
    {
        /* Kart sayıları SAYFA AÇILIRKEN bir kez okunur. Tablo verisi
         * AJAX ile gelir ama bu dört sayı sayfanın "ilk izlenimi"dir;
         * ikinci bir istek beklemeden görünmeleri gerekir. */
        $users = $this->users();

        $this->view('users/index', [
            'title'    => 'Kullanıcılar',
            'subtitle' => 'Panel kullanıcılarını görüntüleyin, ekleyin ve düzenleyin.',
            'roles'    => Role::options(),
            'scripts'  => ['users.js'],
            'istatistik' => [
                'toplam'  => $users->countAll(),
                'aktif'   => $users->countByStatus('aktif'),
                'pasif'   => $users->countByStatus('pasif') + $users->countByStatus('askida'),
                'yonetici' => $users->countByRole(Role::ADMIN),
                'yeni'    => $users->countSince(7),
            ],
        ]);
    }
}
