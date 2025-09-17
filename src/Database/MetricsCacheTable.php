<?php
/**
 * Metrics Cache Table Management
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

/**
 * Metrics Cache Table class for database table management
 * 
 * This class handles the creation and management of the wp_fp_metrics_cache table
 * used to store normalized metrics data from various data sources.
 */
class MetricsCacheTable {

	/**
	 * Table name (without wp_ prefix)
	 */
	public const TABLE_NAME = 'fp_metrics_cache';

	/**
	 * Get the full table name with WordPress prefix
	 *
	 * @return string Full table name
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the metrics cache table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			source varchar(50) NOT NULL,
			metric varchar(100) NOT NULL,
			period_start datetime NOT NULL,
			period_end datetime NOT NULL,
			value text NOT NULL,
			meta longtext,
			fetched_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY client_id (client_id),
			KEY source (source),
			KEY metric (metric),
			KEY period_start (period_start),
			KEY period_end (period_end),
			KEY fetched_at (fetched_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		$result = dbDelta( $sql );
		
		// Check if table was created successfully
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Drop the metrics cache table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function drop_table(): bool {
		global $wpdb;

                $table_name = self::get_table_name();
                $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

                return $result !== false;
	}

	/**
	 * Check if the table exists
	 *
	 * @return bool True if table exists, false otherwise
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		
		return $result === $table_name;
	}
}