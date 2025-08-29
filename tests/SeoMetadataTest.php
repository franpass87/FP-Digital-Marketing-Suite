<?php
/**
 * SEO Metadata Test
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test class for SEO Metadata functionality
 */
class SeoMetadataTest extends TestCase {

	/**
	 * Test setup
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Include the class files.
		require_once __DIR__ . '/bootstrap.php';
		require_once __DIR__ . '/../src/Helpers/SeoMetadata.php';
	}

	/**
	 * Test title generation
	 *
	 * @return void
	 */
	public function test_get_title(): void {
		// Create a mock post.
		$post = (object) [
			'ID' => 123,
			'post_title' => 'Test Post Title',
			'post_content' => 'This is the content of the test post.',
		];

		// Mock WordPress functions.
		global $wp_mock_functions;
		$wp_mock_functions = [];
		
		$wp_mock_functions['get_post'] = function( $post_id ) use ( $post ) {
			return $post_id === 123 ? $post : null;
		};

		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			if ( $post_id === 123 && $key === '_seo_title' ) {
				return '';
			}
			return '';
		};

		$wp_mock_functions['get_the_title'] = function( $post ) {
			return $post->post_title ?? '';
		};

		$wp_mock_functions['get_bloginfo'] = function( $show ) {
			if ( $show === 'name' ) {
				return 'Test Site';
			}
			if ( $show === 'description' ) {
				return 'Test Site Description';
			}
			return '';
		};

		$wp_mock_functions['is_front_page'] = function() {
			return false;
		};

