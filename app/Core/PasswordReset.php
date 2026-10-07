<?php
/**
 * =====================================================================
 *  PasswordReset – "Parolamı unuttum" akışı
 * ---------------------------------------------------------------------
 *      1) /parolami-unuttum   → e-posta yazılır. Yanıt HER DURUMDA
 *                               aynıdır: ekrandan "bu adres kayıtlı mı"
 *                               öğrenilemez.
 *      2) Mektup              → tek kullanımlık, 60 dakika geçerli bağlantı
 *      3) /parola-sifirla     → yeni parola. Başarıda bütün oturumlar,
 *                               "beni hatırla" ve API anahtarları iptal
 *                               edilir (UserRepository::update), sahibine
 *                               "parolanız değişti" mektubu gider.
 *
 *  JETON VERİTABANINDA DURMAZ: yalnızca SHA-256 özeti saklanır. Tablo
 *  sızsa bile bağlantı üretilemez. Jeton 256 bit rastgeledir; özetin
 *  tuzsuz olması bu yüzden sorun değildir (tahmin edilemez).
 *
 *  KAPALI OLDUĞU DURUMLAR: Panel → Ayarlar → Sistem → "Parola sıfırlama"
 *  kapalıysa ya da site e-posta gönderemiyorsa (Mailer::canDeliver)
 *  akış çalışmaz ve giriş ekranındaki bağlantı gizlenir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Events\Events;
use App\Core\Log\Logger;
use App\Core\Mail\Mailer;
use App\Core\Mail\Notifier;
use App\Events\PasswordChanged;
use App\Models\User;
use App\Repositories\UserRepository;
use PDO;

final class PasswordReset
{
    /** Bağlantının ömrü (dakika). */
    public const DAKIKA = 60;

    /** Aynı adrese saatte en fazla bu kadar bağlantı gider. */
    private const SAATLIK_ADRES = 3;

    /**
     * "Parolamı unuttum" açık mı? Ayar açık VE mektup gerçekten gidebiliyor
     * olmalı. Geliştirme önizlemesinde (APP_DEBUG + Kayıt sürücüsü) de
     * açıktır: mektup diske yazılır, bağlantı doğrulama mektubunda olduğu
     * gibi Panel → E-posta'da yöneticiye görünür (MailApiController::
     * developmentLink). Gövde maskesi (MailLog::GUVENLIK_SABLONLARI)
     * değişmez: editör yine göremez.
     */
    public static function enabled(): bool
    {
        return Setting::bool('sistem_parola_sifirlama', true) && self::canSendLinks();
    }

    /**
     * Bağlantılı mektup (sıfırlama, hesap açılışı) alıcısına ulaşabilir
     * mi? Gerçek gönderim ya da geliştirme önizlemesi (bağlantı panelde).
     */
    public static function canSendLinks(): bool
    {
        return Mailer::canDeliver() || Registration::developmentPreview();
    }

    /**
     * Bağlantı ister. Hesap yoksa, etkin değilse ya da sınır dolmuşsa
     * SESSİZCE hiçbir şey yapmaz; çağıran her durumda aynı yanıtı verir.
     *
     * @return bool Mektup kuyruğa/gönderime girdi mi (yalnızca testler ve günlük için)
     */
    public static function request(string $email, string $ip): bool
    {
        $email = mb_strtolower(trim($email));
        $db    = Database::connection();
        $user  = (new UserRepository($db))->findByEmail($email);

        if ($user === null || !$user->isActive()) {
            Logger::info('Parola sıfırlama: kayıtlı/etkin olmayan adres (yanıt aynı)', ['ip' => $ip], 'auth');

            return false;
        }

        // Demo hesapları ortaktır; parolaları ziyaretçiye değiştirtilmez.
        if (Demo::enabled() && Demo::isDemoUser($user)) {
            return false;
        }

        if (Throttle::attempt('parola-sifirlama-adres:' . hash('sha256', $email), self::SAATLIK_ADRES, 3600) > 0) {
            Logger::security('Parola sıfırlama: adres başına saatlik sınır doldu', ['kullanici' => $user->id, 'ip' => $ip]);

            return false;
        }

        $jeton = bin2hex(random_bytes(32));

        $db->prepare(
            'INSERT INTO parola_sifirlama (kullanici_id, ozet, son_gecerlilik, ip)
             VALUES (:kullanici, :ozet, NOW() + INTERVAL ' . self::DAKIKA . ' MINUTE, :ip)'
        )->execute([
            ':kullanici' => $user->id,
            ':ozet'      => hash('sha256', $jeton),
            ':ip'        => mb_substr($ip, 0, 45),
        ]);

        Logger::info('Parola sıfırlama bağlantısı üretildi', ['kullanici' => $user->id, 'ip' => $ip], 'auth');

        return Notifier::parolaSifirlama($user, Mailer::absolute(url('parola-sifirla', ['jeton' => $jeton])), self::DAKIKA);
    }

    /** Yöneticinin açtığı hesaptaki "parolanızı belirleyin" bağlantısının ömrü (saat). */
    public const HESAP_ACILIS_SAAT = 48;

    /** Jeton türleri (parola_sifirlama.tur; bkz. App\Support\Surum162). */
    public const TUR_SIFIRLAMA = 'sifirlama';
    public const TUR_ACILIS    = 'acilis';

    /**
     * Yöneticinin açtığı hesap için "parolanızı belirleyin" bağlantısı
     * üretir (bkz. Notifier::hesapAcildi). Sıfırlamayla AYNI tablo ve
     * aynı tek kullanımlık jeton; yalnızca ömrü uzundur. Hız sınırı
     * yoktur: yalnızca users.create yetkisi olan bir yönetici tetikler.
     */
    public static function setupLink(User $user, string $ip): string
    {
        $jeton = bin2hex(random_bytes(32));

        Database::connection()->prepare(
            'INSERT INTO parola_sifirlama (kullanici_id, ozet, tur, son_gecerlilik, ip)
             VALUES (:kullanici, :ozet, :tur, NOW() + INTERVAL ' . self::HESAP_ACILIS_SAAT . ' HOUR, :ip)'
        )->execute([
            ':kullanici' => $user->id,
            ':ozet'      => hash('sha256', $jeton),
            ':tur'       => self::TUR_ACILIS,
            ':ip'        => mb_substr($ip, 0, 45),
        ]);

        Logger::info('Hesap açılış bağlantısı üretildi', ['kullanici' => $user->id], 'auth');

        return Mailer::absolute(url('parola-sifirla', ['jeton' => $jeton]));
    }

    /** Jeton geçerliyse (kullanılmamış, süresi dolmamış, hesap etkin) sahibini döndürür. */
    public static function find(string $jeton): ?User
    {
        $satir = self::row($jeton);

        if ($satir === null) {
            return null;
        }

        $user = (new UserRepository(Database::connection()))->find((int) $satir['kullanici_id']);

        return $user !== null && $user->isActive() ? $user : null;
    }

    /**
     * Geçerli jetonun türü: TUR_ACILIS (yöneticinin açtığı hesabın ilk
     * parolası) ya da TUR_SIFIRLAMA. Geçersizse null. Sayfa metni ve
     * gönderilecek mektup buna göre seçilir.
     */
    public static function kind(string $jeton): ?string
    {
        return self::find($jeton) !== null ? (string) (self::row($jeton)['tur'] ?? self::TUR_SIFIRLAMA) : null;
    }

    /** @return array{kullanici_id:int|string,tur:string}|null kullanılmamış ve süresi dolmamış jetonun satırı */
    private static function row(string $jeton): ?array
    {
        if (preg_match('/^[a-f0-9]{64}\z/', $jeton) !== 1) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT kullanici_id, tur FROM parola_sifirlama
              WHERE ozet = :ozet AND kullanildi_at IS NULL AND son_gecerlilik > NOW()
              LIMIT 1'
        );
        $stmt->execute([':ozet' => hash('sha256', $jeton)]);
        $satir = $stmt->fetch(PDO::FETCH_ASSOC);

        return $satir === false ? null : $satir;
    }

    /**
     * Jetonu TÜKETİR ve parolayı değiştirir. Aynı bağlantı iki kez
     * kullanılamaz: jeton "kullanıldı" diye işaretlenirken koşul
     * sorgunun içindedir, aynı anda gelen iki istekten yalnızca biri
     * başarılı olur.
     */
    public static function reset(string $jeton, string $parola): ?User
    {
        $user = self::find($jeton);

        if ($user === null) {
            return null;
        }

        $ilk = self::kind($jeton) === self::TUR_ACILIS;

        $db   = Database::connection();
        $stmt = $db->prepare(
            'UPDATE parola_sifirlama SET kullanildi_at = NOW()
              WHERE ozet = :ozet AND kullanildi_at IS NULL AND son_gecerlilik > NOW()'
        );
        $stmt->execute([':ozet' => hash('sha256', $jeton)]);

        if ($stmt->rowCount() !== 1) {
            return null;
        }

        // Aynı kullanıcıya giden ÖTEKİ bağlantılar da artık geçersiz.
        $db->prepare('UPDATE parola_sifirlama SET kullanildi_at = NOW() WHERE kullanici_id = :id AND kullanildi_at IS NULL')
           ->execute([':id' => $user->id]);

        // Oturum sürümü artar, "beni hatırla" ve API anahtarları silinir.
        (new UserRepository($db))->update($user->id, ['sifre' => $parola]);

        Logger::info($ilk ? 'İlk parola belirlendi (hesap açılış bağlantısıyla)' : 'Parola sıfırlandı (e-posta bağlantısıyla)',
            ['kullanici' => $user->id], 'auth');

        /* İlk parolada "Parolanız değiştirildi" mektubu gitmez (bkz.
         * ParolaDegistiBildir): hesabı yeni açılan kişi için anlamsızdı. */
        Events::dispatch(new PasswordChanged($user->id, kendisi: true,
            kaynak: $ilk ? PasswordChanged::ILK : PasswordChanged::SIFIRLAMA));

        return $user;
    }

    /**
     * Süresi bir günden uzun zaman önce dolmuş kayıtları siler
     * (zamanlanmış görev). Oluşturma tarihine BAKILMAZ: 48 saatlik hesap
     * açılış bağlantısı ertesi gün silinip kullanılamaz hâle geliyordu.
     */
    public static function purge(?PDO $db = null): int
    {
        $stmt = ($db ?? Database::connection())->prepare(
            'DELETE FROM parola_sifirlama WHERE son_gecerlilik < NOW() - INTERVAL 1 DAY'
        );
        $stmt->execute();

        return $stmt->rowCount();
    }
}
