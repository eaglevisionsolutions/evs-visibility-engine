<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\JwtAuthMiddleware;

/**
 * All routes live under /api/v1/ (CLAUDE.md). Routes registered with
 * `auth: true` run through JwtAuthMiddleware first, which resolves
 * account_id/user_id from the Bearer token and attaches them to the
 * Request — controllers read them via $request->accountId(), never from
 * a client-supplied parameter.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, paramNames: list<string>, handler: callable, auth: bool}> */
    private array $routes = [];

    public function __construct(
        private readonly JwtAuthMiddleware $authMiddleware,
    ) {
    }

    public function get(string $path, callable $handler, bool $auth = true): void
    {
        $this->add('GET', $path, $handler, $auth);
    }

    public function post(string $path, callable $handler, bool $auth = true): void
    {
        $this->add('POST', $path, $handler, $auth);
    }

    public function put(string $path, callable $handler, bool $auth = true): void
    {
        $this->add('PUT', $path, $handler, $auth);
    }

    public function delete(string $path, callable $handler, bool $auth = true): void
    {
        $this->add('DELETE', $path, $handler, $auth);
    }

    private function add(string $method, string $path, callable $handler, bool $auth): void
    {
        $paramNames = [];
        $pattern = preg_replace_callback(
            '#\{(\w+)\}#',
            function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $pattern . '$#',
            'paramNames' => $paramNames,
            'handler' => $handler,
            'auth' => $auth,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (!preg_match($route['pattern'], $request->path, $matches)) {
                continue;
            }

            $params = array_intersect_key($matches, array_flip($route['paramNames']));

            if ($route['auth']) {
                $result = $this->authMiddleware->handle($request);

                if ($result instanceof Response) {
                    return $result;
                }

                $request = $result;
            }

            return ($route['handler'])($request, $params);
        }

        return Response::error('Not found', 404);
    }
}
