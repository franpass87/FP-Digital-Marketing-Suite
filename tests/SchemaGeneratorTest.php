<?php
/**
 * Schema Generator Test
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test class for Schema Generator functionality
 */
class SchemaGeneratorTest extends TestCase {

	/**
	 * Test setup
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Include the class files
		require_once __DIR__ . '/bootstrap.php';
		require_once __DIR__ . '/../src/Helpers/SchemaGenerator.php';
		require_once __DIR__ . '/../src/Helpers/Schema/BaseSchema.php';
		require_once __DIR__ . '/../src/Helpers/Schema/WebSiteSchema.php';
		require_once __DIR__ . '/../src/Helpers/Schema/OrganizationSchema.php';
		require_once __DIR__ . '/../src/Helpers/Schema/BreadcrumbListSchema.php';
		require_once __DIR__ . '/../src/Helpers/Schema/ArticleSchema.php';
		require_once __DIR__ . '/../src/Helpers/Schema/FAQSchema.php';

		// Reset global mock functions
		global $wp_mock_functions;
		$wp_mock_functions = [];
	}

	/**
	 * Test schema types configuration
	 *
	 * @return void
	 */
	public function test_get_schema_types(): void {
		$types = FP\DigitalMarketing\Helpers\SchemaGenerator::get_schema_types();
		
		$this->assertIsArray( $types );
		$this->assertArrayHasKey( 'website', $types );
		$this->assertArrayHasKey( 'organization', $types );
		$this->assertArrayHasKey( 'breadcrumb', $types );
		$this->assertArrayHasKey( 'article', $types );
		$this->assertArrayHasKey( 'faq', $types );

		// Test schema type structure
		foreach ( $types as $type_id => $type_config ) {
			$this->assertArrayHasKey( 'class', $type_config );
			$this->assertArrayHasKey( 'name', $type_config );
			$this->assertArrayHasKey( 'description', $type_config );
			$this->assertTrue( class_exists( $type_config['class'] ) );
		}
	}

