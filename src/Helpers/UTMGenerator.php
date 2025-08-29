<?php
/**
 * UTM Generator Helper Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * UTM Generator class for creating and managing UTM parameters
 */
class UTMGenerator {

	/**
	 * UTM parameter names
	 */
	private const UTM_PARAMS = [
		'source'   => 'utm_source',
		'medium'   => 'utm_medium',
		'campaign' => 'utm_campaign',
		'term'     => 'utm_term',
		'content'  => 'utm_content',
	];

	/**
	 * Predefined UTM presets
	 *
	 * @var array
	 */
	private static array $presets = [
		'email_newsletter' => [
			'name'   => 'Email Newsletter',
			'source' => 'newsletter',
			'medium' => 'email',
		],
		'social_facebook' => [
			'name'   => 'Facebook Social',
			'source' => 'facebook',
			'medium' => 'social',
		],
		'social_instagram' => [
			'name'   => 'Instagram Social',
			'source' => 'instagram',
			'medium' => 'social',
		],
		'social_linkedin' => [
			'name'   => 'LinkedIn Social',
			'source' => 'linkedin',
			'medium' => 'social',
		],
		'google_ads' => [
			'name'   => 'Google Ads',
			'source' => 'google',
			'medium' => 'cpc',
		],
		'facebook_ads' => [
			'name'   => 'Facebook Ads',
			'source' => 'facebook',
			'medium' => 'cpc',
		],
		'banner_display' => [
			'name'   => 'Display Banner',
			'source' => 'website',
			'medium' => 'banner',
		],
		'affiliate_partner' => [
			'name'   => 'Affiliate Partner',
			'source' => 'partner',
			'medium' => 'referral',
		],
	];

	/**
	 * Generate UTM URL from parameters
	 *
	 * @param string $base_url Base URL without UTM parameters.
	 * @param array  $utm_params UTM parameters array.
	 * @return string Final URL with UTM parameters.
	 */
	public static function generate_utm_url( string $base_url, array $utm_params ): string {
		if ( empty( $base_url ) ) {
			return '';
		}

		// Validate required parameters.
		$required = [ 'source', 'medium', 'campaign' ];
		foreach ( $required as $param ) {
			if ( empty( $utm_params[ $param ] ) ) {
				return '';
			}
		}

		// Clean and validate base URL.
		$url = self::clean_base_url( $base_url );
		if ( empty( $url ) ) {
			return '';
		}

		// Build UTM parameters.
		$query_params = [];
		foreach ( self::UTM_PARAMS as $key => $utm_param ) {
			if ( ! empty( $utm_params[ $key ] ) ) {
				$query_params[ $utm_param ] = self::sanitize_utm_value( $utm_params[ $key ] );
			}
		}

		if ( empty( $query_params ) ) {
			return $url;
		}

		// Add parameters to URL.
		$separator = strpos( $url, '?' ) !== false ? '&' : '?';
		$query_string = http_build_query( $query_params );

		return $url . $separator . $query_string;
	}

	/**
	 * Extract UTM parameters from URL
	 *
	 * @param string $url URL to extract parameters from.
	 * @return array UTM parameters array.
	 */
	public static function extract_utm_params( string $url ): array {
		$parsed_url = wp_parse_url( $url );
		
		if ( empty( $parsed_url['query'] ) ) {
			return [];
		}

		parse_str( $parsed_url['query'], $query_params );

		$utm_params = [];
		foreach ( self::UTM_PARAMS as $key => $utm_param ) {
			if ( isset( $query_params[ $utm_param ] ) ) {
				$utm_params[ $key ] = sanitize_text_field( $query_params[ $utm_param ] );
			}
		}

		return $utm_params;
	}

	/**
	 * Get available presets
	 *
	 * @return array Available presets.
	 */
	public static function get_presets(): array {
		/**
		 * Filter UTM presets
		 *
		 * @param array $presets Default presets.
		 */
		return apply_filters( 'fp_utm_presets', self::$presets );
	}

