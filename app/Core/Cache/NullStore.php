<?php
/**
 * =====================================================================
 *  NullStore – Hiçbir şey saklamayan önbellek
 * ---------------------------------------------------------------------
 *  config/cache.php içinde 'default' => 'kapali' seçilirse devreye
 *  girer. Kod değiştirmeden önbelleği tamamen kapatmanın yoludur:
 *
 *    · Geliştirirken "acaba önbellekten mi geliyor?" sorusunu bitirir
 *    · Bir hatanın önbellek kaynaklı olup olmadığını tek satırda test
 *      etmenizi sağlar
 *    · Testlerde her çalıştırmanın temiz başlamasını garanti eder
 *
 *  remember() bu sürücüyle her seferinde geri çağırımı çalıştırır;
 *  yani uygulama doğru çalışmaya devam eder, sadece yavaşlar.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Cache;

final class NullStore implements CacheStore
{
    public function name(): string
    {
        return 'kapali';
    }

    public function get(string $key): mixed
    {
        return null;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function put(string $key, mixed $value, int $seconds = 0): bool
    {
        return true;
    }

    public function forget(string $key): bool
    {
        return true;
    }

    public function flush(): bool
    {
        return true;
    }

    public function purgeExpired(): int
    {
        return 0;
    }
}
