<?php
/**
 * =====================================================================
 *  Ip – İstemcinin IP adresi ve adres aralıkları
 * ---------------------------------------------------------------------
 *  GÜVENİLEN VEKİL (TRUSTED_PROXIES)
 *  Site Cloudflare, bir yük dengeleyici ya da Nginx ters vekili
 *  arkasındaysa REMOTE_ADDR her istekte VEKİLİN adresidir. O zaman
 *  bütün ziyaretçiler tek bir IP'den geliyormuş gibi görünür: tek bir
 *  saldırganın hatalı denemeleri BÜTÜN siteyi kilitler.
 *
 *  .env'de vekillerin adresleri yazılırsa gerçek adres vekilin
 *  eklediği başlıktan okunur:
 *
 *      TRUSTED_PROXIES=10.0.0.0/8,192.168.1.10
 *      TRUSTED_PROXY_HEADER=X-Forwarded-For       (varsayılan)
 *      TRUSTED_PROXY_HEADER=CF-Connecting-IP      (Cloudflare)
 *
 *  Başlık YALNIZCA istek listedeki bir vekilden geldiyse okunur. Aksi
 *  hâlde herkes "X-Forwarded-For: 1.2.3.4" yazıp istediği adresten
 *  geliyormuş gibi görünürdü.
 *
 *  IPv6 KOVASI
 *  Bir IPv6 kullanıcısı genellikle koca bir /64 bloğuna sahiptir ve
 *  her istekte adres değiştirebilir. Hız sınırları bu yüzden IPv6'yı
 *  tek tek adres olarak değil /64 bloğu olarak sayar (bkz. bucket()).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Ip
{
    private static ?string $client = null;

    /** İstemcinin adresi; geçerli bir adres bulunamazsa "0.0.0.0". */
    public static function client(): string
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $remote = self::normalize((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ($remote === null) {
            return self::$client = '0.0.0.0';
        }

        $proxies = self::trustedProxies();

        if ($proxies === [] || !self::matchesAny($remote, $proxies)) {
            return self::$client = $remote;
        }

        return self::$client = self::fromHeader($proxies) ?? $remote;
    }

    /** Testler için önbelleği sıfırlar. */
    public static function forget(): void
    {
        self::$client = null;
    }

    /**
     * Hız sınırlarında kullanılan KOVA: IPv4 adresin kendisi, IPv6
     * ise /64 bloğu ("2001:db8:1:2::/64").
     */
    public static function bucket(string $ip): string
    {
        $ip = self::normalize($ip);

        if ($ip === null) {
            return '0.0.0.0';
        }

        if (!str_contains($ip, ':')) {
            return $ip;
        }

        $packed = (string) inet_pton($ip);
        $prefix = substr($packed, 0, 8) . str_repeat("\0", 8);

        return (string) inet_ntop($prefix) . '/64';
    }

    /**
     * Adres verilen aralıkta mı? Aralık "10.0.0.0/8", "2001:db8::/32"
     * ya da tek bir adres olabilir.
     */
    public static function inRange(string $ip, string $range): bool
    {
        $ip = self::normalize($ip);

        if ($ip === null) {
            return false;
        }

        [$subnet, $bits] = array_pad(explode('/', trim($range), 2), 2, null);

        $subnet = self::normalize((string) $subnet);

        if ($subnet === null) {
            return false;
        }

        $ipBin     = (string) inet_pton($ip);
        $subnetBin = (string) inet_pton($subnet);

        // IPv4 ile IPv6 birbirine benzemez.
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $max  = strlen($ipBin) * 8;
        $bits = $bits === null ? $max : filter_var($bits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $max]]);

        if ($bits === false) {
            return false;
        }

        $full = intdiv($bits, 8);
        $rest = $bits % 8;

        if (substr($ipBin, 0, $full) !== substr($subnetBin, 0, $full)) {
            return false;
        }

        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($ipBin[$full]) & $mask) === (ord($subnetBin[$full]) & $mask);
    }

    /** @param array<int,string> $ranges */
    public static function matchesAny(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (self::inRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Geçerli bir adres mi? IPv4'e eşlenmiş IPv6 ("::ffff:1.2.3.4")
     * düz IPv4'e çevrilir; aynı ziyaretçi iki farklı kovaya düşmesin.
     */
    public static function normalize(string $ip): ?string
    {
        $ip = trim($ip);

        if (str_starts_with($ip, '[') && str_ends_with($ip, ']')) {
            $ip = substr($ip, 1, -1);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if (str_contains($ip, ':')) {
            $packed = (string) inet_pton($ip);

            if (str_starts_with($packed, str_repeat("\0", 10) . "\xFF\xFF")) {
                return (string) inet_ntop(substr($packed, 12));
            }

            return (string) inet_ntop($packed);
        }

        return $ip;
    }

    /** @return array<int,string> */
    private static function trustedProxies(): array
    {
        $list = Config::get('security.trusted_proxies', []);

        return is_array($list) ? array_values(array_filter(array_map('strval', $list), 'strlen')) : [];
    }

    /**
     * Vekilin eklediği başlıktan gerçek adres.
     *
     * X-Forwarded-For bir ZİNCİRDİR ("istemci, vekil1, vekil2"); en
     * soldaki değer istemcinin kendisi tarafından uydurulabilir. Bu
     * yüzden SAĞDAN sola okunur ve güvenilen vekil olmayan ilk adres
     * alınır.
     *
     * @param array<int,string> $proxies
     */
    private static function fromHeader(array $proxies): ?string
    {
        $header = (string) Config::get('security.proxy_header', 'X-Forwarded-For');
        $key    = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        $value  = $_SERVER[$key] ?? '';

        if (!is_string($value) || $value === '') {
            return null;
        }

        $hops = array_reverse(array_map('trim', explode(',', $value)));

        foreach ($hops as $hop) {
            $ip = self::normalize($hop);

            if ($ip === null) {
                return null; // bozuk zincir: güvenme
            }

            if (!self::matchesAny($ip, $proxies)) {
                return $ip;
            }
        }

        return null;
    }
}
