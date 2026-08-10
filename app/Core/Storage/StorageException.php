<?php
/**
 * =====================================================================
 *  StorageException – Dosya katmanı hataları
 * ---------------------------------------------------------------------
 *  Mesajları KULLANICIYA GÖSTERİLEBİLİR olacak şekilde yazın: dosya
 *  yolu, sunucu dizin yapısı veya sistem hatası metni içermesinler.
 *  Ayrıntı log'a gider (bkz. ErrorHandler).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Storage;

use RuntimeException;

final class StorageException extends RuntimeException
{
}
