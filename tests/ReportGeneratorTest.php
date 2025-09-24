<?php
/**
 * Test suite for ReportGenerator
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\ReportGenerator;

/**
 * Test the ReportGenerator functionality
 */
class ReportGeneratorTest extends TestCase {

	/**
	 * Test demo report data generation
	 */
	public function test_generate_demo_report_data(): void {
		$client_id = 123;
		$report_data = ReportGenerator::generate_demo_report_data( $client_id );

		$this->assertIsArray( $report_data );
		$this->assertEquals( $client_id, $report_data['client_id'] );
		$this->assertArrayHasKey( 'period_start', $report_data );
		$this->assertArrayHasKey( 'period_end', $report_data );
		$this->assertArrayHasKey( 'generated_at', $report_data );
		$this->assertArrayHasKey( 'kpis', $report_data );
		$this->assertArrayHasKey( 'channels', $report_data );

		// Test KPIs structure
		$kpis = $report_data['kpis'];
		$expected_kpis = [ 'sessions', 'users', 'conversion_rate', 'revenue' ];
		
		foreach ( $expected_kpis as $kpi ) {
			$this->assertArrayHasKey( $kpi, $kpis );
			$this->assertArrayHasKey( 'value', $kpis[ $kpi ] );
			$this->assertArrayHasKey( 'previous_value', $kpis[ $kpi ] );
			$this->assertArrayHasKey( 'change_percent', $kpis[ $kpi ] );
			$this->assertArrayHasKey( 'change_type', $kpis[ $kpi ] );
		}

		// Test channels structure
		$this->assertIsArray( $report_data['channels'] );
		$this->assertGreaterThan( 0, count( $report_data['channels'] ) );
		
		foreach ( $report_data['channels'] as $channel ) {
			$this->assertArrayHasKey( 'name', $channel );
			$this->assertArrayHasKey( 'sessions', $channel );
			$this->assertArrayHasKey( 'revenue', $channel );
		}
	}

	/**
	 * Test CSV report generation
	 */
	public function test_generate_csv_report(): void {
		$report_data = ReportGenerator::generate_demo_report_data( 1 );
		$csv_content = ReportGenerator::generate_csv_report( $report_data );

		// Check that CSV content is generated
		$this->assertIsString( $csv_content );
		$this->assertNotEmpty( $csv_content );
		
		// Check UTF-8 BOM
		$this->assertStringStartsWith( "\xEF\xBB\xBF", $csv_content );
		
		// Check that CSV contains expected headers
		$this->assertStringContainsString( 'Report Type', $csv_content );
		$this->assertStringContainsString( 'Client ID', $csv_content );
		$this->assertStringContainsString( 'KPIs', $csv_content );
		$this->assertStringContainsString( 'Current Value', $csv_content );
		
		// Check that KPI data is included
		$this->assertStringContainsString( 'Sessions', $csv_content );
		$this->assertStringContainsString( 'Users', $csv_content );
		$this->assertStringContainsString( 'Revenue', $csv_content );
	}

	/**
	 * Test CSV report generation with custom separator
	 */
        public function test_generate_csv_report_custom_separator(): void {
                $report_data = ReportGenerator::generate_demo_report_data( 1 );
                $csv_content = ReportGenerator::generate_csv_report( $report_data, ';' );

                $this->assertIsString( $csv_content );
                $this->assertNotEmpty( $csv_content );

                // Check that semicolon is used as separator
                $this->assertStringContainsString( ';', $csv_content );

                // Parse CSV to ensure it's valid
                $lines = explode( "\n", trim( str_replace( "\xEF\xBB\xBF", '', $csv_content ) ) );
                $this->assertGreaterThan( 5, count( $lines ) ); // Should have multiple lines
        }

        /**
         * Ensure generated CSV reports sanitize formula injections and markup.
         */
        public function test_generate_csv_report_sanitizes_values(): void {
                $report_data = [
                        'client_id' => 1,
                        'period_start' => '2024-01-01',
                        'period_end' => '2024-01-31',
                        'generated_at' => '2024-01-01 12:00:00',
                        'kpis' => [
                                'sessions' => [
                                        'value' => "=SUM(A1:A2)\n<script>alert('x')</script>",
                                        'previous_value' => 100,
                                        'change_percent' => 5,
                                        'change_type' => 'increase',
                                ],
                        ],
                        'channels' => [
                                [
                                        'name' => '<b>Direct</b>',
                                        'sessions' => 200,
                                        'revenue' => 300.5,
                                ],
                        ],
                ];

                $csv_content = ReportGenerator::generate_csv_report( $report_data );

                $this->assertStringContainsString( "'=SUM(A1:A2) alert('x')", $csv_content );
                $this->assertStringContainsString( 'Direct', $csv_content );
                $this->assertStringNotContainsString( '<b>', $csv_content );
                $this->assertStringNotContainsString( "=SUM(A1:A2)\n", $csv_content );
        }

	/**
	 * Test report data validation
	 */
	public function test_validate_report_data(): void {
		// Test valid report data
		$valid_data = ReportGenerator::generate_demo_report_data( 1 );
		$validation = ReportGenerator::validate_report_data( $valid_data );
		
		$this->assertTrue( $validation['valid'] );
		$this->assertEmpty( $validation['errors'] );

		// Test invalid report data - missing required fields
		$invalid_data = [ 'client_id' => 1 ]; // Missing required fields
		$validation = ReportGenerator::validate_report_data( $invalid_data );
		
		$this->assertFalse( $validation['valid'] );
		$this->assertNotEmpty( $validation['errors'] );
		
		// Test invalid report data - empty KPIs
		$empty_kpis_data = [
			'client_id' => 1,
			'period_start' => '2024-01-01',
			'period_end' => '2024-01-31',
			'kpis' => [],
		];
		$validation = ReportGenerator::validate_report_data( $empty_kpis_data );
		
		$this->assertFalse( $validation['valid'] );
		$this->assertContains( 'No metrics data available for the selected period', $validation['errors'] );
	}

