<?php
/**
 * WebSite Schema Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers\Schema;

/**
 * WebSite schema generator
 */
class WebSiteSchema extends BaseSchema {

	/**
	 * Generate WebSite schema data
	 *
	 * @return array|null WebSite schema or null
	 */
	public static function generate(): ?array {
		$site_info = self::get_site_info();

		$schema = self::create_base_schema( 'WebSite', [
			'name' => $site_info['name'],
			'url' => $site_info['url']
		] );

		// Add description if available
		if ( ! empty( $site_info['description'] ) ) {
			$schema['description'] = $site_info['description'];
		}

		// Add search action if on home page
		if ( is_home() || is_front_page() ) {
			$schema['potentialAction'] = self::get_search_action();
		}

		return apply_filters( 'fp_dms_website_schema', $schema );
	}

	/**
	 * Check if WebSite schema is applicable
	 *
	 * @return bool Always true as website schema is global
	 */
	public static function is_applicable(): bool {
		// Website schema is generally applicable on all pages
		return true;
	}

	/**
	 * Get search action schema
	 *
	 * @return array Search action schema
	 */
	private static function get_search_action(): array {
		$search_url = home_url( '/' );
		
		// Try to get the search URL template
		if ( function_exists( 'get_search_link' ) ) {
			$search_url = str_replace( home_url( '/' ), '', get_search_link() );
			$search_url = home_url( $search_url );
		} else {
			$search_url = home_url( '/?s=' );
		}

		// Ensure the search URL has the search term placeholder
		if ( strpos( $search_url, '{search_term' ) === false ) {
			$search_url = rtrim( $search_url, '/' ) . '{search_term_string}';
		}

		return [
			'@type' => 'SearchAction',
			'target' => [
				'@type' => 'EntryPoint',
				'urlTemplate' => $search_url
			],
			'query-input' => 'required name=search_term_string'
		];
	}
}