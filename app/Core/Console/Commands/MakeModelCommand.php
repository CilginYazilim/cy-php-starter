<?php
/**
 * =====================================================================
 *  php cy make:model <ad> – Yeni model (+ isteğe bağlı repository)
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

final class MakeModelCommand extends MakeCommand
{
    public function name(): string
    {
        return 'make:model';
    }

    public function description(): string
    {
        return 'Yeni bir model (ve isteğe bağlı repository) oluşturur.';
    }

    public function help(): string
    {
        return "  php cy make:model Urun               → app/Models/Urun.php\n"
             . "  php cy make:model Urun --repository  → Repository sınıfını da üretir\n"
             . "\n"
             . "  Bu şablonda model bir ORM DEĞİLDİR: veritabanı satırını\n"
             . "  temsil eden sade bir nesnedir (entity). Sorgular\n"
             . "  Repository sınıflarında yaşar.";
    }

    public function handle(): int
    {
        $class = $this->studly($this->requireName('php cy make:model <ad>'));

        $path = CY_BASE . '/app/Models/' . $class . '.php';

        if (!$this->writeFile($path, 'model', [
            'AD'    => $class,
            'TABLO' => $this->snake($class) . 'lar',
        ])) {
            return self::HATA;
        }

        if ($this->input->hasOption('repository')) {
            $repoPath = CY_BASE . '/app/Repositories/' . $class . 'Repository.php';

            $this->writeFile($repoPath, 'repository', [
                'AD'    => $class . 'Repository',
                'MODEL' => $class,
                'TABLO' => $this->snake($class) . 'lar',
            ]);
        }

        return self::BASARILI;
    }
}
