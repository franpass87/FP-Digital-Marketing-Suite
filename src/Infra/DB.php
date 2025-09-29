<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use wpdb;

class DB
{
    public static function table(string $name): string
    {
        global $wpdb;

        return $wpdb->prefix . 'fpdms_' . $name;
    }

    public static function migrate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (self::schema() as $sql) {
            dbDelta($sql);
        }
    }

    /**
     * @return string[]
     */
    private static function schema(): array
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        return [
            "CREATE TABLE " . self::table('clients') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                email_to LONGTEXT NULL,
                email_cc LONGTEXT NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
                notes LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) $charset;",
            "CREATE TABLE " . self::table('datasources') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(32) NOT NULL,
                auth LONGTEXT NULL,
                config LONGTEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY client_id (client_id)
            ) $charset;",
            "CREATE TABLE " . self::table('schedules') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                cron_key VARCHAR(64) NOT NULL,
                frequency VARCHAR(16) NOT NULL,
                next_run_at DATETIME NULL,
                last_run_at DATETIME NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                template_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY cron_key (cron_key)
            ) $charset;",
            "CREATE TABLE " . self::table('reports') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                status VARCHAR(16) NOT NULL,
                storage_path VARCHAR(255) NULL,
                meta LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY client_id (client_id)
            ) $charset;",
            "CREATE TABLE " . self::table('anomalies') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(64) NOT NULL,
                severity VARCHAR(16) NOT NULL,
                payload LONGTEXT NULL,
                detected_at DATETIME NOT NULL,
                notified TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                KEY client_id (client_id)
            ) $charset;",
            "CREATE TABLE " . self::table('templates') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                description VARCHAR(255) NULL,
                content LONGTEXT NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) $charset;",
            "CREATE TABLE " . self::table('locks') . " (
                lock_key VARCHAR(100) NOT NULL,
                owner VARCHAR(64) NOT NULL,
                acquired_at DATETIME NOT NULL,
                PRIMARY KEY (lock_key)
            ) $charset;"
        ];
    }
}
