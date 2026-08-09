<?php
/**
 * =====================================================================
 *  AuthController – Giriş / kayıt / çıkış
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Setting;
use App\Core\Validator;
use App\Http\Controller;

final class AuthController extends Controller
{
    /**
     * kurulum/database.sql ile birlikte gelen örnek kullanıcılar.
     * Yalnızca geliştirme ortamında (APP_DEBUG=true) giriş ekranında
     * gösterilir — canlıda parolası bilinen hesapların önerilmesi
     * güvenlik açığıdır.
     *
     * @var array<int,array<string,string>>
     */
    private const DEMO_ACCOUNTS = [
        ['identifier' => 'elif.editor', 'password' => 'Demo1234!', 'name' => 'Elif Demir',  'label' => 'Editör',     'variant' => 'editor',  'icon' => 'edit'],
        ['identifier' => 'mehmet.uye',  'password' => 'Demo1234!', 'name' => 'Mehmet Kaya',  'label' => 'Üye',        'variant' => 'member',  'icon' => 'user'],
        ['identifier' => 'ayse.pasif',  'password' => 'Demo1234!', 'name' => 'Ayşe Şahin',   'label' => 'Pasif Üye',  'variant' => 'passive', 'icon' => 'user'],
        ['identifier' => 'can.askida',  'password' => 'Demo1234!', 'name' => 'Can Yıldız',   'label' => 'Askıda Üye', 'variant' => 'hold',    'icon' => 'user'],
    ];

    public function showLogin(Request $request): void
    {
        if (Session::pull('_expired') === true) {
            Flash::warning('Uzun süre işlem yapılmadığı için oturumunuz sonlandırıldı.');
        }

        $this->view('auth/login', [
            'title'        => 'Giriş Yap',
            'errors'       => Flash::errors(),
            'old'          => Flash::old(),
            'scripts'      => ['login.js'],
            'demoAccounts' => Config::get('app.debug', false) ? self::DEMO_ACCOUNTS : [],
        ], 'layouts/site');
    }

    public function login(Request $request): void
    {
        $identifier = $request->input('identifier');
        $password   = (string) ($_POST['password'] ?? '');

        $errors = [];

        if ($identifier === '') {
            $errors['identifier'] = 'E-posta veya kullanıcı adı boş bırakılamaz.';
        }
        if ($password === '') {
            $errors['password'] = 'Parola alanı boş bırakılamaz.';
        }

        if ($errors !== []) {
            Flash::withInput($errors, ['identifier' => $identifier]);
            Response::redirect(url('giris'));
        }

        $result = Auth::attempt($identifier, $password, $request);

        if (!$result['ok']) {
            Flash::error($result['message']);
            Flash::withInput([], ['identifier' => $identifier]);
            Response::redirect(url('giris'));
        }

        Flash::success($result['message']);

        $intended = (string) Session::pull('_intended', '');
        Response::redirect(url($intended !== '' ? $intended : 'panel'));
    }

    public function showRegister(Request $request): void
    {
        if (!Setting::bool('sistem_kayit_acik', false)) {
            Flash::warning('Yeni kayıtlar şu anda kapalı.');
            Response::redirect(url('giris'));
        }

        $this->view('auth/register', [
            'title'   => 'Kayıt Ol',
            'errors'  => Flash::errors(),
            'old'     => Flash::old(),
            'scripts' => ['register.js'],
        ], 'layouts/site');
    }

    public function register(Request $request): void
    {
        if (!Setting::bool('sistem_kayit_acik', false)) {
            Flash::error('Yeni kayıtlar şu anda kapalı.');
            Response::redirect(url('giris'));
        }

        $validator = new Validator($_POST);
        $validator->name('ad', 'Ad')
                  ->name('soyad', 'Soyad')
                  ->username('kullanici_adi')
                  ->email('eposta')
                  ->password('sifre', true, 'sifre_tekrar');

        if ($validator->passes() && $this->users()->fieldTaken('eposta', (string) $validator->validated()['eposta'])) {
            $validator->addError('eposta', 'Bu e-posta adresi zaten kayıtlı.');
        }
        if ($validator->passes() && $this->users()->fieldTaken('kullanici_adi', (string) $validator->validated()['kullanici_adi'])) {
            $validator->addError('kullanici_adi', 'Bu kullanıcı adı zaten alınmış.');
        }

        if ($validator->fails()) {
            Flash::withInput($validator->errors(), $_POST);
            Response::redirect(url('kayit'));
        }

        $data = $validator->validated();

        // Yeni kayıt olan herkes "uye" rolüyle başlar; kimse formdan
        // rol göndererek kendini yönetici yapamaz (bilerek okunmuyor).
        $id = $this->users()->create([
            'ad'            => $data['ad'],
            'soyad'         => $data['soyad'],
            'kullanici_adi' => $data['kullanici_adi'],
            'eposta'        => $data['eposta'],
            'sifre'         => $data['sifre'],
            'rol'           => 'uye',
            'durum'         => 'aktif',
        ]);

        $user = $this->users()->find($id);

        if ($user !== null) {
            Auth::login($user);
        }

        Flash::success('Hesabınız oluşturuldu. Hoş geldiniz!');
        Response::redirect(url('panel'));
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Session::start();
        Flash::info('Oturumunuz güvenli bir şekilde kapatıldı.');
        Response::redirect(url('giris'));
    }
}
