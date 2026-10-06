<?php
/**
 * =====================================================================
 *  PasswordConfirm – Hassas işlemden önce "parolanızı yeniden girin"
 * ---------------------------------------------------------------------
 *  Açık bırakılmış bir oturumu ele geçiren kişi, hesabın e-postasını ya
 *  da parolasını değiştirip hesabı kalıcı olarak elinden alabilir. Bu
 *  yüzden şu işlemler işlemi yapanın MEVCUT parolasını ister:
 *
 *    · Hesabım → e-posta / parola değişikliği, API anahtarı üretme
 *    · Kullanıcılar → bir YÖNETİCİNİN ya da kendi hesabının e-posta
 *      veya parolasını değiştirme
 *
 *  HIZ SINIRLIDIR (kullanıcı başına 15 dakikada 10 deneme): ele geçirilen
 *  oturum bu formları parola tahmin etmek için sınırsız kullanamaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class PasswordConfirm
{
    /** Parola yanlış. (Diğer dönüş metinleri hız sınırı mesajıdır.) */
    public const WRONG = 'yanlis';

    /**
     * @return string|null null → doğru; WRONG → yanlış; başka metin → hız sınırı mesajı
     */
    public static function check(int $userId, string $plain): ?string
    {
        $bekle = Throttle::attempt('parola-dogrula:' . $userId, 10, 900);

        if ($bekle > 0) {
            return sprintf('Çok fazla parola denemesi yaptınız. Lütfen %d dakika sonra tekrar deneyin.', (int) ceil($bekle / 60));
        }

        /* Auth::user() parola özetini TAŞIMAZ (UserRepository::find()
         * "sifre" sütununu okumaz); özeti bu iş için ayrıca okuyoruz. */
        $hesap = (new UserRepository(Database::connection()))->findWithPassword($userId);

        return $hesap !== null && $plain !== '' && $hesap->verifyPassword($plain) ? null : self::WRONG;
    }
}