		// Test title generation.
		$title = \FP\DigitalMarketing\Helpers\SeoMetadata::get_title( $post );
		$this->assertStringContainsString( 'Test Post Title', $title );
		$this->assertStringContainsString( 'Test Site', $title );
	}

	/**
	 * Test description generation with excerpt
	 *
	 * @return void
	 */
	public function test_get_description_with_excerpt(): void {
		$post = (object) [
			'ID' => 123,
			'post_title' => 'Test Post',
			'post_content' => 'This is a long content that should be used as fallback for the description when no excerpt is available.',
			'post_excerpt' => 'This is the excerpt',
		];

		global $wp_mock_functions;
		$wp_mock_functions['get_post'] = function( $post_id ) use ( $post ) {
			return $post_id === 123 ? $post : null;
		};

		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};

		$wp_mock_functions['get_the_excerpt'] = function( $post ) {
			return $post->post_excerpt ?? '';
		};

		$wp_mock_functions['wp_strip_all_tags'] = function( $text ) {
			return strip_tags( $text );
		};

		$description = \FP\DigitalMarketing\Helpers\SeoMetadata::get_description( $post );
		$this->assertEquals( 'This is the excerpt', $description );
	}

	/**
	 * Test description generation from content
	 *
	 * @return void
	 */
	public function test_get_description_from_content(): void {
		$post = (object) [
			'ID' => 123,
			'post_title' => 'Test Post',
			'post_content' => 'This is a long content that should be used as fallback for the description when no excerpt is available.',
			'post_excerpt' => '',
		];

		global $wp_mock_functions;
		$wp_mock_functions['get_post'] = function( $post_id ) use ( $post ) {
			return $post_id === 123 ? $post : null;
		};

		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};

		$wp_mock_functions['get_the_excerpt'] = function( $post ) {
			return '';
		};

		$wp_mock_functions['wp_strip_all_tags'] = function( $text ) {
			return strip_tags( $text );
		};

		$description = \FP\DigitalMarketing\Helpers\SeoMetadata::get_description( $post );
		$this->assertStringContainsString( 'This is a long content', $description );
	}

	/**
	 * Test length validation
	 *
	 * @return void
	 */
	public function test_validate_length(): void {
		// Test valid length.
		$result = \FP\DigitalMarketing\Helpers\SeoMetadata::validate_length( 'Short text', 60 );
		$this->assertTrue( $result['valid'] );
		$this->assertEquals( 10, $result['length'] );

		// Test invalid length.
		$result = \FP\DigitalMarketing\Helpers\SeoMetadata::validate_length( str_repeat( 'a', 70 ), 60 );
		$this->assertFalse( $result['valid'] );
		$this->assertEquals( 70, $result['length'] );
	}

	/**
	 * Test robots directive generation
	 *
	 * @return void
	 */
	public function test_get_robots(): void {
		$post = (object) [
			'ID' => 123,
			'post_type' => 'post',
		];

		global $wp_mock_functions;
		$wp_mock_functions['get_post'] = function( $post_id ) use ( $post ) {
			return $post_id === 123 ? $post : null;
		};

		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};

		$wp_mock_functions['get_post_type'] = function( $post ) {
			return $post->post_type ?? 'post';
		};

		$wp_mock_functions['get_option'] = function( $option_name, $default = false ) {
			return $default;
		};

		$wp_mock_functions['sanitize_text_field'] = function( $text ) {
			return trim( strip_tags( $text ) );
		};

		$robots = \FP\DigitalMarketing\Helpers\SeoMetadata::get_robots( $post );
		$this->assertEquals( 'index, follow', $robots );
	}

	/**
	 * Test canonical URL generation
	 *
	 * @return void
	 */
	public function test_get_canonical(): void {
		$post = (object) [
			'ID' => 123,
		];

		global $wp_mock_functions;
		$wp_mock_functions['get_post'] = function( $post_id ) use ( $post ) {
			return $post_id === 123 ? $post : null;
		};

		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};

		$wp_mock_functions['get_permalink'] = function( $post ) {
			return 'https://example.com/test-post/?param=value';
		};

		$wp_mock_functions['esc_url'] = function( $url ) {
			return $url;
		};

		$wp_mock_functions['wp_parse_url'] = function( $url, $component = -1 ) {
			return parse_url( $url, $component );
		};

		$canonical = \FP\DigitalMarketing\Helpers\SeoMetadata::get_canonical( $post );
		$this->assertEquals( 'https://example.com/test-post/', $canonical );
	}

	/**
	 * Test Open Graph title generation
	 *
	 * @return void
	 */
	public function test_get_og_title(): void {
		$post = (object) [
			'ID' => 123,
			'post_title' => 'Test Post Title',
		];

		global $wp_mock_functions;
		$wp_mock_functions['get_post'] = function( $post_id ) use ( $post ) {
			return $post_id === 123 ? $post : null;
		};

		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};

		$wp_mock_functions['get_the_title'] = function( $post ) {
			return $post->post_title ?? '';
		};

		$wp_mock_functions['get_bloginfo'] = function( $show ) {
			return $show === 'name' ? 'Test Site' : '';
		};

		$wp_mock_functions['is_front_page'] = function() {
			return false;
		};

		$wp_mock_functions['wp_strip_all_tags'] = function( $text ) {
			return strip_tags( $text );
		};

		$og_title = \FP\DigitalMarketing\Helpers\SeoMetadata::get_og_title( $post );
		$this->assertStringContainsString( 'Test Post Title', $og_title );
	}

	/**
	 * Test edge case with empty content
	 *
	 * @return void
	 */
	public function test_empty_content_edge_case(): void {
		$post = (object) [
			'ID' => 123,
			'post_title' => '',
			'post_content' => '',
			'post_excerpt' => '',
		];

		global $wp_mock_functions;
		$wp_mock_functions['get_post'] = function( $post_id ) use ( $post ) {
			return $post_id === 123 ? $post : null;
		};

		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};

		$wp_mock_functions['get_the_title'] = function( $post ) {
			return '';
		};

		$wp_mock_functions['get_the_excerpt'] = function( $post ) {
			return '';
		};

		$wp_mock_functions['wp_strip_all_tags'] = function( $text ) {
			return strip_tags( $text );
		};

		$wp_mock_functions['get_bloginfo'] = function( $show ) {
			return $show === 'name' ? 'Test Site' : '';
		};

		$wp_mock_functions['is_front_page'] = function() {
			return false;
		};

		$description = \FP\DigitalMarketing\Helpers\SeoMetadata::get_description( $post );
		$this->assertEquals( '', $description );
	}

	/**
	 * Test special characters handling
	 *
	 * @return void
	 */
	public function test_special_characters(): void {
		$post = (object) [
			'ID' => 123,
			'post_title' => 'Test "Quote" & Ampersand <script>alert("xss")</script>',
			'post_content' => 'Content with <strong>HTML</strong> & special chars "quotes"',
		];

		global $wp_mock_functions;
		$wp_mock_functions['get_post'] = function( $post_id ) use ( $post ) {
			return $post_id === 123 ? $post : null;
		};

		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};

		$wp_mock_functions['get_the_title'] = function( $post ) {
			return $post->post_title ?? '';
		};

		$wp_mock_functions['get_the_excerpt'] = function( $post ) {
			return '';
		};

		$wp_mock_functions['wp_strip_all_tags'] = function( $text ) {
			return strip_tags( $text );
		};

		$wp_mock_functions['get_bloginfo'] = function( $show ) {
			return $show === 'name' ? 'Test Site' : '';
		};

		$wp_mock_functions['is_front_page'] = function() {
			return false;
		};

		$title = \FP\DigitalMarketing\Helpers\SeoMetadata::get_title( $post );
		$this->assertStringNotContainsString( '<script>', $title );
		$this->assertStringContainsString( 'Test "Quote" & Ampersand', $title );

		$description = \FP\DigitalMarketing\Helpers\SeoMetadata::get_description( $post );
		$this->assertStringNotContainsString( '<strong>', $description );
		$this->assertStringContainsString( 'Content with HTML', $description );
	}
}