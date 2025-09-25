<?php
/**
 * URL Shortener Helper Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * URL Shortener class for managing short URLs
 */
class URLShortener {

        /**
         * Option name used to persist the URL mappings and analytics data.
         */
        private const OPTION_MAPPINGS = 'fp_dms_short_url_mappings';

        /**
         * Query variable used for public redirection handling.
         */
        private const QUERY_VAR = 'fp_dms_short';

        /**
         * Default path used to build short URLs when no filter overrides it.
         */
        private const DEFAULT_PATH = 'fpdms';

        /**
         * Initialise rewrite rules and query vars for front-end redirects.
         *
         * @return void
         */
        public static function bootstrap(): void {
                add_action( 'init', [ __CLASS__, 'register_rewrite_rule' ] );
                add_filter( 'query_vars', [ __CLASS__, 'register_query_var' ] );
                add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect' ] );
        }

        /**
         * Generate a short URL using an internal slug registry.
         *
         * @param string $long_url Original URL to shorten.
         * @return string|null Short URL or null on failure.
         */
        public static function shorten_url( string $long_url ): ?string {
                $long_url = esc_url_raw( $long_url );

                if ( '' === $long_url || ! filter_var( $long_url, FILTER_VALIDATE_URL ) ) {
                        return null;
                }

                $mappings = self::get_mappings();

                $slug = self::generate_unique_slug( $mappings );

                $mappings[ $slug ] = [
                        'target'        => $long_url,
                        'created_at'    => time(),
                        'hits'          => 0,
                        'last_accessed' => null,
                        'referrers'     => [],
                ];

                self::persist_mappings( $mappings );

                return self::build_short_url( $slug );
        }

	/**
	 * Validate short URL
	 *
	 * @param string $short_url Short URL to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_short_url( string $short_url ): bool {
		if ( empty( $short_url ) ) {
			return false;
		}

		// Check if it's a valid URL
		if ( ! filter_var( $short_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Check if it's reasonably short (less than 50 characters)
		if ( strlen( $short_url ) > 50 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get analytics for short URL (mock implementation)
	 *
	 * @param string $short_url Short URL to get analytics for.
	 * @return array Analytics data.
	 */
        public static function get_short_url_analytics( string $short_url ): array {
                if ( ! self::is_valid_short_url( $short_url ) ) {
                        return [];
                }

                $slug = self::extract_slug_from_url( $short_url );
                if ( '' === $slug ) {
                        return [];
                }

                $mapping = self::get_mapping( $slug );

                if ( empty( $mapping ) ) {
                        return [];
                }

                return [
                        'clicks'        => (int) ( $mapping['hits'] ?? 0 ),
                        'unique_clicks' => (int) ( $mapping['unique_hits'] ?? $mapping['hits'] ?? 0 ),
                        'referrers'     => (array) ( $mapping['referrers'] ?? [] ),
                        'countries'     => (array) ( $mapping['countries'] ?? [] ),
                        'devices'       => (array) ( $mapping['devices'] ?? [] ),
                        'last_accessed' => isset( $mapping['last_accessed'] ) ? (int) $mapping['last_accessed'] : null,
                ];
        }

	/**
	 * Get popular short URL services
	 *
	 * @return array List of popular shortening services.
	 */
	public static function get_shortening_services(): array {
		return [
			'bitly'    => [
				'name'     => 'bit.ly',
				'base_url' => 'https://bit.ly/',
				'api_url'  => 'https://api-ssl.bitly.com/v4/shorten',
			],
			'tinyurl'  => [
				'name'     => 'TinyURL',
				'base_url' => 'https://tinyurl.com/',
				'api_url'  => 'https://tinyurl.com/api-create.php',
			],
			'shortio'  => [
				'name'     => 'Short.io',
				'base_url' => 'https://short.io/',
				'api_url'  => 'https://api.short.io/links',
			],
			'rebrandly' => [
				'name'     => 'Rebrandly',
				'base_url' => 'https://rebrand.ly/',
				'api_url'  => 'https://api.rebrandly.com/v1/links',
			],
		];
	}

	/**
	 * Extract original URL from short URL (mock implementation)
	 *
	 * @param string $short_url Short URL to expand.
	 * @return string|null Original URL or null if not found.
	 */
        public static function expand_url( string $short_url ): ?string {
                if ( ! self::is_valid_short_url( $short_url ) ) {
                        return null;
                }

                $slug = self::extract_slug_from_url( $short_url );

                if ( '' === $slug ) {
                        return null;
                }

                $mapping = self::get_mapping( $slug );

                if ( empty( $mapping ) ) {
                        return null;
                }

                return is_string( $mapping['target'] ?? null ) ? $mapping['target'] : null;
	}

