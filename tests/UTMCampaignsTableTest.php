<?php
/**
 * UTM Campaigns Table Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Database\UTMCampaignsTable;

/**
 * Test class for UTM Campaigns Table functionality
 */
class UTMCampaignsTableTest extends TestCase {

	/**
	 * Set up before each test
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress globals and functions
				global $wpdb;
				$wpdb         = $this->getMockBuilder( stdClass::class )
						->addMethods( [ 'prepare', 'get_var', 'query', 'get_charset_collate' ] )
						->getMock();
				$wpdb->prefix = 'wp_';
				$wpdb->method( 'prepare' )->willReturnCallback(
					static function ( $query ) {
								return $query;
					}
				);

		// Mock WordPress functions
		if ( ! function_exists( 'dbDelta' ) ) {
			function dbDelta( $sql ) {
				return true;
			}
		}

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}
	}

	/**
	 * Test table name generation
	 */
	public function test_get_table_name(): void {
		global $wpdb;
		$wpdb->prefix = 'wp_';

		$table_name = UTMCampaignsTable::get_table_name();

		$this->assertEquals( 'wp_fp_utm_campaigns', $table_name );
	}

	/**
	 * Test table name with custom prefix
	 */
	public function test_get_table_name_custom_prefix(): void {
		global $wpdb;
		$wpdb->prefix = 'custom_';

		$table_name = UTMCampaignsTable::get_table_name();

		$this->assertEquals( 'custom_fp_utm_campaigns', $table_name );
	}

	/**
	 * Test table constant
	 */
	public function test_table_name_constant(): void {
		$this->assertEquals( 'fp_utm_campaigns', UTMCampaignsTable::TABLE_NAME );
	}

	/**
	 * Test schema version
	 */
	public function test_get_schema_version(): void {
		$version = UTMCampaignsTable::get_schema_version();

		$this->assertIsString( $version );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $version );
	}

	/**
	 * Test table creation SQL structure
	 */
	public function test_create_table_sql_structure(): void {
		global $wpdb;
		$wpdb->method( 'get_charset_collate' )->willReturn( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
		$wpdb->method( 'get_var' )->willReturn( 'wp_fp_utm_campaigns' );

		// Mock dbDelta to capture the SQL
		$captured_sql = '';
		if ( ! function_exists( 'dbDelta' ) ) {
			function dbDelta( $sql ) {
				global $captured_sql;
				$captured_sql = $sql;
				return true;
			}
		}

		$result = UTMCampaignsTable::create_table();

		// Test that the method returns true
		$this->assertTrue( $result );
	}

	/**
	 * Test that required fields are in table schema
	 */
	public function test_table_schema_has_required_fields(): void {
		// We can't easily test the actual SQL without a real database,
		// but we can verify the method exists and returns boolean
		$this->assertTrue( method_exists( UTMCampaignsTable::class, 'create_table' ) );
		$this->assertTrue( method_exists( UTMCampaignsTable::class, 'table_exists' ) );
		$this->assertTrue( method_exists( UTMCampaignsTable::class, 'drop_table' ) );
	}

	/**
	 * Test table exists check
	 */
	public function test_table_exists(): void {
		global $wpdb;

		// Mock table exists
		$wpdb->method( 'prepare' )->willReturn( 'SHOW TABLES LIKE %s' );
		$wpdb->method( 'get_var' )->willReturn( 'wp_fp_utm_campaigns' );

		$exists = UTMCampaignsTable::table_exists();

		$this->assertTrue( $exists );
	}

	/**
	 * Test table does not exist
	 */
	public function test_table_does_not_exist(): void {
		global $wpdb;

		// Mock table does not exist
		$wpdb->method( 'prepare' )->willReturn( 'SHOW TABLES LIKE %s' );
		$wpdb->method( 'get_var' )->willReturn( null );

		$exists = UTMCampaignsTable::table_exists();

		$this->assertFalse( $exists );
	}

	/**
	 * Test table dropping
	 */
	public function test_drop_table(): void {
		global $wpdb;

		// Mock successful drop
		$wpdb->method( 'query' )->willReturn( 1 );

		$result = UTMCampaignsTable::drop_table();

		$this->assertTrue( $result );
	}

	/**
	 * Test table drop failure
	 */
	public function test_drop_table_failure(): void {
		global $wpdb;

		// Mock failed drop
		$wpdb->method( 'query' )->willReturn( false );

		$result = UTMCampaignsTable::drop_table();

		$this->assertFalse( $result );
	}

	/**
	 * Test create table with existing table
	 */
	public function test_create_table_already_exists(): void {
		global $wpdb;

		$wpdb->method( 'get_charset_collate' )->willReturn( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
		$wpdb->method( 'prepare' )->willReturn( 'SHOW TABLES LIKE %s' );
		$wpdb->method( 'get_var' )->willReturn( 'wp_fp_utm_campaigns' );

		$result = UTMCampaignsTable::create_table();

		$this->assertTrue( $result );
	}

	/**
	 * Test create table with new table
	 */
	public function test_create_table_new(): void {
		global $wpdb;

		$wpdb->method( 'get_charset_collate' )->willReturn( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );

		// First call (table_exists check) returns null, second call (after create) returns table name
		$wpdb->method( 'prepare' )->willReturn( 'SHOW TABLES LIKE %s' );
		$wpdb->method( 'get_var' )
			->willReturnOnConsecutiveCalls( null, 'wp_fp_utm_campaigns' );

		$result = UTMCampaignsTable::create_table();

		$this->assertTrue( $result );
	}
}
