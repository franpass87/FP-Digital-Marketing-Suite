<?php
/**
 * UTM Campaigns Table Management
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

/**
 * UTM Campaigns Table class for database table management
 * 
 * This class handles the creation and management of the wp_fp_utm_campaigns table
 * used to store UTM campaign data and track their performance.
 */
class UTMCampaignsTable {

	/**
	 * Table name (without wp_ prefix)
	 */
	public const TABLE_NAME = 'fp_utm_campaigns';

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
	 * Create the UTM campaigns table
	 *
	 * @return bool True on success, false on failure
	 */
        public static function create_table(): bool {
                global $wpdb;

                $table_name = self::get_table_name();
                $charset_collate = $wpdb->get_charset_collate();

                if ( self::table_exists() ) {
                        return true;
                }

                $sql = "CREATE TABLE $table_name (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_name varchar(255) NOT NULL,
			utm_source varchar(255) NOT NULL,
			utm_medium varchar(255) NOT NULL,
			utm_campaign varchar(255) NOT NULL,
			utm_term varchar(255) DEFAULT NULL,
			utm_content varchar(255) DEFAULT NULL,
			base_url text NOT NULL,
			final_url text NOT NULL,
			short_url varchar(500) DEFAULT NULL,
			preset_used varchar(100) DEFAULT NULL,
			clicks bigint(20) unsigned DEFAULT 0,
			conversions bigint(20) unsigned DEFAULT 0,
			revenue decimal(10,2) DEFAULT 0.00,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			created_by bigint(20) unsigned NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_campaign (utm_source, utm_medium, utm_campaign, utm_term, utm_content, base_url),
			KEY idx_campaign_name (campaign_name),
			KEY idx_utm_source (utm_source),
			KEY idx_utm_medium (utm_medium),
			KEY idx_utm_campaign (utm_campaign),
			KEY idx_status (status),
			KEY idx_created_at (created_at),
			KEY idx_created_by (created_by)
		) $charset_collate;";

                $upgrade_path = rtrim( ABSPATH, '/\\' ) . '/wp-admin/includes/upgrade.php';

                if ( file_exists( $upgrade_path ) ) {
                        require_once $upgrade_path;
                }

                if ( ! function_exists( 'dbDelta' ) ) {
                        return false;
                }

                dbDelta( $sql );

		// Check if table was created successfully.
		return self::table_exists();
	}

	/**
	 * Check if the table exists
	 *
	 * @return bool True if table exists, false otherwise
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		
		return $wpdb->get_var( $query ) === $table_name;
	}

	/**
	 * Drop the table (used for uninstallation)
	 *
	 * @return bool True on success, false on failure
	 */
	public static function drop_table(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$sql = "DROP TABLE IF EXISTS $table_name";
		
		return $wpdb->query( $sql ) !== false;
	}

	/**
	 * Get table schema version for upgrades
	 *
	 * @return string Schema version
	 */
	public static function get_schema_version(): string {
		return '1.0.0';
	}
}