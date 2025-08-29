<?php
/**
 * Article Schema Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers\Schema;

/**
 * Article/BlogPosting schema generator
 */
class ArticleSchema extends BaseSchema {

	/**
	 * Generate Article schema data
	 *
	 * @return array|null Article schema or null
	 */
	public static function generate(): ?array {
		$post = self::get_current_post();

		if ( ! $post ) {
			return null;
		}

		$schema_type = self::get_schema_type( $post );
		
		$schema = self::create_base_schema( $schema_type, [
			'headline' => get_the_title( $post ),
			'url' => self::get_permalink( $post ),
			'datePublished' => self::format_date( $post->post_date ),
			'dateModified' => self::format_date( $post->post_modified ),
			'author' => self::get_author_info( $post ),
			'publisher' => self::get_organization_schema()
		] );

		// Add description/excerpt
		$description = self::get_post_description( $post );
		if ( $description ) {
			$schema['description'] = $description;
		}

		// Add featured image
		$image = self::get_featured_image( $post );
		if ( $image ) {
			$schema['image'] = $image;
		}

		// Add article body (optional)
		if ( apply_filters( 'fp_dms_include_article_body', false ) ) {
			$schema['articleBody'] = self::get_article_body( $post );
		}

		// Add word count
		$word_count = self::get_word_count( $post );
		if ( $word_count > 0 ) {
			$schema['wordCount'] = $word_count;
		}

		// Add categories as keywords
		$keywords = self::get_post_keywords( $post );
		if ( ! empty( $keywords ) ) {
			$schema['keywords'] = $keywords;
		}

		// Add article section for categories
		$categories = get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			$schema['articleSection'] = $categories[0]->name;
		}

		return apply_filters( 'fp_dms_article_schema', $schema, $post );
	}

	/**
	 * Check if Article schema is applicable
	 *
	 * @return bool True for single posts and pages
	 */
	public static function is_applicable(): bool {
		return is_singular( [ 'post', 'page' ] );
	}

	/**
	 * Get appropriate schema type for the post
	 *
	 * @param \WP_Post $post Post object
	 * @return string Schema type
	 */
	private static function get_schema_type( \WP_Post $post ): string {
		$post_type = $post->post_type;

		// Use BlogPosting for blog posts, Article for pages and other content
		if ( $post_type === 'post' ) {
			return 'BlogPosting';
		}

		// Allow filtering of schema type
		return apply_filters( 'fp_dms_article_schema_type', 'Article', $post );
	}

	/**
	 * Get post description/excerpt
	 *
	 * @param \WP_Post $post Post object
	 * @return string|null Post description or null
	 */
	private static function get_post_description( \WP_Post $post ): ?string {
		// Check for custom SEO meta description first
		$seo_description = get_post_meta( $post->ID, '_fp_seo_description', true );
		if ( ! empty( $seo_description ) ) {
			return wp_strip_all_tags( $seo_description );
		}

		// Use excerpt if available
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		// Generate excerpt from content
		$content = wp_strip_all_tags( $post->post_content );
		if ( strlen( $content ) > 160 ) {
			$content = substr( $content, 0, 157 ) . '...';
		}

		return $content ?: null;
	}

	/**
	 * Get featured image schema
	 *
	 * @param \WP_Post $post Post object
	 * @return array|null Image schema or null
	 */
	private static function get_featured_image( \WP_Post $post ): ?array {
		$thumbnail_id = get_post_thumbnail_id( $post->ID );

		if ( ! $thumbnail_id ) {
			return null;
		}

		$image_data = wp_get_attachment_image_src( $thumbnail_id, 'full' );
		if ( ! $image_data ) {
			return null;
		}

		$image_meta = wp_get_attachment_metadata( $thumbnail_id );
		$alt_text = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );

		$image_schema = [
			'@type' => 'ImageObject',
			'url' => $image_data[0],
			'width' => $image_data[1],
			'height' => $image_data[2]
		];

		if ( $alt_text ) {
			$image_schema['alternateName'] = $alt_text;
		}

		// Add caption if available
		$attachment = get_post( $thumbnail_id );
		if ( $attachment && ! empty( $attachment->post_excerpt ) ) {
			$image_schema['caption'] = wp_strip_all_tags( $attachment->post_excerpt );
		}

		return $image_schema;
	}

	/**
	 * Get article body content
	 *
	 * @param \WP_Post $post Post object
	 * @return string Article body
	 */
	private static function get_article_body( \WP_Post $post ): string {
		$content = apply_filters( 'the_content', $post->post_content );
		return wp_strip_all_tags( $content );
	}

	/**
	 * Get word count for the post
	 *
	 * @param \WP_Post $post Post object
	 * @return int Word count
	 */
	private static function get_word_count( \WP_Post $post ): int {
		$content = wp_strip_all_tags( $post->post_content );
		return str_word_count( $content );
	}

	/**
	 * Get post keywords from categories and tags
	 *
	 * @param \WP_Post $post Post object
	 * @return array Keywords
	 */
	private static function get_post_keywords( \WP_Post $post ): array {
		$keywords = [];

		// Get categories
		$categories = get_the_category( $post->ID );
		foreach ( $categories as $category ) {
			$keywords[] = $category->name;
		}

		// Get tags
		$tags = get_the_tags( $post->ID );
		if ( $tags ) {
			foreach ( $tags as $tag ) {
				$keywords[] = $tag->name;
			}
		}

		return array_unique( $keywords );
	}
}