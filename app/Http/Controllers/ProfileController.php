<?php
/**
 * =====================================================================
 *  ProfileController – "Hesabım" sayfası
 * ---------------------------------------------------------------------
 *  KRİTİK GÜVENLİK NOKTASI: Güncellenecek kayıt ID'si formdan DEĞİL,
 *  oturumdan alınır (Auth::id()). "rol" ve "durum" alanları burada
 *  HİÇ okunmaz; kullanıcı kendini yönetici yapamaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Core\Validator;
use App\Http\Controller;
use RuntimeException;

final class ProfileController extends Controller
{
    public function index(Request $request): void
    {
        $user = Auth::user();

        $this->view('profile/index', [
            'title'    => 'Hesabım',
            'subtitle' => 'Kişisel bilgilerinizi ve parolanızı buradan güncelleyin.',
            'user'     => $user,
            'errors'   => Flash::errors(),
            'old'      => Flash::old(),
        ]);
    }

    public function update(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        $validator = new Validator($_POST);
        $validator->name('ad', 'Ad')
                  ->name('soyad', 'Soyad')
                  ->email('eposta')
                  ->phone('telefon')
                  ->text('hakkinda', 'Hakkımda', 0, 1000);

        if ($validator->passes() && $this->users()->fieldTaken('eposta', (string) $validator->validated()['eposta'], $user->id)) {
            $validator->addError('eposta', 'Bu e-posta adresi başka bir hesapta kayıtlı.');
        }

        if ($validator->fails()) {
            Flash::error('Lütfen formdaki hataları düzeltin.');
            Flash::withInput($validator->errors(), $_POST);
            Response::redirect(url('panel/hesabim'));
        }

        $data = $validator->validated();

        $this->users()->update($user->id, [
            'ad'       => $data['ad'],
            'soyad'    => $data['soyad'],
            'eposta'   => $data['eposta'],
            'telefon'  => $data['telefon'] ?? '',
            'hakkinda' => $data['hakkinda'] ?? '',
        ]);

        Flash::success('Bilgileriniz güncellendi.');
        Response::redirect(url('panel/hesabim'));
    }

    public function password(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        $current = (string) ($_POST['mevcut_sifre'] ?? '');

        $validator = new Validator($_POST);
        $validator->password('yeni_sifre', true, 'yeni_sifre_tekrar');

        if (!$user->verifyPassword($current)) {
            $validator->addError('mevcut_sifre', 'Mevcut parolanız hatalı.');
        }
        if ($current !== '' && $current === (string) ($_POST['yeni_sifre'] ?? '')) {
            $validator->addError('yeni_sifre', 'Yeni parola, mevcut parolanızdan farklı olmalıdır.');
        }

        if ($validator->fails()) {
            Flash::error('Parola güncellenemedi.');
            Flash::withInput($validator->errors(), []);
            Response::redirect(url('panel/hesabim'));
        }

        $this->users()->update($user->id, ['sifre' => (string) $validator->validated()['yeni_sifre']]);

        Flash::success('Parolanız güncellendi.');
        Response::redirect(url('panel/hesabim'));
    }

    /**
     * Açık/koyu tema tercihini kaydeder (üst çubuktaki düğmeden AJAX ile
     * çağrılır). Böylece kullanıcı farklı bir cihaz/tarayıcıdan giriş
     * yaptığında da kendi seçtiği temayı görür — yalnızca çereze değil,
     * hesaba bağlıdır.
     */
    public function updateTheme(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::error('Oturum bulunamadı.', 401);
        }

        $tema = $request->input('tema') === 'koyu' ? 'koyu' : 'acik';

        $this->users()->updateTheme($user->id, $tema);

        Response::success('Tema tercihi kaydedildi.', ['tema' => $tema]);
    }

    public function uploadAvatar(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        if (!$request->hasFile('avatar')) {
            Flash::error('Dosya seçilmedi.');
            Response::redirect(url('panel/hesabim'));
        }

        try {
            $newAvatar = Uploader::avatar((array) $request->file('avatar'));
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect(url('panel/hesabim'));
        }

        $old = $user->avatar;

        $this->users()->update($user->id, ['avatar' => $newAvatar]);

        if ($old !== '' && $old !== $newAvatar) {
            Uploader::delete($old);
        }

        Flash::success('Profil fotoğrafı güncellendi.');
        Response::redirect(url('panel/hesabim'));
    }

    public function removeAvatar(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect(url('giris'));
        }

        if ($user->avatar === '') {
            Flash::error('Kaldırılacak profil fotoğrafı yok.');
            Response::redirect(url('panel/hesabim'));
        }

        $this->users()->update($user->id, ['avatar' => '']);
        Uploader::delete($user->avatar);

        Flash::success('Profil fotoğrafı kaldırıldı.');
        Response::redirect(url('panel/hesabim'));
    }
}
