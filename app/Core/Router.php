<?php
/**
 * =====================================================================
 *  Router – İsteği doğru denetleyiciye yönlendirir
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string,array{0:string,1:string,2:array<int,string>}> */
    private array $routes = [];

    private ?\Closure $notFound = null;

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
    public function any(string $path, string $controller, string $method, array $middleware = []): self
    {
        $this->add('GET', $path, $controller, $method, $middleware);

        return $this->add('POST', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    private function add(string $verb, string $path, string $controller, string $method, array $middleware): self
    {
        $this->routes[$verb . ' ' . trim($path, '/')] = [$controller, $method, $middleware];

        return $this;
    }

    public function fallback(\Closure $handler): self
    {
        $this->notFound = $handler;

        return $this;
    }

    public function currentPath(): string
    {
        $raw = (string) ($_GET['r'] ?? '');

        if ($raw === '' && isset($_SERVER['PATH_INFO'])) {
            $raw = (string) $_SERVER['PATH_INFO'];
        }

        $clean = preg_replace('#[^a-zA-Z0-9/_-]#', '', $raw) ?? '';
        $clean = trim(preg_replace('#/+#', '/', $clean) ?? '', '/');

        return $clean === '' ? '' : $clean;
    }

    public function dispatch(Request $request): void
    {
        $path = $this->currentPath();

        // HEAD, gövdesiz GET demektir; tarayıcılar ve izleme araçları kullanır.
        $verb = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $key  = $verb . ' ' . $path;

        if (!isset($this->routes[$key])) {
            $existsWithOtherVerb = isset($this->routes['GET ' . $path]) || isset($this->routes['POST ' . $path]);

            if ($existsWithOtherVerb) {
                if ($request->isAjax()) {
                    Response::error('Bu adres için geçersiz istek yöntemi.', 405);
                }
                http_response_code(405);
            }

            if ($this->notFound !== null) {
                ($this->notFound)($request, $path);
                return;
            }

            http_response_code(404);
            echo 'Sayfa bulunamadı.';
            return;
        }

        [$controllerClass, $method, $middleware] = $this->routes[$key];

        foreach ($middleware as $rule) {
            Middleware::handle($rule, $request);
        }

        $controller = new $controllerClass();
        $controller->{$method}($request);
    }
}
