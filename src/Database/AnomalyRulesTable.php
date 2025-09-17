<?php
/**
 * Anomaly Rules Table Management
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

/**
 * Anomaly Rules Table class for database table management
 * 
 * This class handles the creation and management of the wp_fp_anomaly_rules table
 * used to store anomaly detection rule definitions for statistical monitoring.
 */
class AnomalyRulesTable {

	/**
	 * Table name (without wp_ prefix)
	 */
	public const TABLE_NAME = 'fp_anomaly_rules';

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
	 * Create the anomaly rules table
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
			name varchar(255) NOT NULL,
			description text,
			metric varchar(100) NOT NULL,
			detection_method varchar(50) NOT NULL DEFAULT 'z_score',
			z_score_threshold decimal(5,2) DEFAULT 2.00,
			band_deviations decimal(5,2) DEFAULT 2.00,
			window_size int unsigned DEFAULT 7,
			historical_days int unsigned DEFAULT 30,
			notification_email varchar(255),
			notification_admin_notice tinyint(1) NOT NULL DEFAULT 1,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			silence_until datetime NULL,
			last_triggered datetime,
			triggered_count int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY client_id (client_id),
			KEY metric (metric),
			KEY detection_method (detection_method),
			KEY is_active (is_active),
			KEY silence_until (silence_until),
			KEY last_triggered (last_triggered)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		$result = dbDelta( $sql );
		
		// Check if table was created successfully
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Drop the anomaly rules table
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