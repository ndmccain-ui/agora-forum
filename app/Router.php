<?php
declare(strict_types=1);
defined('AGORA') or exit;

/**
 * Tiny router. Routes look like '/c/{slug}' or '/d/{id:\d+}'.
 * Handlers receive the matched parameters as arguments and return HTML.
 */
final class Router
{
    /** @var array<int, array{0: string[], 1: string, 2: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add(['POST'], $pattern, $handler);
    }

    public function add(array $methods, string $pattern, callable $handler): void
    {
        $regex = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}/',
            fn (array $m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $pattern
        );
        $this->routes[] = [$methods, '#^' . $regex . '$#u', $handler];
    }

    public function dispatch(string $method, string $path): string
    {
        $pathMatched = false;
        foreach ($this->routes as [$methods, $regex, $handler]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if (!in_array($method, $methods, true)) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($method === 'POST') {
                csrf_verify();
            }
            return (string) $handler(...$params);
        }

        if ($pathMatched) {
            http_response_code(405);
            return view('message', [
                'title' => 'Not allowed',
                'message' => "That page can't be used that way.",
            ], 405);
        }
        not_found();
    }
}
