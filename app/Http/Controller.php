<?php
/**
 * =====================================================================
 *  Controller – Tüm denetleyicilerin ortak atası
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http;

use App\Core\Database;
use App\Core\View;
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

    protected function users(): UserRepository
    {
        return new UserRepository($this->db);
    }

    protected function messages(): MessageRepository
    {
        return new MessageRepository($this->db);
    }

    /** @param array<string,mixed> $data */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        View::render($view, $data, $layout);
    }
}
