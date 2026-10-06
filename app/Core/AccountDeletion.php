<?php
/**
 * =====================================================================
 *  AccountDeletion – Üyenin kendi hesabını silmesi (KVKK "unutulma")
 * ---------------------------------------------------------------------
 *  1) Panel → Hesabım → "Hesabımı sil": parola ile onaylanır.
 *     kullanicilar.silinme_at = şimdi + 7 gün yazılır, bütün oturumlar
 *     ve API anahtarları kapatılır, kişi çıkış yapar.
 *  2) 7 gün içinde GİRİŞ YAPARSA silme iptal edilir (Auth::attempt,
 *     mobil API girişi de dahil). Yanlışlıkla ya da hesabı ele geçiren
 *     birinin başlattığı silme böyle geri alınır.
 *  3) Süre dolunca zamanlanmış görev (routes/schedule.php → hesap-sil)
 *     hesabı ve profil görselini siler. Mesajlar, sayfalar ve e-posta
 *     kayıtları kalır; kullanıcıya bağları kopar (ON DELETE SET NULL).
 *
 *  SİSTEMDEKİ SON YÖNETİCİ kendini silemez; görev de silmez.
 *  Ayar: Panel → Ayarlar → Sistem → "Üyeler hesabını silebilir".
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Api\ApiToken;
use App\Core\Log\Logger;
use App\Models\User;
use App\Repositories\UserRepository;
use PDO;
use PDOException;

final class AccountDeletion
{
    /** Bekleme süresi (gün). */
    public const GUN = 7;

    public static function enabled(): bool
    {
        return Setting::bool('sistem_hesap_silme', true);
    }

    /** Son aktif yönetici kendini silemez. */
    public static function allowedFor(User $user): bool
    {
        return !$user->isAdmin() || (new UserRepository(Database::connection()))->otherActiveAdminExists($user->id);
    }

    /**
     * Silmeyi planlar ve her yerdeki oturumu kapatır.
     *
     * @return string|null Silinecek tarih ("d.m.Y H:i"); planlanamadıysa null
     */
    public static function schedule(User $user): ?string
    {
        if (!self::enabled() || !self::allowedFor($user)) {
            return null;
        }

        $db = Database::connection();

        try {
            $db->prepare('UPDATE kullanicilar SET silinme_at = NOW() + INTERVAL ' . self::GUN . ' DAY WHERE id = :id')
               ->execute([':id' => $user->id]);
        } catch (PDOException $e) {
            Logger::warning('Hesap silme planlanamadı (migration bekliyor olabilir): ' . $e->getMessage(), [], 'auth');

            return null;
        }

        $users = new UserRepository($db);
        $users->bumpSessionVersion($user->id);
        ApiToken::revokeAllForUser($user->id);

        Logger::info('Hesap silme planlandı', ['kullanici' => $user->id, 'gun' => self::GUN], 'auth');

        return self::scheduledAt($user->id);
    }

    /** Planlanmış silme tarihi ("d.m.Y H:i") ya da null. */
    public static function scheduledAt(int $userId): ?string
    {
        try {
            $stmt = Database::connection()->prepare('SELECT silinme_at FROM kullanicilar WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $tarih = $stmt->fetchColumn();
        } catch (PDOException) {
            return null;
        }

        return is_string($tarih) && $tarih !== '' ? date('d.m.Y H:i', (int) strtotime($tarih)) : null;
    }

    /** Giriş yapıldı: bekleyen silme varsa iptal eder. */
    public static function cancel(int $userId): bool
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE kullanicilar SET silinme_at = NULL WHERE id = :id AND silinme_at IS NOT NULL'
            );
            $stmt->execute([':id' => $userId]);
        } catch (PDOException) {
            return false;   // sütun yok: 1.6 migration'ı bekliyor
        }

        if ($stmt->rowCount() > 0) {
            Logger::info('Hesap silme iptal edildi (giriş yapıldı)', ['kullanici' => $userId], 'auth');

            return true;
        }

        return false;
    }

    /**
     * Süresi dolan hesapları siler (zamanlanmış görev).
     *
     * @return int Silinen hesap sayısı
     */
    public static function purgeDue(?PDO $db = null): int
    {
        $db ??= Database::connection();

        try {
            $ids = $db->query('SELECT id FROM kullanicilar WHERE silinme_at IS NOT NULL AND silinme_at <= NOW()')
                      ->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException) {
            return 0;
        }

        $users  = new UserRepository($db);
        $silinen = 0;

        foreach ($ids as $id) {
            $user = $users->find((int) $id);

            if ($user === null) {
                continue;
            }

            if (!self::allowedFor($user)) {
                Logger::warning('Hesap silme atlandı: sistemdeki son yönetici', ['kullanici' => $user->id], 'auth');

                continue;
            }

            if ($users->delete($user->id)) {
                Uploader::delete($user->avatar);
                $silinen++;

                Logger::info('Hesap silindi (bekleme süresi doldu)', ['kullanici' => $user->id], 'auth');
            }
        }

        return $silinen;
    }
}
