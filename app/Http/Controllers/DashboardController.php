<?php
/**
 * =====================================================================
 *  DashboardController – Kontrol paneli ana sayfası
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Http\Controller;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $data = [
            'title' => 'Kontrol Paneli',
        ];

        if (Auth::can('dashboard.stats')) {
            $users = $this->users();

            $data['stats'] = [
                'total'   => $users->countAll(),
                'active'  => $users->countByStatus('aktif'),
                'passive' => $users->countByStatus('pasif') + $users->countByStatus('askida'),
                'last7'   => $users->countSince(7),
                'last30'  => $users->countSince(30),
            ];

            $data['latestUsers'] = $users->latest(5);
            $data['chart']       = $this->chartSeries($users->dailyCounts(14), 14);

            if (Auth::can('messages.view')) {
                $messages = $this->messages();

                $data['messageStats'] = [
                    'total'   => $messages->countAll(),
                    'unread'  => $messages->countUnread(),
                    'today'   => $messages->countToday(),
                ];

                $data['latestMessages'] = $messages->latest(5);
            }

            if (Auth::can('mail.view')) {
                $data['mailStats'] = $this->mails()->stats();
            }
        }

        $this->view('dashboard/index', $data);
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
