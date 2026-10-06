<?php
/**
 * =====================================================================
 *  DashboardCards – Modüllerin kontrol paneline eklediği özet kartları
 * ---------------------------------------------------------------------
 *  Çekirdek, hangi modüllerin var olduğunu bilmez. Bir modül panelde
 *  kendi sayısını göstermek isterse (ör. "3 onay bekleyen kayıt")
 *  modules/Ad/events.php içinde bir sağlayıcı kaydeder:
 *
 *      DashboardCards::add('stok', static function (?User $user): ?array {
 *          if ($user === null || !$user->can('stok.view')) {
 *              return null;                       // bu role kart yok
 *          }
 *          return [
 *              'ikon'   => 'database',
 *              'renk'   => 'warning',             // brand|success|warning|danger
 *              'etiket' => 'Azalan stok',
 *              'deger'  => 7,
 *              'ipucu'  => 'eşiğin altındaki ürünler',
 *              'yol'    => 'panel/stok',          // boşsa kart bağlantı olmaz
 *          ];
 *      });
 *
 *  Sağlayıcı yalnızca kontrol paneli açılırken çağrılır (tembel).
 *  Hata verirse kart atlanır ve günlüğe yazılır; panel çökmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Log\Logger;
use App\Models\User;
use Throwable;

final class DashboardCards
{
    /** @var array<string,callable(?User):(array<string,mixed>|null)> */
    private static array $providers = [];

    public static function add(string $id, callable $provider): void
    {
        self::$providers[$id] = $provider;
    }

    /**
     * @return array<int,array{ikon:string,renk:string,etiket:string,deger:string,ipucu:string,yol:string}>
     */
    public static function collect(?User $user): array
    {
        $cards = [];

        foreach (self::$providers as $id => $provider) {
            try {
                $card = $provider($user);
            } catch (Throwable $e) {
                Logger::error('Panel kartı üretilemedi: ' . $id . ' – ' . $e->getMessage(), ['kart' => $id], 'error');

                continue;
            }

            if (is_array($card)) {
                $cards[] = self::normalize($card);
            }
        }

        return $cards;
    }

    /**
     * @param array<string,mixed> $card
     * @return array{ikon:string,renk:string,etiket:string,deger:string,ipucu:string,yol:string}
     */
    public static function normalize(array $card): array
    {
        $renk = (string) ($card['renk'] ?? 'brand');

        return [
            'ikon'   => (string) ($card['ikon'] ?? 'activity'),
            'renk'   => in_array($renk, ['brand', 'success', 'warning', 'danger'], true) ? $renk : 'brand',
            'etiket' => (string) ($card['etiket'] ?? ''),
            'deger'  => (string) ($card['deger'] ?? '0'),
            'ipucu'  => (string) ($card['ipucu'] ?? ''),
            'yol'    => (string) ($card['yol'] ?? ''),
        ];
    }

    /** Testler için. */
    public static function forget(): void
    {
        self::$providers = [];
    }
}
