<?php
/**
 * Test for SyncEngine class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\SyncEngine;
use FP\DigitalMarketing\Models\SyncLog;
use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Test class for SyncEngine
 */
class SyncEngineTest extends TestCase {

	/**
	 * Test sync engine initialization
	 */
	public function test_sync_engine_init(): void {
		// Test that the class exists and can be called
		$this->assertTrue( class_exists( 'FP\DigitalMarketing\Helpers\SyncEngine' ) );
		
		// Test static methods exist
		$this->assertTrue( method_exists( SyncEngine::class, 'init' ) );
		$this->assertTrue( method_exists( SyncEngine::class, 'is_sync_enabled' ) );
		$this->assertTrue( method_exists( SyncEngine::class, 'get_sync_frequency' ) );
	}

	/**
	 * Test sync frequency settings
	 */
	public function test_get_sync_frequency(): void {
		// Test default frequency
		$default_frequency = SyncEngine::get_sync_frequency();
		$this->assertEquals( 3600, $default_frequency ); // 1 hour default

		// Test with custom settings
		update_option( 'fp_digital_marketing_sync_settings', [
			'sync_frequency' => 'every_15_minutes',
		] );
		
		$frequency = SyncEngine::get_sync_frequency();
		$this->assertEquals( 900, $frequency ); // 15 minutes

		// Test hourly setting
		update_option( 'fp_digital_marketing_sync_settings', [
			'sync_frequency' => 'hourly',
		] );
		
		$frequency = SyncEngine::get_sync_frequency();
		$this->assertEquals( 3600, $frequency ); // 1 hour

		// Clean up
		delete_option( 'fp_digital_marketing_sync_settings' );
	}

	/**
	 * Test sync enabled check
	 */
	public function test_is_sync_enabled(): void {
		// Test default (disabled)
		$this->assertFalse( SyncEngine::is_sync_enabled() );

		// Test enabled
		update_option( 'fp_digital_marketing_sync_settings', [
			'enable_sync' => true,
		] );
		
		$this->assertTrue( SyncEngine::is_sync_enabled() );

		// Test explicitly disabled
		update_option( 'fp_digital_marketing_sync_settings', [
			'enable_sync' => false,
		] );
		
		$this->assertFalse( SyncEngine::is_sync_enabled() );

		// Clean up
		delete_option( 'fp_digital_marketing_sync_settings' );
	}

	/**
	 * Test manual sync trigger
	 */
	public function test_trigger_manual_sync(): void {
		// Enable sync for testing
		update_option( 'fp_digital_marketing_sync_settings', [
			'enable_sync' => true,
		] );

		// Test manual sync trigger
		$results = SyncEngine::trigger_manual_sync();
		
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'status', $results );
		$this->assertArrayHasKey( 'sources_count', $results );
		$this->assertArrayHasKey( 'records_updated', $results );

		// Clean up
		delete_option( 'fp_digital_marketing_sync_settings' );
	}

	/**
	 * Test scheduling methods
	 */
	public function test_scheduling_methods(): void {
		// Test is_scheduled method
		$is_scheduled = SyncEngine::is_scheduled();
		$this->assertIsBool( $is_scheduled );

		// Test get_next_scheduled_time method
		$next_time = SyncEngine::get_next_scheduled_time();
		$this->assertTrue( is_null( $next_time ) || is_string( $next_time ) );
	}
}