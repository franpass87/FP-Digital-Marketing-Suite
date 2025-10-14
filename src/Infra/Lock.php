<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Support\Wp;
use wpdb;

class Lock
{
    private const TRANSIENT_PREFIX = 'fpdms_lock_';

    public static function acquire(string $name, string $owner, int $ttl = 120): bool
    {
        // Try to acquire persistent lock first (atomic operation)
        if (! self::acquirePersistent($name, $owner, $ttl)) {
            return false;
        }

        // Set transient as a secondary check (for performance)
        $transientKey = self::transientKey($name);
        set_transient($transientKey, $owner, $ttl);

        return true;
    }

    public static function release(string $name, string $owner): void
    {
        delete_transient(self::transientKey($name));
        self::releasePersistent($name, $owner);
    }

    public static function withLock(string $name, string $owner, callable $callback, int $ttl = 120): mixed
    {
        if (! self::acquire($name, $owner, $ttl)) {
            Logger::log(sprintf('LOCK_CONTENDED:%s', $name));

            return null;
        }

        try {
            return $callback();
        } finally {
            self::release($name, $owner);
        }
    }

    private static function transientKey(string $name): string
    {
        return self::TRANSIENT_PREFIX . md5($name);
    }

    private static function acquirePersistent(string $name, string $owner, int $ttl): bool
    {
        global $wpdb;
        $table = DB::table('locks');
        $now = Wp::currentTime('mysql');

        // First, clean up expired locks
        self::cleanupExpiredLocks($table, $ttl);

        // Try to insert new lock (atomic operation)
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (lock_key, owner, acquired_at) VALUES (%s, %s, %s)",
            $name,
            $owner,
            $now
        );

        if ($sql === false) {
            return false;
        }

        // Suppress errors for duplicate key constraint
        $wpdb->suppress_errors(true);
        $result = $wpdb->query($sql);
        $wpdb->suppress_errors(false);

        // Check if insert was successful
        if ($result !== false && $result > 0) {
            return true;
        }

        // Lock already exists, check if it's expired
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT owner, acquired_at FROM {$table} WHERE lock_key = %s",
                $name
            ),
            ARRAY_A
        );

        if (! is_array($existing)) {
            return false;
        }

        // Calculate if lock is expired
        $acquiredAt = strtotime($existing['acquired_at'] ?? '');
        if ($acquiredAt === false) {
            // Invalid timestamp, consider lock as expired and try to replace it
            $replaced = $wpdb->update(
                $table,
                ['owner' => $owner, 'acquired_at' => $now],
                ['lock_key' => $name],
                ['%s', '%s'],
                ['%s']
            );

            return $replaced !== false && $replaced > 0;
        }

        $expiresAt = $acquiredAt + $ttl;
        $currentTime = time();

        if ($currentTime > $expiresAt) {
            // Lock is expired, try to replace it
            $replaced = $wpdb->update(
                $table,
                ['owner' => $owner, 'acquired_at' => $now],
                ['lock_key' => $name],
                ['%s', '%s'],
                ['%s']
            );

            return $replaced !== false && $replaced > 0;
        }

        return false;
    }

    /**
     * Clean up expired locks
     */
    private static function cleanupExpiredLocks(string $table, int $ttl): void
    {
        global $wpdb;

        $cutoff = Wp::date('Y-m-d H:i:s', time() - $ttl);

        $sql = $wpdb->prepare(
            "DELETE FROM {$table} WHERE acquired_at < %s",
            $cutoff
        );

        if ($sql !== false) {
            $wpdb->query($sql);
        }
    }

    private static function releasePersistent(string $name, string $owner): void
    {
        global $wpdb;
        $table = DB::table('locks');
        $wpdb->delete($table, ['lock_key' => $name, 'owner' => $owner], ['%s', '%s']);
    }
}
