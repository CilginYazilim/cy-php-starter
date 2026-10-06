<?php
/**
 * =====================================================================
 *  Registration – Kayıt formunun kuralları tek yerde
 * ---------------------------------------------------------------------
 *  · Kayıt açık mı?  (görünümler "Kayıt Ol" bağlantısını buna göre gösterir)
 *  · E-posta doğrulaması gerekli mi?
 *  · Doğrulama bağlantısı üret / doğrula
 *
 *  DOĞRULAMA BAĞLANTISI TABLO GEREKTİRMEZ. Bağlantı "kullanıcı + e-posta
 *  + son kullanma" taşır ve APP_KEY ile imzalanır (bkz. Signer). E-posta
 *  imzanın içinde olduğu için adres sonradan değişirse eski bağlantı
 *  çalışmaz; hesap zaten etkinse bağlantı hiçbir şey yapmaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Log\Logger;
use App\Core\Mail\Mailer;
use App\Models\User;

final class Registration
{
    /** Doğrulama bağlantısının ömrü (saat). */
    public const LINK_HOURS = 48;

    /** Ziyaretçi kayıt formunu kullanabilir mi? */
    public static function isOpen(): bool
    {
        if (!Setting::bool('sistem_kayit_acik', false)) {
            return false;
        }

        /* Doğrulama açık ama mektup ALICIYA ULAŞAMIYORSA form KAPANIR:
         * açılan hesaplar hiç etkinleştirilemez, ziyaretçi boşuna
         * bekler. Yönetici nedeni panel bildiriminde görür.
         *
         * Eskiden Mailer::enabled() soruluyordu; "Kayıt" sürücüsünde o
         * da true döndüğü için form açık kalıyor, mektup yalnızca
         * storage/mail/ klasörüne yazılıyordu.
         *
         * Geliştirme modunda (APP_DEBUG) form açık kalır: geliştirici
         * akışı dener, bağlantıyı Panel → E-posta'dan açar. */
        if (self::requiresVerification() && !Mailer::canDeliver() && !self::developmentPreview()) {
            return false;
        }

        return true;
    }

    /**
     * Geliştirme kolaylığı: APP_DEBUG açık ve mektuplar gönderilmiyorsa
     * doğrulama bağlantısı yöneticiye Panel → E-posta'da gösterilir.
     */
    public static function developmentPreview(): bool
    {
        return Config::isDebug() && !Mailer::canDeliver();
    }

    /**
     * Ayar satırı yoksa (migration bekliyor) KAPALI kabul edilir: o
     * şemada "onay_bekliyor" durumu da yoktur.
     */
    public static function requiresVerification(): bool
    {
        return Setting::bool('sistem_kayit_dogrulama', false);
    }

    /** Kayıt neden kapalı? (yönetici uyarısı için; açıksa boş) */
    public static function closedReason(): string
    {
        if (!Setting::bool('sistem_kayit_acik', false)) {
            return '';
        }

        if (self::requiresVerification() && !Mailer::canDeliver()) {
            return self::developmentPreview()
                ? 'Geliştirme modu: doğrulama mektupları gönderilmiyor; bağlantıyı Panel → E-posta geçmişindeki mektuptan açabilirsiniz. Yayında kayıt formu kapanır.'
                : 'Kayıt formu kapalı: e-posta doğrulaması açık ama mektuplar gönderilemiyor. SMTP ayarlayın ya da Ayarlar → Sistem\'den doğrulamayı kapatın.';
        }

        return '';
    }

    /** Mutlak doğrulama adresi. */
    public static function verificationLink(User $user): string
    {
        $son  = time() + self::LINK_HOURS * 3600;
        $imza = Signer::sign(self::payload($user->id, $user->eposta, $son));

        return Mailer::absolute(url('kayit/dogrula', ['k' => $user->id, 's' => $son, 'i' => $imza]));
    }

    /**
     * Bağlantıdaki değerleri doğrular.
     *
     * @return 'gecerli'|'suresi_doldu'|'gecersiz'
     */
    public static function checkLink(User $user, int $expires, string $signature): string
    {
        if (!Signer::check(self::payload($user->id, $user->eposta, $expires), $signature)) {
            Logger::security('Geçersiz e-posta doğrulama bağlantısı', ['kullanici' => $user->id]);

            return 'gecersiz';
        }

        return $expires < time() ? 'suresi_doldu' : 'gecerli';
    }

    private static function payload(int $userId, string $email, int $expires): string
    {
        return 'dogrula|' . $userId . '|' . mb_strtolower(trim($email)) . '|' . $expires;
    }
}
