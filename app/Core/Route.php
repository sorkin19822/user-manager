<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Declares the HTTP route for a controller method.
 *
 * App::buildRouteMap() scans all controllers via Reflection and reads
 * this attribute to build the route map automatically — no routes.php needed.
 *
 * Example:
 *   #[Route('/users/create', methods: 'POST')]
 *   public function create(): void { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Route
{
    public function __construct(
        public readonly string $path,
        public readonly string $methods = 'GET',
    ) {}
}
