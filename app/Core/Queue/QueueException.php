<?php
declare(strict_types=1);

namespace App\Core\Queue;

use RuntimeException;

/** Kuyruk altyapısı hataları (kayıp iş sınıfı, bozuk veri…). */
final class QueueException extends RuntimeException
{
}
