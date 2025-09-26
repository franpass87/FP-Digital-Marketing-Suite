<?php
/**
 * Schema.org Structured Data Generator
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Helpers\Schema\WebSiteSchema;
use FP\DigitalMarketing\Helpers\Schema\OrganizationSchema;
use FP\DigitalMarketing\Helpers\Schema\BreadcrumbListSchema;
use FP\DigitalMarketing\Helpers\Schema\ArticleSchema;
use FP\DigitalMarketing\Helpers\Schema\FAQSchema;

/**
 * Schema.org structured data generator class
 */
class SchemaGenerator {

	/**
	 * Schema types configuration
	 */
	private const SCHEMA_TYPES = [
		'website'      => [
			'class'       => WebSiteSchema::class,
			'name'        => 'WebSite + SearchAction',
			'description' => 'Markup per il sito web e funzionalità di ricerca',
		],
		'organization' => [
			'class'       => OrganizationSchema::class,
			'name'        => 'Organization',
			'description' => 'Informazioni sull\'organizzazione/azienda',
		],
		'breadcrumb'   => [
			'class'       => BreadcrumbListSchema::class,
			'name'        => 'BreadcrumbList',
			'description' => 'Navigazione breadcrumb per le pagine',
		],
		'article'      => [
			'class'       => ArticleSchema::class,
			'name'        => 'Article/BlogPosting',
			'description' => 'Markup per articoli e post del blog',
		],
		'faq'          => [
			'class'       => FAQSchema::class,
			'name'        => 'FAQ',
			'description' => 'Sezioni domande frequenti',
		],
	];

	/**
	 * Initialize schema output
	 *
	 * @return void
	 */
	public static function init(): void {
		// Hook into wp_head to output structured data
		add_action( 'wp_head', [ self::class, 'output_structured_data' ], 5 );

		// Allow developers to register custom schema types
		add_filter( 'fp_dms_schema_types', [ self::class, 'get_schema_types' ] );
	}

	/**
	 * Output structured data for the current page
	 *
	 * @return void
	 */
	public static function output_structured_data(): void {
		$schemas = self::generate_schemas();

		if ( empty( $schemas ) ) {
			return;
		}

		echo "\n<!-- FP Digital Marketing Suite - Structured Data -->\n";
		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( $schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		echo "\n</script>\n";
		echo "<!-- /FP Digital Marketing Suite - Structured Data -->\n\n";
	}

	/**
	 * Generate all enabled schemas for the current page
	 *
	 * @return array Array of schema objects
	 */
	public static function generate_schemas(): array {
		$enabled_types = self::get_enabled_schema_types();
		$schemas       = [];

		foreach ( $enabled_types as $type ) {
			$schema_data = self::generate_schema( $type );
			if ( $schema_data ) {
				// Support for multiple schemas of the same type
				if ( is_array( $schema_data ) && isset( $schema_data[0] ) ) {
					$schemas = array_merge( $schemas, $schema_data );
				} else {
					$schemas[] = $schema_data;
				}
			}
		}

		// Allow developers to filter the final schemas
		$schemas = apply_filters( 'fp_dms_generated_schemas', $schemas );

		return $schemas;
	}

	/**
	 * Generate schema for a specific type
	 *
	 * @param string $type Schema type identifier
	 * @return array|null Schema data or null if not available
	 */
	public static function generate_schema( string $type ): ?array {
		$schema_types = self::get_schema_types();

		if ( ! isset( $schema_types[ $type ] ) ) {
			return null;
		}

		$class = $schema_types[ $type ]['class'];

		if ( ! class_exists( $class ) ) {
			return null;
		}

		// Check if the schema type is applicable to the current page
		if ( method_exists( $class, 'is_applicable' ) && ! $class::is_applicable() ) {
			return null;
		}

		return $class::generate();
	}

	/**
	 * Get all available schema types
	 *
	 * @return array Schema types configuration
	 */
	public static function get_schema_types(): array {
		return apply_filters( 'fp_dms_schema_types', self::SCHEMA_TYPES );
	}

	/**
	 * Get enabled schema types from settings
	 *
	 * @return array Enabled schema type identifiers
	 */
	public static function get_enabled_schema_types(): array {
				$settings = get_option( 'fp_digital_marketing_schema_settings', [] );

		if ( ! is_array( $settings ) ) {
				$settings = [];
		}

		if ( array_key_exists( 'enabled_types', $settings ) ) {
				$requested     = is_array( $settings['enabled_types'] ) ? $settings['enabled_types'] : [];
				$requested     = array_map( 'strval', $requested );
				$valid_types   = array_keys( self::SCHEMA_TYPES );
				$enabled_types = array_values( array_intersect( $valid_types, $requested ) );
		} else {
				$enabled_types = array_keys( self::SCHEMA_TYPES );
		}

				return apply_filters( 'fp_dms_enabled_schema_types', $enabled_types );
	}

	/**
	 * Check if a schema type is enabled
	 *
	 * @param string $type Schema type identifier
	 * @return bool True if enabled
	 */
	public static function is_schema_type_enabled( string $type ): bool {
		return in_array( $type, self::get_enabled_schema_types(), true );
	}

	/**
	 * Sanitize and escape schema data
	 *
	 * @param array $data Raw schema data
	 * @return array Sanitized schema data
	 */
	public static function sanitize_schema_data( array $data ): array {
		$sanitized = [];

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
						$sanitized[ $key ] = self::sanitize_schema_data( $value );
						continue;
			}

			if ( ! is_string( $value ) ) {
							$sanitized[ $key ] = $value;
							continue;
			}

							$sanitized[ $key ] = self::sanitize_schema_string( $value );
		}

				return $sanitized;
	}

		/**
		 * Sanitize a string value for schema output.
		 *
		 * @param string $value Raw string value.
		 * @return string Sanitized string value.
		 */
	private static function sanitize_schema_string( string $value ): string {
			// Remove script/style blocks entirely to prevent executable payloads.
			$value = (string) preg_replace( '#<\s*(script|style)[^>]*>.*?<\/\s*\1>#is', '', $value );

			// Strip remaining HTML tags while preserving entities and quotes.
			$value = wp_strip_all_tags( $value );

			// Collapse consecutive whitespace characters into a single space.
			$value = (string) preg_replace( '/\s+/u', ' ', $value );

			return trim( $value );
	}

	/**
	 * Validate schema data structure
	 *
	 * @param array $schema Schema data to validate
	 * @return bool True if valid
	 */
	public static function validate_schema( array $schema ): bool {
		// Basic validation - must have @context and @type
		if ( ! isset( $schema['@context'] ) || ! isset( $schema['@type'] ) ) {
			return false;
		}

		// Validate @context is Schema.org
		if ( $schema['@context'] !== 'https://schema.org' ) {
			return false;
		}

		// Allow developers to add custom validation
		return apply_filters( 'fp_dms_validate_schema', true, $schema );
	}

	/**
	 * Get default settings for schema configuration
	 *
	 * @return array Default settings
	 */
	public static function get_default_settings(): array {
		return [
			'enabled_types'            => array_keys( self::SCHEMA_TYPES ),
			'organization_name'        => get_bloginfo( 'name' ),
			'organization_url'         => home_url(),
			'organization_logo'        => '',
			'organization_description' => get_bloginfo( 'description' ),
			'enable_breadcrumbs'       => true,
			'faq_post_types'           => [ 'post', 'page' ],
		];
	}
}
