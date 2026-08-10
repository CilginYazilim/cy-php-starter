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

        $statement = self::db()->prepare(
            'INSERT INTO `api_anahtarlari` (kullanici_id, ad, onek, ozet, son_gecerlilik)
             VALUES (:kullanici_id, :ad, :onek, :ozet, :son)'
        );

        $statement->execute([
            ':kullanici_id' => $userId,
            ':ad'           => mb_substr(trim($name), 0, 100, 'UTF-8') ?: 'Anahtar',
            ':onek'         => substr($plain, 0, self::PREFIX_LEN),
            ':ozet'         => hash('sha256', $plain),
            ':son'          => $daysValid !== null && $daysValid > 0
                ? date('Y-m-d H:i:s', time() + ($daysValid * 86400))
                : null,
        ]);

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

        $statement = self::db()->prepare(
            'SELECT id, kullanici_id, ozet, son_gecerlilik
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

            if ($row['son_gecerlilik'] !== null && strtotime((string) $row['son_gecerlilik']) < time()) {
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
     * Bir kullanıcının anahtarları (açık hali ASLA döndürülmez).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forUser(int $userId): array
    {
        $statement = self::db()->prepare(
            'SELECT id, ad, onek, son_gecerlilik, son_kullanim, created_at
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

        if ($header !== '' && preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) === 1) {
            return trim($m[1]);
        }

        return '';
    }

    private static function db(): PDO
    {
        return Database::connection();
    }
}
