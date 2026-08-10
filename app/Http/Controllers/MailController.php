<?php
/**
 * =====================================================================
 *  MailController – E-posta merkezi SAYFASI
 * ---------------------------------------------------------------------
 *  İki sekme:
 *    "Gönder"    → tekil ya da toplu e-posta yazma ekranı
 *    "Geçmiş"    → giden mektupların listesi (gitti mi, gitmediyse neden?)
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Mail\Mailer;
use App\Core\Request;
use App\Core\Setting;
use App\Http\Controller;
use App\Models\Role;

final class MailController extends Controller
{
    public function index(Request $request): void
    {
        $config = Mailer::config();

        // Alıcı kutusuna doğrudan bir adres taşımak için:
        //   panel/eposta?adres=ali@ornek.com&konu=Re: ...
        // Mesaj penceresindeki "E-posta ile yanıtla" düğmesi bunu kullanır.
        $prefill = [
            'adres' => (string) ($_GET['adres'] ?? ''),
            'konu'  => (string) ($_GET['konu'] ?? ''),
        ];

        if ($prefill['adres'] !== '' && filter_var($prefill['adres'], FILTER_VALIDATE_EMAIL) === false) {
            $prefill['adres'] = '';
        }

        $this->view('mail/index', [
            'title'      => 'E-posta',
            'subtitle'   => 'Tekil veya toplu e-posta gönderin, giden mektupların geçmişini izleyin.',
            'scripts'    => ['mail.js'],
            'stats'      => $this->mails()->stats(),
            'config'     => $config,
            'roles'      => Role::options(),
            'users'      => $this->users()->pickList(),
            'sayilar'    => $this->audienceCounts(),
            'prefill'    => $prefill,
            'canSend'    => Auth::can('mail.send'),
            'gonderen'   => trim(($config['gonderenAd'] !== '' ? $config['gonderenAd'] . ' ' : '') . '<' . $config['gonderen'] . '>'),
            'yapilandi'  => Mailer::enabled() && Setting::get('mail_surucu', 'kayit') !== 'kayit',
        ]);
    }

    /**
     * Hedef kitle seçeneklerinin yanında görünen kişi sayıları.
     *
     * @return array<string,int>
     */
    private function audienceCounts(): array
    {
        $counts = ['tumu' => count($this->users()->mailRecipients('', 'aktif'))];

        foreach (array_keys(Role::options()) as $role) {
            $counts['rol:' . $role] = count($this->users()->mailRecipients($role, 'aktif'));
        }

        return $counts;
    }
}
