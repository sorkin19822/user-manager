<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PDO wrapper — prepares statements, binds params, executes queries.
 *
 * Usage:
 *   $db->query('SELECT * FROM users WHERE id = :id');
 *   $db->bind(':id', $id, \PDO::PARAM_INT);
 *   return $db->result();
 */
class Database
{
    private \PDO $dbh;
    private ?\PDOStatement $stmt = null;

    public function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        $this->dbh = new \PDO($dsn, DB_USER, DB_PASS, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    /** Prepares an SQL statement. Must be called before bind/execute. */
    public function query(string $sql): void
    {
        $this->stmt = $this->dbh->prepare($sql);
    }

    /** Binds a value to a named placeholder. */
    public function bind(string $param, mixed $value, int $type = \PDO::PARAM_STR): void
    {
        $this->stmt->bindValue($param, $value, $type);
    }

    /** Executes the prepared statement. */
    public function execute(): bool
    {
        if ($this->stmt === null) {
            throw new \LogicException('Call query() before execute()');
        }
        return $this->stmt->execute();
    }

    /** Executes and returns all rows as an associative array. */
    public function results(): array
    {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    /** Executes and returns a single row, or false if not found. */
    public function result(): array|false
    {
        $this->execute();
        return $this->stmt->fetch();
    }

    /** Returns the last auto-increment ID after an INSERT. */
    public function lastInsertId(): int
    {
        return (int) $this->dbh->lastInsertId();
    }

    /** Returns the number of rows affected by the last statement. */
    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }
}