	/**
	 * Test enabled schema types
	 *
	 * @return void
	 */
	public function test_get_enabled_schema_types(): void {
		// Mock get_option to return specific enabled types
		global $wp_mock_functions;
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_schema_settings' ) {
				return [ 'enabled_types' => [ 'website', 'article' ] ];
			}
			return $default;
		};

		$enabled_types = FP\DigitalMarketing\Helpers\SchemaGenerator::get_enabled_schema_types();
		
		$this->assertIsArray( $enabled_types );
		$this->assertContains( 'website', $enabled_types );
		$this->assertContains( 'article', $enabled_types );
		$this->assertNotContains( 'organization', $enabled_types );
	}

	/**
	 * Test schema type enabled check
	 *
	 * @return void
	 */
	public function test_is_schema_type_enabled(): void {
		// Mock get_option
		global $wp_mock_functions;
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_schema_settings' ) {
				return [ 'enabled_types' => [ 'website', 'article' ] ];
			}
			return $default;
		};

		$this->assertTrue( FP\DigitalMarketing\Helpers\SchemaGenerator::is_schema_type_enabled( 'website' ) );
		$this->assertTrue( FP\DigitalMarketing\Helpers\SchemaGenerator::is_schema_type_enabled( 'article' ) );
		$this->assertFalse( FP\DigitalMarketing\Helpers\SchemaGenerator::is_schema_type_enabled( 'organization' ) );
	}

	/**
	 * Test schema data sanitization
	 *
	 * @return void
	 */
	public function test_sanitize_schema_data(): void {
		$input = [
			'@context' => 'https://schema.org',
			'@type' => 'WebSite',
			'name' => 'Test Site <script>alert("xss")</script>',
			'description' => 'Site description with <b>HTML</b> tags',
			'nested' => [
				'property' => 'Value with <em>markup</em>',
				'number' => 123,
				'boolean' => true
			]
		];

		$sanitized = FP\DigitalMarketing\Helpers\SchemaGenerator::sanitize_schema_data( $input );

		$this->assertEquals( 'https://schema.org', $sanitized['@context'] );
		$this->assertEquals( 'WebSite', $sanitized['@type'] );
		$this->assertEquals( 'Test Site', $sanitized['name'] );
		$this->assertEquals( 'Site description with HTML tags', $sanitized['description'] );
		$this->assertEquals( 'Value with markup', $sanitized['nested']['property'] );
		$this->assertEquals( 123, $sanitized['nested']['number'] );
		$this->assertTrue( $sanitized['nested']['boolean'] );
	}

	/**
	 * Test schema validation
	 *
	 * @return void
	 */
	public function test_validate_schema(): void {
		// Valid schema
		$valid_schema = [
			'@context' => 'https://schema.org',
			'@type' => 'WebSite',
			'name' => 'Test Site'
		];

		$this->assertTrue( FP\DigitalMarketing\Helpers\SchemaGenerator::validate_schema( $valid_schema ) );

		// Invalid schema - missing @context
		$invalid_schema1 = [
			'@type' => 'WebSite',
			'name' => 'Test Site'
		];

		$this->assertFalse( FP\DigitalMarketing\Helpers\SchemaGenerator::validate_schema( $invalid_schema1 ) );

		// Invalid schema - missing @type
		$invalid_schema2 = [
			'@context' => 'https://schema.org',
			'name' => 'Test Site'
		];

		$this->assertFalse( FP\DigitalMarketing\Helpers\SchemaGenerator::validate_schema( $invalid_schema2 ) );

		// Invalid schema - wrong @context
		$invalid_schema3 = [
			'@context' => 'https://wrong-context.com',
			'@type' => 'WebSite',
			'name' => 'Test Site'
		];

		$this->assertFalse( FP\DigitalMarketing\Helpers\SchemaGenerator::validate_schema( $invalid_schema3 ) );
	}

	/**
	 * Test default settings
	 *
	 * @return void
	 */
	public function test_get_default_settings(): void {
		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions['get_bloginfo'] = function( $show = '' ) {
			switch ( $show ) {
				case 'name':
					return 'Test Blog';
				case 'description':
					return 'Test Blog Description';
				default:
					return '';
			}
		};
		$wp_mock_functions['home_url'] = function() {
			return 'https://example.com';
		};

		$defaults = FP\DigitalMarketing\Helpers\SchemaGenerator::get_default_settings();

		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'enabled_types', $defaults );
		$this->assertArrayHasKey( 'organization_name', $defaults );
		$this->assertArrayHasKey( 'organization_url', $defaults );
		$this->assertArrayHasKey( 'enable_breadcrumbs', $defaults );
		$this->assertArrayHasKey( 'faq_post_types', $defaults );

		$this->assertEquals( 'Test Blog', $defaults['organization_name'] );
		$this->assertEquals( 'https://example.com', $defaults['organization_url'] );
		$this->assertTrue( $defaults['enable_breadcrumbs'] );
		$this->assertContains( 'post', $defaults['faq_post_types'] );
		$this->assertContains( 'page', $defaults['faq_post_types'] );
	}

	/**
	 * Test individual schema generation
	 *
	 * @return void
	 */
	public function test_generate_schema(): void {
		// Mock WordPress functions for WebSite schema
		global $wp_mock_functions;
		$wp_mock_functions['get_bloginfo'] = function( $show = '' ) {
			switch ( $show ) {
				case 'name':
					return 'Test Site';
				case 'description':
					return 'Test Description';
				default:
					return '';
			}
		};
		$wp_mock_functions['home_url'] = function() {
			return 'https://example.com';
		};
		$wp_mock_functions['is_home'] = function() {
			return true;
		};
		$wp_mock_functions['is_front_page'] = function() {
			return true;
		};

		// Test WebSite schema generation
		$website_schema = FP\DigitalMarketing\Helpers\SchemaGenerator::generate_schema( 'website' );
		
		$this->assertIsArray( $website_schema );
		$this->assertEquals( 'https://schema.org', $website_schema['@context'] );
		$this->assertEquals( 'WebSite', $website_schema['@type'] );
		$this->assertEquals( 'Test Site', $website_schema['name'] );
		$this->assertEquals( 'https://example.com', $website_schema['url'] );
		$this->assertArrayHasKey( 'potentialAction', $website_schema );

		// Test invalid schema type
		$invalid_schema = FP\DigitalMarketing\Helpers\SchemaGenerator::generate_schema( 'nonexistent' );
		$this->assertNull( $invalid_schema );
	}

	/**
	 * Test schema escaping for JSON-LD output
	 *
	 * @return void
	 */
	public function test_schema_json_escaping(): void {
		$schema_data = [
			'@context' => 'https://schema.org',
			'@type' => 'WebSite',
			'name' => 'Test & Co "Quotes" \'Single\'',
			'description' => 'Description with / slashes and unicode: àáâã'
		];

		// Test that sanitization removes HTML but preserves special characters appropriately
		$sanitized = FP\DigitalMarketing\Helpers\SchemaGenerator::sanitize_schema_data( $schema_data );
		
		$this->assertEquals( 'Test & Co "Quotes" \'Single\'', $sanitized['name'] );
		$this->assertEquals( 'Description with / slashes and unicode: àáâã', $sanitized['description'] );

		// Test JSON encoding works properly with WordPress wp_json_encode
		if ( function_exists( 'wp_json_encode' ) ) {
			$json_output = wp_json_encode( $sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$this->assertNotFalse( $json_output );
			$this->assertStringContainsString( 'Test & Co', $json_output );
			$this->assertStringContainsString( 'unicode: àáâã', $json_output );
		}
	}

	/**
	 * Test schema generation integration
	 *
	 * @return void
	 */
	public function test_generate_schemas_integration(): void {
		// Mock enabled types
		global $wp_mock_functions;
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_schema_settings' ) {
				return [ 'enabled_types' => [ 'website' ] ];
			}
			return $default;
		};

		// Mock WordPress functions
		$wp_mock_functions['get_bloginfo'] = function( $show = '' ) {
			return $show === 'name' ? 'Test Site' : 'Test Description';
		};
		$wp_mock_functions['home_url'] = function() {
			return 'https://example.com';
		};
		$wp_mock_functions['is_home'] = function() {
			return true;
		};
		$wp_mock_functions['is_front_page'] = function() {
			return true;
		};

		$schemas = FP\DigitalMarketing\Helpers\SchemaGenerator::generate_schemas();

		$this->assertIsArray( $schemas );
		$this->assertCount( 1, $schemas );
		$this->assertEquals( 'WebSite', $schemas[0]['@type'] );
	}

	/**
	 * Test empty schema generation when no types enabled
	 *
	 * @return void
	 */
	public function test_empty_schema_generation(): void {
		// Mock no enabled types
		global $wp_mock_functions;
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_schema_settings' ) {
				return [ 'enabled_types' => [] ];
			}
			return $default;
		};

		$schemas = FP\DigitalMarketing\Helpers\SchemaGenerator::generate_schemas();

		$this->assertIsArray( $schemas );
		$this->assertEmpty( $schemas );
	}

	/**
	 * Test schema duplication prevention
	 *
	 * @return void
	 */
	public function test_schema_duplication_prevention(): void {
		// Mock multiple enabled types that could generate similar schemas
		global $wp_mock_functions;
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_schema_settings' ) {
				return [ 'enabled_types' => [ 'website', 'organization' ] ];
			}
			return $default;
		};

		// Mock WordPress functions
		$wp_mock_functions['get_bloginfo'] = function( $show = '' ) {
			return $show === 'name' ? 'Test Site' : 'Test Description';
		};
		$wp_mock_functions['home_url'] = function() {
			return 'https://example.com';
		};
		$wp_mock_functions['is_home'] = function() {
			return true;
		};
		$wp_mock_functions['is_front_page'] = function() {
			return true;
		};

		$schemas = FP\DigitalMarketing\Helpers\SchemaGenerator::generate_schemas();

		// Should have both WebSite and Organization schemas
		$this->assertIsArray( $schemas );
		$this->assertGreaterThanOrEqual( 1, count( $schemas ) );
		
		// Check that each schema has proper @type
		foreach ( $schemas as $schema ) {
			$this->assertArrayHasKey( '@type', $schema );
			$this->assertArrayHasKey( '@context', $schema );
			$this->assertEquals( 'https://schema.org', $schema['@context'] );
		}
	}
}