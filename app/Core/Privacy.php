<?php
/**
 * =====================================================================
 *  Privacy – KVKK / gizlilik kuralları tek yerde
 * ---------------------------------------------------------------------
 *  1) AYDINLATMA ONAYI: Kayıt ve iletişim formlarında "Aydınlatma
 *     metnini okudum" kutusu. Ayar: Panel → Ayarlar → Sistem →
 *     "KVKK onay kutusu" ve "Aydınlatma metni sayfası". Seçilen sayfa
 *     yayında değilse kutu gösterilmez (bağlantısız bir onay anlamsız).
 *
 *  2) SAKLAMA SÜRESİ: İletişim mesajlarındaki IP ve tarayıcı bilgisi
 *     kişisel veridir; yalnızca kötüye kullanımı incelemek için gerekir.
 *     "sistem_ip_saklama" günden (varsayılan 180) eski mesajlarda bu iki
 *     alan zamanlanmış görevle boşaltılır (routes/schedule.php). Mesajın
 *     kendisi silinmez; o yöneticinin kararıdır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Repositories\PageRepository;
use PDO;

final class Privacy
{
    /** İstek boyunca tek sorgu. */
    private static ?string $sayfaAdresi = null;

    /** Formda onay kutusu gösterilip zorunlu tutulacak mı? */
    public static function consentRequired(): bool
    {
        return Setting::bool('sistem_kvkk_onay', true) && self::pageUrl() !== '';
    }

    /** Aydınlatma metni sayfasının adresi; sayfa yoksa ya da taslaksa boş. */
    public static function pageUrl(): string
    {
        if (self::$sayfaAdresi !== null) {
            return self::$sayfaAdresi;
        }

        $slug = trim(Setting::get('sistem_kvkk_sayfa', ''));

        try {
            $sayfa = $slug !== '' ? (new PageRepository(Database::connection()))->findPublished($slug) : null;
        } catch (\Throwable) {
            $sayfa = null;
        }

        return self::$sayfaAdresi = $sayfa !== null ? url($sayfa->slug) : '';
    }

    /** Kutu işaretlenmeden gönderilen formdaki hata metni. */
    public static function consentError(): string
    {
        return 'Devam etmek için aydınlatma metnini okuduğunuzu onaylayın.';
    }

    /** Saklama süresi (gün); 0 → anonimleştirme kapalı. */
    public static function retentionDays(): int
    {
        return max(0, (int) Setting::get('sistem_ip_saklama', '180'));
    }

    /**
     * Süresi dolan mesajlarda IP ve tarayıcı bilgisini boşaltır.
     *
     * @return int Anonimleştirilen mesaj sayısı
     */
    public static function anonymizeMessages(?PDO $db = null, ?int $gun = null): int
    {
        $gun ??= self::retentionDays();

        if ($gun <= 0) {
            return 0;
        }

        $stmt = ($db ?? Database::connection())->prepare(
            "UPDATE mesajlar SET ip = '', tarayici = ''
              WHERE created_at < NOW() - INTERVAL :gun DAY AND (ip <> '' OR tarayici <> '')"
        );
        $stmt->bindValue(':gun', $gun, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /** Testler için. */
    public static function forget(): void
    {
        self::$sayfaAdresi = null;
    }
}
