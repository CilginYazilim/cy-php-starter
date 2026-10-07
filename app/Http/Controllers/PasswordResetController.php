<?php
/**
 * =====================================================================
 *  PasswordResetController – "Parolamı unuttum" ekranları
 * ---------------------------------------------------------------------
 *  İş kuralları App\Core\PasswordReset'tedir; burada yalnızca form,
 *  hız sınırı ve yanıt vardır.
 *
 *  AYNI YANIT: Adres kayıtlı olsun olmasın, etkin olsun olmasın ekran
 *  hep aynı cümleyi söyler. Aksi hâlde bu form "şu e-posta üye mi?"
 *  diye sormanın bir yolu olurdu.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Flash;
use App\Core\Ip;
use App\Core\Log\Logger;
use App\Core\PasswordReset;
use App\Core\Request;
use App\Core\Response;
use App\Core\Throttle;
use App\Core\Validator;
use App\Http\Controller;

final class PasswordResetController extends Controller
{
    private const GONDERILDI = 'Bu adrese kayıtlı etkin bir hesap varsa parola sıfırlama bağlantısını gönderdik. Birkaç dakika içinde gelmezse istenmeyen posta klasörüne bakın.';

    /** IP başına saatte en fazla bu kadar istek. */
    private const IP_SAATLIK = 10;

    public function showRequest(Request $request): void
    {
        self::requireEnabled();

        $this->view('auth/forgot', [
            'title'   => 'Parolamı Unuttum',
            'noindex' => true,
            'errors'  => Flash::errors(),
            'old'     => Flash::old(),
        ], 'layouts/site');
    }

    public function sendLink(Request $request): void
    {
        self::requireEnabled();

        $eposta = mb_strtolower(trim($request->input('eposta')));

        if ($eposta === '' || filter_var($eposta, FILTER_VALIDATE_EMAIL) === false) {
            Flash::withInput(['eposta' => 'Geçerli bir e-posta adresi yazın.'], ['eposta' => $eposta]);
            Response::redirect(url('parolami-unuttum'));
        }

        $bekle = Throttle::attempt('parola-sifirlama-ip:' . Ip::bucket($request->ip()), self::IP_SAATLIK, 3600);

        if ($bekle > 0) {
            Logger::security('Parola sıfırlama: IP saatlik sınırı aşıldı', ['ip' => $request->ip()]);

            Flash::error(sprintf('Bu adresten çok fazla istek yapıldı. Lütfen %d dakika sonra tekrar deneyin.', (int) ceil($bekle / 60)));
            Response::redirect(url('parolami-unuttum'));
        }

        PasswordReset::request($eposta, $request->ip());

        Flash::success(self::GONDERILDI);
        Response::redirect(url('giris'));
    }

    public function showReset(Request $request): void
    {
        self::noReferrer();

        $jeton = $request->input('jeton');
        $tur   = PasswordReset::kind($jeton);

        self::requireEnabled($tur);

        if ($tur === null) {
            Flash::error('Bağlantı geçersiz, kullanılmış ya da süresi dolmuş. Yeni bir bağlantı isteyin.');
            Response::redirect(url('parolami-unuttum'));
        }

        $ilk = $tur === PasswordReset::TUR_ACILIS;

        $this->view('auth/reset', [
            'title'   => $ilk ? 'Hesabınızı Etkinleştirin' : 'Yeni Parola',
            'noindex' => true,
            'jeton'   => $jeton,
            'ilk'     => $ilk,
            'errors'  => Flash::errors(),
        ], 'layouts/site');
    }

    public function reset(Request $request): void
    {
        $jeton = $request->string('jeton');
        $geri  = url('parola-sifirla', ['jeton' => $jeton]);
        $ilk   = PasswordReset::kind($jeton) === PasswordReset::TUR_ACILIS;   // jeton tüketilmeden önce

        self::requireEnabled($ilk ? PasswordReset::TUR_ACILIS : PasswordReset::TUR_SIFIRLAMA);

        $validator = (new Validator($_POST))->password('sifre', true, 'sifre_tekrar');

        if ($validator->fails()) {
            Flash::withInput($validator->errors(), []);
            Response::redirect($geri);
        }

        $user = PasswordReset::reset($jeton, (string) $validator->validated()['sifre']);

        if ($user === null) {
            Flash::error('Bağlantı geçersiz, kullanılmış ya da süresi dolmuş. Yeni bir bağlantı isteyin.');
            Response::redirect(url('parolami-unuttum'));
        }

        Flash::success($ilk
            ? 'Hesabınız hazır. Giriş yapabilirsiniz.'
            : 'Parolanız değiştirildi. Yeni parolanızla giriş yapabilirsiniz; diğer cihazlardaki oturumlarınız kapatıldı.');
        Response::redirect(url('giris'));
    }

    /**
     * Ayar kapalıysa ya da site e-posta gönderemiyorsa "Parolamı unuttum"
     * akışı yoktur. Hesap AÇILIŞ bağlantısı bundan muaftır: mektup zaten
     * gitti; yönetici sıfırlamayı kapattı diye yeni kullanıcı parolasını
     * belirleyemez olmamalı.
     */
    private static function requireEnabled(?string $tur = null): void
    {
        if ($tur !== PasswordReset::TUR_ACILIS && !PasswordReset::enabled()) {
            Flash::warning('Parola sıfırlama şu anda kullanılamıyor. Site yöneticisiyle iletişime geçin.');
            Response::redirect(url('giris'));
        }
    }

    /** Jetonlu adres başka bir siteye "Referer" olarak sızmasın. */
    private static function noReferrer(): void
    {
        if (!headers_sent()) {
            header('Referrer-Policy: no-referrer');
            header('Cache-Control: private, no-store');
        }
    }
}
