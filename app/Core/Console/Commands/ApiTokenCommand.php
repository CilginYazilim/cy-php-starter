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
        return "  php cy api:token admin \"Mobil uygulama\"            Süresiz anahtar\n"
             . "  php cy api:token admin \"Rapor betiği\" --gun=90     90 gün geçerli\n"
             . "  php cy api:token admin --liste                     Anahtarları listeler\n"
             . "  php cy api:token admin --iptal=3                   3 numaralı anahtarı siler\n"
             . "\n"
             . "  Kullanıcı; kullanıcı adı ya da e-posta ile verilir. Anahtar o\n"
             . "  kullanıcının yetkileriyle çalışır.";
    }

    public function handle(): int
    {
        $kimlik = trim($this->input->argument(0));

        if ($kimlik === '') {
            $this->out->error('Bir kullanıcı adı ya da e-posta verin.');
            $this->out->muted('  Kullanım: php cy api:token <kullanıcı> "<anahtar adı>" [--gun=90]');

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
            $id = $this->input->intOption('iptal');

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

        $ad  = trim($this->input->argument(1, 'Komut satırı'));
        $gun = $this->input->intOption('gun');

        $sonuc = ApiToken::create($user->id, $ad, $gun > 0 ? $gun : null);

        $this->out->success('Anahtar üretildi (#' . $sonuc['id'] . ', ' . $user->kullaniciAdi . ', ' . ($gun > 0 ? $gun . ' gün' : 'süresiz') . ')');
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
