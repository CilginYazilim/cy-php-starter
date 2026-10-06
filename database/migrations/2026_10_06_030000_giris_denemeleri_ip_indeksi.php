<?php
/**
 * =====================================================================
 *  MIGRATION: login_attempts (ip, attempted_at) indeksi
 * ---------------------------------------------------------------------
 *  IP geneli yavaşlatma her girişte "bu IP kovasından son 15 dakikada
 *  kaç farklı kimlik denendi" diye sorar (bkz. RateLimiter). Tabloda
 *  yalnızca (identifier, attempted_at) indeksi vardı; bu sorgu her
 *  seferinde bütün tabloyu tarıyordu. Saldırı anında tablo büyürken
 *  her giriş yavaşlıyor, aynı anda gelen istekler sınırı aşıyordu.
 *
 *  Taze kurulumda indeks kurulum/database.sql ile gelir.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    /** Çekirdek migration: temel partiye (0) yazılır, geri alınmaz (bkz. Migration::baseline). */
    public function baseline(): bool
    {
        return true;
    }

    public function up(): void
    {
        if ($this->indexExists('login_attempts', 'idx_attempts_ip')) {
            return;
        }

        $this->execute('ALTER TABLE `login_attempts` ADD KEY `idx_attempts_ip` (`ip`, `attempted_at`)');
    }

    public function down(): void
    {
        if (!$this->indexExists('login_attempts', 'idx_attempts_ip')) {
            return;
        }

        $this->execute('ALTER TABLE `login_attempts` DROP KEY `idx_attempts_ip`');
    }
};
