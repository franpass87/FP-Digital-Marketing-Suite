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
	 * Base URL for short links
	 */
	private const SHORT_BASE_URL = 'https://short.ly/';

	/**
	 * Generate a short URL (mock implementation)
	 *
	 * @param string $long_url Original URL to shorten.
	 * @return string|null Short URL or null on failure.
	 */
	public static function shorten_url( string $long_url ): ?string {
		if ( empty( $long_url ) || ! filter_var( $long_url, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		// In a real implementation, this would call an external service like bit.ly, tinyurl, etc.
		// For now, we'll create a mock short URL
		$hash = substr( md5( $long_url ), 0, 8 );
		
		/**
		 * Filter short URL base
		 *
		 * @param string $base_url Base URL for short links.
		 */
		$base_url = apply_filters( 'fp_utm_short_url_base', self::SHORT_BASE_URL );

		return $base_url . $hash;
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

		// In a real implementation, this would fetch data from the shortening service
		return [
			'clicks'        => rand( 0, 1000 ),
			'unique_clicks' => rand( 0, 500 ),
			'referrers'     => [
				'direct'   => rand( 0, 300 ),
				'facebook' => rand( 0, 200 ),
				'google'   => rand( 0, 150 ),
				'twitter'  => rand( 0, 100 ),
			],
			'countries'     => [
				'IT' => rand( 0, 400 ),
				'US' => rand( 0, 300 ),
				'DE' => rand( 0, 200 ),
				'FR' => rand( 0, 150 ),
			],
			'devices'       => [
				'desktop' => rand( 0, 400 ),
				'mobile'  => rand( 0, 500 ),
				'tablet'  => rand( 0, 100 ),
			],
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

		// In a real implementation, this would make a HEAD request to follow redirects
		// For now, we'll return a mock expanded URL
		return 'https://example.com/expanded-url-from-' . basename( $short_url );
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
		return apply_filters( 'fp_utm_shortening_enabled', false );
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
			'service' => 'mock',
			'api_key' => '',
			'domain'  => '',
		] );
	}
}