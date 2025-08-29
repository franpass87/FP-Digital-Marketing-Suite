<?php
/**
 * WebSite Schema Test
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test class for WebSite Schema functionality
 */
class WebSiteSchemaTest extends TestCase {

	/**
	 * Test setup
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Include the class files
		require_once __DIR__ . '/bootstrap.php';
		require_once __DIR__ . '/../src/Helpers/Schema/BaseSchema.php';
		require_once __DIR__ . '/../src/Helpers/Schema/WebSiteSchema.php';

		// Reset global mock functions
		global $wp_mock_functions;
		$wp_mock_functions = [];
	}

	/**
	 * Test WebSite schema generation on home page
	 *
	 * @return void
	 */
	public function test_website_schema_generation_home_page(): void {
		global $wp_mock_functions;
		
		// Mock WordPress functions
		$wp_mock_functions['get_bloginfo'] = function( $show = '' ) {
			switch ( $show ) {
				case 'name':
					return 'Test Website';
				case 'description':
					return 'A test website description';
				default:
					return '';
			}
		};
		$wp_mock_functions['home_url'] = function() {
			return 'https://testsite.com';
		};
		$wp_mock_functions['is_home'] = function() {
			return true;
		};
		$wp_mock_functions['is_front_page'] = function() {
			return true;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\WebSiteSchema::generate();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'https://schema.org', $schema['@context'] );
		$this->assertEquals( 'WebSite', $schema['@type'] );
		$this->assertEquals( 'Test Website', $schema['name'] );
		$this->assertEquals( 'https://testsite.com', $schema['url'] );
		$this->assertEquals( 'A test website description', $schema['description'] );
		
		// Should include search action on home page
		$this->assertArrayHasKey( 'potentialAction', $schema );
		$this->assertEquals( 'SearchAction', $schema['potentialAction']['@type'] );
		$this->assertArrayHasKey( 'target', $schema['potentialAction'] );
		$this->assertArrayHasKey( 'query-input', $schema['potentialAction'] );
	}

	/**
	 * Test WebSite schema generation on non-home page
	 *
	 * @return void
	 */
	public function test_website_schema_generation_non_home_page(): void {
		global $wp_mock_functions;
		
		// Mock WordPress functions
		$wp_mock_functions['get_bloginfo'] = function( $show = '' ) {
			switch ( $show ) {
				case 'name':
					return 'Test Website';
				case 'description':
					return 'A test website description';
				default:
					return '';
			}
		};
		$wp_mock_functions['home_url'] = function() {
			return 'https://testsite.com';
		};
		$wp_mock_functions['is_home'] = function() {
			return false;
		};
		$wp_mock_functions['is_front_page'] = function() {
			return false;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\WebSiteSchema::generate();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'WebSite', $schema['@type'] );
		
		// Should NOT include search action on non-home pages
		$this->assertArrayNotHasKey( 'potentialAction', $schema );
	}

	/**
	 * Test WebSite schema without description
	 *
	 * @return void
	 */
	public function test_website_schema_without_description(): void {
		global $wp_mock_functions;
		
		// Mock WordPress functions
		$wp_mock_functions['get_bloginfo'] = function( $show = '' ) {
			switch ( $show ) {
				case 'name':
					return 'Test Website';
				case 'description':
					return ''; // Empty description
				default:
					return '';
			}
		};
		$wp_mock_functions['home_url'] = function() {
			return 'https://testsite.com';
		};
		$wp_mock_functions['is_home'] = function() {
			return false;
		};
		$wp_mock_functions['is_front_page'] = function() {
			return false;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\WebSiteSchema::generate();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'WebSite', $schema['@type'] );
		
		// Should not include empty description
		$this->assertArrayNotHasKey( 'description', $schema );
	}

	/**
	 * Test WebSite schema is always applicable
	 *
	 * @return void
	 */
	public function test_website_schema_is_applicable(): void {
		// WebSite schema should always be applicable
		$this->assertTrue( FP\DigitalMarketing\Helpers\Schema\WebSiteSchema::is_applicable() );
	}

	/**
	 * Test search action URL template generation
	 *
	 * @return void
	 */
	public function test_search_action_url_template(): void {
		global $wp_mock_functions;
		
		// Mock WordPress functions for search functionality
		$wp_mock_functions['get_bloginfo'] = function( $show = '' ) {
			return $show === 'name' ? 'Test Website' : '';
		};
		$wp_mock_functions['home_url'] = function( $path = '' ) {
			return 'https://testsite.com' . $path;
		};
		$wp_mock_functions['is_home'] = function() {
			return true;
		};
		$wp_mock_functions['is_front_page'] = function() {
			return true;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\WebSiteSchema::generate();

		$this->assertArrayHasKey( 'potentialAction', $schema );
		$search_action = $schema['potentialAction'];
		
		$this->assertEquals( 'SearchAction', $search_action['@type'] );
		$this->assertArrayHasKey( 'target', $search_action );
		$this->assertArrayHasKey( 'urlTemplate', $search_action['target'] );
		$this->assertStringContainsString( 'search_term_string', $search_action['target']['urlTemplate'] );
		$this->assertEquals( 'required name=search_term_string', $search_action['query-input'] );
	}

	/**
	 * Test schema with special characters in site name
	 *
	 * @return void
	 */
	public function test_website_schema_with_special_characters(): void {
		global $wp_mock_functions;
		
		// Mock WordPress functions with special characters
		$wp_mock_functions['get_bloginfo'] = function( $show = '' ) {
			switch ( $show ) {
				case 'name':
					return 'Test & Company "Website" \'Site\'';
				case 'description':
					return 'Description with <tags> & special chars';
				default:
					return '';
			}
		};
		$wp_mock_functions['home_url'] = function() {
			return 'https://testsite.com';
		};
		$wp_mock_functions['is_home'] = function() {
			return false;
		};
		$wp_mock_functions['is_front_page'] = function() {
			return false;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\WebSiteSchema::generate();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'Test & Company "Website" \'Site\'', $schema['name'] );
		$this->assertEquals( 'Description with <tags> & special chars', $schema['description'] );
		
		// Verify the schema is valid JSON when encoded
		$json = json_encode( $schema );
		$this->assertNotFalse( $json );
		
		$decoded = json_decode( $json, true );
		$this->assertEquals( $schema, $decoded );
	}
}