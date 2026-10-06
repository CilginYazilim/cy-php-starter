<?php
/**
 * =====================================================================
 *  php cy serve – XAMPP olmadan geliştirme sunucusu
 * ---------------------------------------------------------------------
 *  PHP'nin yerleşik sunucusunu, .htaccess kurallarını taklit eden
 *  yönlendiriciyle başlatır (app/Support/gelistirme-sunucusu.php).
 *  Kurulum sihirbazı da bu adresten açılır: http://127.0.0.1:8000/kurulum/
 *
 *  Yayın için DEĞİLDİR: tek iş parçacıklıdır, Apache/Nginx kullanın.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;

final class ServeCommand extends Command
{
    public function name(): string
    {
        return 'serve';
    }

    public function description(): string
    {
        return 'Geliştirme sunucusunu başlatır (PHP yerleşik sunucusu, XAMPP gerekmez).';
    }

    public function help(): string
    {
        return "  php cy serve                     http://127.0.0.1:8000\n"
             . "  php cy serve --port=8080\n"
             . "  php cy serve --host=0.0.0.0      Aynı ağdaki telefondan denemek için\n"
             . "\n"
             . "  İlk kez: tarayıcıda /kurulum/ adresini açın. .env içindeki APP_URL\n"
             . "  sunucunun adresiyle aynı olmalıdır (sihirbaz bunu kendisi yazar).";
    }

    public function handle(): int
    {
        $host = $this->input->option('host', '127.0.0.1');
        $port = (int) $this->input->option('port', '8000');

        if (preg_match('/^[A-Za-z0-9.:-]+\z/', $host) !== 1 || $port < 1 || $port > 65535) {
            $this->out->error('Geçersiz adres ya da port.');

            return self::HATA;
        }

        $kok     = CY_BASE;
        $router  = $kok . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'gelistirme-sunucusu.php';
        $adres   = 'http://' . ($host === '0.0.0.0' ? '127.0.0.1' : $host) . ':' . $port;

        $this->out->success('Geliştirme sunucusu: ' . $adres);
        $this->out->muted('  Kurulum sihirbazı: ' . $adres . '/kurulum/   ·   Durdurmak için Ctrl+C');

        $komut = escapeshellarg(PHP_BINARY) . ' -S ' . escapeshellarg($host . ':' . $port)
               . ' -t ' . escapeshellarg($kok) . ' ' . escapeshellarg($router);

        passthru($komut, $cikis);

        return $cikis === 0 ? self::BASARILI : self::HATA;
    }
}
