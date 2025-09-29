<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use wpdb;

class Lock
{
    private const TRANSIENT_PREFIX = 'fpdms_lock_';

    public static function acquire(string $name, string $owner, int $ttl = 120): bool
    {
        $transientKey = self::transientKey($name);
        if (get_transient($transientKey)) {
            return false;
        }

        set_transient($transientKey, $owner, $ttl);

        if (! self::acquirePersistent($name, $owner)) {
            delete_transient($transientKey);

            return false;
        }

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

    private static function acquirePersistent(string $name, string $owner): bool
    {
        global $wpdb;
        $table = DB::table('locks');
        $now = current_time('mysql');

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (lock_key, owner, acquired_at) VALUES (%s, %s, %s)",
            $name,
            $owner,
            $now
        );

        if ($sql === false) {
            return false;
        }

        $inTransaction = self::beginTransaction();
        $rolledBack = false;
        $result = $wpdb->query($sql);

        if ($inTransaction) {
            if ($result === false || $result === 0) {
                $wpdb->query('ROLLBACK');
                $rolledBack = true;
            } else {
                $wpdb->query('COMMIT');

                return true;
            }
        }

        if ($result !== false && $result > 0) {
            return true;
        }

        if ($inTransaction && ! $rolledBack) {
            $wpdb->query('ROLLBACK');
        }

        $fallback = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (lock_key, owner, acquired_at)
                 SELECT %s, %s, %s FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM {$table} WHERE lock_key = %s)",
                $name,
                $owner,
                $now,
                $name
            )
        );

        return $fallback !== false && $fallback > 0;
    }

    private static function beginTransaction(): bool
    {
        global $wpdb;
        $result = $wpdb->query('START TRANSACTION');

        return $result !== false;
    }

    private static function releasePersistent(string $name, string $owner): void
    {
        global $wpdb;
        $table = DB::table('locks');
        $wpdb->delete($table, ['lock_key' => $name, 'owner' => $owner], ['%s', '%s']);
    }
}
