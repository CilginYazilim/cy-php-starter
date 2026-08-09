<?php
/**
 * =====================================================================
 *  ContactController – İletişim formu (ön yüz)
 * ---------------------------------------------------------------------
 *  BU UÇ HERKESE AÇIKTIR — giriş istemez. Açık uçlarda üç şeye ayrıca
 *  dikkat edilir: bal küpü (spam botu), hız sınırı, XSS (ekrana
 *  basarken kaçışlama).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Setting;
use App\Core\Validator;
use App\Http\Controller;

final class ContactController extends Controller
{
    public function show(Request $request): void
    {
        $this->view('site/contact', [
            'title'   => 'İletişim',
            'errors'  => \App\Core\Flash::errors(),
            'old'     => \App\Core\Flash::old(),
            'scripts' => ['contact.js'],
        ], 'layouts/site');
    }

    public function send(Request $request): void
    {
        if (!Setting::bool('sistem_iletisim_formu', true)) {
            Response::error('İletişim formu şu anda kapalı.', 403);
        }

        /* --- BAL KÜPÜ (honeypot) ---
         * Ekranda görünmeyen "website" alanı doluysa gönderen bir
         * bottur. Hata DÖNDÜRMÜYORUZ: bot denemesinin başarısız
         * olduğunu anlarsa yöntemini değiştirir. Başarılı gibi
         * davranıp mesajı sessizce çöpe atmak daha etkilidir. */
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            Response::success('Mesajınız alındı. Teşekkür ederiz.');
        }

        /* --- HIZ SINIRI ---
         * Aynı oturumdan 60 saniye içinde ikinci mesaja izin verilmez. */
        $lastSent = (int) Session::get('_last_message_at', 0);
        $cooldown = 60;

        if ($lastSent > 0 && (time() - $lastSent) < $cooldown) {
            $remaining = $cooldown - (time() - $lastSent);
            Response::error('Çok hızlı gönderiyorsunuz. Lütfen ' . $remaining . ' saniye bekleyin.', 429);
        }

        $validator = new Validator($_POST);
        $validator->name('ad', 'Ad')
                  ->email('eposta')
                  ->text('konu', 'Konu', 0, 190)
                  ->text('mesaj', 'Mesaj', 10, 4000, true);

        if ($validator->fails()) {
            Response::error('Lütfen formdaki hataları düzeltin.', 422, ['errors' => $validator->errors()]);
        }

        $data = $validator->validated();

        $this->messages()->create([
            'ad'           => $data['ad'],
            'eposta'       => $data['eposta'],
            'konu'         => $data['konu'] ?? '',
            'mesaj'        => $data['mesaj'],
            'kullanici_id' => Auth::id(),
            'ip'           => $request->ip(),
            'tarayici'     => $request->userAgent(),
        ]);

        Session::set('_last_message_at', time());

        Response::success('Mesajınız bize ulaştı. En kısa sürede dönüş yapacağız.');
    }
}
