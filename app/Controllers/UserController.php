<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Route;

/**
 * Handles all user-related routes.
 *
 * Page route:  GET  /        → index()   renders the SPA shell
 * API routes:  all others    → return JSON {status, error, ...payload}
 *
 * Full CRUD + bulk logic implemented in iteration 3.
 */
class UserController extends Controller
{
    /** Renders the main page (Bootstrap SPA shell). */
    #[Route('/users', methods: 'GET')]
    public function index(): void
    {
        $this->renderView('User/index', [], 'User Manager');
    }

    /** Returns all users as a JSON array. */
    #[Route('/users/list', methods: 'GET')]
    public function list(): void
    {
        $this->jsonResponse(['status' => true, 'error' => null, 'users' => []]);
    }

    /** Returns a single user by URL segment id. */
    #[Route('/users/get', methods: 'GET')]
    public function get(string $id = ''): void
    {
        $this->jsonResponse(['status' => true, 'error' => null, 'user' => null]);
    }

    /** Creates a new user from POST body. Returns {status, error, id}. */
    #[Route('/users/create', methods: 'POST')]
    public function create(): void
    {
        $this->jsonResponse(['status' => true, 'error' => null, 'id' => null]);
    }

    /** Updates an existing user. Returns {status, error, user}. */
    #[Route('/users/update', methods: 'POST')]
    public function update(string $id = ''): void
    {
        $this->jsonResponse(['status' => true, 'error' => null, 'user' => null]);
    }

    /** Deletes a single user. Returns {status, error}. */
    #[Route('/users/delete', methods: 'POST')]
    public function delete(string $id = ''): void
    {
        $this->jsonResponse(['status' => true, 'error' => null]);
    }

    /**
     * Executes a bulk action on multiple users.
     *
     * Expected POST body: ids[] (int[]), action (set_active|set_inactive|delete)
     */
    #[Route('/users/bulk', methods: 'POST')]
    public function bulk(): void
    {
        $this->jsonResponse(['status' => true, 'error' => null]);
    }
}
