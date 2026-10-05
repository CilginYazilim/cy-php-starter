<?php
/**
 * =====================================================================
 *  MIGRATION: kullanicilar.oturum_surumu
 * ---------------------------------------------------------------------
 *  Parola değiştiğinde (ya da "diğer cihazlardan çıkış yap" denince)
 *  bir artar. Oturumda saklanan sürüm veritabanındakiyle tutmuyorsa
 *  oturum geçersizdir (bkz. App\Core\Auth::user).
 *
 *  NEDEN? Eski sürümde parola değişse bile başka cihazlardaki
 *  oturumlar ve çalınmış bir "beni hatırla" çerezi geçerli kalıyordu.
 *  Parolasını "hesabım ele geçirildi" şüphesiyle değiştiren kullanıcı
 *  saldırganı dışarı atamıyordu.
 *
 *  Taze kurulumda sütun kurulum/database.sql ile gelir ve bu dosya
 *  orada "temel parti" (0) olarak işaretlidir; burada yalnızca eski
 *  kurulumlar güncellenir.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        if ($this->columnExists('kullanicilar', 'oturum_surumu')) {
            return;
        }

        $this->execute(
            'ALTER TABLE `kullanicilar`
               ADD COLUMN `oturum_surumu` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `hatirla_bitis`'
        );
    }

    public function down(): void
    {
        if (!$this->columnExists('kullanicilar', 'oturum_surumu')) {
            return;
        }

        $this->execute('ALTER TABLE `kullanicilar` DROP COLUMN `oturum_surumu`');
    }
};
