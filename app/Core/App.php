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
        $params          = array_slice($urlParts, 2);

        $controller = new $controllerClass();

        $expected = (new \ReflectionMethod($controller, $method))->getNumberOfParameters();
        call_user_func_array([$controller, $method], array_slice($params, 0, $expected));
    }

    /**
     * Scans app/Controllers/ and builds a route map from #[Route] attributes.
     *
     * @return array<string, array{controller: string, method: string}>
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

                /** @var Route $route */
                $route    = $attrs[0]->newInstance();
                $routeKey = ltrim($route->path, '/');

                $routes[$routeKey] = [
                    'controller' => $className,
                    'method'     => $reflMethod->getName(),
                ];
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

    /** Sends a JSON error response and terminates. */
    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => false, 'error' => ['code' => $code, 'message' => $message]]);
        exit;
    }
}
