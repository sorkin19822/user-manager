<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Base controller.
 *
 * Page routes call renderView(); API routes call jsonResponse().
 * Child controllers inherit loadModel() to get a model instance.
 */
class Controller
{
    /**
     * Instantiates a model from app/Models/{$model}.php.
     *
     * @template T of object
     * @param  string $model  Class name without namespace, e.g. 'User'
     * @return object
     */
    protected function loadModel(string $model): object
    {
        $class = "App\\Models\\{$model}";
        return new $class();
    }

    /**
     * Renders an HTML view through layout.php.
     *
     * @param string $viewPath  Relative to app/Views/, e.g. 'User/index'
     * @param array  $data      Variables extracted into view scope (EXTR_SKIP)
     * @param string $title     <title> tag value
     */
    protected function renderView(string $viewPath, array $data = [], string $title = 'User Manager'): void
    {
        extract($data, EXTR_SKIP);
        require_once __DIR__ . '/../Views/layout.php';
    }

    /**
     * Sends a JSON response and terminates execution.
     *
     * @param array $data  Response payload
     * @param int   $code  HTTP status code (default 200)
     */
    protected function jsonResponse(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
