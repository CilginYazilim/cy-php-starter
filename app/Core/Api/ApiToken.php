<?php
/**
 * =====================================================================
 *  ApiToken – REST erişim anahtarları ve mobil oturumlar
 * ---------------------------------------------------------------------
 *  Oturum çerezi bir TARAYICI kavramıdır; mobil uygulama ya da başka
 *  bir sunucu çerez taşımaz. API istemcileri kendilerini "Bearer"
 *  anahtarıyla tanıtır:
 *
 *      Authorization: Bearer cy_3f9a…
 *
 *  İKİ TÜR VARDIR (aynı tablo, "tur" sütunu):
 *    anahtar → Panel → Hesabım'dan ya da "php cy api:token" ile elle
 *              üretilir; entegrasyonlar ve betikler içindir.
 *    oturum  → Mobil uygulama POST /api/v1/oturum ile kullanıcı adı ve
 *              parolayla alır (varsayılan 30 gün, API_SESSION_DAYS).
 *              "cihaz" sütunu hangi telefondan açıldığını söyler;
 *              kullanıcı Hesabım ekranından tek tek kapatabilir.
 *
 *  KAPSAM ("kapsam" sütunu):
 *    okuma → yalnızca GET/HEAD; veri değiştiren istek 403 alır
 *    yazma → sahibinin rolünün izin verdiği her şey
 *  Kapsam yetkiyi DARALTIR, asla genişletmez: anahtar, sahibi olan
 *  kullanıcıdan FAZLA yetkiye sahip olamaz (yetkiler Role'den gelir).
 *
 *  ANAHTAR VERİTABANINDA DÜZ METİN OLARAK TUTULMAZ, SHA-256 özeti
 *  saklanır. Açık hali YALNIZCA üretildiği anda bir kez gösterilir.
 *  Hızlı arama için başındaki kısa "ön ek" ayrıca saklanır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Api;

use App\Core\Database;
use App\Core\Log\Logger;
use App\Models\User;
use App\Repositories\UserRepository;
use PDO;
use PDOException;

final class ApiToken
{
    private const PREFIX     = 'cy_';
    private const PREFIX_LEN = 12;

    public const OKUMA = 'okuma';
    public const YAZMA = 'yazma';

    public const TUR_ANAHTAR = 'anahtar';
    public const TUR_OTURUM  = 'oturum';

    /** Bir kullanıcının aynı anda açık tutabileceği mobil oturum sayısı. */
    public const EN_FAZLA_OTURUM = 20;

    /** 1.6 sütunları (kapsam, tur, cihaz) var mı? null → henüz bakılmadı. */
    private static ?bool $yeniSema = null;

    /**
     * Yeni anahtar üretir.
     *
     * @return array{token:string,id:int} token YALNIZCA burada açık döner
     */
    public static function create(
        int $userId,
        string $name,
        ?int $daysValid = null,
        string $kapsam = self::YAZMA,
        string $tur = self::TUR_ANAHTAR,
        string $cihaz = '',
    ): array {
        $plain = self::PREFIX . bin2hex(random_bytes(24));

        /* Son geçerlilik SQL'in saatiyle yazılır; resolve() de onu
         * NOW() ile karşılaştırır (bkz. Database::syncTimezone). */
        $sure = $daysValid !== null && $daysValid > 0;
        $yeni = self::hasScopes();

        $statement = self::db()->prepare(
            'INSERT INTO `api_anahtarlari` (kullanici_id, ad, onek, ozet, ' . ($yeni ? 'kapsam, tur, cihaz, ' : '') . 'son_gecerlilik)
             VALUES (:kullanici_id, :ad, :onek, :ozet, ' . ($yeni ? ':kapsam, :tur, :cihaz, ' : '')
                . ($sure ? 'NOW() + INTERVAL :gun DAY' : 'NULL') . ')'
        );

        $params = [
            ':kullanici_id' => $userId,
            ':ad'           => mb_substr(trim($name), 0, 100, 'UTF-8') ?: 'Anahtar',
            ':onek'         => substr($plain, 0, self::PREFIX_LEN),
            ':ozet'         => hash('sha256', $plain),
        ];

        if ($yeni) {
            $params[':kapsam'] = $kapsam === self::OKUMA ? self::OKUMA : self::YAZMA;
            $params[':tur']    = $tur === self::TUR_OTURUM ? self::TUR_OTURUM : self::TUR_ANAHTAR;
            $params[':cihaz']  = mb_substr(trim(strip_tags($cihaz)), 0, 100, 'UTF-8');
        }

        if ($sure) {
            $params[':gun'] = min($daysValid, 3650);
        }

        $statement->execute($params);

        $id = (int) self::db()->lastInsertId();

        Logger::info($tur === self::TUR_OTURUM ? 'Mobil oturum açıldı' : 'API anahtarı üretildi', [
            'anahtar'   => $id,
            'kullanici' => $userId,
            'kapsam'    => $params[':kapsam'] ?? self::YAZMA,
        ], 'auth');

        if ($tur === self::TUR_OTURUM) {
            self::trimSessions($userId);
        }

        return ['token' => $plain, 'id' => $id];
    }

    /**
     * Anahtarı doğrular ve sahibini döndürür.
     *
     * @return User|null null → geçersiz, süresi dolmuş ya da sahibi pasif
     */
    public static function resolve(string $token): ?User
    {
        return self::resolveToken($token)['user'] ?? null;
    }

    /**
     * Anahtarı doğrular; sahibini ve anahtarın kendi bilgilerini döndürür.
     *
     * @return array{user:User,id:int,kapsam:string,tur:string}|null
     */
    public static function resolveToken(string $token): ?array
    {
        $token = trim($token);

        if ($token === '' || !str_starts_with($token, self::PREFIX)) {
            return null;
        }

        /* Süre kontrolü SQL'de: PHP'nin strtotime()'ı ile okumak, iki
         * saat dilimi farklıysa süresi dolmuş anahtarı saatlerce geçerli
         * sayıyordu. */
        $statement = self::db()->prepare(
            'SELECT id, kullanici_id, ozet, ' . (self::hasScopes() ? 'kapsam, tur, ' : "'yazma' AS kapsam, 'anahtar' AS tur, ") . '
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

            return [
                'user'   => $user,
                'id'     => (int) $row['id'],
                'kapsam' => (string) $row['kapsam'] === self::OKUMA ? self::OKUMA : self::YAZMA,
                'tur'    => (string) $row['tur'] === self::TUR_OTURUM ? self::TUR_OTURUM : self::TUR_ANAHTAR,
            ];
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

    /** $tur verilirse yalnızca o türdekiler sayılır (anahtar sınırı mobil oturumları saymaz). */
    public static function countForUser(int $userId, ?string $tur = null): int
    {
        $turlu     = $tur !== null && self::hasScopes();
        $statement = self::db()->prepare(
            'SELECT COUNT(*) FROM `api_anahtarlari` WHERE kullanici_id = :id' . ($turlu ? ' AND tur = :tur' : '')
        );
        $statement->execute($turlu ? [':id' => $userId, ':tur' => $tur] : [':id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Bir kullanıcının anahtarları ve mobil oturumları (açık hali ASLA
     * döndürülmez). $tur verilirse yalnızca o tür.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forUser(int $userId, ?string $tur = null): array
    {
        $yeni = self::hasScopes();

        $statement = self::db()->prepare(
            'SELECT id, ad, onek, son_gecerlilik, son_kullanim, created_at, '
                . ($yeni ? 'kapsam, tur, cihaz, ' : "'yazma' AS kapsam, 'anahtar' AS tur, '' AS cihaz, ") . '
                    (son_gecerlilik IS NOT NULL AND son_gecerlilik <= NOW()) AS suresi_doldu
               FROM `api_anahtarlari`
              WHERE kullanici_id = :id' . ($yeni && $tur !== null ? ' AND tur = :tur' : '') . '
              ORDER BY id DESC'
        );

        $params = [':id' => $userId];

        if ($yeni && $tur !== null) {
            $params[':tur'] = $tur;
        }

        $statement->execute($params);

        $satirlar = $statement->fetchAll();

        // Şema eskiyse "oturum" diye bir şey yoktur.
        return !$yeni && $tur === self::TUR_OTURUM ? [] : $satirlar;
    }

    /** En eski mobil oturumları kapatır; sınırın üstünde kalmasın. */
    private static function trimSessions(int $userId): void
    {
        $statement = self::db()->prepare(
            "SELECT id FROM `api_anahtarlari` WHERE kullanici_id = :id AND tur = 'oturum' ORDER BY id DESC"
        );
        $statement->execute([':id' => $userId]);

        $fazla = array_slice($statement->fetchAll(PDO::FETCH_COLUMN), self::EN_FAZLA_OTURUM);

        foreach ($fazla as $id) {
            self::revoke((int) $id);
        }
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

    /**
     * Kapsam/tür sütunları var mı? 1.6 migration'ı çalışmadan önce de
     * API çalışmaya devam etsin (bütün anahtarlar "yazma" sayılır).
     */
    public static function hasScopes(): bool
    {
        if (self::$yeniSema !== null) {
            return self::$yeniSema;
        }

        try {
            self::db()->query('SELECT kapsam, tur, cihaz FROM `api_anahtarlari` LIMIT 0');

            return self::$yeniSema = true;
        } catch (PDOException) {
            return self::$yeniSema = false;
        }
    }

    private static function db(): PDO
    {
        return Database::connection();
    }
}
