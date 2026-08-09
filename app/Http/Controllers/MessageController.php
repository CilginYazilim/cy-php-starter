<?php
/**
 * =====================================================================
 *  MessageController – Mesaj yönetimi SAYFASI
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Http\Controller;

final class MessageController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('messages/index', [
            'title'    => 'Mesajlar',
            'subtitle' => 'İletişim formundan gelen mesajları yönetin.',
            'scripts'  => ['messages.js'],
        ]);
    }
}
