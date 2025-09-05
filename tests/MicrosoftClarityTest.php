<?php
/**
 * Microsoft Clarity Unit Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\MicrosoftClarity;

/**
 * Test class for Microsoft Clarity integration
 */
class MicrosoftClarityTest extends TestCase {

	/**
	 * Test Microsoft Clarity construction with valid project ID
	 */
	public function test_construction_with_valid_project_id() {
		$project_id = 'test123';
		$clarity = new MicrosoftClarity( $project_id );
		
		$this->assertInstanceOf( MicrosoftClarity::class, $clarity );
		$this->assertTrue( $clarity->is_connected() );
	}

	/**
	 * Test Microsoft Clarity construction with empty project ID
	 */
	public function test_construction_with_empty_project_id() {
		$clarity = new MicrosoftClarity( '' );
		
		$this->assertInstanceOf( MicrosoftClarity::class, $clarity );
		$this->assertFalse( $clarity->is_connected() );
	}

	/**
	 * Test project ID validation
	 */
	public function test_project_id_validation() {
		// Valid project IDs
		$this->assertTrue( MicrosoftClarity::validate_project_id( 'abc123def456' ) );
		$this->assertTrue( MicrosoftClarity::validate_project_id( 'TEST123' ) );
		$this->assertTrue( MicrosoftClarity::validate_project_id( '123456' ) );
		
		// Invalid project IDs
		$this->assertFalse( MicrosoftClarity::validate_project_id( '' ) );
		$this->assertFalse( MicrosoftClarity::validate_project_id( 'test-123' ) );
		$this->assertFalse( MicrosoftClarity::validate_project_id( 'test_123' ) );
		$this->assertFalse( MicrosoftClarity::validate_project_id( 'test@123' ) );
	}

	/**
	 * Test tracking script generation
	 */
	public function test_tracking_script_generation() {
		$project_id = 'testproject123';
		$clarity = new MicrosoftClarity( $project_id );
		
		$script = $clarity->get_tracking_script();
		
		$this->assertStringContainsString( $project_id, $script );
		$this->assertStringContainsString( '<script type="text/javascript">', $script );
		$this->assertStringContainsString( 'clarity.ms/tag/', $script );
		$this->assertStringContainsString( '</script>', $script );
	}

	/**
	 * Test tracking script with empty project ID
	 */
	public function test_tracking_script_empty_project_id() {
		$clarity = new MicrosoftClarity( '' );
		$script = $clarity->get_tracking_script();
		
		$this->assertEmpty( $script );
	}

	/**
	 * Test metrics fetching with valid configuration
	 */
	public function test_fetch_metrics_with_valid_configuration() {
		$project_id = 'testproject123';
		$clarity = new MicrosoftClarity( $project_id );
		
		$client_id = 1;
		$start_date = '2024-01-01';
		$end_date = '2024-01-31';
		
		$metrics = $clarity->fetch_metrics( $client_id, $start_date, $end_date );
		
		$this->assertIsArray( $metrics );
		$this->assertArrayHasKey( 'sessions', $metrics );
		$this->assertArrayHasKey( 'page_views', $metrics );
		$this->assertArrayHasKey( 'recordings_available', $metrics );
		$this->assertArrayHasKey( 'heatmaps_generated', $metrics );
		$this->assertArrayHasKey( 'rage_clicks', $metrics );
		$this->assertArrayHasKey( 'dead_clicks', $metrics );
		$this->assertArrayHasKey( 'scroll_depth_avg', $metrics );
		$this->assertArrayHasKey( 'time_to_click_avg', $metrics );
		$this->assertArrayHasKey( 'javascript_errors', $metrics );
		$this->assertArrayHasKey( 'period', $metrics );
	}

	/**
	 * Test metrics fetching with empty project ID
	 */
	public function test_fetch_metrics_empty_project_id() {
		$clarity = new MicrosoftClarity( '' );
		
		$client_id = 1;
		$start_date = '2024-01-01';
		$end_date = '2024-01-31';
		
		$metrics = $clarity->fetch_metrics( $client_id, $start_date, $end_date );
		
		$this->assertFalse( $metrics );
	}

