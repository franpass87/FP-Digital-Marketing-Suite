<?php
/**
 * SEO Metadata Helper
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * SEO Metadata helper class for generating meta tags, Open Graph, and Twitter Cards
 */
class SeoMetadata {

	/**
	 * Meta field keys
	 */
	public const META_TITLE               = '_seo_title';
	public const META_DESCRIPTION         = '_seo_description';
	public const META_ROBOTS              = '_seo_robots';
	public const META_CANONICAL           = '_seo_canonical';
	public const META_OG_TITLE            = '_seo_og_title';
	public const META_OG_DESCRIPTION      = '_seo_og_description';
	public const META_OG_IMAGE            = '_seo_og_image';
	public const META_TWITTER_TITLE       = '_seo_twitter_title';
	public const META_TWITTER_DESCRIPTION = '_seo_twitter_description';
	public const META_TWITTER_IMAGE       = '_seo_twitter_image';
	public const META_FOCUS_KEYWORD       = '_seo_focus_keyword';

	/**
	 * Length limits for validation
	 */
	public const TITLE_MAX_LENGTH               = 60;
	public const DESCRIPTION_MAX_LENGTH         = 160;
	public const OG_TITLE_MAX_LENGTH            = 95;
	public const OG_DESCRIPTION_MAX_LENGTH      = 300;
	public const TWITTER_TITLE_MAX_LENGTH       = 70;
	public const TWITTER_DESCRIPTION_MAX_LENGTH = 200;

	/**
	 * Fallback content length for excerpts
	 */
	public const FALLBACK_EXCERPT_LENGTH = 155;

	/**
	 * Get the SEO title for a post
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The SEO title.
	 */
	public static function get_title( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return '';
		}

			// Check for custom SEO title.
			$custom_title = get_post_meta( $post->ID, self::META_TITLE, true );
		if ( ! empty( $custom_title ) ) {
			return self::sanitize_title( $custom_title );
		}

			// Fallback to post title with site name.
			$title     = get_the_title( $post );
			$site_name = get_bloginfo( 'name' );

		if ( is_front_page() ) {
			$formatted_title = $site_name;
			$tagline         = get_bloginfo( 'description' );
			if ( ! empty( $tagline ) ) {
				$formatted_title .= ' - ' . $tagline;
			}
		} else {
			$formatted_title = $title . ' - ' . $site_name;
		}

