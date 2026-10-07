<?php
/**
 * =====================================================================
 *  NotificationController – Duyuru mektubundaki "almak istemiyorum"
 * ---------------------------------------------------------------------
 *  GET duyurular/iptal?k={kullanıcı}&i={imza}
 *
 *  Giriş GEREKTİRMEZ: bağlantı İMZALIDIR (NotificationPrefs::
 *  unsubscribeUrl), başka bir kullanıcının numarasıyla üretilemez.
 *  Tek tıkla kapatır; tekrar açmak Hesabım → E-posta Bildirimleri'nden.
 *  İşlem geri alınabilir ve yalnızca duyuruları etkiler (güvenlik
 *  mektupları kapanmaz), bu yüzden GET ile yapılması güvenlidir —
 *  posta istemcilerinin bağlantıyı önceden açması en kötü ihtimalle
 *  duyuruları kapatır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\NotificationPrefs;
use App\Core\Request;
use App\Http\Controller;

final class NotificationController extends Controller
{
    public function unsubscribe(Request $request): void
    {
        $id      = (int) $request->input('k', '0');
        $gecerli = NotificationPrefs::verify($id, $request->input('i'));
        $user    = $gecerli ? $this->users()->find($id) : null;

        $kapatildi = false;

        if ($user !== null) {
            $tercih = $this->users()->notificationPrefs($user->id);
            $tercih[NotificationPrefs::DUYURU] = false;
            $kapatildi = $this->users()->saveNotificationPrefs($user->id, $tercih);
        }

        if (!$kapatildi) {
            http_response_code($user === null ? 403 : 500);
        }

        $this->view('site/duyuru-iptal', [
            'title'     => 'Duyurular',
            'noindex'   => true,
            'kapatildi' => $kapatildi,
            'gecersiz'  => $user === null,
        ], 'layouts/site');
    }
}
