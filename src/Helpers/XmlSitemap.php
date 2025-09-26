<?php
/**
 * XML Sitemap Generator
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Helpers\PerformanceCache;
use FP\DigitalMarketing\Helpers\SeoMetadata;

/**
 * XML Sitemap generator class for creating modular sitemaps
 */
class XmlSitemap {

	/**
	 * Maximum URLs per sitemap file
	 */
	private const MAX_URLS_PER_SITEMAP = 50000;

	/**
	 * Maximum file size in bytes (50MB)
	 */
	private const MAX_FILE_SIZE = 52428800;

	/**
	 * Cache group for sitemap data
	 */
	private const CACHE_GROUP = 'xml_sitemap';

	/**
	 * Cache TTL for sitemaps (12 hours)
	 */
	private const CACHE_TTL = 43200;

	/**
	 * Settings option name
	 */
	private const SETTINGS_OPTION = 'fp_digital_marketing_sitemap_settings';

	/**
	 * Initialize sitemap functionality
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_sitemap_request' ] );
		add_action( 'save_post', [ __CLASS__, 'invalidate_sitemap_cache' ] );
		add_action( 'delete_post', [ __CLASS__, 'invalidate_sitemap_cache' ] );
		add_action( 'wp_update_nav_menu', [ __CLASS__, 'invalidate_sitemap_cache' ] );
	}

	/**
	 * Add rewrite rules for sitemap URLs
	 *
	 * @return void
	 */
	public static function add_rewrite_rules(): void {
		// Sitemap index
		add_rewrite_rule(
			'^sitemap\.xml$',
			'index.php?fp_sitemap=index',
			'top'
		);

		// Individual sitemaps
		add_rewrite_rule(
			'^sitemap-([a-z_]+)-([0-9]+)\.xml$',
			'index.php?fp_sitemap=$matches[1]&fp_sitemap_page=$matches[2]',
			'top'
		);

		// Single sitemap (no pagination)
		add_rewrite_rule(
			'^sitemap-([a-z_]+)\.xml$',
			'index.php?fp_sitemap=$matches[1]&fp_sitemap_page=1',
			'top'
		);

		// Add query vars
		add_filter(
			'query_vars',
			function ( $vars ) {
				$vars[] = 'fp_sitemap';
				$vars[] = 'fp_sitemap_page';
				return $vars;
			}
		);
	}

	/**
	 * Handle sitemap requests
	 *
	 * @return void
	 */
	public static function handle_sitemap_request(): void {
		$sitemap_type = get_query_var( 'fp_sitemap' );
		$sitemap_page = (int) get_query_var( 'fp_sitemap_page', 1 );

		if ( empty( $sitemap_type ) ) {
			return;
		}

		// Set proper headers
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );

		if ( $sitemap_type === 'index' ) {
			echo self::generate_sitemap_index();
		} else {
			echo self::generate_sitemap( $sitemap_type, $sitemap_page );
		}

