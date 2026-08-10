<?php
/**
 * =====================================================================
 *  OLAY: Yeni bir kullanıcı kaydoldu
 * ---------------------------------------------------------------------
 *  Kayıt VERİTABANINA YAZILDIKTAN sonra yayınlanır. Dinleyiciler
 *  kullanıcının var olduğuna güvenebilir.
 *
 *  TİPİK DİNLEYİCİLER
 *    · Karşılama e-postası (bu şablonda hazır)
 *    · Yöneticiye bildirim
 *    · ERP/CRM modülünde personel ya da müşteri kartı açmak
 *    · Varsayılan tercihleri oluşturmak
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Events;

use App\Core\Events\Event;
use App\Models\User;

final class UserRegistered extends Event
{
    public function __construct(public readonly User $user)
    {
        parent::__construct();
    }

    public function toArray(): array
    {
        return ['kullanici' => $this->user->id, 'rol' => $this->user->rol];
    }
}
