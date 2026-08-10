<?php
/**
 * =====================================================================
 *  Role – Roller ve yetkiler (yetkilendirme tablosu)
 * ---------------------------------------------------------------------
 *  Kodda rol adı değil YETKİ adı kullanılır:
 *      Auth::can('users.delete')      ✔ doğru
 *      Auth::user()->role === 'admin' ✘ kırılgan
 *
 *  ROLLER
 *    admin  → Yönetici. Her şeyi yapabilir.
 *    editor → Editör. Kullanıcı ekler/düzenler ama silemez, rol veremez.
 *    uye    → Üye. Yalnızca kendi profilini görür ve düzenler.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Models;

final class Role
{
    public const ADMIN  = 'admin';
    public const EDITOR = 'editor';
    public const MEMBER = 'uye';

    /** @var array<string,array<int,string>> */
    private const ABILITIES = [
        self::ADMIN => [
            'dashboard.view', 'dashboard.stats',
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.role', 'users.status',
            'messages.view', 'messages.manage',
            'mail.view', 'mail.send',
            'settings.view', 'settings.manage',
            'system.view', 'system.manage',
            'profile.view', 'profile.update',
        ],
        self::EDITOR => [
            'dashboard.view', 'dashboard.stats',
            'messages.view', 'messages.manage',
            // Editör giden e-postaların geçmişini görebilir ama
            // toplu duyuru gönderemez (mail.send yalnızca yöneticide).
            'mail.view',
            'profile.view', 'profile.update',
        ],
        self::MEMBER => [
            'dashboard.view',
            'profile.view', 'profile.update',
        ],
    ];

    /** @var array<string,string> */
    private const LABELS = [
        self::ADMIN  => 'Yönetici',
        self::EDITOR => 'Editör',
        self::MEMBER => 'Üye',
    ];

    /** @var array<string,string> */
    private const VARIANTS = [
        self::ADMIN  => 'admin',
        self::EDITOR => 'editor',
        self::MEMBER => 'member',
    ];

    /** @return array<int,string> */
    public static function all(): array
    {
        return array_keys(self::ABILITIES);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return self::LABELS;
    }

    public static function label(string $role): string
    {
        return self::LABELS[$role] ?? 'Bilinmiyor';
    }

    public static function variant(string $role): string
    {
        return self::VARIANTS[$role] ?? 'member';
    }

    public static function exists(string $role): bool
    {
        return array_key_exists($role, self::ABILITIES);
    }

    /**
     * Bu rol şu işi yapabilir mi?
     *
     * YÖNETİCİ HER ŞEYİ YAPABİLİR — listeye bakılmaz. Bunun nedeni
     * modüllerdir: modules/Stok kendi "stok.view" yetkisini tanımlar
     * ama çekirdeğin ABILITIES listesini değiştiremez. Yönetici için
     * beyaz liste şart koşsaydık, kurulan her modül için bu dosyaya
     * elle satır eklemek gerekirdi — ve unutulduğunda "yönetici
     * kendi kurduğu modüle giremiyor" gibi kafa karıştırıcı bir
     * durum çıkardı.
     *
     * Diğer roller için liste bağlayıcıdır: bir modülün editöre de
     * açılmasını istiyorsanız yetkisini aşağıdaki listeye ekleyin.
     */
    public static function can(string $role, string $ability): bool
    {
        if ($role === self::ADMIN) {
            return true;
        }

        return in_array($ability, self::ABILITIES[$role] ?? [], true);
    }

    /** @return array<int,string> */
    public static function abilities(string $role): array
    {
        return self::ABILITIES[$role] ?? [];
    }
}
