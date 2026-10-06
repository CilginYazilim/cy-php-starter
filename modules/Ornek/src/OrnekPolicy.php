<?php
/**
 * =====================================================================
 *  OrnekPolicy – "Bu kullanıcı bu kayda ne yapabilir?" sorusunun TEK
 *  cevap yeri
 * ---------------------------------------------------------------------
 *  RBAC iki katmandır:
 *
 *    1) ROL → YETKİ  (kaba): "editör kayıt ekleyebilir mi?"
 *       Rotadaki can:… ara katmanı ve Auth::can() cevaplar. Editör ve
 *       üyenin yetkileri modules/Ornek/module.json → "yetkiler"
 *       bloğundadır; yönetici her yetkiye sahiptir (Role::can).
 *
 *    2) SATIR DÜZEYİ (ince): "editör BU kaydı silebilir mi?"
 *       Rol bunu tek başına söyleyemez; kaydın sahibine ve durumuna
 *       bakmak gerekir. O kural buradadır:
 *
 *         Yönetici  (ornek.manage)     → her kaydı görür ve yönetir
 *         Editör    (ornek.update.own) → yalnızca KENDİ kaydını yönetir;
 *                                        başkasının taslağını görmez
 *         Üye       (ornek.view)       → yalnızca yayındakileri görür
 *
 *  Görünüm düğmeleri bu sınıfa sorarak gösterir/gizler, denetleyici de
 *  AYNI sınıfa sorarak isteği kabul eder/reddeder. Düğmeyi gizlemek
 *  güvenlik değildir: elle gönderilen bir istek de buradan geçer.
 * =====================================================================
 */

declare(strict_types=1);

namespace Modules\Ornek;

use App\Models\User;

final class OrnekPolicy
{
    /** Ekranda ve yetki matrisinde gösterilen yetkiler, sırasıyla. */
    public const YETKILER = [
        'ornek.view'       => 'Listeyi görmek',
        'ornek.create'     => 'Kayıt eklemek',
        'ornek.update.own' => 'Kendi kaydını yayınlamak / silmek',
        'ornek.manage'     => 'Herkesin kaydını yönetmek, örnek üretmek',
    ];

    /** Listeleme kapsamları (OrnekRepository::listFor). */
    public const KAPSAM_HEPSI = 'hepsi';   // her kayıt
    public const KAPSAM_KENDI = 'kendi';   // yayındakiler + kendi taslakları
    public const KAPSAM_YAYIN = 'yayin';   // yalnızca yayındakiler

    public function __construct(private readonly ?User $user)
    {
    }

    public function can(string $yetki): bool
    {
        return $this->user !== null && $this->user->can($yetki);
    }

    public function canCreate(): bool
    {
        return $this->can('ornek.create');
    }

    public function canManageAll(): bool
    {
        return $this->can('ornek.manage');
    }

    /** Bu kullanıcı hangi kayıtları görür? */
    public function scope(): string
    {
        return match (true) {
            $this->canManageAll()            => self::KAPSAM_HEPSI,
            $this->can('ornek.update.own')   => self::KAPSAM_KENDI,
            default                          => self::KAPSAM_YAYIN,
        };
    }

    /** @param array<string,mixed> $kayit */
    public function canView(array $kayit): bool
    {
        return match ($this->scope()) {
            self::KAPSAM_HEPSI => true,
            self::KAPSAM_KENDI => ($kayit['durum'] ?? '') === 'yayinda' || $this->owns($kayit),
            default            => ($kayit['durum'] ?? '') === 'yayinda',
        };
    }

    /**
     * Kaydı yayınlamak / taslağa almak / silmek.
     *
     * @param array<string,mixed> $kayit
     */
    public function canModify(array $kayit): bool
    {
        return $this->canManageAll() || ($this->can('ornek.update.own') && $this->owns($kayit));
    }

    /**
     * Arayüzdeki kilit simgesinin açıklaması: NEDEN yapamıyor?
     *
     * @param array<string,mixed> $kayit
     */
    public function denialReason(array $kayit): string
    {
        return $this->can('ornek.update.own')
            ? 'Bu kayıt başkasına ait; yalnızca sahibi ve yönetici değiştirebilir.'
            : 'Rolünüz kayıt değiştirmeye izin vermiyor (yalnızca görüntüleme).';
    }

    /** @param array<string,mixed> $kayit */
    private function owns(array $kayit): bool
    {
        return $this->user !== null && (int) ($kayit['kullanici_id'] ?? 0) === $this->user->id;
    }
}
