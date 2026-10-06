<?php
/**
 * =====================================================================
 *  php cy api:token – Komut satırından API anahtarı üretir / listeler
 * ---------------------------------------------------------------------
 *  Panel → Hesabım → API Anahtarları ekranının komut satırı karşılığı.
 *  Sunucuya SSH ile bağlanan yönetici, panele girmeden bir entegrasyon
 *  için anahtar üretebilsin; dağıtım betikleri de anahtar alabilsin.
 *
 *  Anahtarın açık hali YALNIZCA burada bir kez basılır; veritabanında
 *  yalnızca SHA-256 özeti durur.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Api\ApiToken;
use App\Core\Console\Command;
use App\Repositories\UserRepository;

final class ApiTokenCommand extends Command
{
    /** Süre belirtilmezse anahtarın geçerlilik süresi (gün). */
    private const VARSAYILAN_GUN = 90;

    public function name(): string
    {
        return 'api:token';
    }

    public function description(): string
    {
        return 'Bir kullanıcı için API anahtarı üretir ya da anahtarlarını listeler.';
    }

    public function help(): string
    {
        return "  php cy api:token admin \"Rapor betiği\" --gun=90     90 gün geçerli (1-3650)\n"
             . "  php cy api:token admin \"Sunucu\" --suresiz          Süresiz anahtar (açıkça istenmeli)\n"
             . "  php cy api:token admin --liste                     Anahtarları listeler\n"
             . "  php cy api:token admin --iptal=3                   3 numaralı anahtarı siler\n"
             . "\n"
             . "  Kullanıcı; kullanıcı adı ya da e-posta ile verilir. Anahtar o\n"
             . "  kullanıcının yetkileriyle çalışır. Ne --gun ne --suresiz\n"
             . "  verilirse anahtar " . self::VARSAYILAN_GUN . " gün geçerli olur.";
    }

    public function handle(): int
    {
        $kimlik = trim($this->input->argument(0));

        if ($kimlik === '') {
            $this->out->error('Bir kullanıcı adı ya da e-posta verin.');
            $this->out->muted('  Kullanım: php cy api:token <kullanıcı> "<anahtar adı>" [--gun=90 | --suresiz]');

            return self::HATA;
        }

        $user = (new UserRepository($this->db()))->findForLogin($kimlik);

        if ($user === null) {
            $this->out->error('Kullanıcı bulunamadı: ' . $kimlik);

            return self::HATA;
        }

        if ($this->input->hasOption('liste')) {
            return $this->listTokens($user->id);
        }

        if ($this->input->hasOption('iptal')) {
            /* Numara ZORUNLUDUR. Eskiden değersiz "--iptal" içeride "1"
             * sayılıyor ve 1 numaralı anahtar sessizce siliniyordu. */
            $id = $this->input->positiveIntOption('iptal');

            if (!is_int($id)) {
                $this->out->error('İptal edilecek anahtarın numarasını verin: --iptal=3 (numaralar için --liste).');

                return self::HATA;
            }

            if (!ApiToken::revokeOwned($user->id, $id)) {
                $this->out->error('Bu kullanıcıya ait ' . $id . ' numaralı anahtar yok.');

                return self::HATA;
            }

            $this->out->success('Anahtar iptal edildi.');

            return self::BASARILI;
        }

        if (!$user->isActive()) {
            $this->out->error('Kullanıcı aktif değil; pasif hesabın anahtarı zaten çalışmaz.');

            return self::HATA;
        }

        $ad = trim($this->input->argument(1, 'Komut satırı'));

        /* Süre KATI doğrulanır. Eskiden "--gun=90g" sayı sayılmıyor ve
         * sessizce SÜRESİZ anahtar üretiliyordu; yazım hatası en uzun
         * ömürlü anahtara dönüşüyordu. Süresiz anahtar artık yalnızca
         * --suresiz ile istenir. */
        $gun = $this->input->positiveIntOption('gun', 3650);

        if ($gun === false) {
            $this->out->error('--gun 1 ile 3650 arasında bir tam sayı olmalı (örn. --gun=90).');

            return self::HATA;
        }

        if ($gun !== null && $this->input->hasOption('suresiz')) {
            $this->out->error('--gun ile --suresiz birlikte verilemez.');

            return self::HATA;
        }

        if ($gun === null && !$this->input->hasOption('suresiz')) {
            $gun = self::VARSAYILAN_GUN;
        }

        $sonuc = ApiToken::create($user->id, $ad, $gun);

        $this->out->success('Anahtar üretildi (#' . $sonuc['id'] . ', ' . $user->kullaniciAdi . ', ' . ($gun !== null ? $gun . ' gün' : 'süresiz') . ')');
        $this->out->blank();
        $this->out->line('  ' . $sonuc['token']);
        $this->out->blank();
        $this->out->warn('Bu anahtar bir daha GÖSTERİLMEYECEK. Şimdi güvenli bir yere kaydedin.');
        $this->out->muted('  Deneme: curl -H "Authorization: Bearer <anahtar>" <site>/api/v1/ben');

        return self::BASARILI;
    }

    private function listTokens(int $userId): int
    {
        $rows = [];

        foreach (ApiToken::forUser($userId) as $row) {
            $rows[] = [
                (string) $row['id'],
                (string) $row['ad'],
                (string) $row['onek'] . '…',
                (string) ($row['son_kullanim'] ?? '—'),
                (int) ($row['suresi_doldu'] ?? 0) === 1
                    ? 'SÜRESİ DOLDU'
                    : (string) ($row['son_gecerlilik'] ?? 'süresiz'),
            ];
        }

        if ($rows === []) {
            $this->out->info('Bu kullanıcının anahtarı yok.');

            return self::BASARILI;
        }

        $this->out->table(['#', 'Ad', 'Ön ek', 'Son kullanım', 'Geçerlilik'], $rows);

        return self::BASARILI;
    }
}
