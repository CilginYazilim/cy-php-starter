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
        $this->view('users/index', [
            'title'    => 'Kullanıcılar',
            'subtitle' => 'Panel kullanıcılarını görüntüleyin, ekleyin ve düzenleyin.',
            'roles'    => Role::options(),
            'scripts'  => ['users.js'],
        ]);
    }
}
