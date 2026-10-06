<?php
/**
 * =====================================================================
 *  OrnekPolicy – "Bu kullanıcı bu kayda ne yapabilir?" sorusunun TEK
 *  cevap yeri
 * ---------------------------------------------------------------------
 *  RBAC iki katmandır:
 *
 *    1) ROL → YETKİ  (kaba): "üye kayıt ekleyebilir mi?"
 *       Rotadaki can:… ara katmanı ve Auth::can() cevaplar. Editör ve
 *       üyenin yetkileri modules/Ornek/module.json → "yetkiler"
 *       bloğundadır; yönetici her yetkiye sahiptir (Role::can).
 *
 *    2) SATIR DÜZEYİ (ince): "üye BU kaydı silebilir mi?"
 *       Rol bunu tek başına söyleyemez; kaydın sahibine ve durumuna
 *       bakmak gerekir. O kural buradadır.
 *
 *  ONAY AKIŞI (yaygın bir içerik düzeni):
 *
 *      taslak ──(sahibi: onaya gönder)──▶ onay ──(editör: onayla)──▶ yayinda
 *         ▲                                 │                          │
 *         └──────(sahibi: geri çek)─────────┘◀──(düzenleme: yeniden)───┘
 *
 *    Yönetici  (ornek.manage)   her kaydı görür, düzenler, siler, yayınlar
 *    Editör    (ornek.publish)  onay bekleyenleri görür ve yayınlar;
 *                               yalnızca KENDİ kaydını düzenler/siler
 *    Üye       (ornek.update.own) kendi kaydını yazar ve onaya gönderir,
 *                               YAYINLAYAMAZ; başkasının taslağını görmez
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
        'ornek.view'       => 'Yayındaki kayıtları görmek',
        'ornek.create'     => 'Kayıt eklemek',
        'ornek.update.own' => 'Kendi kaydını düzenlemek, silmek, onaya göndermek',
        'ornek.publish'    => 'Onay bekleyenleri görmek ve yayınlamak',
        'ornek.manage'     => 'Herkesin kaydını düzenlemek ve silmek, örnek üretmek',
    ];

    /** Listeleme kapsamları (OrnekRepository::listFor). */
    public const KAPSAM_HEPSI   = 'hepsi';   // her kayıt
    public const KAPSAM_EDITOR  = 'editor';  // yayındakiler + onay bekleyenler + kendi kayıtları
    public const KAPSAM_KENDI   = 'kendi';   // yayındakiler + kendi kayıtları
    public const KAPSAM_YAYIN   = 'yayin';   // yalnızca yayındakiler

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

    /** Yayına alabilir mi? (yönetici ve editör) */
    public function canPublish(): bool
    {
        return $this->canManageAll() || $this->can('ornek.publish');
    }

    /** Bu kullanıcı hangi kayıtları görür? */
    public function scope(): string
    {
        return match (true) {
            $this->canManageAll()          => self::KAPSAM_HEPSI,
            $this->canPublish()            => self::KAPSAM_EDITOR,
            $this->can('ornek.update.own') => self::KAPSAM_KENDI,
            default                        => self::KAPSAM_YAYIN,
        };
    }

    /** @param array<string,mixed> $kayit */
    public function canView(array $kayit): bool
    {
        $durum = (string) ($kayit['durum'] ?? '');

        return match ($this->scope()) {
            self::KAPSAM_HEPSI  => true,
            self::KAPSAM_EDITOR => $durum === 'yayinda' || $durum === 'onay' || $this->owns($kayit),
            self::KAPSAM_KENDI  => $durum === 'yayinda' || $this->owns($kayit),
            default             => $durum === 'yayinda',
        };
    }

    /**
     * Başlığı/açıklamayı düzenlemek ve silmek.
     *
     * @param array<string,mixed> $kayit
     */
    public function canEdit(array $kayit): bool
    {
        return $this->canManageAll() || ($this->can('ornek.update.own') && $this->owns($kayit));
    }

    /**
     * Yayınlayabilenin (editör, yönetici) geçişleri: şimdiki durum → hedefler.
     * Yayından kaldırılan kayıt taslağa değil ONAY sırasına döner: editör
     * başkasının taslağını göremez, kaldırdığı kaydı kaybetmesin.
     */
    private const GECIS_YAYINCI = [
        'taslak'  => ['yayinda'],
        'onay'    => ['yayinda', 'taslak'],   // onayla ya da sahibine geri gönder
        'yayinda' => ['onay'],                // yayından kaldır
    ];

    /** Kaydın sahibinin (yayınlayamayan) geçişleri. YAYINA ALAMAZ. */
    private const GECIS_SAHIP = [
        'taslak'  => ['onay'],                // onaya gönder
        'onay'    => ['taslak'],              // geri çek
        'yayinda' => ['taslak'],              // yayından kaldır
    ];

    /**
     * Kayıt bu kullanıcı tarafından hangi durumlara taşınabilir?
     * Boş dizi → durum düğmesi yok.
     *
     * @param array<string,mixed> $kayit
     * @return array<int,string>
     */
    public function transitions(array $kayit): array
    {
        $simdiki = (string) ($kayit['durum'] ?? '');

        return match (true) {
            $this->canPublish() && $this->canView($kayit)         => self::GECIS_YAYINCI[$simdiki] ?? [],
            $this->can('ornek.update.own') && $this->owns($kayit) => self::GECIS_SAHIP[$simdiki] ?? [],
            default                                               => [],
        };
    }

    /** @param array<string,mixed> $kayit */
    public function canMoveTo(array $kayit, string $hedef): bool
    {
        return in_array($hedef, $this->transitions($kayit), true);
    }

    /**
     * Yeni kayıt ve düzenleme formunda seçilebilecek durumlar.
     * Yayınlayamayan, "Yayında" seçeneğini hiç görmez.
     *
     * @return array<int,string>
     */
    public function formStatuses(): array
    {
        return $this->canPublish() ? ['yayinda', 'taslak'] : ['onay', 'taslak'];
    }

    /**
     * Kaydedilecek durum. Yayınlayamayan biri YAYINDAKİ kaydını
     * düzenlerse kayıt yeniden onaya düşer: onaylanmamış metin
     * yayında kalmasın.
     */
    public function statusAfterSave(string $istenen): string
    {
        return in_array($istenen, $this->formStatuses(), true)
            ? $istenen
            : ($this->canPublish() ? 'yayinda' : 'onay');
    }

    /**
     * Arayüzdeki kilit simgesinin açıklaması: NEDEN yapamıyor?
     *
     * @param array<string,mixed> $kayit
     */
    public function denialReason(array $kayit): string
    {
        return match (true) {
            $this->can('ornek.update.own') => 'Bu kayıt başkasına ait; yalnızca sahibi ve yönetici düzenleyebilir.',
            default                        => 'Rolünüz kayıt değiştirmeye izin vermiyor (yalnızca görüntüleme).',
        };
    }

    /** @param array<string,mixed> $kayit */
    public function owns(array $kayit): bool
    {
        return $this->user !== null && (int) ($kayit['kullanici_id'] ?? 0) === $this->user->id;
    }
}
