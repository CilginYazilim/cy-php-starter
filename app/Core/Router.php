<?php
/**
 * =====================================================================
 *  Router – İsteği doğru denetleyiciye yönlendirir
 * ---------------------------------------------------------------------
 *      $router->get('panel/kullanicilar', UserController::class, 'index',
 *          ['installed', 'auth', 'can:users.view']);
 *
 *      // Parametreli rota — değer metoda argüman olarak geçer:
 *      $router->get('api/v1/urunler/{id}', UrunApi::class, 'show', ['api']);
 *      // → public function show(Request $request, string $id): void
 *
 *  İKİ AŞAMALI EŞLEŞTİRME
 *  Parametresiz rotalar bir DİZİ ANAHTARINDA tutulur ve O(1) bulunur.
 *  Yalnızca parametreli rotalar sırayla denenir. Böylece yüzlerce rota
 *  eklendiğinde bile her istek düzinelerce düzenli ifade çalıştırmaz.
 *
 *  DESTEKLENEN YÖNTEMLER: GET, POST, PUT, PATCH, DELETE.
 *  HTML formları yalnızca GET/POST gönderebildiği için, formdan gelen
 *  "_method" alanı da dikkate alınır (yöntem sahteleme).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

final class Router
{
    private const VERBS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var array<string,array{0:string,1:string,2:array<int,string>}> Sabit rotalar */
    private array $static = [];

    /** @var array<int,array{verb:string,regex:string,params:array<int,string>,handler:array{0:string,1:string,2:array<int,string>}}> */
    private array $dynamic = [];

    private ?\Closure $notFound = null;

    private string $prefix = '';

    /** @var array<int,string> */
    private array $groupMiddleware = [];

    /* =================================================================
     *  KAYIT
     * ============================================================== */

    /** @param array<int,string> $middleware */
    public function get(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('GET', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function post(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('POST', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function put(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('PUT', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function patch(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('PATCH', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function delete(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('DELETE', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function any(string $path, string $controller, string $method, array $middleware = []): self
    {
        $this->add('GET', $path, $controller, $method, $middleware);

        return $this->add('POST', $path, $controller, $method, $middleware);
    }

    /**
     * Ortak önek ve ara katmanla rota grubu.
     *
     *      $router->group('api/v1', ['api'], function (Router $r) {
     *          $r->get('urunler', UrunApi::class, 'index');
     *      });
     *
     * @param array<int,string> $middleware
     */
    public function group(string $prefix, array $middleware, callable $callback): self
    {
        $oncekiPrefix     = $this->prefix;
        $oncekiMiddleware = $this->groupMiddleware;

        $this->prefix          = trim($oncekiPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = array_merge($oncekiMiddleware, $middleware);

        $callback($this);

        $this->prefix          = $oncekiPrefix;
        $this->groupMiddleware = $oncekiMiddleware;

        return $this;
    }

    /** @param array<int,string> $middleware */
    private function add(string $verb, string $path, string $controller, string $method, array $middleware): self
    {
        $path = trim($path, '/');

        if ($this->prefix !== '') {
            $path = $path === '' ? $this->prefix : $this->prefix . '/' . $path;
        }

        $middleware = array_values(array_unique(array_merge($this->groupMiddleware, $middleware)));
        $handler    = [$controller, $method, $middleware];

        if (!str_contains($path, '{')) {
            /* İLK KAYIT KAZANIR. Modül rotaları çekirdekten sonra
             * eklenir; bir modülün yanlışlıkla "giris" gibi temel bir
             * adresi ele geçirmesini böylece engelleriz. */
            if (!isset($this->static[$verb . ' ' . $path])) {
                $this->static[$verb . ' ' . $path] = $handler;
            }

            return $this;
        }

        $params = [];

        /* SIRALAMA ÖNEMLİ: önce yolu kaçışlarız (rotada nokta gibi
         * özel karakter olabilir), SONRA süslü parantezleri geri
         * açarız — çünkü preg_quote "{" ve "}" karakterlerini de
         * kaçışlar ve bunu yapmazsak yer tutucular tanınmaz. */
        $quoted = str_replace(['\{', '\}'], ['{', '}'], preg_quote($path, '#'));

        /* "{id}" → yakalama grubu. Değerler ROTADAN gelir, yani
         * kullanıcı girdisidir: yalnızca güvenli karakterlere izin
         * veriyoruz (Url::current zaten süzüyor, bu ikinci kilit). */
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];

                return '([a-zA-Z0-9_-]+)';
            },
            $quoted
        ) ?? '';

        $this->dynamic[] = [
            'verb'    => $verb,
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];

        return $this;
    }

    public function fallback(\Closure $handler): self
    {
        $this->notFound = $handler;

        return $this;
    }

    /* =================================================================
     *  ÇÖZÜMLEME
     * ============================================================== */

    public function currentPath(): string
    {
        return Url::current();
    }

    public function dispatch(Request $request): void
    {
        $path = $this->currentPath();
        $verb = $this->resolveVerb($request);

        $key = $verb . ' ' . $path;

        if (isset($this->static[$key])) {
            $this->run($this->static[$key], $request, []);

            return;
        }

        foreach ($this->dynamic as $route) {
            if ($route['verb'] !== $verb) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) === 1) {
                array_shift($matches);

                $this->run($route['handler'], $request, $matches);

                return;
            }
        }

        $this->miss($request, $path, $verb);
    }

    /**
     * İsteğin yöntemi.
     *
     * HEAD, gövdesiz GET demektir. HTML formları yalnızca GET/POST
     * gönderebildiği için POST içindeki "_method" alanı da dikkate
     * alınır — böylece bir formdan DELETE isteği yapılabilir.
     */
    private function resolveVerb(Request $request): string
    {
        $verb = $request->method();

        if ($verb === 'HEAD') {
            return 'GET';
        }

        if ($verb === 'POST') {
            $spoofed = strtoupper(trim((string) ($_POST['_method'] ?? '')));

            if ($spoofed !== '' && in_array($spoofed, self::VERBS, true)) {
                return $spoofed;
            }
        }

        return $verb;
    }

    /**
     * @param array{0:string,1:string,2:array<int,string>} $handler
     * @param array<int,string> $params
     */
    private function run(array $handler, Request $request, array $params): void
    {
        [$controllerClass, $method, $middleware] = $handler;

        foreach ($middleware as $rule) {
            Middleware::handle($rule, $request);
        }

        $controller = new $controllerClass();

        $controller->{$method}($request, ...$params);
    }

    private function miss(Request $request, string $path, string $verb): void
    {
        /* Adres var ama yöntem yanlışsa 405 demek 404'ten daha
         * dürüsttür: geliştirici "rotayı yazmayı unuttum" diye
         * aramak yerine doğrudan yöntem uyuşmazlığını görür. */
        $allowed = [];

        foreach (self::VERBS as $candidate) {
            if (isset($this->static[$candidate . ' ' . $path])) {
                $allowed[] = $candidate;

                continue;
            }

            foreach ($this->dynamic as $route) {
                if ($route['verb'] === $candidate && preg_match($route['regex'], $path) === 1) {
                    $allowed[] = $candidate;

                    break;
                }
            }
        }

        if ($allowed !== []) {
            throw HttpException::methodNotAllowed($verb, $allowed);
        }

        // Modüller kendi 404 davranışını tanımlayabilsin diye
        // özel bir işleyici varsa ona öncelik veriyoruz.
        if ($this->notFound !== null) {
            ($this->notFound)($request, $path);

            return;
        }

        throw HttpException::notFound($path);
    }
}
