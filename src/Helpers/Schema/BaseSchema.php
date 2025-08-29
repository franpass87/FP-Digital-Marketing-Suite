<?php
/**
 * Base Schema Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers\Schema;

/**
 * Base class for all schema types
 */
abstract class BaseSchema {

	/**
	 * Schema.org context URL
	 */
	protected const SCHEMA_CONTEXT = 'https://schema.org';

	/**
	 * Generate schema data for the current context
	 *
	 * @return array|null Schema data or null if not applicable
	 */
	abstract public static function generate(): ?array;

	/**
	 * Check if this schema type is applicable to the current page
	 *
	 * @return bool True if applicable
	 */
	public static function is_applicable(): bool {
		return true;
	}

	/**
	 * Create base schema structure
	 *
	 * @param string $type Schema type
	 * @param array  $data Additional schema data
	 * @return array Base schema structure
	 */
	protected static function create_base_schema( string $type, array $data = [] ): array {
		$schema = [
			'@context' => self::SCHEMA_CONTEXT,
			'@type' => $type
		];

		return array_merge( $schema, $data );
	}

	/**
	 * Get site information
	 *
	 * @return array Site information
	 */
	protected static function get_site_info(): array {
		return [
			'name' => get_bloginfo( 'name' ),
			'url' => home_url(),
			'description' => get_bloginfo( 'description' )
		];
	}

	/**
	 * Get current post data
	 *
	 * @return \WP_Post|null Current post or null
	 */
	protected static function get_current_post(): ?\WP_Post {
		global $post;
		
		if ( is_singular() && $post instanceof \WP_Post ) {
			return $post;
		}

		return null;
	}

	/**
	 * Get author information for a post
	 *
	 * @param \WP_Post $post Post object
	 * @return array Author information
	 */
	protected static function get_author_info( \WP_Post $post ): array {
		$author_id = $post->post_author;
		$author = get_userdata( $author_id );

		if ( ! $author ) {
			return [];
		}

		return [
			'@type' => 'Person',
			'name' => $author->display_name,
			'url' => get_author_posts_url( $author_id )
		];
	}

	/**
	 * Get organization schema from settings
	 *
	 * @return array Organization schema
	 */
	protected static function get_organization_schema(): array {
		$settings = get_option( 'fp_digital_marketing_schema_settings', [] );
		$site_info = self::get_site_info();

		$organization = [
			'@type' => 'Organization',
			'name' => $settings['organization_name'] ?? $site_info['name'],
			'url' => $settings['organization_url'] ?? $site_info['url']
		];

		if ( ! empty( $settings['organization_description'] ) ) {
			$organization['description'] = $settings['organization_description'];
		}

		if ( ! empty( $settings['organization_logo'] ) ) {
			$organization['logo'] = [
				'@type' => 'ImageObject',
				'url' => $settings['organization_logo']
			];
		}

		return $organization;
	}

	/**
	 * Format date for schema output
	 *
	 * @param string $date Date string
	 * @return string ISO 8601 formatted date
	 */
	protected static function format_date( string $date ): string {
		return date( 'c', strtotime( $date ) );
	}

	/**
	 * Get permalink for a post
	 *
	 * @param int|\WP_Post $post Post ID or object
	 * @return string Post permalink
	 */
	protected static function get_permalink( $post ): string {
		return get_permalink( $post ) ?: '';
	}
}