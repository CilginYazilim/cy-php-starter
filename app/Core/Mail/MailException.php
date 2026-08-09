<?php
/**
 * =====================================================================
 *  MailException – E-posta katmanının tek hata tipi
 * ---------------------------------------------------------------------
 *  Taşıyıcılar (SMTP, PHP mail(), kayıt) sorun çıktığında HEP bu
 *  istisnayı fırlatır. Böylece çağıran kod tek bir catch bloğuyla
 *  bütün e-posta hatalarını yakalayabilir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

use RuntimeException;

final class MailException extends RuntimeException
{
}
