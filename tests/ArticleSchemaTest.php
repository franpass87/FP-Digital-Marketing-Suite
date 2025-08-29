<?php
/**
 * Article Schema Test
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test class for Article Schema functionality
 */
class ArticleSchemaTest extends TestCase {

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
		require_once __DIR__ . '/../src/Helpers/Schema/ArticleSchema.php';

		// Reset global mock functions
		global $wp_mock_functions;
		$wp_mock_functions = [];
	}

	/**
	 * Test Article schema generation for blog post
	 *
	 * @return void
	 */
	public function test_article_schema_generation_blog_post(): void {
		global $wp_mock_functions, $post;
		
		// Create mock post
		$post = (object) [
			'ID' => 123,
			'post_title' => 'Test Blog Post',
			'post_content' => 'This is the content of the test blog post with more than 160 characters to test excerpt generation and word count functionality.',
			'post_excerpt' => 'This is a custom excerpt for the post',
			'post_type' => 'post',
			'post_date' => '2023-01-15 10:30:00',
			'post_modified' => '2023-01-16 14:45:00',
			'post_author' => 1
		];
		
		// Mock WordPress functions
		$wp_mock_functions['is_singular'] = function( $post_types = null ) {
			return true;
		};
		$wp_mock_functions['get_the_title'] = function( $post = null ) {
			return 'Test Blog Post';
		};
		$wp_mock_functions['get_permalink'] = function( $post = null ) {
			return 'https://testsite.com/test-blog-post/';
		};
		$wp_mock_functions['get_userdata'] = function( $user_id ) {
			if ( $user_id === 1 ) {
				return (object) [
					'display_name' => 'Test Author',
					'ID' => 1
				];
			}
			return false;
		};
		$wp_mock_functions['get_author_posts_url'] = function( $author_id ) {
			return 'https://testsite.com/author/test-author/';
		};
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_schema_settings' ) {
				return [
					'organization_name' => 'Test Organization',
					'organization_url' => 'https://testsite.com'
				];
			}
			return $default;
		};
		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};
		$wp_mock_functions['wp_strip_all_tags'] = function( $string ) {
			return strip_tags( $string );
		};
		$wp_mock_functions['get_the_category'] = function( $post_id = null ) {
			return [
				(object) [
					'name' => 'Test Category',
					'term_id' => 1
				]
			];
		};
		$wp_mock_functions['get_the_tags'] = function( $post_id = null ) {
			return [
				(object) [
					'name' => 'test-tag'
				]
			];
		};
		$wp_mock_functions['get_post_thumbnail_id'] = function( $post_id = null ) {
			return 0; // No featured image
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\ArticleSchema::generate();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'https://schema.org', $schema['@context'] );
		$this->assertEquals( 'BlogPosting', $schema['@type'] ); // Should be BlogPosting for posts
		$this->assertEquals( 'Test Blog Post', $schema['headline'] );
		$this->assertEquals( 'https://testsite.com/test-blog-post/', $schema['url'] );
		$this->assertArrayHasKey( 'datePublished', $schema );
		$this->assertArrayHasKey( 'dateModified', $schema );
		$this->assertArrayHasKey( 'author', $schema );
		$this->assertArrayHasKey( 'publisher', $schema );
		$this->assertEquals( 'Person', $schema['author']['@type'] );
		$this->assertEquals( 'Test Author', $schema['author']['name'] );
		$this->assertEquals( 'This is a custom excerpt for the post', $schema['description'] );
		$this->assertEquals( 'Test Category', $schema['articleSection'] );
		$this->assertContains( 'Test Category', $schema['keywords'] );
		$this->assertContains( 'test-tag', $schema['keywords'] );
	}

	/**
	 * Test Article schema generation for page
	 *
	 * @return void
	 */
	public function test_article_schema_generation_page(): void {
		global $wp_mock_functions, $post;
		
		// Create mock page
		$post = (object) [
			'ID' => 456,
			'post_title' => 'Test Page',
			'post_content' => 'This is the content of the test page.',
			'post_excerpt' => '',
			'post_type' => 'page',
			'post_date' => '2023-01-15 10:30:00',
			'post_modified' => '2023-01-16 14:45:00',
			'post_author' => 1
		];
		
		// Mock WordPress functions
		$wp_mock_functions['is_singular'] = function( $post_types = null ) {
			return true;
		};
		$wp_mock_functions['get_the_title'] = function( $post = null ) {
			return 'Test Page';
		};
		$wp_mock_functions['get_permalink'] = function( $post = null ) {
			return 'https://testsite.com/test-page/';
		};
		$wp_mock_functions['get_userdata'] = function( $user_id ) {
			if ( $user_id === 1 ) {
				return (object) [
					'display_name' => 'Test Author',
					'ID' => 1
				];
			}
			return false;
		};
		$wp_mock_functions['get_author_posts_url'] = function( $author_id ) {
			return 'https://testsite.com/author/test-author/';
		};
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_schema_settings' ) {
				return [
					'organization_name' => 'Test Organization',
					'organization_url' => 'https://testsite.com'
				];
			}
			return $default;
		};
		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};
		$wp_mock_functions['wp_strip_all_tags'] = function( $string ) {
			return strip_tags( $string );
		};
		$wp_mock_functions['get_the_category'] = function( $post_id = null ) {
			return [];
		};
		$wp_mock_functions['get_the_tags'] = function( $post_id = null ) {
			return false;
		};
		$wp_mock_functions['get_post_thumbnail_id'] = function( $post_id = null ) {
			return 0;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\ArticleSchema::generate();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'Article', $schema['@type'] ); // Should be Article for pages
		$this->assertEquals( 'Test Page', $schema['headline'] );
		$this->assertEquals( 'https://testsite.com/test-page/', $schema['url'] );
	}

	/**
	 * Test Article schema with featured image
	 *
	 * @return void
	 */
	public function test_article_schema_with_featured_image(): void {
		global $wp_mock_functions, $post;
		
		// Create mock post
		$post = (object) [
			'ID' => 123,
			'post_title' => 'Test Post with Image',
			'post_content' => 'Post content',
			'post_excerpt' => '',
			'post_type' => 'post',
			'post_date' => '2023-01-15 10:30:00',
			'post_modified' => '2023-01-16 14:45:00',
			'post_author' => 1
		];
		
		// Mock WordPress functions
		$wp_mock_functions['is_singular'] = function( $post_types = null ) {
			return true;
		};
		$wp_mock_functions['get_the_title'] = function( $post = null ) {
			return 'Test Post with Image';
		};
		$wp_mock_functions['get_permalink'] = function( $post = null ) {
			return 'https://testsite.com/test-post/';
		};
		$wp_mock_functions['get_userdata'] = function( $user_id ) {
			return (object) [
				'display_name' => 'Test Author',
				'ID' => 1
			];
		};
		$wp_mock_functions['get_author_posts_url'] = function( $author_id ) {
			return 'https://testsite.com/author/test-author/';
		};
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			return [];
		};
		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			if ( $key === '_wp_attachment_image_alt' ) {
				return 'Test image alt text';
			}
			return '';
		};
		$wp_mock_functions['wp_strip_all_tags'] = function( $string ) {
			return strip_tags( $string );
		};
		$wp_mock_functions['get_the_category'] = function( $post_id = null ) {
			return [];
		};
		$wp_mock_functions['get_the_tags'] = function( $post_id = null ) {
			return false;
		};
		$wp_mock_functions['get_post_thumbnail_id'] = function( $post_id = null ) {
			return 789; // Has featured image
		};
		$wp_mock_functions['wp_get_attachment_image_src'] = function( $attachment_id, $size = 'thumbnail' ) {
			if ( $attachment_id === 789 ) {
				return [
					'https://testsite.com/wp-content/uploads/image.jpg',
					800,
					600
				];
			}
			return false;
		};
		$wp_mock_functions['wp_get_attachment_metadata'] = function( $attachment_id ) {
			return [];
		};
		$wp_mock_functions['get_post'] = function( $post_id ) {
			if ( $post_id === 789 ) {
				return (object) [
					'post_excerpt' => 'Image caption'
				];
			}
			return null;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\ArticleSchema::generate();

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'image', $schema );
		$this->assertEquals( 'ImageObject', $schema['image']['@type'] );
		$this->assertEquals( 'https://testsite.com/wp-content/uploads/image.jpg', $schema['image']['url'] );
		$this->assertEquals( 800, $schema['image']['width'] );
		$this->assertEquals( 600, $schema['image']['height'] );
		$this->assertEquals( 'Test image alt text', $schema['image']['alternateName'] );
		$this->assertEquals( 'Image caption', $schema['image']['caption'] );
	}

	/**
	 * Test Article schema is not applicable when not on singular pages
	 *
	 * @return void
	 */
	public function test_article_schema_not_applicable(): void {
		global $wp_mock_functions;
		
		$wp_mock_functions['is_singular'] = function( $post_types = null ) {
			return false; // Not on singular page
		};

		$this->assertFalse( FP\DigitalMarketing\Helpers\Schema\ArticleSchema::is_applicable() );
	}

	/**
	 * Test Article schema returns null when no post
	 *
	 * @return void
	 */
	public function test_article_schema_no_post(): void {
		global $wp_mock_functions, $post;
		
		$post = null; // No current post
		
		$wp_mock_functions['is_singular'] = function( $post_types = null ) {
			return true;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\ArticleSchema::generate();
		$this->assertNull( $schema );
	}

	/**
	 * Test Article schema with SEO description
	 *
	 * @return void
	 */
	public function test_article_schema_with_seo_description(): void {
		global $wp_mock_functions, $post;
		
		// Create mock post
		$post = (object) [
			'ID' => 123,
			'post_title' => 'Test Post',
			'post_content' => 'Post content',
			'post_excerpt' => 'Regular excerpt',
			'post_type' => 'post',
			'post_date' => '2023-01-15 10:30:00',
			'post_modified' => '2023-01-16 14:45:00',
			'post_author' => 1
		];
		
		// Mock WordPress functions
		$wp_mock_functions['is_singular'] = function( $post_types = null ) {
			return true;
		};
		$wp_mock_functions['get_the_title'] = function( $post = null ) {
			return 'Test Post';
		};
		$wp_mock_functions['get_permalink'] = function( $post = null ) {
			return 'https://testsite.com/test-post/';
		};
		$wp_mock_functions['get_userdata'] = function( $user_id ) {
			return (object) [
				'display_name' => 'Test Author',
				'ID' => 1
			];
		};
		$wp_mock_functions['get_author_posts_url'] = function( $author_id ) {
			return 'https://testsite.com/author/test-author/';
		};
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			return [];
		};
		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			if ( $key === '_fp_seo_description' ) {
				return 'Custom SEO description';
			}
			return '';
		};
		$wp_mock_functions['wp_strip_all_tags'] = function( $string ) {
			return strip_tags( $string );
		};
		$wp_mock_functions['get_the_category'] = function( $post_id = null ) {
			return [];
		};
		$wp_mock_functions['get_the_tags'] = function( $post_id = null ) {
			return false;
		};
		$wp_mock_functions['get_post_thumbnail_id'] = function( $post_id = null ) {
			return 0;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\ArticleSchema::generate();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'Custom SEO description', $schema['description'] );
	}

	/**
	 * Test Article schema word count calculation
	 *
	 * @return void
	 */
	public function test_article_schema_word_count(): void {
		global $wp_mock_functions, $post;
		
		// Create mock post with content that has a known word count
		$post = (object) [
			'ID' => 123,
			'post_title' => 'Test Post',
			'post_content' => 'This is a test post with exactly ten words in content.',
			'post_excerpt' => '',
			'post_type' => 'post',
			'post_date' => '2023-01-15 10:30:00',
			'post_modified' => '2023-01-16 14:45:00',
			'post_author' => 1
		];
		
		// Mock basic WordPress functions
		$wp_mock_functions['is_singular'] = function( $post_types = null ) {
			return true;
		};
		$wp_mock_functions['get_the_title'] = function( $post = null ) {
			return 'Test Post';
		};
		$wp_mock_functions['get_permalink'] = function( $post = null ) {
			return 'https://testsite.com/test-post/';
		};
		$wp_mock_functions['get_userdata'] = function( $user_id ) {
			return (object) [
				'display_name' => 'Test Author',
				'ID' => 1
			];
		};
		$wp_mock_functions['get_author_posts_url'] = function( $author_id ) {
			return 'https://testsite.com/author/test-author/';
		};
		$wp_mock_functions['get_option'] = function( $option, $default = false ) {
			return [];
		};
		$wp_mock_functions['get_post_meta'] = function( $post_id, $key, $single = false ) {
			return '';
		};
		$wp_mock_functions['wp_strip_all_tags'] = function( $string ) {
			return strip_tags( $string );
		};
		$wp_mock_functions['get_the_category'] = function( $post_id = null ) {
			return [];
		};
		$wp_mock_functions['get_the_tags'] = function( $post_id = null ) {
			return false;
		};
		$wp_mock_functions['get_post_thumbnail_id'] = function( $post_id = null ) {
			return 0;
		};

		$schema = FP\DigitalMarketing\Helpers\Schema\ArticleSchema::generate();

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'wordCount', $schema );
		$this->assertEquals( 10, $schema['wordCount'] );
	}
}