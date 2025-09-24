<?php
/**
 * Detected Anomalies Table Management
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

use FP\DigitalMarketing\Database\DatabaseUtils;

/**
 * Detected Anomalies Table class for database table management
 * 
 * This class handles the creation and management of the wp_fp_detected_anomalies table
 * used to store historical records of detected anomalies.
 */
class DetectedAnomaliesTable {

	/**
	 * Table name (without wp_ prefix)
	 */
	public const TABLE_NAME = 'fp_detected_anomalies';

	/**
	 * Get the full table name with WordPress prefix
	 *
	 * @return string Full table name
	 */
        public static function get_table_name(): string {
                global $wpdb;
                return DatabaseUtils::resolve_table_name( $wpdb, self::TABLE_NAME );
        }

	/**
	 * Create the detected anomalies table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
                $charset_collate = DatabaseUtils::get_charset_collate( $wpdb );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			rule_id bigint(20) unsigned,
			metric varchar(100) NOT NULL,
			detection_method varchar(50) NOT NULL,
			current_value decimal(15,4) NOT NULL,
			expected_value decimal(15,4),
			z_score decimal(8,4),
			confidence_level varchar(20),
			severity varchar(20),
			deviation_type varchar(20),
			analysis_data longtext,
			notification_sent tinyint(1) NOT NULL DEFAULT 0,
			acknowledged tinyint(1) NOT NULL DEFAULT 0,
			acknowledged_by bigint(20) unsigned,
			acknowledged_at datetime,
			detected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY client_id (client_id),
			KEY rule_id (rule_id),
			KEY metric (metric),
			KEY detection_method (detection_method),
			KEY severity (severity),
			KEY acknowledged (acknowledged),
			KEY detected_at (detected_at)
		) $charset_collate;";

                if ( ! DatabaseUtils::run_schema_delta( $sql, $wpdb ) ) {
                        return false;
                }

                // Check if table was created successfully
                return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Drop the detected anomalies table
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