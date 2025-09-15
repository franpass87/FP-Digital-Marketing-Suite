<?php
/**
 * Data Exporter Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\DataExporter;

/**
 * Test cases for Data Exporter functionality
 */
class DataExporterTest extends TestCase {

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Mock WordPress functions
		if ( ! function_exists( 'wp_verify_nonce' ) ) {
			function wp_verify_nonce( $nonce, $action ) {
				return true;
			}
		}

		if ( ! function_exists( 'wp_send_json_success' ) ) {
			function wp_send_json_success( $data ) {
				echo json_encode( [ 'success' => true, 'data' => $data ] );
			}
		}

		if ( ! function_exists( 'wp_send_json_error' ) ) {
			function wp_send_json_error( $data ) {
				echo json_encode( [ 'success' => false, 'data' => $data ] );
			}
		}

		if ( ! function_exists( 'sanitize_text_field' ) ) {
			function sanitize_text_field( $text ) {
				return trim( strip_tags( $text ) );
			}
		}

                if ( ! function_exists( 'admin_url' ) ) {
                        function admin_url( $path ) {
                                return 'http://example.com/wp-admin/' . $path;
                        }
                }

                if ( ! function_exists( 'add_query_arg' ) ) {
                        function add_query_arg( $args, $url ) {
                                return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $args );
                        }
                }

                if ( ! function_exists( 'wp_upload_dir' ) ) {
			function wp_upload_dir() {
				return [
					'basedir' => '/tmp',
					'baseurl' => 'http://example.com/uploads'
				];
			}
		}

		if ( ! function_exists( 'wp_mkdir_p' ) ) {
			function wp_mkdir_p( $path ) {
				return mkdir( $path, 0755, true );
			}
		}

		if ( ! function_exists( 'wp_generate_password' ) ) {
			function wp_generate_password( $length, $special_chars ) {
				return substr( str_shuffle( str_repeat( 'abcdefghijklmnopqrstuvwxyz0123456789', $length ) ), 0, $length );
			}
		}

		if ( ! function_exists( 'set_transient' ) ) {
			function set_transient( $key, $value, $expiration ) {
				return true;
			}
		}

		if ( ! function_exists( 'get_transient' ) ) {
			function get_transient( $key ) {
				return false;
			}
		}

		if ( ! function_exists( 'delete_transient' ) ) {
			function delete_transient( $key ) {
				return true;
			}
		}

		if ( ! function_exists( 'current_time' ) ) {
			function current_time( $format ) {
				return date( $format );
			}
		}

		if ( ! function_exists( 'wp_json_encode' ) ) {
			function wp_json_encode( $data, $options = 0 ) {
				return json_encode( $data, $options );
			}
		}
	}

	/**
	 * Test exporter initialization
	 *
	 * @return void
	 */
	public function test_exporter_init(): void {
		$this->assertTrue( method_exists( DataExporter::class, 'init' ) );
		$this->assertTrue( is_callable( [ DataExporter::class, 'init' ] ) );
	}

	/**
	 * Test export format constants
	 *
	 * @return void
	 */
	public function test_export_format_constants(): void {
		$this->assertEquals( 'csv', DataExporter::FORMAT_CSV );
		$this->assertEquals( 'json', DataExporter::FORMAT_JSON );
		$this->assertEquals( 'xml', DataExporter::FORMAT_XML );
		$this->assertEquals( 'pdf', DataExporter::FORMAT_PDF );
	}

	/**
	 * Test CSV content generation
	 *
	 * @return void
	 */
	public function test_csv_content_generation(): void {
		$reflection = new ReflectionClass( DataExporter::class );
		$method = $reflection->getMethod( 'generate_csv_content' );
		$method->setAccessible( true );

		$test_data = [
			[ 'name' => 'John', 'age' => 30, 'city' => 'New York' ],
			[ 'name' => 'Jane', 'age' => 25, 'city' => 'London' ],
		];

		$csv_content = $method->invoke( null, $test_data );

		// Should contain BOM for UTF-8
		$this->assertStringStartsWith( "\xEF\xBB\xBF", $csv_content );
		
                // Should contain headers
                $this->assertStringContainsString( 'name,age,city', $csv_content );

                // Should contain data
                $this->assertStringContainsString( 'John,30,"New York"', $csv_content );
                $this->assertStringContainsString( 'Jane,25,London', $csv_content );
	}

	/**
	 * Test JSON content generation
	 *
	 * @return void
	 */
	public function test_json_content_generation(): void {
		$reflection = new ReflectionClass( DataExporter::class );
		$method = $reflection->getMethod( 'generate_json_content' );
		$method->setAccessible( true );

		$test_data = [
			[ 'name' => 'John', 'age' => 30 ],
			[ 'name' => 'Jane', 'age' => 25 ],
		];

		$json_content = $method->invoke( null, $test_data );
		$decoded = json_decode( $json_content, true );

		// Should be valid JSON
		$this->assertNotNull( $decoded );
		
		// Should contain metadata
		$this->assertArrayHasKey( 'export_date', $decoded );
		$this->assertArrayHasKey( 'row_count', $decoded );
		$this->assertArrayHasKey( 'data', $decoded );
		
		// Should contain our test data
		$this->assertEquals( 2, $decoded['row_count'] );
		$this->assertEquals( $test_data, $decoded['data'] );
	}

	/**
	 * Test XML content generation
	 *
	 * @return void
	 */
	public function test_xml_content_generation(): void {
		$reflection = new ReflectionClass( DataExporter::class );
		$method = $reflection->getMethod( 'generate_xml_content' );
		$method->setAccessible( true );

		$test_data = [
			[ 'name' => 'John', 'age' => 30 ],
		];

		$xml_content = $method->invoke( null, $test_data );

		// Should be valid XML
		$this->assertStringStartsWith( '<?xml version="1.0" encoding="UTF-8"?>', $xml_content );
		
                // Should contain our data
                $this->assertStringContainsString( '<export', $xml_content );
                $this->assertStringContainsString( '<records>', $xml_content );
                $this->assertStringContainsString( '<record', $xml_content );
                $this->assertStringContainsString( 'John', $xml_content );
                $this->assertStringContainsString( '30', $xml_content );
	}

	/**
	 * Test MIME type detection
	 *
	 * @return void
	 */
	public function test_mime_type_detection(): void {
		$reflection = new ReflectionClass( DataExporter::class );
		$method = $reflection->getMethod( 'get_mime_type' );
		$method->setAccessible( true );

		$this->assertEquals( 'text/csv', $method->invoke( null, 'test.csv' ) );
		$this->assertEquals( 'application/json', $method->invoke( null, 'test.json' ) );
		$this->assertEquals( 'application/xml', $method->invoke( null, 'test.xml' ) );
		$this->assertEquals( 'application/pdf', $method->invoke( null, 'test.pdf' ) );
		$this->assertEquals( 'application/octet-stream', $method->invoke( null, 'test.unknown' ) );
	}

	/**
	 * Test export data methods exist
	 *
	 * @return void
	 */
	public function test_export_data_methods_exist(): void {
		$reflection = new ReflectionClass( DataExporter::class );
		
		$methods = [
			'get_analytics_export_data',
			'get_clients_export_data',
			'get_campaigns_export_data',
			'get_reports_export_data',
			'get_settings_export_data',
		];

		foreach ( $methods as $method_name ) {
			$this->assertTrue( 
				$reflection->hasMethod( $method_name ),
				"Method {$method_name} should exist"
			);
		}
	}

	/**
	 * Test cleanup functionality
	 *
	 * @return void
	 */
	public function test_cleanup_functionality(): void {
		$this->assertTrue( method_exists( DataExporter::class, 'cleanup_old_exports' ) );
		$this->assertTrue( is_callable( [ DataExporter::class, 'cleanup_old_exports' ] ) );
	}

	/**
	 * Test settings export data structure
	 *
	 * @return void
	 */
	public function test_settings_export_data(): void {
		// Mock get_option function
		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				$options = [
					'fp_digital_marketing_demo_option' => 'test_value',
					'fp_digital_marketing_cache_settings' => [ 'enabled' => true ],
				];
				return $options[ $option ] ?? $default;
			}
		}

		$reflection = new ReflectionClass( DataExporter::class );
		$method = $reflection->getMethod( 'get_settings_export_data' );
		$method->setAccessible( true );

		$data = $method->invoke( null, [] );

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );
		
		// Check structure of first item
		if ( ! empty( $data ) ) {
			$first_item = $data[0];
			$this->assertArrayHasKey( 'Setting Name', $first_item );
			$this->assertArrayHasKey( 'Setting Value', $first_item );
			$this->assertArrayHasKey( 'Export Date', $first_item );
		}
	}

	/**
	 * Test download URL generation
	 *
	 * @return void
	 */
	public function test_download_url_generation(): void {
		$reflection = new ReflectionClass( DataExporter::class );
		$method = $reflection->getMethod( 'get_download_url' );
		$method->setAccessible( true );

		$token = 'test_token_123';
		$url = $method->invoke( null, $token );

                $this->assertStringContainsString( 'admin-ajax.php', $url );
                $this->assertStringContainsString( 'action=fp_download_export', $url );
                $this->assertStringContainsString( 'token=' . $token, $url );
	}

	/**
	 * Test empty data handling
	 *
	 * @return void
	 */
	public function test_empty_data_handling(): void {
		$reflection = new ReflectionClass( DataExporter::class );
		
		// Test CSV with empty data
		$csv_method = $reflection->getMethod( 'generate_csv_content' );
		$csv_method->setAccessible( true );
		$csv_result = $csv_method->invoke( null, [] );
		$this->assertEquals( '', $csv_result );
		
		// Test JSON with empty data
		$json_method = $reflection->getMethod( 'generate_json_content' );
		$json_method->setAccessible( true );
		$json_result = $json_method->invoke( null, [] );
		$decoded = json_decode( $json_result, true );
		$this->assertEquals( 0, $decoded['row_count'] );
		$this->assertEmpty( $decoded['data'] );
	}
}