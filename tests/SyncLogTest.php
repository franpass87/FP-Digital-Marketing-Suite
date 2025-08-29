<?php
/**
 * Test for SyncLog model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Models\SyncLog;

/**
 * Test class for SyncLog
 */
class SyncLogTest extends TestCase {

	/**
	 * Set up before each test
	 */
	protected function setUp(): void {
		parent::setUp();
		// Clear existing logs
		delete_option( 'fp_dms_sync_logs' );
	}

	/**
	 * Clean up after each test
	 */
	protected function tearDown(): void {
		parent::tearDown();
		// Clear logs
		delete_option( 'fp_dms_sync_logs' );
	}

	/**
	 * Test creating sync log entry
	 */
	public function test_create_sync_log(): void {
		$log_id = SyncLog::create( [
			'sync_type' => 'manual',
			'status' => 'running',
			'message' => 'Test sync started',
		] );

		$this->assertIsInt( $log_id );
		$this->assertGreaterThan( 0, $log_id );

		$logs = SyncLog::get_all_logs();
		$this->assertCount( 1, $logs );
		$this->assertEquals( 'manual', $logs[0]['sync_type'] );
		$this->assertEquals( 'running', $logs[0]['status'] );
		$this->assertEquals( 'Test sync started', $logs[0]['message'] );
	}

	/**
	 * Test updating sync log entry
	 */
	public function test_update_sync_log(): void {
		$log_id = SyncLog::create( [
			'sync_type' => 'automatic',
			'status' => 'running',
		] );

		$result = SyncLog::update( $log_id, [
			'status' => 'success',
			'message' => 'Sync completed successfully',
			'completed_at' => current_time( 'mysql' ),
		] );

		$this->assertTrue( $result );

		$logs = SyncLog::get_all_logs();
		$this->assertCount( 1, $logs );
		$this->assertEquals( 'success', $logs[0]['status'] );
		$this->assertEquals( 'Sync completed successfully', $logs[0]['message'] );
		$this->assertNotNull( $logs[0]['completed_at'] );
	}

	/**
	 * Test getting logs by status
	 */
	public function test_get_logs_by_status(): void {
		// Create logs with different statuses
		SyncLog::create( [ 'status' => 'success' ] );
		SyncLog::create( [ 'status' => 'error' ] );
		SyncLog::create( [ 'status' => 'success' ] );
		SyncLog::create( [ 'status' => 'warning' ] );

		$success_logs = SyncLog::get_logs_by_status( 'success' );
		$error_logs = SyncLog::get_logs_by_status( 'error' );
		$warning_logs = SyncLog::get_logs_by_status( 'warning' );

		$this->assertCount( 2, $success_logs );
		$this->assertCount( 1, $error_logs );
		$this->assertCount( 1, $warning_logs );
	}

	/**
	 * Test getting error logs
	 */
	public function test_get_error_logs(): void {
		SyncLog::create( [ 'status' => 'success' ] );
		SyncLog::create( [ 'status' => 'error', 'message' => 'Connection failed' ] );
		SyncLog::create( [ 'status' => 'error', 'message' => 'API timeout' ] );

		$error_logs = SyncLog::get_error_logs();
		
		$this->assertCount( 2, $error_logs );
		$this->assertEquals( 'error', $error_logs[0]['status'] );
		$this->assertEquals( 'error', $error_logs[1]['status'] );
	}

	/**
	 * Test sync statistics
	 */
	public function test_get_sync_stats(): void {
		// Create test logs with various statuses
		SyncLog::create( [
			'status' => 'success',
			'started_at' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'completed_at' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
		] );
		
		SyncLog::create( [
			'status' => 'error',
			'started_at' => date( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
		] );
		
		SyncLog::create( [
			'status' => 'success',
			'started_at' => date( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
			'completed_at' => date( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
		] );

		$stats = SyncLog::get_sync_stats( 7 );

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total_syncs', $stats );
		$this->assertArrayHasKey( 'successful_syncs', $stats );
		$this->assertArrayHasKey( 'failed_syncs', $stats );
		$this->assertArrayHasKey( 'error_rate', $stats );

		$this->assertEquals( 3, $stats['total_syncs'] );
		$this->assertEquals( 2, $stats['successful_syncs'] );
		$this->assertEquals( 1, $stats['failed_syncs'] );
		$this->assertEquals( 33.3, $stats['error_rate'] );
	}

	/**
	 * Test cleanup old logs
	 */
	public function test_cleanup_old_logs(): void {
		// Create old logs
		SyncLog::create( [
			'started_at' => date( 'Y-m-d H:i:s', strtotime( '-40 days' ) ),
		] );
		
		SyncLog::create( [
			'started_at' => date( 'Y-m-d H:i:s', strtotime( '-20 days' ) ),
		] );
		
		SyncLog::create( [
			'started_at' => date( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
		] );

		$this->assertCount( 3, SyncLog::get_all_logs() );

		$removed = SyncLog::cleanup_old_logs( 30 );
		
		$this->assertEquals( 1, $removed );
		$this->assertCount( 2, SyncLog::get_all_logs() );
	}

	/**
	 * Test log limit functionality
	 */
	public function test_log_limit(): void {
		// Create more than 100 logs to test the limit
		for ( $i = 0; $i < 105; $i++ ) {
			SyncLog::create( [
				'sync_type' => 'automatic',
				'status' => 'success',
				'started_at' => date( 'Y-m-d H:i:s', strtotime( "-{$i} minutes" ) ),
			] );
		}

		$logs = SyncLog::get_all_logs();
		
		// Should be limited to 100 logs
		$this->assertLessThanOrEqual( 100, count( $logs ) );
	}
}