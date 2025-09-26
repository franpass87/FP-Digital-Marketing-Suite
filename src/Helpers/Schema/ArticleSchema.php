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

				$schema = self::create_base_schema(
					$schema_type,
					[
						'headline'      => get_the_title( $post ),
						'url'           => self::get_permalink( $post ),
						'datePublished' => isset( $post->post_date ) ? self::format_date( (string) $post->post_date ) : '',
						'dateModified'  => isset( $post->post_modified ) ? self::format_date( (string) $post->post_modified ) : '',
						'author'        => self::get_author_info( $post ),
						'publisher'     => self::get_organization_schema(),
					]
				);

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
				$post_id    = isset( $post->ID ) ? (int) $post->ID : 0;
				$categories = get_the_category( $post_id );
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
	 * @param object $post Post object
	 * @return string Schema type
	 */
	private static function get_schema_type( object $post ): string {
			$post_type = $post->post_type ?? '';

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
	 * @param object $post Post object
	 * @return string|null Post description or null
	 */
	private static function get_post_description( object $post ): ?string {
		// Check for custom SEO meta description first
			$post_id         = isset( $post->ID ) ? (int) $post->ID : 0;
			$seo_description = get_post_meta( $post_id, '_fp_seo_description', true );
		if ( ! empty( $seo_description ) ) {
			return wp_strip_all_tags( $seo_description );
		}

		// Use excerpt if available
		if ( ! empty( $post->post_excerpt ) ) {
				return wp_strip_all_tags( (string) $post->post_excerpt );
		}

			// Generate excerpt from content
			$content = isset( $post->post_content ) ? wp_strip_all_tags( (string) $post->post_content ) : '';
		if ( strlen( $content ) > 160 ) {
				$content = substr( $content, 0, 157 ) . '...';
		}

			return $content ?: null;
	}

	/**
	 * Get featured image schema
	 *
	 * @param object $post Post object
	 * @return array|null Image schema or null
	 */
	private static function get_featured_image( object $post ): ?array {
			$thumbnail_id = get_post_thumbnail_id( isset( $post->ID ) ? (int) $post->ID : 0 );

		if ( ! $thumbnail_id ) {
			return null;
		}

		$image_data = wp_get_attachment_image_src( $thumbnail_id, 'full' );
		if ( ! $image_data ) {
			return null;
		}

		$image_meta = wp_get_attachment_metadata( $thumbnail_id );
		$alt_text   = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );

		$image_schema = [
			'@type'  => 'ImageObject',
			'url'    => $image_data[0],
			'width'  => $image_data[1],
			'height' => $image_data[2],
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
	 * @param object $post Post object
	 * @return string Article body
	 */
	private static function get_article_body( object $post ): string {
		$content = apply_filters( 'the_content', $post->post_content );
		return wp_strip_all_tags( $content );
	}

	/**
	 * Get word count for the post
	 *
	 * @param object $post Post object
	 * @return int Word count
	 */
	private static function get_word_count( object $post ): int {
			$content = isset( $post->post_content ) ? wp_strip_all_tags( (string) $post->post_content ) : '';

		if ( $content === '' ) {
				return 0;
		}

			$words = str_word_count( $content, 1 );

		if ( empty( $words ) ) {
				return 0;
		}

			$words = array_filter(
				$words,
				static function ( $word ): bool {
							$length = function_exists( 'mb_strlen' ) ? mb_strlen( $word ) : strlen( $word );
							return $length > 1;
				}
			);

			return count( $words );
	}

	/**
	 * Get post keywords from categories and tags
	 *
	 * @param object $post Post object
	 * @return array Keywords
	 */
	private static function get_post_keywords( object $post ): array {
			$keywords = [];
			$post_id  = isset( $post->ID ) ? (int) $post->ID : 0;

			// Get categories
			$categories = get_the_category( $post_id );
		foreach ( $categories as $category ) {
			$keywords[] = $category->name;
		}

		// Get tags
			$tags = get_the_tags( $post_id );
		if ( $tags ) {
			foreach ( $tags as $tag ) {
				$keywords[] = $tag->name;
			}
		}

		return array_unique( $keywords );
	}
}
