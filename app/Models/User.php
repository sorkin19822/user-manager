<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * User data-access layer.
 *
 * All queries use prepared statements via Database::bind().
 * Bulk IN-clause params are generated as named placeholders (:id0, :id1, …)
 * to avoid any string interpolation of user-supplied data.
 */
class User
{
    private const ALLOWED_ROLES    = ['admin', 'user'];
    private const ALLOWED_STATUSES = ['active', 'inactive'];
    private const ALLOWED_ACTIONS  = ['set_active', 'set_inactive', 'delete'];

    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /** Returns all users ordered by id. */
    public function getAllUsers(): array
    {
        $this->db->query('SELECT id, name_first, name_last, role, status FROM users ORDER BY id');
        return array_map([$this, 'formatUser'], $this->db->results());
    }

    /** Returns a single user row, or false if not found. */
    public function getUserById(int $id): array|false
    {
        $this->db->query('SELECT id, name_first, name_last, role, status FROM users WHERE id = :id');
        $this->db->bind(':id', $id, \PDO::PARAM_INT);
        $user = $this->db->result();

        return $user === false ? false : $this->formatUser($user);
    }

    /**
     * Inserts a new user and returns its id.
     *
     * @param  array{name_first: string, name_last: string, role: string, status: string} $data
     */
    public function createUser(array $data): int
    {
        $this->db->query(
            'INSERT INTO users (name_first, name_last, role, status)
             VALUES (:name_first, :name_last, :role, :status)'
        );
        $this->db->bind(':name_first', $data['name_first']);
        $this->db->bind(':name_last',  $data['name_last']);
        $this->db->bind(':role',       $data['role']);
        $this->db->bind(':status',     $data['status']);
        $this->db->execute();

        return $this->db->lastInsertId();
    }

    /**
     * Updates an existing user. Returns true if the row exists.
     *
     * @param  array{name_first: string, name_last: string, role: string, status: string} $data
     */
    public function updateUser(int $id, array $data): bool
    {
        if (!$this->userExists($id)) {
            return false;
        }

        $this->db->query(
            'UPDATE users
             SET name_first = :name_first, name_last = :name_last,
                 role = :role, status = :status
             WHERE id = :id'
        );
        $this->db->bind(':name_first', $data['name_first']);
        $this->db->bind(':name_last',  $data['name_last']);
        $this->db->bind(':role',       $data['role']);
        $this->db->bind(':status',     $data['status']);
        $this->db->bind(':id',         $id, \PDO::PARAM_INT);
        $this->db->execute();

        return true;
    }

    /** Deletes a user. Returns true if a row was deleted. */
    public function deleteUser(int $id): bool
    {
        $this->db->query('DELETE FROM users WHERE id = :id');
        $this->db->bind(':id', $id, \PDO::PARAM_INT);
        $this->db->execute();

        return $this->db->rowCount() > 0;
    }

    /**
     * Executes a bulk action on multiple users.
     *
     * Defensive: re-validates $action here even though the controller
     * already checked it — the model must be safe to call in isolation.
     *
     * @param  int[]  $ids     Validated array of positive integer user ids
     * @param  string $action  One of: set_active | set_inactive | delete
     * @return int[]|false     Existing user ids affected by the action, or false if none matched
     */
    public function bulkAction(array $ids, string $action): array|false
    {
        if (empty($ids) || !in_array($action, self::ALLOWED_ACTIONS, true)) {
            return false;
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $matchedIds = $this->getUserIdsByIds($ids);

        $placeholders = [];
        foreach ($matchedIds as $i => $_) {
            $placeholders[] = ":id{$i}";
        }
        $in = implode(',', $placeholders);

        if (empty($matchedIds)) {
            return false;
        }

        if ($action === 'delete') {
            $this->db->query("DELETE FROM users WHERE id IN ({$in})");
        } else {
            $status = $action === 'set_active' ? 'active' : 'inactive';
            $this->db->query("UPDATE users SET status = :status WHERE id IN ({$in})");
            $this->db->bind(':status', $status);
        }

        foreach ($matchedIds as $i => $id) {
            $this->db->bind(":id{$i}", $id, \PDO::PARAM_INT);
        }

        $this->db->execute();
        return $matchedIds;
    }

    /** @return string[] */
    public static function allowedRoles(): array
    {
        return self::ALLOWED_ROLES;
    }

    /** @return string[] */
    public static function allowedStatuses(): array
    {
        return self::ALLOWED_STATUSES;
    }

    /** @return string[] */
    public static function allowedActions(): array
    {
        return self::ALLOWED_ACTIONS;
    }

    private function formatUser(array $user): array
    {
        $user['id']     = (int) $user['id'];
        $user['status'] = $user['status'] === 'active';

        return $user;
    }

    private function userExists(int $id): bool
    {
        $this->db->query('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $this->db->bind(':id', $id, \PDO::PARAM_INT);

        return $this->db->result() !== false;
    }

    /**
     * @param  int[] $ids
     * @return int[]
     */
    private function getUserIdsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = [];
        foreach ($ids as $i => $_) {
            $placeholders[] = ":id{$i}";
        }

        $this->db->query('SELECT id FROM users WHERE id IN (' . implode(',', $placeholders) . ') ORDER BY id');
        foreach ($ids as $i => $id) {
            $this->db->bind(":id{$i}", $id, \PDO::PARAM_INT);
        }

        return array_map('intval', array_column($this->db->results(), 'id'));
    }
}
