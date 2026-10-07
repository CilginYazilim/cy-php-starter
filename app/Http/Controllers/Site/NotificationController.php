<?php
/**
 * =====================================================================
 *  NotificationController – Duyuru mektubundaki "almak istemiyorum"
 * ---------------------------------------------------------------------
 *  GET  duyurular/iptal?k={kullanıcı}&i={imza}  → onay sayfası
 *  POST duyurular/iptal?k={kullanıcı}&i={imza}  → duyuruları kapatır
 *
 *  Giriş GEREKTİRMEZ: bağlantı İMZALIDIR (NotificationPrefs::
 *  unsubscribeUrl), başka bir kullanıcının numarasıyla üretilemez.
 *
 *  NEDEN GET DEĞİŞTİRMEZ? 1.6.1'de bağlantı açılır açılmaz tercihi
 *  kapatıyordu. Kurumsal e-posta güvenlik tarayıcıları (Outlook Safe
 *  Links vb.) mektuptaki bağlantıları otomatik açar; üye hiçbir şeye
 *  tıklamadan duyurulardan çıkıyordu. Artık GET yalnızca "Duyuruları
 *  kapat" düğmesini gösterir.
 *
 *  POST'TA CSRF YOK, İMZA VAR: posta istemcilerinin "Abonelikten çık"
 *  düğmesi (RFC 8058, List-Unsubscribe-Post) bu adrese oturumsuz POST
 *  atar; CSRF jetonu taşıyamaz. Yetki imzadır: bağlantıyı yalnızca
 *  mektubun sahibi bilir, üstelik işlem geri alınabilir ve güvenlik
 *  mektuplarını kapatmaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\NotificationPrefs;
use App\Core\Request;
use App\Http\Controller;
use App\Models\User;

final class NotificationController extends Controller
{
    /** GET: imzayı doğrular, onay sayfasını gösterir; tercihi DEĞİŞTİRMEZ. */
    public function unsubscribe(Request $request): void
    {
        $user = $this->signedUser($request);

        if ($user === null) {
            http_response_code(403);
        }

        $this->render($request, $user === null ? 'gecersiz' : (
            $this->users()->notificationPrefs($user->id)[NotificationPrefs::DUYURU] ? 'onay' : 'zaten'
        ));
    }

    /** POST: duyuruları kapatır (sayfadaki düğme ya da posta istemcisinin tek tık isteği). */
    public function confirm(Request $request): void
    {
        $user = $this->signedUser($request);

        if ($user === null) {
            http_response_code(403);
            $this->render($request, 'gecersiz');

            return;
        }

        $tercih = $this->users()->notificationPrefs($user->id);
        $tercih[NotificationPrefs::DUYURU] = false;

        if (!$this->users()->saveNotificationPrefs($user->id, $tercih)) {
            http_response_code(500);
            $this->render($request, 'hata');

            return;
        }

        $this->render($request, 'kapatildi');
    }

    private function signedUser(Request $request): ?User
    {
        $id = (int) $request->input('k', '0');

        return NotificationPrefs::verify($id, $request->input('i')) ? $this->users()->find($id) : null;
    }

    private function render(Request $request, string $durum): void
    {
        $this->view('site/duyuru-iptal', [
            'title'   => 'Duyurular',
            'noindex' => true,
            'durum'   => $durum,
            'eylem'   => url('duyurular/iptal', ['k' => (int) $request->input('k', '0'), 'i' => $request->input('i')]),
        ], 'layouts/site');
    }
}
