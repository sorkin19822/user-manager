<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Route;
use App\Models\User;

/**
 * Handles all user-related routes.
 *
 * Page routes: GET / and /users → index() renders the SPA shell
 * API routes:  all others   → return JSON {status, error, ...payload}
 *
 * Error codes:
 *   100 — user not found
 *   101 — validation error (empty / invalid field)
 *   102 — invalid bulk action
 */
class UserController extends Controller
{
    private const NAME_MAX_LENGTH = 100;

    /** Renders the main page (Bootstrap SPA shell). */
    #[Route('/', methods: 'GET')]
    #[Route('/users', methods: 'GET')]
    public function index(): void
    {
        $this->renderView('User/index', [], 'User Manager');
    }

    /** Returns all users as a JSON array. */
    #[Route('/users/list', methods: 'GET')]
    public function list(): void
    {
        /** @var User $model */
        $model = $this->loadModel('User');
        $this->jsonResponse(['status' => true, 'error' => null, 'users' => $model->getAllUsers()]);
    }

    /** Returns a single user by URL-segment id. */
    #[Route('/users/get', methods: 'GET')]
    public function get(string $id = ''): void
    {
        $intId = (int) $id;
        if ($intId <= 0) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 101, 'message' => 'invalid id']],
                400
            );
            return;
        }

        /** @var User $model */
        $model = $this->loadModel('User');
        $user  = $model->getUserById($intId);

        if ($user === false) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 100, 'message' => 'not found user']],
                404
            );
            return;
        }

        $this->jsonResponse(['status' => true, 'error' => null, 'user' => $user]);
    }

    /**
     * Creates a new user from POST body.
     * Returns {status, error, id}.
     * Missing status defaults to 'active'.
     */
    #[Route('/users/create', methods: 'POST')]
    public function create(): void
    {
        $data = $this->extractUserInput();
        $err  = $this->validateUserInput($data);

        if ($err !== null) {
            $this->jsonResponse(['status' => false, 'error' => $err], 422);
            return;
        }

        /** @var User $model */
        $model = $this->loadModel('User');
        $id    = $model->createUser($data);

        $this->jsonResponse(['status' => true, 'error' => null, 'id' => $id]);
    }

    /**
     * Updates an existing user.
     * Uses updateUser() return value as the authoritative existence check
     * to avoid a TOCTOU race between a pre-check SELECT and the UPDATE.
     * Returns {status, error, user}.
     */
    #[Route('/users/update', methods: 'POST')]
    public function update(string $id = ''): void
    {
        $intId = (int) $id;
        if ($intId <= 0) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 101, 'message' => 'invalid id']],
                400
            );
            return;
        }

        $data = $this->extractUserInput();
        $err  = $this->validateUserInput($data);

        if ($err !== null) {
            $this->jsonResponse(['status' => false, 'error' => $err], 422);
            return;
        }

        /** @var User $model */
        $model   = $this->loadModel('User');
        $updated = $model->updateUser($intId, $data);

        if (!$updated) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 100, 'message' => 'not found user']],
                404
            );
            return;
        }

        $this->jsonResponse(['status' => true, 'error' => null, 'user' => $model->getUserById($intId)]);
    }

    /**
     * Deletes a single user.
     * Returns {status, error}.
     */
    #[Route('/users/delete', methods: 'POST')]
    public function delete(string $id = ''): void
    {
        $intId = (int) $id;
        if ($intId <= 0) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 101, 'message' => 'invalid id']],
                400
            );
            return;
        }

        /** @var User $model */
        $model   = $this->loadModel('User');
        $deleted = $model->deleteUser($intId);

        if (!$deleted) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 100, 'message' => 'not found user']],
                404
            );
            return;
        }

        $this->jsonResponse(['status' => true, 'error' => null]);
    }

    /**
     * Executes a bulk action on multiple users.
     *
     * Expected POST body:
     *   ids[]  — one or more integer user ids
     *   action — set_active | set_inactive | delete
     */
    #[Route('/users/bulk', methods: 'POST')]
    public function bulk(): void
    {
        $action = trim($_POST['action'] ?? '');
        $rawIds = $_POST['ids'] ?? [];

        if (!in_array($action, User::allowedActions(), true)) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 102, 'message' => 'invalid action']],
                422
            );
            return;
        }

        if (!is_array($rawIds) || empty($rawIds)) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 101, 'message' => 'ids must be a non-empty array']],
                422
            );
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $rawIds), static fn(int $id): bool => $id > 0));

        if (empty($ids)) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 101, 'message' => 'ids must contain positive integers']],
                422
            );
            return;
        }

        /** @var User $model */
        $model    = $this->loadModel('User');
        $affected = $model->bulkAction($ids, $action);

        if (!$affected) {
            $this->jsonResponse(
                ['status' => false, 'error' => ['code' => 100, 'message' => 'no users matched']],
                404
            );
            return;
        }

        $this->jsonResponse(['status' => true, 'error' => null]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Reads and trims user fields from $_POST.
     * Missing status defaults to 'active'.
     *
     * @return array{name_first: string, name_last: string, role: string, status: string}
     */
    private function extractUserInput(): array
    {
        return [
            'name_first' => trim($_POST['name_first'] ?? ''),
            'name_last'  => trim($_POST['name_last']  ?? ''),
            'role'       => trim($_POST['role']        ?? ''),
            'status'     => $this->normalizeStatus($_POST['status'] ?? 'active'),
        ];
    }

    /**
     * Validates user input fields.
     * Returns an error array on failure, or null on success.
     *
     * @param  array{name_first: string, name_last: string, role: string, status: string} $data
     * @return array{code: int, message: string}|null
     */
    private function validateUserInput(array $data): ?array
    {
        if ($data['name_first'] === '') {
            return ['code' => 101, 'message' => 'name_first is required'];
        }

        if (mb_strlen($data['name_first']) > self::NAME_MAX_LENGTH) {
            return ['code' => 101, 'message' => 'name_first must not exceed ' . self::NAME_MAX_LENGTH . ' characters'];
        }

        if ($data['name_last'] === '') {
            return ['code' => 101, 'message' => 'name_last is required'];
        }

        if (mb_strlen($data['name_last']) > self::NAME_MAX_LENGTH) {
            return ['code' => 101, 'message' => 'name_last must not exceed ' . self::NAME_MAX_LENGTH . ' characters'];
        }

        if (!in_array($data['role'], User::allowedRoles(), true)) {
            return ['code' => 101, 'message' => 'role must be admin or user'];
        }

        if (!in_array($data['status'], User::allowedStatuses(), true)) {
            return ['code' => 101, 'message' => 'status must be active or inactive'];
        }

        return null;
    }

    private function normalizeStatus(mixed $status): string
    {
        if (is_bool($status)) {
            return $status ? 'active' : 'inactive';
        }

        $status = strtolower(trim((string) $status));

        return match ($status) {
            '1', 'true', 'on', 'active' => 'active',
            '0', 'false', 'off', 'inactive' => 'inactive',
            default => $status,
        };
    }
}
