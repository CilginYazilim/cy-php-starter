<?php
/**
 * =====================================================================
 *  EventException – Olay sisteminin yapılandırma hataları
 * ---------------------------------------------------------------------
 *  Bu istisna genellikle bir DİNLEYİCİ YANLIŞ KAYDEDİLDİĞİNDE atılır
 *  (var olmayan sınıf, handle() metodu olmayan sınıf gibi). Yani bir
 *  çalışma zamanı sorunu değil, routes/events.php içindeki bir yazım
 *  hatasıdır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Events;

use RuntimeException;

final class EventException extends RuntimeException
{
}
