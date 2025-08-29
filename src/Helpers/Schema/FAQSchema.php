<?php
/**
 * FAQ Schema Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers\Schema;

/**
 * FAQ schema generator
 */
class FAQSchema extends BaseSchema {

	/**
	 * Generate FAQ schema data
	 *
	 * @return array|null FAQ schema or null
	 */
	public static function generate(): ?array {
		$post = self::get_current_post();

		if ( ! $post ) {
			return null;
		}

		$faq_items = self::get_faq_items( $post );

		if ( empty( $faq_items ) ) {
			return null;
		}

		$schema = self::create_base_schema( 'FAQPage', [
			'mainEntity' => $faq_items
		] );

		return apply_filters( 'fp_dms_faq_schema', $schema, $post );
	}

	/**
	 * Check if FAQ schema is applicable
	 *
	 * @return bool True if post has FAQ content
	 */
	public static function is_applicable(): bool {
		$post = self::get_current_post();

		if ( ! $post ) {
			return false;
		}

		$settings = get_option( 'fp_digital_marketing_schema_settings', [] );
		$enabled_post_types = $settings['faq_post_types'] ?? [ 'post', 'page' ];

		// Check if current post type is enabled for FAQ
		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			return false;
		}

		// Check if post has FAQ content
		return self::has_faq_content( $post );
	}

	/**
	 * Check if post has FAQ content
	 *
	 * @param \WP_Post $post Post object
	 * @return bool True if has FAQ content
	 */
	private static function has_faq_content( \WP_Post $post ): bool {
		// Check for FAQ blocks (Gutenberg)
		if ( has_blocks( $post->post_content ) ) {
			if ( self::has_faq_blocks( $post->post_content ) ) {
				return true;
			}
		}

		// Check for FAQ shortcodes
		if ( self::has_faq_shortcodes( $post->post_content ) ) {
			return true;
		}

		// Check for FAQ patterns in content
		if ( self::has_faq_patterns( $post->post_content ) ) {
			return true;
		}

		// Check for custom FAQ meta fields
		$custom_faqs = get_post_meta( $post->ID, '_fp_faqs', true );
		if ( ! empty( $custom_faqs ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get FAQ items from post content
	 *
	 * @param \WP_Post $post Post object
	 * @return array FAQ items
	 */
	private static function get_faq_items( \WP_Post $post ): array {
		$faq_items = [];

		// Get FAQ items from Gutenberg blocks
		if ( has_blocks( $post->post_content ) ) {
			$faq_items = array_merge( $faq_items, self::get_faq_from_blocks( $post->post_content ) );
		}

		// Get FAQ items from shortcodes
		$faq_items = array_merge( $faq_items, self::get_faq_from_shortcodes( $post->post_content ) );

		// Get FAQ items from content patterns
		$faq_items = array_merge( $faq_items, self::get_faq_from_patterns( $post->post_content ) );

		// Get FAQ items from custom meta fields
		$custom_faqs = get_post_meta( $post->ID, '_fp_faqs', true );
		if ( is_array( $custom_faqs ) ) {
			$faq_items = array_merge( $faq_items, self::format_custom_faqs( $custom_faqs ) );
		}

		// Remove duplicates and filter
		$faq_items = array_unique( $faq_items, SORT_REGULAR );
		$faq_items = apply_filters( 'fp_dms_faq_items', $faq_items, $post );

		return $faq_items;
	}

	/**
	 * Check for FAQ blocks in content
	 *
	 * @param string $content Post content
	 * @return bool True if has FAQ blocks
	 */
	private static function has_faq_blocks( string $content ): bool {
		// Check for common FAQ block patterns
		$faq_block_patterns = [
			'wp:group.*faq',
			'wp:heading.*\?',
			'wp:details',
			'fp-dms/faq'
		];

		foreach ( $faq_block_patterns as $pattern ) {
			if ( preg_match( '/' . $pattern . '/i', $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get FAQ items from Gutenberg blocks
	 *
	 * @param string $content Post content
	 * @return array FAQ items
	 */
	private static function get_faq_from_blocks( string $content ): array {
		$faq_items = [];
		$blocks = parse_blocks( $content );

		foreach ( $blocks as $block ) {
			$faq_items = array_merge( $faq_items, self::extract_faq_from_block( $block ) );
		}

		return $faq_items;
	}

	/**
	 * Extract FAQ from a single block
	 *
	 * @param array $block Block data
	 * @return array FAQ items
	 */
	private static function extract_faq_from_block( array $block ): array {
		$faq_items = [];

		// Check for custom FAQ block
		if ( $block['blockName'] === 'fp-dms/faq' ) {
			if ( isset( $block['attrs']['faqs'] ) && is_array( $block['attrs']['faqs'] ) ) {
				foreach ( $block['attrs']['faqs'] as $faq ) {
					if ( ! empty( $faq['question'] ) && ! empty( $faq['answer'] ) ) {
						$faq_items[] = self::create_faq_item( $faq['question'], $faq['answer'] );
					}
				}
			}
		}

		// Check for details/summary pattern
		if ( $block['blockName'] === 'core/details' ) {
			$question = $block['attrs']['summary'] ?? '';
			$answer = wp_strip_all_tags( render_block( $block ) );
			
			if ( $question && $answer ) {
				$faq_items[] = self::create_faq_item( $question, $answer );
			}
		}

		// Recursively check inner blocks
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$faq_items = array_merge( $faq_items, self::extract_faq_from_block( $inner_block ) );
			}
		}

		return $faq_items;
	}

	/**
	 * Check for FAQ shortcodes
	 *
	 * @param string $content Post content
	 * @return bool True if has FAQ shortcodes
	 */
	private static function has_faq_shortcodes( string $content ): bool {
		return has_shortcode( $content, 'faq' ) || has_shortcode( $content, 'fp_faq' );
	}

	/**
	 * Get FAQ items from shortcodes
	 *
	 * @param string $content Post content
	 * @return array FAQ items
	 */
	private static function get_faq_from_shortcodes( string $content ): array {
		$faq_items = [];

		// Simple FAQ shortcode pattern: [faq question="..." answer="..."]
		$pattern = '/\[faq\s+question="([^"]+)"\s+answer="([^"]+)"\]/';
		preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$faq_items[] = self::create_faq_item( $match[1], $match[2] );
		}

		return $faq_items;
	}

	/**
	 * Check for FAQ patterns in content
	 *
	 * @param string $content Post content
	 * @return bool True if has FAQ patterns
	 */
	private static function has_faq_patterns( string $content ): bool {
		// Look for question patterns (headings with question marks)
		$question_patterns = [
			'/<h[2-6][^>]*>.*\?.*<\/h[2-6]>/',
			'/\*\*.*\?\*\*/',
			'/^Q:|Question:/',
		];

		foreach ( $question_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get FAQ items from content patterns
	 *
	 * @param string $content Post content
	 * @return array FAQ items
	 */
	private static function get_faq_from_patterns( string $content ): array {
		$faq_items = [];

		// Pattern: Heading with question mark followed by content
		$pattern = '/<h([2-6])[^>]*>(.*\?[^<]*)<\/h\1>\s*<p>(.*?)<\/p>/s';
		preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$question = wp_strip_all_tags( $match[2] );
			$answer = wp_strip_all_tags( $match[3] );
			
			if ( $question && $answer ) {
				$faq_items[] = self::create_faq_item( $question, $answer );
			}
		}

		return $faq_items;
	}

	/**
	 * Format custom FAQ meta fields
	 *
	 * @param array $custom_faqs Custom FAQ data
	 * @return array FAQ items
	 */
	private static function format_custom_faqs( array $custom_faqs ): array {
		$faq_items = [];

		foreach ( $custom_faqs as $faq ) {
			if ( ! empty( $faq['question'] ) && ! empty( $faq['answer'] ) ) {
				$faq_items[] = self::create_faq_item( $faq['question'], $faq['answer'] );
			}
		}

		return $faq_items;
	}

	/**
	 * Create a FAQ item schema
	 *
	 * @param string $question The question
	 * @param string $answer The answer
	 * @return array FAQ item schema
	 */
	private static function create_faq_item( string $question, string $answer ): array {
		return [
			'@type' => 'Question',
			'name' => wp_strip_all_tags( $question ),
			'acceptedAnswer' => [
				'@type' => 'Answer',
				'text' => wp_strip_all_tags( $answer )
			]
		];
	}
}