		exit;
	}

	/**
	 * Generate sitemap index
	 *
	 * @return string XML content for sitemap index
	 */
	public static function generate_sitemap_index(): string {
		$cache_key      = 'sitemap_index';
		$cached_content = PerformanceCache::get_cached(
			$cache_key,
			self::CACHE_GROUP,
			function () {
				return self::build_sitemap_index();
			},
			self::CACHE_TTL
		);

		return $cached_content ?: self::build_sitemap_index();
	}

	/**
	 * Generate individual sitemap
	 *
	 * @param string $type Sitemap type (posts, pages, etc.)
	 * @param int    $page Page number for pagination
	 * @return string XML content for sitemap
	 */
	public static function generate_sitemap( string $type, int $page = 1 ): string {
		$cache_key      = "sitemap_{$type}_page_{$page}";
		$cached_content = PerformanceCache::get_cached(
			$cache_key,
			self::CACHE_GROUP,
			function () use ( $type, $page ) {
				return self::build_sitemap( $type, $page );
			},
			self::CACHE_TTL
		);

		return $cached_content ?: self::build_sitemap( $type, $page );
	}

	/**
	 * Build sitemap index XML
	 *
	 * @return string XML content
	 */
	private static function build_sitemap_index(): string {
		$settings = self::get_settings();
		$sitemaps = [];

		// Get enabled post types
		$enabled_post_types = $settings['enabled_post_types'] ?? [ 'post', 'page' ];

		foreach ( $enabled_post_types as $post_type ) {
			if ( ! self::is_post_type_eligible( $post_type ) ) {
				continue;
			}

			$url_count    = self::get_post_type_url_count( $post_type );
			$pages_needed = max( 1, ceil( $url_count / self::MAX_URLS_PER_SITEMAP ) );

			for ( $page = 1; $page <= $pages_needed; $page++ ) {
				$sitemap_url = $pages_needed > 1
					? home_url( "/sitemap-{$post_type}-{$page}.xml" )
					: home_url( "/sitemap-{$post_type}.xml" );

				$sitemaps[] = [
					'loc'     => $sitemap_url,
					'lastmod' => self::get_post_type_lastmod( $post_type ),
				];
			}
		}

		return self::build_xml_content( 'sitemapindex', $sitemaps );
	}

	/**
	 * Build individual sitemap XML
	 *
	 * @param string $type Sitemap type
	 * @param int    $page Page number
	 * @return string XML content
	 */
	private static function build_sitemap( string $type, int $page ): string {
		if ( ! self::is_post_type_eligible( $type ) ) {
			return self::build_empty_sitemap();
		}

		$settings           = self::get_settings();
		$enabled_post_types = $settings['enabled_post_types'] ?? [ 'post', 'page' ];

		if ( ! in_array( $type, $enabled_post_types, true ) ) {
			return self::build_empty_sitemap();
		}

		$urls = self::get_sitemap_urls( $type, $page );
		return self::build_xml_content( 'urlset', $urls );
	}

	/**
	 * Get URLs for a sitemap
	 *
	 * @param string $post_type Post type
	 * @param int    $page Page number
	 * @return array Array of URL data
	 */
	private static function get_sitemap_urls( string $post_type, int $page ): array {
		$offset = ( $page - 1 ) * self::MAX_URLS_PER_SITEMAP;

		$posts = get_posts(
			[
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'numberposts' => self::MAX_URLS_PER_SITEMAP,
				'offset'      => $offset,
				'orderby'     => 'modified',
				'order'       => 'DESC',
				'meta_query'  => [
					'relation' => 'OR',
					[
						'key'     => SeoMetadata::META_ROBOTS,
						'value'   => 'noindex',
						'compare' => 'NOT LIKE',
					],
					[
						'key'     => SeoMetadata::META_ROBOTS,
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		$urls = [];
		foreach ( $posts as $post ) {
			// Additional check for noindex
			$robots = SeoMetadata::get_robots( $post );
			if ( strpos( $robots, 'noindex' ) !== false ) {
				continue;
			}

			$permalink = get_permalink( $post );
			if ( ! $permalink ) {
				continue;
			}

			$urls[] = [
				'loc'        => $permalink,
				'lastmod'    => get_post_modified_time( 'c', true, $post ),
				'changefreq' => self::get_change_frequency( $post_type ),
				'priority'   => self::get_priority( $post_type, $post ),
			];
		}

		return $urls;
	}

	/**
	 * Build XML content
	 *
	 * @param string $root_element Root element name (urlset or sitemapindex)
	 * @param array  $items Array of items to include
	 * @return string XML content
	 */
	private static function build_xml_content( string $root_element, array $items ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

		if ( $root_element === 'urlset' ) {
			$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

			foreach ( $items as $url ) {
				$xml .= "\t<url>\n";
				$xml .= "\t\t<loc>" . esc_url( $url['loc'] ) . "</loc>\n";

				if ( isset( $url['lastmod'] ) ) {
					$xml .= "\t\t<lastmod>" . esc_xml( $url['lastmod'] ) . "</lastmod>\n";
				}

				if ( isset( $url['changefreq'] ) ) {
					$xml .= "\t\t<changefreq>" . esc_xml( $url['changefreq'] ) . "</changefreq>\n";
				}

				if ( isset( $url['priority'] ) ) {
					$xml .= "\t\t<priority>" . esc_xml( $url['priority'] ) . "</priority>\n";
				}

				$xml .= "\t</url>\n";
			}
		} else {
			$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

			foreach ( $items as $sitemap ) {
				$xml .= "\t<sitemap>\n";
				$xml .= "\t\t<loc>" . esc_url( $sitemap['loc'] ) . "</loc>\n";

				if ( isset( $sitemap['lastmod'] ) ) {
					$xml .= "\t\t<lastmod>" . esc_xml( $sitemap['lastmod'] ) . "</lastmod>\n";
				}

				$xml .= "\t</sitemap>\n";
			}
		}

		$xml .= "</{$root_element}>\n";

		return $xml;
	}

	/**
	 * Build empty sitemap
	 *
	 * @return string Empty sitemap XML
	 */
	private static function build_empty_sitemap(): string {
		return self::build_xml_content( 'urlset', [] );
	}

	/**
	 * Check if post type is eligible for sitemap
	 *
	 * @param string $post_type Post type name
	 * @return bool True if eligible
	 */
	private static function is_post_type_eligible( string $post_type ): bool {
		$post_type_object = get_post_type_object( $post_type );
		return $post_type_object && $post_type_object->public;
	}

	/**
	 * Get URL count for post type
	 *
	 * @param string $post_type Post type name
	 * @return int URL count
	 */
	private static function get_post_type_url_count( string $post_type ): int {
		$count = wp_count_posts( $post_type );
		return $count->publish ?? 0;
	}

	/**
	 * Get last modified date for post type
	 *
	 * @param string $post_type Post type name
	 * @return string ISO 8601 date
	 */
	private static function get_post_type_lastmod( string $post_type ): string {
		$posts = get_posts(
			[
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'numberposts' => 1,
				'orderby'     => 'modified',
				'order'       => 'DESC',
			]
		);

		if ( empty( $posts ) ) {
			return gmdate( 'c' );
		}

		return get_post_modified_time( 'c', true, $posts[0] );
	}

	/**
	 * Get change frequency for post type
	 *
	 * @param string $post_type Post type name
	 * @return string Change frequency
	 */
	private static function get_change_frequency( string $post_type ): string {
		$frequencies = [
			'post'    => 'weekly',
			'page'    => 'monthly',
			'product' => 'weekly',
		];

		return $frequencies[ $post_type ] ?? 'monthly';
	}

	/**
	 * Get priority for URL
	 *
	 * @param string                $post_type Post type name
	 * @param \WP_Post|object|array $post Post object
	 * @return string Priority value
	 */
	private static function get_priority( string $post_type, $post ): string {
		// Homepage gets highest priority
		if ( (int) $post->ID === (int) get_option( 'page_on_front' ) ) {
			return '1.0';
		}

		// Default priorities by post type
		$priorities = [
			'page'    => '0.8',
			'post'    => '0.6',
			'product' => '0.7',
		];

		return $priorities[ $post_type ] ?? '0.5';
	}

	/**
	 * Invalidate sitemap cache
	 *
	 * @param int $post_id Post ID (optional)
	 * @return void
	 */
	public static function invalidate_sitemap_cache( int $post_id = 0 ): void {
		PerformanceCache::invalidate_group( self::CACHE_GROUP );

		// Ping search engines if enabled
		$settings = self::get_settings();
		if ( $settings['ping_search_engines'] ?? true ) {
			self::ping_search_engines();
		}
	}

	/**
	 * Ping search engines about sitemap updates
	 *
	 * @return void
	 */
	public static function ping_search_engines(): void {
		$sitemap_url = home_url( 'sitemap.xml' );

		$ping_urls = [
			'google' => 'https://www.google.com/ping?sitemap=' . urlencode( $sitemap_url ),
			'bing'   => 'https://www.bing.com/ping?sitemap=' . urlencode( $sitemap_url ),
		];

		foreach ( $ping_urls as $engine => $ping_url ) {
			wp_remote_get(
				$ping_url,
				[
					'timeout'  => 10,
					'blocking' => false, // Non-blocking request
				]
			);
		}
	}

	/**
	 * Get sitemap settings
	 *
	 * @return array Settings array
	 */
	private static function get_settings(): array {
		$defaults = [
			'enabled_post_types'  => [ 'post', 'page' ],
			'ping_search_engines' => true,
			'exclude_noindex'     => true,
		];

		$settings = get_option( self::SETTINGS_OPTION, [] );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update sitemap settings
	 *
	 * @param array $settings New settings
	 * @return bool Update result
	 */
	public static function update_settings( array $settings ): bool {
		$current_settings = self::get_settings();
		$new_settings     = wp_parse_args( $settings, $current_settings );

		$result = update_option( self::SETTINGS_OPTION, $new_settings );

		// Invalidate cache when settings change
		if ( $result ) {
			self::invalidate_sitemap_cache();
		}

		return $result;
	}

	/**
	 * Get available post types for sitemap
	 *
	 * @return array Array of post type objects
	 */
	public static function get_available_post_types(): array {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		// Remove attachment post type
		unset( $post_types['attachment'] );

		return $post_types;
	}

	/**
	 * Add sitemap reference to robots.txt
	 *
	 * @return void
	 */
	public static function init_robots_txt(): void {
		add_filter( 'robots_txt', [ __CLASS__, 'add_sitemap_to_robots' ], 10, 2 );
	}

	/**
	 * Add sitemap reference to robots.txt
	 *
	 * @param string $output Robots.txt output
	 * @param bool   $public Whether the site is public
	 * @return string Modified output
	 */
	public static function add_sitemap_to_robots( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}

		$sitemap_url = home_url( '/sitemap.xml' );
		$output     .= "\nSitemap: {$sitemap_url}\n";

		return $output;
	}
}