	/**
	 * Test report logging functionality
	 */
	public function test_report_logging(): void {
		// Clear existing logs for clean test
		update_option( 'fp_dms_report_logs', [] );
		
		// Log a successful report generation
		ReportGenerator::log_report_generation( 123, 'pdf', 15000, true );
		
		// Log a failed report generation
		ReportGenerator::log_report_generation( 456, 'csv', 0, false, 'Test error message' );
		
		// Get logs
		$logs = ReportGenerator::get_report_logs( 10 );
		
		$this->assertCount( 2, $logs );
		
		// Check first log (most recent - failed)
		$this->assertEquals( 456, $logs[0]['client_id'] );
		$this->assertEquals( 'csv', $logs[0]['format'] );
		$this->assertFalse( $logs[0]['success'] );
		$this->assertEquals( 'Test error message', $logs[0]['error_message'] );
		
		// Check second log (successful)
		$this->assertEquals( 123, $logs[1]['client_id'] );
		$this->assertEquals( 'pdf', $logs[1]['format'] );
		$this->assertTrue( $logs[1]['success'] );
		$this->assertEquals( 15000, $logs[1]['file_size'] );
	}

	/**
	 * Test report logging with client filter
	 */
	public function test_report_logging_with_filter(): void {
		// Clear existing logs
		update_option( 'fp_dms_report_logs', [] );
		
		// Log reports for different clients
		ReportGenerator::log_report_generation( 123, 'pdf', 15000, true );
		ReportGenerator::log_report_generation( 456, 'csv', 5000, true );
		ReportGenerator::log_report_generation( 123, 'csv', 3000, true );
		
		// Get logs for specific client
		$client_123_logs = ReportGenerator::get_report_logs( 10, 123 );
		$this->assertCount( 2, $client_123_logs );
		
		foreach ( $client_123_logs as $log ) {
			$this->assertEquals( 123, $log['client_id'] );
		}
		
		// Get logs for different client
		$client_456_logs = ReportGenerator::get_report_logs( 10, 456 );
		$this->assertCount( 1, $client_456_logs );
		$this->assertEquals( 456, $client_456_logs[0]['client_id'] );
	}

	/**
	 * Test HTML report generation
	 */
	public function test_generate_html_report(): void {
		$report_data = ReportGenerator::generate_demo_report_data( 1 );
		$html = ReportGenerator::generate_html_report( $report_data );

		$this->assertIsString( $html );
		$this->assertStringContainsString( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( '<html', $html );
		$this->assertStringContainsString( '</html>', $html );
		
		// Check if KPI values are present
		$this->assertStringContainsString( number_format( $report_data['kpis']['sessions']['value'] ), $html );
		$this->assertStringContainsString( number_format( $report_data['kpis']['users']['value'] ), $html );
		
		// Check if channels are present
		foreach ( $report_data['channels'] as $channel ) {
			$this->assertStringContainsString( $channel['name'], $html );
		}
	}

	/**
	 * Test PDF report generation
	 */
	public function test_generate_pdf_report(): void {
		$report_data = ReportGenerator::generate_demo_report_data( 1 );
		$pdf = ReportGenerator::generate_pdf_report( $report_data );

		$this->assertIsString( $pdf );
		$this->assertStringStartsWith( '%PDF', $pdf );
		$this->assertGreaterThan( 1000, strlen( $pdf ) ); // PDF should be substantial in size
	}

	/**
	 * Test that periods are correctly formatted
	 */
	public function test_period_formatting(): void {
		$report_data = ReportGenerator::generate_demo_report_data( 1 );
		
		// Test that periods are valid dates
		$this->assertNotFalse( strtotime( $report_data['period_start'] ) );
		$this->assertNotFalse( strtotime( $report_data['period_end'] ) );
		$this->assertNotFalse( strtotime( $report_data['generated_at'] ) );
		
		// Test that start is before end
		$this->assertLessThan( 
			strtotime( $report_data['period_end'] ), 
			strtotime( $report_data['period_start'] ) 
		);
	}

	/**
	 * Test KPI data types and ranges
	 */
	public function test_kpi_data_validity(): void {
		$report_data = ReportGenerator::generate_demo_report_data( 1 );
		$kpis = $report_data['kpis'];

		foreach ( $kpis as $kpi_name => $kpi_data ) {
			$this->assertIsNumeric( $kpi_data['value'] );
			$this->assertIsNumeric( $kpi_data['previous_value'] );
			$this->assertIsNumeric( $kpi_data['change_percent'] );
			$this->assertContains( $kpi_data['change_type'], [ 'increase', 'decrease' ] );
			
			// Test that values are positive (for these metrics)
			$this->assertGreaterThanOrEqual( 0, $kpi_data['value'] );
			$this->assertGreaterThanOrEqual( 0, $kpi_data['previous_value'] );
		}

		// Test specific ranges for conversion rate
		$this->assertLessThanOrEqual( 100, $kpis['conversion_rate']['value'] );
		$this->assertGreaterThanOrEqual( 0, $kpis['conversion_rate']['value'] );
	}
}