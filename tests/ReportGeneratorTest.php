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