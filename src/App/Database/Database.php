<?php

declare(strict_types=1);

namespace FP\DMS\App\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database abstraction layer
 * Replaces WordPress's wpdb with PDO
 */
class Database
{
    private ?PDO $pdo = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $driver = $this->config['driver'] ?? 'mysql';
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $driver,
            $host,
            $port,
            $database,
            $charset
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'] ?? '',
                $this->config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->pdo;
    }

    public function getPrefix(): string
    {
        return $this->config['prefix'] ?? '';
    }

    public function table(string $name): string
    {
        return $this->getPrefix() . $name;
    }

    /**
     * Execute a query and return all results
     *
     * @param string $query
     * @param array $params
     * @return array
     */
    public function get_results(string $query, array $params = []): array
    {
        $pdo = $this->connect();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return a single row
     *
     * @param string $query
     * @param array $params
     * @return object|null
     */
    public function get_row(string $query, array $params = []): ?object
    {
        $pdo = $this->connect();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_OBJ);

        return $result !== false ? $result : null;
    }

    /**
     * Execute a query and return a single variable
     *
     * @param string $query
     * @param array $params
     * @return mixed
     */
    public function get_var(string $query, array $params = []): mixed
    {
        $pdo = $this->connect();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * Insert a row
     *
     * @param string $table
     * @param array $data
     * @return int Last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $pdo = $this->connect();
        
        // Validate table name to prevent SQL injection
        $table = $this->validateIdentifier($table, 'table');
        
        $fields = array_keys($data);
        
        // Validate field names to prevent SQL injection
        $validatedFields = array_map(fn($field) => $this->validateIdentifier($field, 'field'), $fields);
        $placeholders = array_map(fn($field) => ':' . $field, $fields);
        
        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $validatedFields),
            implode(', ', $placeholders)
        );
        
        $stmt = $pdo->prepare($query);
        
        foreach ($data as $field => $value) {
            $stmt->bindValue(':' . $field, $value);
        }
        
        $stmt->execute();

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update rows
     *
     * @param string $table
     * @param array $data
     * @param array $where
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, array $where): int
    {
        $pdo = $this->connect();
        
        // Validate table name
        $table = $this->validateIdentifier($table, 'table');
        
        $setClause = [];
        foreach (array_keys($data) as $field) {
            $validField = $this->validateIdentifier($field, 'field');
            $setClause[] = sprintf('%s = :data_%s', $validField, $field);
        }
        
        $whereClause = [];
        foreach (array_keys($where) as $field) {
            $validField = $this->validateIdentifier($field, 'field');
            $whereClause[] = sprintf('%s = :where_%s', $validField, $field);
        }
        
        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setClause),
            implode(' AND ', $whereClause)
        );
        
        $stmt = $pdo->prepare($query);
        
        foreach ($data as $field => $value) {
            $stmt->bindValue(':data_' . $field, $value);
        }
        
        foreach ($where as $field => $value) {
            $stmt->bindValue(':where_' . $field, $value);
        }
        
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Delete rows
     *
     * @param string $table
     * @param array $where
     * @return int Number of affected rows
     */
    public function delete(string $table, array $where): int
    {
        $pdo = $this->connect();
        
        // Validate table name
        $table = $this->validateIdentifier($table, 'table');
        
        $whereClause = [];
        foreach (array_keys($where) as $field) {
            $validField = $this->validateIdentifier($field, 'field');
            $whereClause[] = sprintf('%s = :%s', $validField, $field);
        }
        
        $query = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $whereClause)
        );
        
        $stmt = $pdo->prepare($query);
        
        foreach ($where as $field => $value) {
            $stmt->bindValue(':' . $field, $value);
        }
        
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Execute a query
     *
     * @param string $query
     * @param array $params
     * @return bool
     */
    public function query(string $query, array $params = []): bool
    {
        $pdo = $this->connect();
        $stmt = $pdo->prepare($query);

        return $stmt->execute($params);
    }

    /**
     * Prepare a query
     *
     * @deprecated This method is unsafe and should not be used. Use PDO prepared statements instead.
     * @param string $query
     * @param array $args
     * @return string
     * @throws RuntimeException Always throws exception to prevent usage
     */
    public function prepare(string $query, ...$args): string
    {
        throw new RuntimeException(
            'Database::prepare() is deprecated and unsafe. ' .
            'Use PDO prepared statements with get_results(), get_row(), get_var(), insert(), update(), or delete() instead.'
        );
    }

    /**
     * Get charset collate string
     *
     * @return string
     */
    public function get_charset_collate(): string
    {
        $charset = $this->config['charset'] ?? 'utf8mb4';
        $collation = $this->config['collation'] ?? 'utf8mb4_unicode_ci';

        return sprintf('DEFAULT CHARACTER SET %s COLLATE %s', $charset, $collation);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connect()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connect()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->connect()->rollBack();
    }

    /**
     * Validate SQL identifier (table or field name) to prevent SQL injection.
     *
     * @param string $identifier The identifier to validate
     * @param string $type Type of identifier (table or field)
     * @return string The validated identifier
     * @throws RuntimeException If identifier is invalid
     */
    private function validateIdentifier(string $identifier, string $type = 'field'): string
    {
        // Allow alphanumeric characters, underscores, and dots (for table.field)
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $identifier)) {
            throw new RuntimeException("Invalid {$type} name: {$identifier}");
        }

        // Additional length check
        if (strlen($identifier) > 64) {
            throw new RuntimeException("{$type} name too long: {$identifier}");
        }

        return $identifier;
    }
}
