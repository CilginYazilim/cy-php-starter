<?php
/**
 * =====================================================================
 *  DashboardController – Kontrol paneli ana sayfası
 * ---------------------------------------------------------------------
 *  HER KART KENDİ YETKİSİNE BAĞLIDIR. Eskiden bütün özet tek bir
 *  "dashboard.stats" yetkisine bağlıydı ve editör, kullanıcı ekranına
 *  hiç giremediği hâlde son eklenen kullanıcıların adını, rolünü ve
 *  toplam sayıları görüyordu. Artık:
 *
 *      Kullanıcı kartları, son eklenenler, grafik → users.view
 *      Mesaj kartı ve son mesajlar               → messages.view
 *      E-posta kartı                             → mail.view
 *      Taslak sayfa kartı (kullanıcı yöneticisi değilse) → pages.view
 *      Modül kartları                            → modülün kendi kuralı
 *                                                  (bkz. DashboardCards)
 *
 *  Kartı olmayan roller (üye) profil tamamlama ilerlemesini ve
 *  erişebildikleri bölümlerin kısayollarını görür.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DashboardCards;
use App\Core\Request;
use App\Http\Controller;
use App\Repositories\PageRepository;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $user   = Auth::user();
        $kartlar = [];
        $data   = ['title' => 'Kontrol Paneli'];

        if (Auth::can('users.view')) {
            $users  = $this->users();
            $toplam = $users->countAll();
            $aktif  = $users->countByStatus('aktif');
            $pasif  = $users->countByStatus('pasif') + $users->countByStatus('askida');
            $onay   = $users->countByStatus('onay_bekliyor');
            $son7   = $users->countSince(7);

            $kartlar[] = ['ikon' => 'users', 'renk' => 'brand', 'etiket' => 'Kullanıcı', 'deger' => $toplam,
                          'ipucu' => 'son 7 günde +' . $son7, 'yol' => 'panel/kullanicilar'];

            /* Toplam = aktif + pasif/askıda + onay bekleyen. Eskiden
             * "onay bekliyor" hiçbir kartta sayılmıyor, sayılar tutmuyordu. */
            $kartlar[] = ['ikon' => 'check', 'renk' => $onay > 0 ? 'warning' : 'success', 'etiket' => 'Aktif hesap', 'deger' => $aktif,
                          'ipucu' => $pasif . ' pasif / askıda' . ($onay > 0 ? ' · ' . $onay . ' onay bekliyor' : ''),
                          'yol' => $onay > 0 ? 'panel/kullanicilar?durum=onay_bekliyor' : 'panel/kullanicilar'];

            $data['latestUsers'] = $users->latest(5);
            $data['chart']       = $this->chartSeries($users->dailyCounts(14), 14);
        }

        if (Auth::can('messages.view')) {
            $messages = $this->messages();
            $okunmamis = $messages->countUnread();

            $kartlar[] = ['ikon' => 'inbox', 'renk' => $okunmamis > 0 ? 'warning' : 'brand', 'etiket' => 'Mesaj',
                          'deger' => $messages->countAll(), 'ipucu' => $okunmamis . ' okunmamış', 'yol' => 'panel/mesajlar'];

            $data['messageStats']   = ['unread' => $okunmamis];
            $data['latestMessages'] = $messages->latest(5);
        }

        if (Auth::can('mail.view')) {
            $mail = $this->mails()->stats();

            $kartlar[] = ['ikon' => 'send', 'renk' => $mail['basarisiz'] > 0 ? 'danger' : 'brand', 'etiket' => 'E-posta kuyruğu',
                          'deger' => $mail['kuyrukta'],
                          'ipucu' => $mail['bugun'] . ' bugün gitti' . ($mail['basarisiz'] > 0 ? ' · ' . $mail['basarisiz'] . ' başarısız' : ''),
                          'yol' => 'panel/eposta'];
        }

        // Editörün asıl işi içerik: taslak sayısı ona daha anlamlı.
        if (Auth::can('pages.view') && !Auth::can('users.view')) {
            $sayfalar = (new PageRepository($this->db))->stats();

            $kartlar[] = ['ikon' => 'files', 'renk' => $sayfalar['taslak'] > 0 ? 'warning' : 'brand', 'etiket' => 'Taslak sayfa',
                          'deger' => $sayfalar['taslak'], 'ipucu' => $sayfalar['yayin'] . ' sayfa yayında', 'yol' => 'panel/sayfalar'];
        }

        $data['kartlar'] = array_map(
            [DashboardCards::class, 'normalize'],
            array_merge($kartlar, DashboardCards::collect($user))
        );

        if ($user !== null && !Auth::can('users.view')) {
            $data['profil'] = $this->profileCompletion($user);
        }

        $this->view('dashboard/index', $data);
    }

    /**
     * Bir bilgi bildirimini bu oturum için kapatır (bkz. PanelNotices).
     * Çerezde değil oturumda tutulur: sunucu bildirimi hiç basmaz.
     */
    public function dismissNotice(Request $request): void
    {
        \App\Core\Response::json([
            'success' => \App\Core\PanelNotices::dismiss($request->input('id')),
        ]);
    }

    /**
     * "Profilini tamamla" ilerlemesi: fotoğraf, telefon, hakkında.
     *
     * @return array{yuzde:int,eksik:array<int,string>}
     */
    private function profileCompletion(\App\Models\User $user): array
    {
        $alanlar = [
            'Profil fotoğrafı' => $user->avatar !== '',
            'Telefon'          => $user->telefon !== '',
            'Hakkımda'         => trim($user->hakkinda) !== '',
        ];

        $dolu = count(array_filter($alanlar));

        return [
            'yuzde' => (int) round(($dolu + 1) / (count($alanlar) + 1) * 100), // ad/e-posta hep dolu: +1
            'eksik' => array_keys(array_filter($alanlar, static fn (bool $v): bool => !$v)),
        ];
    }

    /** @param array<string,int> $counts @return array<int,array{label:string,value:int}> */
    private function chartSeries(array $counts, int $days): array
    {
        $series = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));

            $series[] = [
                'label' => date('d.m', strtotime($date)),
                'value' => $counts[$date] ?? 0,
            ];
        }

        return $series;
    }
}
