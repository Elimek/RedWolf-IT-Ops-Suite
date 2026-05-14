<?php

declare(strict_types=1);

/**
 * Database.php - Singleton PDO Connection Manager
 *
 * Provides a single PDO instance for database operations throughout
 * the RedWolf Magento Lite application. Reads connection credentials
 * from environment variables with sensible defaults for development.
 *
 * @package RedWolf\MagentoLite\Database
 * @version 1.0.0
 */

namespace RedWolf\MagentoLite\Database;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    /** @var ?Database Singleton instance */
    private static ?Database $instance = null;

    /** @var ?PDO The PDO connection handle */
    private ?PDO $pdo = null;

    /**
     * Private constructor to enforce singleton pattern.
     * Establishes the PDO connection using environment variables.
     */
    private function __construct()
    {
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'redwolf';
        $user = getenv('DB_USER') ?: 'redwolf';
        $pass = getenv('DB_PASS') ?: 'redwolf_secret';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone(): void
    {
    }

    /**
     * Returns the singleton Database instance.
     * Creates the instance on first call.
     *
     * @return self The singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns the underlying PDO connection.
     *
     * @return PDO The PDO connection handle
     * @throws RuntimeException If no connection is available
     */
    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            throw new RuntimeException('Database connection is not initialized.');
        }

        return $this->pdo;
    }

    /**
     * Prepares and executes a SQL query with bound parameters.
     *
     * @param string $sql    The SQL statement with placeholders
     * @param array  $params Parameters to bind (key-value pairs)
     * @return \PDOStatement The executed statement
     * @throws PDOException On query execution failure
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Begins a database transaction.
     *
     * @return bool True on success
     * @throws PDOException If a transaction is already active
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commits the current transaction.
     *
     * @return bool True on success
     * @throws PDOException If no transaction is active
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Rolls back the current transaction.
     *
     * @return bool True on success
     * @throws PDOException If no transaction is active
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }

    /**
     * Returns the last inserted auto-increment ID.
     *
     * @return string The last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }
}