			return self::sanitize_title( self::trim_to_length( $formatted_title, self::TITLE_MAX_LENGTH ) );
	}

	/**
	 * Get the SEO description for a post
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The SEO description.
	 */
	public static function get_description( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return '';
		}

			// Check for custom SEO description.
			$custom_description = get_post_meta( $post->ID, self::META_DESCRIPTION, true );
		if ( ! empty( $custom_description ) ) {
			return self::sanitize_description( $custom_description );
		}

			// Fallback to excerpt.
			$excerpt = get_the_excerpt( $post );
		if ( ! empty( $excerpt ) ) {
			return self::sanitize_description( self::trim_to_length( $excerpt, self::DESCRIPTION_MAX_LENGTH ) );
		}

			// Fallback to content snippet.
			$content = wp_strip_all_tags( $post->post_content );
			$content = self::clean_content( $content );

			return self::sanitize_description( self::trim_to_length( $content, self::DESCRIPTION_MAX_LENGTH ) );
	}

	/**
	 * Get meta robots directive for a post
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The robots directive.
	 */
	public static function get_robots( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return 'index, follow';
		}

			// Check for custom robots directive.
			$custom_robots = get_post_meta( $post->ID, self::META_ROBOTS, true );
		if ( ! empty( $custom_robots ) ) {
			return sanitize_text_field( $custom_robots );
		}

			// Check global settings for post type.
			$post_type       = get_post_type( $post );
			$global_settings = get_option( 'fp_digital_marketing_seo_settings', [] );

		if ( isset( $global_settings['noindex_post_types'] ) &&
		in_array( $post_type, $global_settings['noindex_post_types'], true ) ) {
			return 'noindex, nofollow';
		}

			// Default to index, follow.
			return 'index, follow';
	}

	/**
	 * Get canonical URL for a post
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The canonical URL.
	 */
	public static function get_canonical( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return '';
		}

			// Check for custom canonical URL.
			$custom_canonical = get_post_meta( $post->ID, self::META_CANONICAL, true );
		if ( ! empty( $custom_canonical ) ) {
			return esc_url( $custom_canonical );
		}

			// Generate canonical URL without parameters.
			$permalink = get_permalink( $post );
		if ( ! $permalink ) {
				return '';
		}

			// Remove query parameters to avoid duplicate content.
			$parsed_url = wp_parse_url( $permalink );

		if ( empty( $parsed_url['host'] ) || empty( $parsed_url['scheme'] ) ) {
				return esc_url( $permalink );
		}

			$canonical = $parsed_url['scheme'] . '://' . $parsed_url['host'];

		if ( isset( $parsed_url['port'] ) ) {
				$canonical .= ':' . $parsed_url['port'];
		}

			$path       = $parsed_url['path'] ?? '/';
			$canonical .= $path;

		if ( substr( $canonical, -1 ) !== '/' && ! empty( $path ) ) {
				$canonical .= '/';
		}

			return esc_url( $canonical );
	}

	/**
	 * Get Open Graph title
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The OG title.
	 */
	public static function get_og_title( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return '';
		}

			// Check for custom OG title.
			$custom_og_title = get_post_meta( $post->ID, self::META_OG_TITLE, true );
		if ( ! empty( $custom_og_title ) ) {
			return self::sanitize_title( $custom_og_title );
		}

			// Fallback to regular SEO title, but trim for OG length.
			$title = self::get_title( $post );
			return self::trim_to_length( $title, self::OG_TITLE_MAX_LENGTH );
	}

	/**
	 * Get Open Graph description
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The OG description.
	 */
	public static function get_og_description( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return '';
		}

			// Check for custom OG description.
			$custom_og_description = get_post_meta( $post->ID, self::META_OG_DESCRIPTION, true );
		if ( ! empty( $custom_og_description ) ) {
			return self::sanitize_description( $custom_og_description );
		}

			// Fallback to regular SEO description, but trim for OG length.
			$description = self::get_description( $post );
			return self::trim_to_length( $description, self::OG_DESCRIPTION_MAX_LENGTH );
	}

	/**
	 * Get Open Graph image
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The OG image URL.
	 */
	public static function get_og_image( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return '';
		}

			// Check for custom OG image.
			$custom_og_image = get_post_meta( $post->ID, self::META_OG_IMAGE, true );
		if ( ! empty( $custom_og_image ) ) {
			return esc_url( $custom_og_image );
		}

			// Fallback to featured image.
			$featured_image_id = get_post_thumbnail_id( $post );
		if ( $featured_image_id ) {
			$image_url = wp_get_attachment_image_url( $featured_image_id, 'large' );
			if ( $image_url ) {
				return $image_url;
			}
		}

			// Fallback to default OG image from settings.
			$global_settings = get_option( 'fp_digital_marketing_seo_settings', [] );
		if ( ! empty( $global_settings['default_og_image'] ) ) {
			return esc_url( $global_settings['default_og_image'] );
		}

			return '';
	}

	/**
	 * Get Twitter title
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The Twitter title.
	 */
	public static function get_twitter_title( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return '';
		}

			// Check for custom Twitter title.
			$custom_twitter_title = get_post_meta( $post->ID, self::META_TWITTER_TITLE, true );
		if ( ! empty( $custom_twitter_title ) ) {
			return self::sanitize_title( $custom_twitter_title );
		}

			// Fallback to OG title, but trim for Twitter length.
			$title = self::get_og_title( $post );
			return self::trim_to_length( $title, self::TWITTER_TITLE_MAX_LENGTH );
	}

	/**
	 * Get Twitter description
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The Twitter description.
	 */
	public static function get_twitter_description( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return '';
		}

			// Check for custom Twitter description.
			$custom_twitter_description = get_post_meta( $post->ID, self::META_TWITTER_DESCRIPTION, true );
		if ( ! empty( $custom_twitter_description ) ) {
			return self::sanitize_description( $custom_twitter_description );
		}

			// Fallback to OG description, but trim for Twitter length.
			$description = self::get_og_description( $post );
			return self::trim_to_length( $description, self::TWITTER_DESCRIPTION_MAX_LENGTH );
	}

	/**
	 * Get Twitter image
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return string The Twitter image URL.
	 */
	public static function get_twitter_image( $post ): string {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return '';
		}

			// Check for custom Twitter image.
			$custom_twitter_image = get_post_meta( $post->ID, self::META_TWITTER_IMAGE, true );
		if ( ! empty( $custom_twitter_image ) ) {
			return esc_url( $custom_twitter_image );
		}

			// Fallback to OG image.
			return self::get_og_image( $post );
	}

	/**
	 * Validate length of text field
	 *
	 * @param string $text  The text to validate.
	 * @param int    $limit The character limit.
	 * @return array Validation result with 'valid' boolean and 'message' string.
	 */
	public static function validate_length( string $text, int $limit ): array {
		$length = mb_strlen( $text );

		if ( $length <= $limit ) {
			return [
				'valid'   => true,
				'message' => sprintf(
					/* translators: %1$d: current length, %2$d: limit */
					__( '%1$d/%2$d caratteri', 'fp-digital-marketing' ),
					$length,
					$limit
				),
				'length'  => $length,
			];
		}

		return [
			'valid'   => false,
			'message' => sprintf(
				/* translators: %1$d: current length, %2$d: limit, %3$d: excess characters */
				__( '%1$d/%2$d caratteri (%3$d in eccesso)', 'fp-digital-marketing' ),
				$length,
				$limit,
				$length - $limit
			),
			'length'  => $length,
		];
	}

	/**
	 * Generate all meta tags for a post
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return array Array of meta tags.
	 */
	public static function generate_meta_tags( $post ): array {
			$post = self::resolve_post( $post );
		if ( ! $post ) {
			return [];
		}

			$meta_tags = [];

			// Basic SEO meta tags.
			$title       = self::get_title( $post );
			$description = self::get_description( $post );
			$robots      = self::get_robots( $post );
			$canonical   = self::get_canonical( $post );

		if ( $title ) {
			$meta_tags['title'] = $title;
		}

		if ( $description ) {
			$meta_tags['description'] = $description;
		}

			$meta_tags['robots'] = $robots;

		if ( $canonical ) {
			$meta_tags['canonical'] = $canonical;
		}

			// Open Graph meta tags.
			$og_title       = self::get_og_title( $post );
			$og_description = self::get_og_description( $post );
			$og_image       = self::get_og_image( $post );

		if ( $og_title ) {
			$meta_tags['og:title'] = $og_title;
		}

		if ( $og_description ) {
			$meta_tags['og:description'] = $og_description;
		}

			$meta_tags['og:type'] = is_front_page() ? 'website' : 'article';
			$meta_tags['og:url']  = $canonical;

		if ( $og_image ) {
			$meta_tags['og:image'] = $og_image;
		}

			$site_name = get_bloginfo( 'name' );
		if ( $site_name ) {
			$meta_tags['og:site_name'] = $site_name;
		}

			// Twitter Card meta tags.
			$twitter_title       = self::get_twitter_title( $post );
			$twitter_description = self::get_twitter_description( $post );
			$twitter_image       = self::get_twitter_image( $post );

			$meta_tags['twitter:card'] = 'summary_large_image';

		if ( $twitter_title ) {
			$meta_tags['twitter:title'] = $twitter_title;
		}

		if ( $twitter_description ) {
			$meta_tags['twitter:description'] = $twitter_description;
		}

		if ( $twitter_image ) {
			$meta_tags['twitter:image'] = $twitter_image;
		}

			// Twitter site handle from settings.
			$global_settings = get_option( 'fp_digital_marketing_seo_settings', [] );
		if ( ! empty( $global_settings['twitter_site'] ) ) {
			$meta_tags['twitter:site'] = sanitize_text_field( $global_settings['twitter_site'] );
		}

			return $meta_tags;
	}

	/**
	 * Sanitize title text
	 *
	 * @param string $title The title to sanitize.
	 * @return string Sanitized title.
	 */
	private static function sanitize_title( string $title ): string {
		return wp_strip_all_tags( trim( $title ) );
	}

	/**
	 * Sanitize description text
	 *
	 * @param string $description The description to sanitize.
	 * @return string Sanitized description.
	 */
	private static function sanitize_description( string $description ): string {
		return wp_strip_all_tags( trim( $description ) );
	}

	/**
	 * Trim text to specified length
	 *
	 * @param string $text   The text to trim.
	 * @param int    $length Maximum length.
	 * @return string Trimmed text.
	 */
	private static function trim_to_length( string $text, int $length ): string {
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		$trimmed = mb_substr( $text, 0, $length - 3 );

		// Try to break at word boundary.
		$last_space = mb_strrpos( $trimmed, ' ' );
		if ( $last_space !== false && $last_space > $length * 0.8 ) {
			$trimmed = mb_substr( $trimmed, 0, $last_space );
		}

		return $trimmed . '...';
	}

	/**
	 * Clean content for meta description
	 *
	 * @param string $content The content to clean.
	 * @return string Cleaned content.
	 */
	private static function clean_content( string $content ): string {
			// Remove extra whitespace and line breaks.
			$content = preg_replace( '/\s+/', ' ', $content );

			// Remove shortcodes.
			$content = strip_shortcodes( $content );

			return trim( $content );
	}

		/**
		 * Resolve the incoming post reference to a WP_Post like object.
		 *
		 * WordPress' get_post() gracefully handles integers, objects and
		 * WP_Post instances. The lightweight test environment used in this
		 * project provides simplified shims which only accept an ID, so we
		 * need a defensive helper to keep the behaviour consistent across
		 * environments.
		 *
		 * @param mixed $post Post identifier or object.
		 * @return object|null Post object or null when it cannot be resolved.
		 */
	private static function resolve_post( $post ): ?object {
		if ( is_object( $post ) && isset( $post->ID ) ) {
				$resolved = get_post( $post->ID );

			if ( $resolved && is_object( $resolved ) ) {
				return $resolved;
			}

				return $post;
		}

		if ( is_numeric( $post ) || is_string( $post ) ) {
				$resolved = get_post( $post );

			if ( $resolved && is_object( $resolved ) ) {
					return $resolved;
			}
		}

			return null;
	}
}
