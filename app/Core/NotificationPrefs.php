<?php
/**
 * =====================================================================
 *  NotificationPrefs – Üyenin e-posta bildirim tercihleri
 * ---------------------------------------------------------------------
 *  kullanicilar.bildirim_tercihleri sütununda JSON durur; NULL =
 *  varsayılan (hepsi açık). Bugün tek tercih vardır:
 *
 *      duyuru → panelden kitleye (tüm üyeler / bir rol) gönderilen
 *               toplu duyurular
 *
 *  GÜVENLİK MEKTUPLARI BU TERCİHE BAKMAZ ve kapatılamaz: parola
 *  değişti, e-posta değişti, hesap silinecek, parola sıfırlama,
 *  doğrulama. Hesabı ele geçirilen kişinin fark etmesinin yolu onlardır.
 *
 *  Yönetici bildirimleri (yeni iletişim mesajı, yeni üye) kişisel değil
 *  SİTE ayarıdır (Ayarlar → E-posta): iletişim adresine giderler.
 *  Hesabım ekranı onları yalnızca ayar yetkisi olana gösterir.
 *
 *  Duyuru mektubunun altındaki "Duyuruları almak istemiyorum"
 *  bağlantısı İMZALIDIR (Signer): giriş gerektirmez, başkasının
 *  numarasıyla üretilemez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class NotificationPrefs
{
    public const DUYURU = 'duyuru';

    /** @var array<string,bool> */
    public const VARSAYILAN = [self::DUYURU => true];

    /**
     * Sütundaki JSON'u tercih dizisine çevirir. Bilinmeyen anahtarlar
     * atılır, eksikler varsayılanla dolar; bozuk JSON varsayılandır.
     *
     * @return array<string,bool>
     */
    public static function decode(?string $json): array
    {
        $veri = $json !== null && $json !== '' ? json_decode($json, true) : null;
        $veri = is_array($veri) ? $veri : [];

        $sonuc = [];

        foreach (self::VARSAYILAN as $anahtar => $varsayilan) {
            $sonuc[$anahtar] = array_key_exists($anahtar, $veri) ? (bool) $veri[$anahtar] : $varsayilan;
        }

        return $sonuc;
    }

    /**
     * Tercihleri sütuna yazılacak biçime çevirir. Hepsi varsayılansa
     * NULL döner: yeni bir tercih eklendiğinde eski kayıtlar da onun
     * varsayılanını alsın.
     *
     * @param array<string,mixed> $tercihler
     */
    public static function encode(array $tercihler): ?string
    {
        $temiz = self::decode((string) json_encode($tercihler));

        return $temiz === self::VARSAYILAN ? null : (string) json_encode($temiz);
    }

    /** Tek tıkla duyuru iptali bağlantısı (mutlak adres, imzalı). */
    public static function unsubscribeUrl(int $userId): string
    {
        return Mail\Mailer::absolute(url('duyurular/iptal', ['k' => $userId, 'i' => Signer::sign(self::payload($userId))]));
    }

    /** İptal bağlantısındaki imza bu kullanıcı için mi üretilmiş? */
    public static function verify(int $userId, string $imza): bool
    {
        return $userId > 0 && $imza !== '' && Signer::check(self::payload($userId), $imza);
    }

    private static function payload(int $userId): string
    {
        return 'duyuru-iptal|' . $userId;
    }
}
