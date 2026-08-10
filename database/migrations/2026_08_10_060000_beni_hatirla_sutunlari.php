<?php
/**
 * =====================================================================
 *  MIGRATION: "Beni hatırla" sütunları
 * ---------------------------------------------------------------------
 *  Giriş ekranındaki "Beni hatırla" kutusu, oturum çerezi ölse bile
 *  kullanıcıyı tanıyabilmek için uzun ömürlü ayrı bir çerez bırakır.
 *
 *  ÇEREZDE HAM JETON, VERİTABANINDA YALNIZCA ÖZETİ durur. Veritabanı
 *  sızsa bile kimse geçerli bir çerez üretemez — parola özetleriyle
 *  aynı mantık.
 * =====================================================================
 */

declare(strict_types=1);

return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        if (!$this->columnExists('kullanicilar', 'hatirla_token')) {
            $this->execute(
                'ALTER TABLE `kullanicilar`
                   ADD COLUMN `hatirla_token` CHAR(64) NULL DEFAULT NULL AFTER `hakkinda`,
                   ADD COLUMN `hatirla_bitis` DATETIME NULL DEFAULT NULL AFTER `hatirla_token`'
            );
        }

        if (!$this->indexExists('kullanicilar', 'idx_kullanicilar_hatirla')) {
            $this->execute('ALTER TABLE `kullanicilar` ADD KEY `idx_kullanicilar_hatirla` (`hatirla_token`)');
        }
    }

    public function down(): void
    {
        if ($this->indexExists('kullanicilar', 'idx_kullanicilar_hatirla')) {
            $this->execute('ALTER TABLE `kullanicilar` DROP INDEX `idx_kullanicilar_hatirla`');
        }

        if ($this->columnExists('kullanicilar', 'hatirla_token')) {
            $this->execute(
                'ALTER TABLE `kullanicilar`
                   DROP COLUMN `hatirla_token`,
                   DROP COLUMN `hatirla_bitis`'
            );
        }
    }
};