	/**
	 * Get specific preset by ID
	 *
	 * @param string $preset_id Preset identifier.
	 * @return array|null Preset data or null if not found.
	 */
	public static function get_preset( string $preset_id ): ?array {
		$presets = self::get_presets();
		return $presets[ $preset_id ] ?? null;
	}

	/**
	 * Validate UTM parameters
	 *
	 * @param array $utm_params UTM parameters to validate.
	 * @return array Validation results with 'valid' boolean and 'errors' array.
	 */
	public static function validate_utm_params( array $utm_params ): array {
		$errors = [];
		$required = [ 'source', 'medium', 'campaign' ];

		// Check required parameters.
		foreach ( $required as $param ) {
			if ( empty( $utm_params[ $param ] ) ) {
				$errors[] = sprintf( 
					/* translators: %s: parameter name */
					__( 'Il parametro %s è obbligatorio.', 'fp-digital-marketing' ), 
					$param 
				);
			}
		}

		// Validate parameter format.
		foreach ( $utm_params as $key => $value ) {
			if ( ! empty( $value ) && ! self::is_valid_utm_value( $value ) ) {
				$errors[] = sprintf( 
					/* translators: %s: parameter name */
					__( 'Il parametro %s contiene caratteri non validi.', 'fp-digital-marketing' ), 
					$key 
				);
			}
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Generate campaign name suggestion
	 *
	 * @param array $utm_params UTM parameters.
	 * @return string Suggested campaign name.
	 */
	public static function suggest_campaign_name( array $utm_params ): string {
		$parts = [];

		if ( ! empty( $utm_params['campaign'] ) ) {
			$parts[] = $utm_params['campaign'];
		}

		if ( ! empty( $utm_params['source'] ) ) {
			$parts[] = $utm_params['source'];
		}

		if ( ! empty( $utm_params['medium'] ) ) {
			$parts[] = $utm_params['medium'];
		}

		if ( empty( $parts ) ) {
			return __( 'Nuova Campagna', 'fp-digital-marketing' );
		}

		$name = implode( ' - ', $parts );
		return ucwords( str_replace( [ '_', '-' ], ' ', $name ) );
	}

	/**
	 * Clean base URL
	 *
	 * @param string $url URL to clean.
	 * @return string Cleaned URL.
	 */
	private static function clean_base_url( string $url ): string {
		// Remove existing UTM parameters.
		$parsed_url = wp_parse_url( $url );
		
		if ( empty( $parsed_url['host'] ) ) {
			return '';
		}

		$clean_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
		
		if ( ! empty( $parsed_url['port'] ) ) {
			$clean_url .= ':' . $parsed_url['port'];
		}

		if ( ! empty( $parsed_url['path'] ) ) {
			$clean_url .= $parsed_url['path'];
		}

		// Keep non-UTM query parameters.
		if ( ! empty( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $query_params );
			
			// Remove UTM parameters.
			foreach ( self::UTM_PARAMS as $utm_param ) {
				unset( $query_params[ $utm_param ] );
			}

			if ( ! empty( $query_params ) ) {
				$clean_url .= '?' . http_build_query( $query_params );
			}
		}

		if ( ! empty( $parsed_url['fragment'] ) ) {
			$clean_url .= '#' . $parsed_url['fragment'];
		}

		return $clean_url;
	}

	/**
	 * Sanitize UTM parameter value
	 *
	 * @param string $value Value to sanitize.
	 * @return string Sanitized value.
	 */
	private static function sanitize_utm_value( string $value ): string {
		// Convert to lowercase and replace spaces with underscores.
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9\-_.]/', '_', $value );
		$value = preg_replace( '/_{2,}/', '_', $value );
		
		return trim( $value, '_' );
	}

	/**
	 * Check if UTM value is valid
	 *
	 * @param string $value Value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private static function is_valid_utm_value( string $value ): bool {
		// Allow alphanumeric, hyphens, underscores, and dots.
		return preg_match( '/^[a-zA-Z0-9\-_.\s]+$/', $value ) === 1;
	}
}