	/**
	 * Generate QR code for URL
	 *
	 * @param string $url URL to generate QR code for.
	 * @param int    $size QR code size in pixels.
	 * @return string QR code image URL.
	 */
	public static function generate_qr_code( string $url, int $size = 200 ): string {
		if ( empty( $url ) ) {
			return '';
		}

		// Use Google Chart API for QR code generation
		$base_url = 'https://chart.googleapis.com/chart';
		$params = [
			'chs' => $size . 'x' . $size,
			'cht' => 'qr',
			'chl' => urlencode( $url ),
		];

		return $base_url . '?' . http_build_query( $params );
	}

	/**
	 * Check if URL shortening is enabled
	 *
	 * @return bool True if enabled, false otherwise.
	 */
        public static function is_shortening_enabled(): bool {
                /**
                 * Filter to enable/disable URL shortening
                 *
                 * @param bool $enabled Whether URL shortening is enabled.
                 */
                $enabled = apply_filters( 'fp_utm_shortening_enabled', true );

                return (bool) $enabled;
        }

	/**
	 * Get shortening service configuration
	 *
	 * @return array Service configuration.
	 */
        public static function get_shortening_config(): array {
                /**
                 * Filter shortening service configuration
                 *
                 * @param array $config Service configuration.
                 */
                return apply_filters( 'fp_utm_shortening_config', [
                        'service' => 'internal',
                        'api_key' => '',
                        'domain'  => wp_parse_url( self::get_base_url(), PHP_URL_HOST ) ?: '',
                ] );
        }