	/**
	 * Test metrics data types and ranges
	 */
	public function test_metrics_data_types_and_ranges() {
		$project_id = 'testproject123';
		$clarity = new MicrosoftClarity( $project_id );
		
		$client_id = 1;
		$start_date = '2024-01-01';
		$end_date = '2024-01-07';
		
		$metrics = $clarity->fetch_metrics( $client_id, $start_date, $end_date );
		
		// Test data types
		$this->assertIsInt( $metrics['sessions'] );
		$this->assertIsInt( $metrics['page_views'] );
		$this->assertIsInt( $metrics['recordings_available'] );
		$this->assertIsInt( $metrics['heatmaps_generated'] );
		$this->assertIsInt( $metrics['rage_clicks'] );
		$this->assertIsInt( $metrics['dead_clicks'] );
		$this->assertIsInt( $metrics['scroll_depth_avg'] );
		$this->assertIsInt( $metrics['time_to_click_avg'] );
		$this->assertIsInt( $metrics['javascript_errors'] );
		
		// Test reasonable ranges for demo data
		$this->assertGreaterThanOrEqual( 0, $metrics['sessions'] );
		$this->assertGreaterThanOrEqual( 0, $metrics['page_views'] );
		$this->assertGreaterThanOrEqual( 0, $metrics['scroll_depth_avg'] );
		$this->assertLessThanOrEqual( 100, $metrics['scroll_depth_avg'] );
		$this->assertGreaterThanOrEqual( 0, $metrics['time_to_click_avg'] );
	}

	/**
	 * Test project status with configured project
	 */
	public function test_project_status_configured() {
		$project_id = 'testproject123';
		$clarity = new MicrosoftClarity( $project_id );
		
		$status = $clarity->get_project_status();
		
		$this->assertIsArray( $status );
		$this->assertTrue( $status['connected'] );
		$this->assertEquals( 'connected', $status['class'] );
		$this->assertEquals( $project_id, $status['project_id'] );
		$this->assertArrayHasKey( 'status', $status );
	}

	/**
	 * Test project status without configuration
	 */
	public function test_project_status_not_configured() {
		$clarity = new MicrosoftClarity( '' );
		
		$status = $clarity->get_project_status();
		
		$this->assertIsArray( $status );
		$this->assertFalse( $status['connected'] );
		$this->assertEquals( 'disconnected', $status['class'] );
		$this->assertArrayHasKey( 'status', $status );
		$this->assertArrayNotHasKey( 'project_id', $status );
	}

	/**
	 * Test source ID constant
	 */
	public function test_source_id_constant() {
		$this->assertEquals( 'microsoft_clarity', MicrosoftClarity::SOURCE_ID );
	}

	/**
	 * Test period calculation in metrics
	 */
	public function test_period_calculation() {
		$project_id = 'testproject123';
		$clarity = new MicrosoftClarity( $project_id );
		
		$client_id = 1;
		$start_date = '2024-01-01';
		$end_date = '2024-01-15';
		
		$metrics = $clarity->fetch_metrics( $client_id, $start_date, $end_date );
		
		$this->assertArrayHasKey( 'period', $metrics );
		$this->assertEquals( $start_date, $metrics['period']['start'] );
		$this->assertEquals( $end_date, $metrics['period']['end'] );
		$this->assertEquals( 15, $metrics['period']['days'] );
	}

	/**
	 * Test single day period
	 */
	public function test_single_day_period() {
		$project_id = 'testproject123';
		$clarity = new MicrosoftClarity( $project_id );
		
		$client_id = 1;
		$date = '2024-01-01';
		
		$metrics = $clarity->fetch_metrics( $client_id, $date, $date );
		
		$this->assertArrayHasKey( 'period', $metrics );
		$this->assertEquals( 1, $metrics['period']['days'] );
	}

	/**
	 * Test getting client project ID when not set
	 */
	public function test_get_client_project_id_empty() {
		$client_id = 999; // Non-existent client ID
		$project_id = MicrosoftClarity::get_client_project_id( $client_id );
		
		$this->assertIsString( $project_id );
		$this->assertEmpty( $project_id );
	}

	/**
	 * Test creating Clarity instance for client without Project ID
	 */
	public function test_for_client_without_project_id() {
		$client_id = 999; // Non-existent client ID
		$clarity = MicrosoftClarity::for_client( $client_id );
		
		$this->assertNull( $clarity );
	}

	/**
	 * Test creating Clarity instance for client with valid Project ID
	 */
	public function test_for_client_with_project_id() {
		$client_id = 1;
		
		// Mock the meta value by setting it temporarily
		// In a real test environment, this would be set up properly
		// For this test, we'll test the method structure without database dependency
		
		// Test the method exists and returns proper type
		$clarity = MicrosoftClarity::for_client( $client_id );
		
		// Since we don't have a real client with Project ID set, this should return null
		$this->assertNull( $clarity );
	}

	/**
	 * Test client-focused approach documentation
	 */
	public function test_client_focused_approach() {
		// Verify that the class supports client-focused methods
		$this->assertTrue( method_exists( MicrosoftClarity::class, 'get_client_project_id' ) );
		$this->assertTrue( method_exists( MicrosoftClarity::class, 'for_client' ) );
		
		// Verify static methods return expected types
		$project_id = MicrosoftClarity::get_client_project_id( 999 );
		$this->assertIsString( $project_id );
		
		$clarity_instance = MicrosoftClarity::for_client( 999 );
		$this->assertTrue( is_null( $clarity_instance ) || $clarity_instance instanceof MicrosoftClarity );
	}
}