<?php

declare(strict_types=1);

namespace FP\DMS\App\Database;

/**
 * Global database adapter
 * This class provides a global $wpdb replacement for the existing codebase
 */
class DatabaseAdapter
{
    private static ?Database $instance = null;

    public static function setInstance(Database $db): void
    {
        self::$instance = $db;
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Database not initialized. Call DatabaseAdapter::setInstance() first.');
        }

        return self::$instance;
    }

    /**
     * Get table name with prefix
     */
    public static function table(string $name): string
    {
        return self::getInstance()->table($name);
    }

    /**
     * Magic property for compatibility
     */
    public function __get(string $name)
    {
        if ($name === 'prefix') {
            return self::getInstance()->getPrefix();
        }

        return null;
    }
}

/**
 * Global $wpdb compatibility
 * This makes the Database instance available globally as $wpdb
 */
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public string $prefix = '';

        public function __construct()
        {
            $this->prefix = $_ENV['DB_PREFIX'] ?? 'fpdms_';
        }

        public function get_results(string $query, $output = OBJECT): array
        {
            try {
                return DatabaseAdapter::getInstance()->get_results($query);
            } catch (\Exception $e) {
                return [];
            }
        }

        public function get_row(string $query, $output = OBJECT, int $y = 0): ?object
        {
            try {
                return DatabaseAdapter::getInstance()->get_row($query);
            } catch (\Exception $e) {
                return null;
            }
        }

        public function get_var(string $query, int $x = 0, int $y = 0): mixed
        {
            try {
                return DatabaseAdapter::getInstance()->get_var($query);
            } catch (\Exception $e) {
                return null;
            }
        }

        public function insert(string $table, array $data, $format = null): int|false
        {
            try {
                return DatabaseAdapter::getInstance()->insert($table, $data);
            } catch (\Exception $e) {
                return false;
            }
        }

        public function update(string $table, array $data, array $where, $format = null, $where_format = null): int|false
        {
            try {
                return DatabaseAdapter::getInstance()->update($table, $data, $where);
            } catch (\Exception $e) {
                return false;
            }
        }

        public function delete(string $table, array $where, $where_format = null): int|false
        {
            try {
                return DatabaseAdapter::getInstance()->delete($table, $where);
            } catch (\Exception $e) {
                return false;
            }
        }

        public function query(string $query): bool
        {
            try {
                return DatabaseAdapter::getInstance()->query($query);
            } catch (\Exception $e) {
                return false;
            }
        }

        public function prepare(string $query, ...$args): string
        {
            return DatabaseAdapter::getInstance()->prepare($query, ...$args);
        }

        public function get_charset_collate(): string
        {
            return DatabaseAdapter::getInstance()->get_charset_collate();
        }
    };
}
