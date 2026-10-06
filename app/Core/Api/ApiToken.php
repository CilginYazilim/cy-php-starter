<?php
/**
 * =====================================================================
 *  ApiToken – REST erişim anahtarları
 * ---------------------------------------------------------------------
 *  Oturum çerezi bir TARAYICI kavramıdır; mobil uygulama ya da başka
 *  bir sunucu çerez taşımaz. API istemcileri kendilerini "Bearer"
 *  anahtarıyla tanıtır:
 *
 *      Authorization: Bearer cy_3f9a…
 *
 *  ANAHTAR VERİTABANINDA DÜZ METİN OLARAK TUTULMAZ.
 *  Parola gibi hash'lenir. Veritabanı sızarsa saldırgan anahtarları
 *  kullanamaz. Anahtarın açık hali YALNIZCA üretildiği anda, bir kez
 *  gösterilir — kaybedilirse yenisi üretilir.
 *
 *  Hızlı arama için anahtarın başındaki kısa "ön ek" ayrıca saklanır:
 *  hash'lenmiş sütunda arama yapılamayacağı için önce ön ekle aday
 *  kayıt bulunur, sonra hash doğrulanır.
 *
 *  YETKİLER kullanıcının rolünden gelir (bkz. Role). Anahtar, sahibi
 *  olan kullanıcıdan FAZLA yetkiye asla sahip olamaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Api;

use App\Core\Database;
use App\Core\Log\Logger;
use App\Models\User;
use App\Repositories\UserRepository;
use PDO;

final class ApiToken
{
    private const PREFIX     = 'cy_';
    private const PREFIX_LEN = 12;

    /**
     * Yeni anahtar üretir.
     *
     * @return array{token:string,id:int} token YALNIZCA burada açık döner
     */
    public static function create(int $userId, string $name, ?int $daysValid = null): array
    {
        $plain = self::PREFIX . bin2hex(random_bytes(24));

        /* Son geçerlilik SQL'in saatiyle yazılır; resolve() de onu
         * NOW() ile karşılaştırır (bkz. Database::syncTimezone). */
        $sure = $daysValid !== null && $daysValid > 0;

        $statement = self::db()->prepare(
            'INSERT INTO `api_anahtarlari` (kullanici_id, ad, onek, ozet, son_gecerlilik)
             VALUES (:kullanici_id, :ad, :onek, :ozet, '
                . ($sure ? 'NOW() + INTERVAL :gun DAY' : 'NULL') . ')'
        );

        $params = [
            ':kullanici_id' => $userId,
            ':ad'           => mb_substr(trim($name), 0, 100, 'UTF-8') ?: 'Anahtar',
            ':onek'         => substr($plain, 0, self::PREFIX_LEN),
            ':ozet'         => hash('sha256', $plain),
        ];

        if ($sure) {
            $params[':gun'] = min($daysValid, 3650);
        }

        $statement->execute($params);

        $id = (int) self::db()->lastInsertId();

        Logger::info('API anahtarı üretildi', ['anahtar' => $id, 'kullanici' => $userId], 'auth');

        return ['token' => $plain, 'id' => $id];
    }

    /**
     * Anahtarı doğrular ve sahibini döndürür.
     *
     * @return User|null null → geçersiz, süresi dolmuş ya da sahibi pasif
     */
    public static function resolve(string $token): ?User
    {
        $token = trim($token);

        if ($token === '' || !str_starts_with($token, self::PREFIX)) {
            return null;
        }

        /* Süre kontrolü SQL'de: PHP'nin strtotime()'ı ile okumak, iki
         * saat dilimi farklıysa süresi dolmuş anahtarı saatlerce geçerli
         * sayıyordu. */
        $statement = self::db()->prepare(
            'SELECT id, kullanici_id, ozet,
                    (son_gecerlilik IS NOT NULL AND son_gecerlilik <= NOW()) AS suresi_doldu
               FROM `api_anahtarlari`
              WHERE onek = :onek
              LIMIT 5'
        );
        $statement->execute([':onek' => substr($token, 0, self::PREFIX_LEN)]);

        $ozet = hash('sha256', $token);

        foreach ($statement->fetchAll() as $row) {
            /* hash_equals: karşılaştırma süresi içeriğe göre değişmesin
             * (zamanlama saldırısı). */
            if (!hash_equals((string) $row['ozet'], $ozet)) {
                continue;
            }

            if ((int) $row['suresi_doldu'] === 1) {
                return null;
            }

            $user = (new UserRepository(self::db()))->find((int) $row['kullanici_id']);

            // Kullanıcı pasife alındıysa anahtarı da geçersizdir.
            if ($user === null || !$user->isActive()) {
                return null;
            }

            self::touch((int) $row['id']);

            return $user;
        }

        return null;
    }

    /** Anahtarın son kullanım zamanını günceller (kullanılmayanları ayıklamak için). */
    private static function touch(int $id): void
    {
        self::db()->prepare(
            'UPDATE `api_anahtarlari` SET son_kullanim = NOW() WHERE id = :id'
        )->execute([':id' => $id]);
    }

    public static function revoke(int $id): bool
    {
        $statement = self::db()->prepare('DELETE FROM `api_anahtarlari` WHERE id = :id');
        $statement->execute([':id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * YALNIZCA SAHİBİNİN anahtarını siler. Profil ekranından gelen
     * istekte anahtar numarası formdan gelir; sahiplik koşulu olmadan
     * bir üye başkasının anahtarını iptal edebilirdi.
     */
    public static function revokeOwned(int $userId, int $id): bool
    {
        $statement = self::db()->prepare(
            'DELETE FROM `api_anahtarlari` WHERE id = :id AND kullanici_id = :kullanici'
        );
        $statement->execute([':id' => $id, ':kullanici' => $userId]);

        if ($statement->rowCount() > 0) {
            Logger::info('API anahtarı iptal edildi', ['anahtar' => $id, 'kullanici' => $userId], 'auth');

            return true;
        }

        return false;
    }

    /**
     * Kullanıcının BÜTÜN anahtarlarını siler.
     *
     * Parola değişince (bkz. UserRepository::update) ve "diğer
     * cihazlardan çıkış yap" ile birlikte istenirse çağrılır. Eskiden
     * ekranda "diğer oturumlar kapatıldı" yazarken çalınmış bir API
     * anahtarı çalışmaya devam ediyordu.
     *
     * @return int Silinen anahtar sayısı
     */
    public static function revokeAllForUser(int $userId): int
    {
        $statement = self::db()->prepare('DELETE FROM `api_anahtarlari` WHERE kullanici_id = :kullanici');
        $statement->execute([':kullanici' => $userId]);

        $adet = $statement->rowCount();

        if ($adet > 0) {
            Logger::info('Kullanıcının bütün API anahtarları iptal edildi', ['kullanici' => $userId, 'adet' => $adet], 'auth');
        }

        return $adet;
    }

    public static function countForUser(int $userId): int
    {
        $statement = self::db()->prepare('SELECT COUNT(*) FROM `api_anahtarlari` WHERE kullanici_id = :id');
        $statement->execute([':id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Bir kullanıcının anahtarları (açık hali ASLA döndürülmez).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forUser(int $userId): array
    {
        $statement = self::db()->prepare(
            'SELECT id, ad, onek, son_gecerlilik, son_kullanim, created_at,
                    (son_gecerlilik IS NOT NULL AND son_gecerlilik <= NOW()) AS suresi_doldu
               FROM `api_anahtarlari`
              WHERE kullanici_id = :id
              ORDER BY id DESC'
        );
        $statement->execute([':id' => $userId]);

        return $statement->fetchAll();
    }

    /** İstek başlığından "Bearer" anahtarını çıkarır. */
    public static function fromRequest(): string
    {
        $header = (string) (
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']  // bazı Apache kurulumları
            ?? ''
        );

        if ($header !== '' && preg_match('/^Bearer\s+(.+)\z/i', trim($header), $m) === 1) {
            return trim($m[1]);
        }

        return '';
    }

    private static function db(): PDO
    {
        return Database::connection();
    }
}
