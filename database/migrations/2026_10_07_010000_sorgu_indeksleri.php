<?php
/**
 * =====================================================================
 *  MIGRATION: Sık çalışan sorgular için eksik indeksler
 * ---------------------------------------------------------------------
 *  Her biri, büyüyen bir tabloda TAM TARAMA yapan bir sorguyu kapatır
 *  (300 bin e-posta, 400 bin giriş denemesi, 50 bin kullanıcıyla
 *  ölçüldü; süreler sorgu başına):
 *
 *    mail_kayitlari (durum, gonderildi_at)
 *        "Bugün gönderilen" sayacı — kontrol paneli ve Sistem sayfası
 *        her açılışta sorar. 968 ms → 1,7 ms.
 *
 *    mail_kayitlari (alici_eposta, created_at)
 *        "Bu adrese son X saatte aynı türde mektup gitti mi?" — iletişim
 *        formu ve kayıt doğrulaması her gönderimde sorar. Alıcı
 *        sütununda indeks yoktu; o gün giden bütün mektuplar taranıyordu.
 *
 *    login_attempts (attempted_at)
 *        Bir günden eski denemelerin silinmesi (her 20 denemede bir).
 *        İndekssiz DELETE bütün tabloyu tarıyor VE taradığı satırları
 *        kilitliyordu: silme sürerken gelen giriş denemeleri kilit
 *        beklemesinde kalıyordu — saldırı anında gerçek kullanıcılar da.
 *
 *    kullanicilar (created_at)
 *        Kontrol panelindeki "son eklenenler" ve 14 günlük grafik.
 *        43 ms (tam tarama + sıralama) → 0,2 ms.
 *
 *  Taze kurulumda indeksler kurulum/database.sql ile gelir. Var olan
 *  indeks yeniden eklenmez (birden fazla kez çalıştırılabilir).
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    /** tablo => [indeks adı => sütunlar] */
    private const INDEKSLER = [
        'mail_kayitlari' => [
            'idx_mail_gonderim' => '`durum`, `gonderildi_at`',
            'idx_mail_alici'    => '`alici_eposta`, `created_at`',
        ],
        'login_attempts' => [
            'idx_attempts_tarih' => '`attempted_at`',
        ],
        'kullanicilar' => [
            'idx_kullanicilar_kayit' => '`created_at`',
        ],
    ];

    /** Çekirdek migration: temel partiye (0) yazılır, geri alınmaz (bkz. Migration::baseline). */
    public function baseline(): bool
    {
        return true;
    }

    public function up(): void
    {
        foreach (self::INDEKSLER as $tablo => $indeksler) {
            foreach ($indeksler as $ad => $sutunlar) {
                if (!$this->indexExists($tablo, $ad)) {
                    $this->execute("ALTER TABLE `{$tablo}` ADD KEY `{$ad}` ({$sutunlar})");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEKSLER as $tablo => $indeksler) {
            foreach (array_keys($indeksler) as $ad) {
                if ($this->indexExists($tablo, $ad)) {
                    $this->execute("ALTER TABLE `{$tablo}` DROP KEY `{$ad}`");
                }
            }
        }
    }
};
