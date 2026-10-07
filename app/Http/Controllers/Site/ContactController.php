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
use App\Core\Events\Events;
use App\Core\Exceptions\HttpException;
use App\Core\Log\Logger;
use App\Core\Privacy;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Setting;
use App\Core\Signer;
use App\Core\Validator;
use App\Events\ContactMessageReceived;
use App\Http\Controller;
use App\Models\Page;
use App\Repositories\PageRepository;

final class ContactController extends Controller
{
    /**
     * İletişim sayfası.
     *
     * Sayfanın ÜST METNİ artık kodda değil, "sayfalar" tablosundaki
     * "iletisim" kaydındadır; yönetici panelden düzenler. Rota bu
     * metotu doğrudan çağırmaz — Site\PageController slug'ı çözer ve
     * sayfayı buraya devreder (bkz. routes/web.php → "{slug}").
     */
    public function show(Request $request, ?Page $sayfa = null): void
    {
        $sayfa ??= (new PageRepository($this->db))->findPublished('iletisim');

        $this->view('site/contact', [
            'title'      => $sayfa?->baslikSeo() ?? 'İletişim',
            'ogAciklama' => $sayfa?->aciklamaSeo() ?? Setting::get('site_aciklama'),
            'sayfa'      => $sayfa,
            'iletisim'   => HomeController::contactCards(),
            // Öteki iç sayfalarla aynı yapısal veri (WebPage + BreadcrumbList).
            'jsonLd'     => $sayfa !== null ? [PageController::structuredData($sayfa)] : [],
            'errors'     => \App\Core\Flash::errors(),
            'old'        => \App\Core\Flash::old(),
            'scripts'    => ['contact.js'],
        ], 'layouts/site');
    }

    public function send(Request $request): void
    {
        if (!Setting::bool('sistem_iletisim_formu', true)) {
            Response::error('İletişim formu şu anda kapalı.', 403);
        }

        /* --- BAL KÜPÜ (honeypot) ---
         * Ekranda görünmeyen "cy_kontrol" alanı doluysa gönderen bir
         * bottur. Hata DÖNDÜRMÜYORUZ: bot denemesinin başarısız
         * olduğunu anlarsa yöntemini değiştirir. Başarılı gibi
         * davranıp mesajı sessizce çöpe atmak daha etkilidir.
         *
         * AMA SESSİZ ≠ İZSİZ. Bir zamanlar bu alanın adı "website" idi
         * ve tarayıcıların otomatik doldurma özelliği onu doldurduğu
         * için GERÇEK ziyaretçilerin mesajları da çöpe gidiyordu —
         * üstelik hiçbir yerde iz kalmadığı için haftalarca fark
         * edilmedi. Artık her eleme "security" kanalına yazılıyor:
         * bir daha aynı sorunu tespit etmek beş dakika sürer. */
        $honeypot = trim($request->string('cy_kontrol'));

        if ($honeypot !== '') {
            Logger::security('İletişim formu: bal küpü doldurulmuş, mesaj elendi', [
                'ip'       => $request->ip(),
                'uzunluk'  => mb_strlen($honeypot),
                'tarayici' => $request->userAgent(),
            ]);

            Response::success('Mesajınız alındı. Teşekkür ederiz.');
        }

        /* --- ÇOK HIZLI GÖNDERİM ---
         * Form üretildikten sonraki ilk 3 saniyede gelen gönderim
         * insan işi değildir. Bal küpünden daha güvenilir bir
         * ölçüttür çünkü otomatik doldurmadan etkilenmez.
         *
         * Damga İMZALIDIR ve ZORUNLUDUR (bkz. Signer::stamp). Eskiden
         * düz bir sayıydı: bot "1 saat önce" yazabiliyor ya da alanı hiç
         * göndermeyerek kontrolü tamamen atlayabiliyordu. */
        $yas = Signer::stampAge('iletisim', $request->string('cy_zaman'));

        if ($yas === null || $yas < 3) {
            Logger::security('İletişim formu: zaman damgası yok/geçersiz ya da insanüstü hız, mesaj elendi', [
                'ip'   => $request->ip(),
                'sure' => $yas,
            ]);

            Response::success('Mesajınız alındı. Teşekkür ederiz.');
        }

        /* --- HIZ SINIRI (İKİ KATMAN) ---
         * 1) Oturum: aynı tarayıcıdan 60 saniye içinde ikinci mesaj yok.
         *    Ucuzdur, veritabanına hiç gitmez.
         * 2) IP: saatte en fazla 5 mesaj. Birincisi TEK BAŞINA yetmez —
         *    çerezleri saklamayan bir betik her istekte yeni oturum
         *    açar ve oturum sayacını hiç görmez. */
        $lastSent = (int) Session::get('_last_message_at', 0);
        $cooldown = 60;

        if ($lastSent > 0 && (time() - $lastSent) < $cooldown) {
            $remaining = $cooldown - (time() - $lastSent);

            throw HttpException::tooManyRequests($remaining, 'Çok hızlı gönderiyorsunuz. Lütfen ' . $remaining . ' saniye bekleyin.');
        }

        if ($this->messages()->countFromIp($request->ip(), 3600) >= 5) {
            Logger::security('İletişim formu: IP saatlik sınırı aşıldı', [
                'ip'       => $request->ip(),
                'tarayici' => $request->userAgent(),
            ]);

            throw HttpException::tooManyRequests(3600, 'Bu adresten çok fazla mesaj gönderildi. Lütfen bir süre sonra tekrar deneyin.');
        }

        $validator = new Validator($_POST);
        $validator->name('ad', 'Ad')
                  ->email('eposta')
                  ->text('konu', 'Konu', 0, 190)
                  ->text('mesaj', 'Mesaj', 10, 4000, true);

        // KVKK: aydınlatma metni onayı (ayardan kapatılabilir, bkz. Privacy).
        if (Privacy::consentRequired() && !$request->bool('kvkk_onay')) {
            $validator->addError('kvkk_onay', Privacy::consentError());
        }

        if ($validator->fails()) {
            Response::error('Lütfen formdaki hataları düzeltin.', 422, ['errors' => $validator->errors()]);
        }

        $data = $validator->validated();

        $id = $this->messages()->create([
            'ad'           => $data['ad'],
            'eposta'       => $data['eposta'],
            'konu'         => $data['konu'] ?? '',
            'mesaj'        => $data['mesaj'],
            'kullanici_id' => Auth::id(),
            'ip'           => $request->ip(),
            'tarayici'     => $request->userAgent(),
        ]);

        Session::set('_last_message_at', time());

        /* --- OLAY ---
         * Mesaj ARTIK VERİTABANINDA. Bundan sonrası "olursa iyi olur"
         * bölümüdür. Denetleyici artık kimin haberdar edileceğini
         * BİLMİYOR: yalnızca "iletişim mesajı geldi" diye duyuruyor.
         *
         * Yöneticiye bildirim ve ziyaretçiye otomatik yanıt
         * app/Listeners/IletisimMesajiniBildir.php içinde; CRM'de talep
         * açmak ya da Slack'e düşmek isterseniz routes/events.php'ye
         * bir satır eklemeniz yeterli — burası değişmez. */
        $message = $this->messages()->find($id);

        if ($message !== null) {
            Events::dispatch(new ContactMessageReceived($message));
        }

        Response::success('Mesajınız bize ulaştı. En kısa sürede dönüş yapacağız.');
    }
}
