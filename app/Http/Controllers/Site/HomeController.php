<?php
/**
 * =====================================================================
 *  HomeController – Ana sayfa (ön yüz)
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Request;
use App\Http\Controller;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('site/home', ['title' => ''], 'layouts/site');
    }

    public function about(Request $request): void
    {
        $this->view('site/about', ['title' => 'Hakkımızda'], 'layouts/site');
    }
}
