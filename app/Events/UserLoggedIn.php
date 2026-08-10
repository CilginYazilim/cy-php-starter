<?php
/**
 * =====================================================================
 *  OLAY: Kullanıcı giriş yaptı
 * ---------------------------------------------------------------------
 *  Yalnızca BAŞARILI girişte yayınlanır. Başarısız denemeler olay
 *  değildir; onlar doğrudan "security" log kanalına yazılır
 *  (bkz. Auth::logFailure).
 *
 *  TİPİK DİNLEYİCİLER
 *    · "Yeni cihazdan giriş" uyarı e-postası
 *    · Son giriş konumu/istatistiği
 *    · Oturum açılınca hazırlanması gereken modül verileri
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Events;

use App\Core\Events\Event;
use App\Models\User;

final class UserLoggedIn extends Event
{
    public function __construct(
        public readonly User $user,
        public readonly string $ip = '',
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return ['kullanici' => $this->user->id, 'ip' => $this->ip];
    }
}
