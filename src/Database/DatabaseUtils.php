<?php
/**
 * Database utility helpers.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

/**
 * Helper methods for interacting with the WordPress database layer.
 */
class DatabaseUtils {
		/**
		 * Safely determine the charset and collation clause for table creation.
		 *
		 * WordPress exposes the information through $wpdb->get_charset_collate(),
		 * but the method might not be available in testing environments or when
		 * custom database layers are used. This helper normalises the behaviour
		 * and always returns a valid SQL clause.
		 *
		 * @param object|null $wpdb The global wpdb instance or a compatible stub.
		 * @return string Charset and collation clause suitable for CREATE TABLE.
		 */
	public static function get_charset_collate( $wpdb ): string {
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'get_charset_collate' ) ) {
				$collate = $wpdb->get_charset_collate();
			if ( is_string( $collate ) && '' !== $collate ) {
				return $collate;
			}
		}

			$charset   = 'utf8mb4';
			$collation = 'utf8mb4_unicode_ci';

		if ( is_object( $wpdb ) ) {
			if ( property_exists( $wpdb, 'charset' ) && is_string( $wpdb->charset ) && '' !== $wpdb->charset ) {
					$charset = $wpdb->charset;
			}

			if ( property_exists( $wpdb, 'collate' ) && is_string( $wpdb->collate ) && '' !== $wpdb->collate ) {
					$collation = $wpdb->collate;
			}
		}

			return sprintf( 'DEFAULT CHARACTER SET %s COLLATE %s', $charset, $collation );
	}

		/**
		 * Resolve the table name using the WordPress database prefix.
		 *
		 * @param object|null $wpdb         Database connection or stub.
		 * @param string      $table_suffix Table identifier without prefix.
		 * @return string Fully qualified table name.
		 */
	public static function resolve_table_name( $wpdb, string $table_suffix ): string {
			$prefix = 'wp_';

		if (
					is_object( $wpdb )
					&& isset( $wpdb->prefix )
					&& is_string( $wpdb->prefix )
					&& '' !== $wpdb->prefix
			) {
				$prefix = $wpdb->prefix;
		}

			return $prefix . ltrim( $table_suffix, '_' );
	}

		/**
		 * Execute a schema creation/update statement with graceful fallbacks.
		 *
		 * The helper mirrors WordPress' dbDelta() behaviour but tolerates
		 * environments where the function or upgrade library is not available
		 * (e.g. when running unit tests outside of a full WordPress stack).
		 *
		 * @param string      $sql  SQL statement to execute.
		 * @param object|null $wpdb Database connection or stub.
		 * @return bool Whether the schema operation completed successfully.
		 */
	public static function run_schema_delta( string $sql, $wpdb ): bool {
		if ( function_exists( 'dbDelta' ) ) {
				$result = dbDelta( $sql );
				return ! empty( $result );
		}

			$upgrade_file = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/upgrade.php' : '';
		if ( '' !== $upgrade_file && file_exists( $upgrade_file ) ) {
				require_once $upgrade_file;

			if ( function_exists( 'dbDelta' ) ) {
					$result = dbDelta( $sql );
					return ! empty( $result );
			}
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
				$result = $wpdb->query( $sql );
				return false !== $result;
		}

			// As a last resort assume success so that higher-level logic can proceed.
			return true;
	}
}
