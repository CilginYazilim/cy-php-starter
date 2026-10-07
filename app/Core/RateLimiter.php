<?php
/**
 * =====================================================================
 *  RateLimiter – Kaba kuvvet (brute force) saldırısı koruması
 * ---------------------------------------------------------------------
 *  Sayaç VERİTABANINDA tutulur (oturumda değil); aksi halde saldırgan
 *  çerezini silerek sayacı sıfırlayabilirdi.
 *
 *  İKİ KİLİT
 *
 *   1. KİMLİK KİLİDİ — aynı e-posta/kullanıcı adına, aynı KAPSAMDAN
 *      (IP kovası ya da güvenilen cihaz) 5 hatalı denemede 15 dakika.
 *
 *   2. IP GENELİ YAVAŞLATMA — tek bir IP kovasından FARKLI kimliklere
 *      yapılan hatalı denemeler (parola püskürtme). Sınır aşılınca sert
 *      kilit yerine GİDEREK ARTAN bir bekleme uygulanır: 1, 2, 4, 8…
 *      saniye, en fazla kilit süresi kadar. Eskiden 30 ham hata IP'yi
 *      15 dakika kilitliyordu; rastgele 30 kullanıcı adı deneyen biri,
 *      aynı IP'yi paylaşan yöneticiyi (ya da vekil arkasında BÜTÜN
 *      siteyi) doğru parolayla bile dışarıda bırakabiliyordu.
 *
 *  KAPSAM ("ip" sütunu)
 *   · IPv4 adresin kendisi, IPv6 /64 bloğu (bkz. Ip::bucket). Aksi
 *     hâlde IPv6 kullanıcısı her denemede adres değiştirip kilidi
 *     aşabiliyordu.
 *   · Daha önce bu hesaba başarıyla girmiş bir tarayıcı (güvenilen
 *     cihaz çerezi) "cihaz:<kimlik>" kapsamında sayılır: IP'sindeki
 *     saldırgan onu kilitleyemez (bkz. Auth::attempt).
 *
 *  DENEME, PAROLA DOĞRULANMADAN ÖNCE YAZILIR (begin). Eskiden önce
 *  sayılıp sonra yazılıyordu; aynı anda gönderilen 50 istek sayacı
 *  hep 0 görüp sınırı aşıyordu. Şimdi her istek önce kendi satırını
 *  ekler, sonra YALNIZCA KENDİSİNDEN ÖNCEKİ satırları sayar: aynı
 *  anda gelen isteklerden en fazla "sınır" kadarı geçebilir. Başarılı
 *  girişte satır silinir (finish).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use PDO;

final class RateLimiter
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Denemeyi kayda geçirir. Parola doğrulanmadan ÖNCE çağrılır.
     *
     * @param string $identifier Girilen e-posta/kullanıcı adı (özeti yazılır)
     * @param string $scope      IP kovası ya da "cihaz:..." (bkz. sınıf açıklaması)
     * @return int Kaydın numarası (lockedFor/cancel için)
     */
    public function begin(string $identifier, string $scope): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (identifier, ip, attempted_at) VALUES (:kimlik, :kapsam, NOW())'
        );
        $stmt->execute([':kimlik' => $this->hash($identifier), ':kapsam' => $scope]);

        /* NUMARA BUDAMADAN ÖNCE ALINIR. lastInsertId() bağlantıdaki SON
         * sorguya bakar; araya giren DELETE (prune) onu 0 yapıyordu. Deneme
         * numarası 0 olunca kilit sorgusu ("id < 0") hiçbir denemeyi
         * saymıyor, her 20 denemeden biri kilidi atlayıp parola
         * denetimine ulaşıyordu (1.6.0'da duman testi yakaladı). */
        $id = (int) $this->db->lastInsertId();

        if (random_int(1, 20) === 1) {
            $this->prune();
        }

        return $id;
    }

    /**
     * Kimlik kilidi: bu denemeden ÖNCEKİ hatalı denemelere göre kalan
     * süre (saniye). 0 → kilit yok.
     */
    public function lockedFor(string $identifier, string $scope, int $beforeId = PHP_INT_MAX): int
    {
        $max    = (int) Config::get('security.login_max_attempts', 5);
        $window = (int) Config::get('security.login_window', 900);
        $lock   = (int) Config::get('security.login_lockout', 900);

        if ($max <= 0) {
            return 0;
        }

        /* KALAN SÜRE SQL'DE HESAPLANIR: kayıt MySQL'in NOW() değeriyle
         * yazılır; PHP saatiyle karşılaştırmak iki saat dilimi farklı
         * olduğunda kilidi baştan "geçmiş" gösteriyordu. */
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS adet,
                    TIMESTAMPDIFF(SECOND, NOW(), MAX(attempted_at) + INTERVAL :lock SECOND) AS kalan
               FROM login_attempts
              WHERE identifier = :kimlik AND ip = :kapsam AND id < :once
                AND attempted_at >= (NOW() - INTERVAL :window SECOND)'
        );
        $stmt->bindValue(':kimlik', $this->hash($identifier));
        $stmt->bindValue(':kapsam', $scope);
        $stmt->bindValue(':once', $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(':window', $window, PDO::PARAM_INT);
        $stmt->bindValue(':lock', $lock, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        if ((int) ($row['adet'] ?? 0) < $max) {
            return 0;
        }

        return max(0, (int) ($row['kalan'] ?? 0));
    }

    /**
     * IP GENELİ YAVAŞLATMA: kovadan FARKLI kimliklere yapılan hatalı
     * deneme sayısı sınırı aştıysa, son denemeden bu yana beklenmesi
     * gereken süre (saniye). 0 → bekleme yok.
     *
     * Ham hata sayısı değil FARKLI KİMLİK sayısı ölçülür: kendi
     * parolasını beş kez yanlış yazan kullanıcı bunu tetiklemez (onu
     * kimlik kilidi durdurur); yalnızca çok sayıda hesabı yoklayan biri
     * tetikler.
     */
    public function ipLockedFor(string $scope, int $beforeId = PHP_INT_MAX): int
    {
        $max    = (int) Config::get('security.login_ip_max_attempts', 30);
        $window = (int) Config::get('security.login_window', 900);
        $lock   = (int) Config::get('security.login_lockout', 900);

        if ($max <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT identifier) AS farkli,
                    TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) AS gecen
               FROM login_attempts
              WHERE ip = :kapsam AND id < :once
                AND attempted_at >= (NOW() - INTERVAL :window SECOND)'
        );
        $stmt->bindValue(':kapsam', $scope);
        $stmt->bindValue(':once', $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(':window', $window, PDO::PARAM_INT);
        $stmt->execute();

        $row    = $stmt->fetch() ?: [];
        $farkli = (int) ($row['farkli'] ?? 0);

        if ($farkli < $max) {
            return 0;
        }

        // Sınırdaki ilk aşım 1 sn, sonra her farklı kimlikte iki katı.
        $bekle = (int) min($lock, 2 ** min(20, $farkli - $max));

        return max(0, $bekle - (int) ($row['gecen'] ?? 0));
    }

    /** Denemeyi kayıttan siler (başarılı giriş ya da kilide takılan deneme). */
    public function cancel(int $attemptId): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE id = :id');
        $stmt->execute([':id' => $attemptId]);
    }

    /** Bu kimliğin bütün denemelerini siler (başarılı giriş). */
    public function clear(string $identifier): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE identifier = :kimlik');
        $stmt->execute([':kimlik' => $this->hash($identifier)]);
    }

    /** Kilide kadar kalan hak. */
    public function remaining(string $identifier, string $scope): int
    {
        $max    = (int) Config::get('security.login_max_attempts', 5);
        $window = (int) Config::get('security.login_window', 900);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM login_attempts
              WHERE identifier = :kimlik AND ip = :kapsam
                AND attempted_at >= (NOW() - INTERVAL :window SECOND)'
        );
        $stmt->bindValue(':kimlik', $this->hash($identifier));
        $stmt->bindValue(':kapsam', $scope);
        $stmt->bindValue(':window', $window, PDO::PARAM_INT);
        $stmt->execute();

        return max(0, $max - (int) $stmt->fetchColumn());
    }

    public function prune(): void
    {
        $this->db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }

    /**
     * Bir kullanıcının e-posta VEYA kullanıcı adıyla yapılmış son
     * başarısız giriş denemeleri (panelde göstermek için).
     *
     * Kayıtlar başarılı girişte silindiği (bkz. clear()) ve bir günden
     * eskisi budandığı (bkz. prune()) için bu yalnızca YAKIN ZAMANDAKİ
     * başarısız denemeleri gösterir — kalıcı bir denetim günlüğü değildir.
     *
     * @return array{adet:int,son_ip:string,son_tarih:?string}
     */
    public function recentFailures(string $eposta, string $kullaniciAdi): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS adet, MAX(attempted_at) AS son, SUBSTRING_INDEX(GROUP_CONCAT(ip ORDER BY attempted_at DESC), \',\', 1) AS son_ip
               FROM login_attempts
              WHERE identifier IN (:eposta, :kadi)'
        );
        $stmt->execute([':eposta' => $this->hash($eposta), ':kadi' => $this->hash($kullaniciAdi)]);

        $row = $stmt->fetch() ?: [];

        return [
            'adet'      => (int) ($row['adet'] ?? 0),
            'son_ip'    => (string) ($row['son_ip'] ?? ''),
            'son_tarih' => $row['son'] !== null ? (string) $row['son'] : null,
        ];
    }

    /**
     * Kimliğin ÖZETİ. Düz metin saklanmaz: veritabanı sızsa bile "kime
     * saldırıldı" bilgisi sızmaz.
     */
    private function hash(string $identifier): string
    {
        return hash('sha256', mb_strtolower(trim($identifier)));
    }
}
