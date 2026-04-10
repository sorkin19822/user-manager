<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Front router — attribute-based.
 *
 * On startup scans every class in app/Controllers/ via Reflection,
 * reads #[Route(path, methods)] attributes and builds the route map
 * in memory. No routes.php — routes are declared once, next to the method.
 *
 * Route key format: path with leading slash stripped, e.g. 'users/create'.
 * Extra URL segments beyond position 2 are passed as method parameters,
 * capped by ReflectionMethod to the exact number the method declares.
 */
class App
{
    public function __construct()
    {
        $urlParts = $this->parseUrl();
        $routes   = $this->buildRouteMap();

        $segment0 = $urlParts[0] ?? '';
        $segment1 = $urlParts[1] ?? null;

        $routeKey = $segment1 !== null
            ? "{$segment0}/{$segment1}"
            : $segment0;

        if (!isset($routes[$routeKey])) {
            $this->abort(404, 'Route not found');
            return;
        }

        $controllerClass = $routes[$routeKey]['controller'];
        $method          = $routes[$routeKey]['method'];
        $allowedMethods  = $routes[$routeKey]['methods'];
        $params          = array_slice($urlParts, 2);

        if (!in_array($this->requestMethod(), $allowedMethods, true)) {
            $this->abort(405, 'Method not allowed', ['Allow' => implode(', ', $allowedMethods)]);
            return;
        }

        $controller = new $controllerClass();

        $expected = (new \ReflectionMethod($controller, $method))->getNumberOfParameters();
        call_user_func_array([$controller, $method], array_slice($params, 0, $expected));
    }

    /**
     * Scans app/Controllers/ and builds a route map from #[Route] attributes.
     *
     * @return array<string, array{controller: string, method: string, methods: string[]}>
     */
    private function buildRouteMap(): array
    {
        $routes          = [];
        $controllersPath = __DIR__ . '/../Controllers/*.php';

        foreach (glob($controllersPath) as $file) {
            $className = 'App\\Controllers\\' . basename($file, '.php');

            foreach ((new \ReflectionClass($className))->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflMethod) {
                $attrs = $reflMethod->getAttributes(Route::class);

                if (empty($attrs)) {
                    continue;
                }

                foreach ($attrs as $attr) {
                    /** @var Route $route */
                    $route    = $attr->newInstance();
                    $routeKey = ltrim($route->path, '/');

                    $routes[$routeKey] = [
                        'controller' => $className,
                        'method'     => $reflMethod->getName(),
                        'methods'    => $this->normalizeMethods($route->methods),
                    ];
                }
            }
        }

        return $routes;
    }

    /** Splits $_GET['url'] into sanitised path segments. */
    private function parseUrl(): array
    {
        if (!empty($_GET['url'])) {
            return explode(
                '/',
                filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL)
            );
        }

        return [''];
    }

    /** Returns the current HTTP method as an uppercase token. */
    private function requestMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Normalises a comma/pipe separated methods declaration.
     *
     * @return string[]
     */
    private function normalizeMethods(string $methods): array
    {
        $parts = preg_split('/[\s,|]+/', strtoupper($methods), -1, PREG_SPLIT_NO_EMPTY);
        $normalized = $parts === false || empty($parts) ? ['GET'] : array_values(array_unique($parts));

        if (in_array('GET', $normalized, true) && !in_array('HEAD', $normalized, true)) {
            $normalized[] = 'HEAD';
        }

        return $normalized;
    }

    /** Sends a JSON error response and terminates. */
    private function abort(int $code, string $message, array $headers = []): never
    {
        http_response_code($code);
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => false, 'error' => ['code' => $code, 'message' => $message]]);
        exit;
    }
}
