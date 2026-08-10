<?php
/**
 * =====================================================================
 *  php cy make:controller <ad> – Yeni denetleyici oluşturur
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

final class MakeControllerCommand extends MakeCommand
{
    public function name(): string
    {
        return 'make:controller';
    }

    public function description(): string
    {
        return 'Yeni bir denetleyici (controller) oluşturur.';
    }

    public function help(): string
    {
        return "  php cy make:controller Urun          → app/Http/Controllers/UrunController.php\n"
             . "  php cy make:controller Urun --api    → app/Http/Controllers/Api/UrunApiController.php\n"
             . "  php cy make:controller Urun --site   → app/Http/Controllers/Site/UrunController.php\n"
             . "\n"
             . "  Oluşturduktan sonra rotasını routes/web.php içine eklemeyi\n"
             . "  unutmayın; bu şablonda otomatik rota keşfi YOKTUR.";
    }

    public function handle(): int
    {
        $base = $this->studly($this->requireName('php cy make:controller <ad>'));
        $base = preg_replace('/Controller$/', '', $base) ?? $base;

        if ($this->input->hasOption('api')) {
            $class     = $base . 'ApiController';
            $namespace = 'App\Http\Controllers\Api';
            $dir       = '/app/Http/Controllers/Api/';
            $stub      = 'controller-api';
        } elseif ($this->input->hasOption('site')) {
            $class     = $base . 'Controller';
            $namespace = 'App\Http\Controllers\Site';
            $dir       = '/app/Http/Controllers/Site/';
            $stub      = 'controller';
        } else {
            $class     = $base . 'Controller';
            $namespace = 'App\Http\Controllers';
            $dir       = '/app/Http/Controllers/';
            $stub      = 'controller';
        }

        $path = CY_BASE . $dir . $class . '.php';

        if (!$this->writeFile($path, $stub, [
            'AD'        => $class,
            'NAMESPACE' => $namespace,
            'GORUNUM'   => strtolower($base),
            'BASLIK'    => $base,
        ])) {
            return self::HATA;
        }

        $this->out->muted('  Rota eklemeyi unutmayın: routes/web.php');

        return self::BASARILI;
    }
}