        /**
         * Register rewrite rule for short URLs.
         *
         * @return void
         */
        public static function register_rewrite_rule(): void {
                $parser = function_exists( 'wp_parse_url' ) ? 'wp_parse_url' : 'parse_url';
                $path = trim( $parser( self::get_base_url(), PHP_URL_PATH ) ?? '', '/' );

                if ( '' === $path ) {
                        $path = self::DEFAULT_PATH;
                }

                add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
                add_rewrite_rule( $path . '/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
        }

        /**
         * Register query var for short URLs.
         *
         * @param array $vars Existing query vars.
         * @return array
         */
        public static function register_query_var( array $vars ): array {
                $vars[] = self::QUERY_VAR;
                return $vars;
        }

        /**
         * Handle template redirect for short URLs.
         *
         * @return void
         */
        public static function maybe_redirect(): void {
                if ( ! self::is_shortening_enabled() ) {
                        return;
                }

                $slug = get_query_var( self::QUERY_VAR );

                if ( empty( $slug ) || ! is_string( $slug ) ) {
                        return;
                }

                $mapping = self::get_mapping( $slug );

                if ( empty( $mapping ) || empty( $mapping['target'] ) ) {
                        return;
                }

                self::record_hit( $slug, $mapping );

                wp_safe_redirect( $mapping['target'], 301 );
                exit;
        }

        /**
         * Record a hit on a short URL for analytics purposes.
         *
         * @param string $slug    Short URL slug.
         * @param array  $mapping Stored mapping.
         * @return void
         */
        private static function record_hit( string $slug, array $mapping ): void {
                $mappings = self::get_mappings();

                if ( ! isset( $mappings[ $slug ] ) ) {
                        return;
                }

                $hit_data = $mappings[ $slug ];

                $hit_data['hits'] = isset( $hit_data['hits'] ) ? ( (int) $hit_data['hits'] + 1 ) : 1;
                $hit_data['last_accessed'] = time();

                $referer = wp_get_referer();
                if ( $referer ) {
                        $referer_host = wp_parse_url( $referer, PHP_URL_HOST ) ?: 'direct';
                        $hit_data['referrers'][ $referer_host ] = isset( $hit_data['referrers'][ $referer_host ] )
                                ? ( (int) $hit_data['referrers'][ $referer_host ] + 1 )
                                : 1;
                }

                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                if ( '' !== $user_agent ) {
                        $hit_data['devices'] = $hit_data['devices'] ?? [];
                        $device_type = self::detect_device_type( $user_agent );
                        $hit_data['devices'][ $device_type ] = isset( $hit_data['devices'][ $device_type ] )
                                ? ( (int) $hit_data['devices'][ $device_type ] + 1 )
                                : 1;
                }

                $mappings[ $slug ] = $hit_data;
                self::persist_mappings( $mappings );
        }

        /**
         * Determine device type from user agent string.
         *
         * @param string $user_agent User agent string.
         * @return string Device type label.
         */
        private static function detect_device_type( string $user_agent ): string {
                $ua = strtolower( $user_agent );

                if ( strpos( $ua, 'mobile' ) !== false || strpos( $ua, 'android' ) !== false || strpos( $ua, 'iphone' ) !== false ) {
                        return 'mobile';
                }

                if ( strpos( $ua, 'tablet' ) !== false || strpos( $ua, 'ipad' ) !== false ) {
                        return 'tablet';
                }

                return 'desktop';
        }

        /**
         * Retrieve the base URL for short links.
         *
         * @return string
         */
        private static function get_base_url(): string {
                $default_base = trailingslashit( home_url( '/' . self::DEFAULT_PATH ) );

                /**
                 * Filter short URL base.
                 *
                 * @param string $base_url Base URL for short links.
                 */
                $base_url = apply_filters( 'fp_utm_short_url_base', $default_base );

                return esc_url_raw( trailingslashit( $base_url ) );
        }

        /**
         * Build the final short URL for a given slug.
         *
         * @param string $slug Short URL slug.
         * @return string
         */
        private static function build_short_url( string $slug ): string {
                return self::get_base_url() . rawurlencode( $slug );
        }

        /**
         * Extract slug from a short URL.
         *
         * @param string $short_url Short URL.
         * @return string
         */
        private static function extract_slug_from_url( string $short_url ): string {
                $parser = function_exists( 'wp_parse_url' ) ? 'wp_parse_url' : 'parse_url';
                $parsed = $parser( $short_url );

                if ( empty( $parsed['path'] ) ) {
                        return '';
                }

                $path_segments = explode( '/', trim( $parsed['path'], '/' ) );

                return $path_segments ? end( $path_segments ) : '';
        }

        /**
         * Retrieve all stored mappings.
         *
         * @return array<string, array<string, mixed>>
         */
        private static function get_mappings(): array {
                $mappings = get_option( self::OPTION_MAPPINGS, [] );

                return is_array( $mappings ) ? $mappings : [];
        }

        /**
         * Retrieve a single mapping by slug.
         *
         * @param string $slug Short URL slug.
         * @return array<string, mixed>
         */
        private static function get_mapping( string $slug ): array {
                $mappings = self::get_mappings();

                if ( isset( $mappings[ $slug ] ) && is_array( $mappings[ $slug ] ) ) {
                        return $mappings[ $slug ];
                }

                // Backwards compatibility with the legacy per-option storage.
                $legacy = get_option( 'fp_utm_short_url_' . $slug );

                if ( is_string( $legacy ) && '' !== $legacy ) {
                        return [ 'target' => esc_url_raw( $legacy ) ];
                }

                return [];
        }

        /**
         * Persist mappings to the database.
         *
         * @param array<string, array<string, mixed>> $mappings Mappings to store.
         * @return void
         */
        private static function persist_mappings( array $mappings ): void {
                update_option( self::OPTION_MAPPINGS, $mappings, false );
        }

        /**
         * Generate a unique slug for a new short URL.
         *
         * @param array<string, array<string, mixed>> $existing Existing mappings.
         * @return string
         */
        private static function generate_unique_slug( array $existing ): string {
                $attempts = 0;

                do {
                        $slug = self::generate_slug();
                        $attempts++;
                } while ( isset( $existing[ $slug ] ) && $attempts < 5 );

                if ( isset( $existing[ $slug ] ) ) {
                        $suffix = function_exists( 'wp_generate_password' )
                                ? wp_generate_password( 2, false, false )
                                : substr( str_replace( [ '+', '/', '=' ], '', base64_encode( random_bytes( 2 ) ) ), 0, 2 );
                        $slug  .= strtolower( $suffix );
                }

                return $slug;
        }

        /**
         * Generate a random slug using WordPress helpers when available.
         *
         * @return string
         */
        private static function generate_slug(): string {
                if ( function_exists( 'wp_generate_password' ) ) {
                        return strtolower( wp_generate_password( 8, false, false ) );
                }

                try {
                        $bytes = random_bytes( 6 );
                } catch ( \Throwable $e ) {
                        $bytes = uniqid( '', true );
                }

                return substr( str_replace( [ '+', '/', '=' ], '', base64_encode( (string) $bytes ) ), 0, 8 );
        }
}
