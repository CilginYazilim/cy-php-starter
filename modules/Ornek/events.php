<?php
/**
 * =====================================================================
 *  Ornek MODÜLÜ – Açılışta kurulan bağlantılar
 * ---------------------------------------------------------------------
 *  Bu dosya modül AÇIKKEN her istekte bir kez yüklenir (Modules::boot).
 *  Olay dinleyicileri ve kontrol paneli kartları burada kaydedilir;
 *  çekirdeğin hiçbir dosyasına dokunulmaz.
 *
 *  Buradaki kod HER İSTEKTE çalışır: ağır iş yapmayın, yalnızca
 *  KAYDEDİN. Kart sağlayıcısı gibi geri çağrılar ancak gerektiğinde
 *  (kontrol paneli açılınca) çalışır.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\DashboardCards;
use App\Core\Database;
use App\Models\User;
use Modules\Ornek\OrnekPolicy;
use Modules\Ornek\Repositories\OrnekRepository;

/* KONTROL PANELİ KARTI — role göre farklı:
 *   yayınlayabilen (editör, yönetici) → onay bekleyen kayıt sayısı
 *   yazabilen (üye)                    → kendi kayıtlarının durumu */
DashboardCards::add('ornek', static function (?User $user): ?array {
    $policy = new OrnekPolicy($user);

    if ($user === null || !$policy->can('ornek.view')) {
        return null;
    }

    $kayitlar = new OrnekRepository(Database::connection());

    if ($policy->canPublish()) {
        $bekleyen = $kayitlar->countByStatus('onay');

        return [
            'ikon'   => 'box',
            'renk'   => $bekleyen > 0 ? 'warning' : 'brand',
            'etiket' => 'Onay bekleyen kayıt',
            'deger'  => $bekleyen,
            'ipucu'  => 'Örnek Modül',
            'yol'    => 'panel/ornek',
        ];
    }

    if ($policy->can('ornek.update.own')) {
        $benim = $kayitlar->statusCountsFor($user->id);

        return [
            'ikon'   => 'box',
            'renk'   => 'brand',
            'etiket' => 'Kayıtlarım',
            'deger'  => array_sum($benim),
            'ipucu'  => sprintf('%d yayında · %d onayda · %d taslak', $benim['yayinda'], $benim['onay'], $benim['taslak']),
            'yol'    => 'panel/ornek',
        ];
    }

    return null;
});
