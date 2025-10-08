<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\App\Database\Database;

/**
 * Configuration management
 * Replaces WordPress's get_option/update_option with database storage
 */
class Config
{
    private static ?Database $db = null;
    private static array $cache = [];

    public static function setDatabase(Database $db): void
    {
        self::$db = $db;
    }

    /**
     * Get a configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        if (self::$db === null) {
            return $default;
        }

        $table = self::$db->table('options');
        $query = "SELECT option_value FROM {$table} WHERE option_name = :key LIMIT 1";
        
        try {
            $result = self::$db->get_var($query, ['key' => $key]);
            
            if ($result === null || $result === false) {
                return $default;
            }

            $value = maybe_unserialize($result);
            self::$cache[$key] = $value;

            return $value;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Update a configuration value
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function set(string $key, mixed $value): bool
    {
        if (self::$db === null) {
            return false;
        }

        $table = self::$db->table('options');
        $serialized = maybe_serialize($value);
        
        try {
            // Try to update first
            $updateQuery = "UPDATE {$table} SET option_value = :value WHERE option_name = :key";
            $stmt = self::$db->connect()->prepare($updateQuery);
            $stmt->execute(['value' => $serialized, 'key' => $key]);
            
            if ($stmt->rowCount() === 0) {
                // If no rows affected, insert
                $insertQuery = "INSERT INTO {$table} (option_name, option_value) VALUES (:key, :value)";
                $stmt = self::$db->connect()->prepare($insertQuery);
                $stmt->execute(['key' => $key, 'value' => $serialized]);
            }

            self::$cache[$key] = $value;

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a configuration value
     *
     * @param string $key
     * @return bool
     */
    public static function delete(string $key): bool
    {
        if (self::$db === null) {
            return false;
        }

        $table = self::$db->table('options');
        
        try {
            $query = "DELETE FROM {$table} WHERE option_name = :key";
            $stmt = self::$db->connect()->prepare($query);
            $stmt->execute(['key' => $key]);

            unset(self::$cache[$key]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a configuration key exists
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        if (isset(self::$cache[$key])) {
            return true;
        }

        if (self::$db === null) {
            return false;
        }

        $table = self::$db->table('options');
        $query = "SELECT COUNT(*) FROM {$table} WHERE option_name = :key";
        
        try {
            $count = self::$db->get_var($query, ['key' => $key]);

            return (int) $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}

/**
 * Serialize data if needed
 */
function maybe_serialize(mixed $data): string
{
    if (is_array($data) || is_object($data)) {
        return serialize($data);
    }

    return (string) $data;
}

/**
 * Unserialize value only if it was serialized
 */
function maybe_unserialize(mixed $data): mixed
{
    if (!is_string($data)) {
        return $data;
    }

    $data = trim($data);

    if ($data === 'b:0;') {
        return false;
    }

    if ($data === 'b:1;') {
        return true;
    }

    if ($data === 'N;') {
        return null;
    }

    if (strlen($data) < 4 || $data[1] !== ':') {
        return $data;
    }

    // Use unserialize with allowed_classes => false to prevent object injection attacks
    $unserialized = @unserialize($data, ['allowed_classes' => false]);

    if ($unserialized !== false || $data === 'b:0;') {
        return $unserialized;
    }

    return $data;
}
