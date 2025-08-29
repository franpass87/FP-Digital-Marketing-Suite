<?php
/**
 * Organization Schema Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers\Schema;

/**
 * Organization schema generator
 */
class OrganizationSchema extends BaseSchema {

	/**
	 * Generate Organization schema data
	 *
	 * @return array|null Organization schema or null
	 */
	public static function generate(): ?array {
		$schema = self::create_base_schema( 'Organization' );
		$organization_data = self::get_organization_schema();

		// Merge organization data
		$schema = array_merge( $schema, $organization_data );

		// Add social media profiles if available
		$social_profiles = self::get_social_profiles();
		if ( ! empty( $social_profiles ) ) {
			$schema['sameAs'] = $social_profiles;
		}

		// Add contact information if available
		$contact_info = self::get_contact_info();
		if ( ! empty( $contact_info ) ) {
			$schema = array_merge( $schema, $contact_info );
		}

		return apply_filters( 'fp_dms_organization_schema', $schema );
	}

	/**
	 * Check if Organization schema is applicable
	 *
	 * @return bool True on home/front page
	 */
	public static function is_applicable(): bool {
		// Organization schema is typically shown on the home page
		return is_home() || is_front_page();
	}

	/**
	 * Get social media profiles
	 *
	 * @return array Social media URLs
	 */
	private static function get_social_profiles(): array {
		$settings = get_option( 'fp_digital_marketing_schema_settings', [] );
		$profiles = [];

		$social_fields = [
			'facebook_url',
			'twitter_url',
			'instagram_url',
			'linkedin_url',
			'youtube_url'
		];

		foreach ( $social_fields as $field ) {
			if ( ! empty( $settings[ $field ] ) ) {
				$profiles[] = $settings[ $field ];
			}
		}

		return apply_filters( 'fp_dms_organization_social_profiles', $profiles );
	}

	/**
	 * Get contact information
	 *
	 * @return array Contact information schema
	 */
	private static function get_contact_info(): array {
		$settings = get_option( 'fp_digital_marketing_schema_settings', [] );
		$contact_info = [];

		// Add telephone if available
		if ( ! empty( $settings['organization_telephone'] ) ) {
			$contact_info['telephone'] = $settings['organization_telephone'];
		}

		// Add email if available
		if ( ! empty( $settings['organization_email'] ) ) {
			$contact_info['email'] = $settings['organization_email'];
		}

		// Add address if available
		$address = self::get_address_schema();
		if ( ! empty( $address ) ) {
			$contact_info['address'] = $address;
		}

		return $contact_info;
	}

	/**
	 * Get address schema
	 *
	 * @return array|null Address schema or null
	 */
	private static function get_address_schema(): ?array {
		$settings = get_option( 'fp_digital_marketing_schema_settings', [] );

		$address_fields = [
			'street_address' => 'streetAddress',
			'address_locality' => 'addressLocality',
			'address_region' => 'addressRegion',
			'postal_code' => 'postalCode',
			'address_country' => 'addressCountry'
		];

		$address_data = [];
		foreach ( $address_fields as $setting_key => $schema_key ) {
			if ( ! empty( $settings[ $setting_key ] ) ) {
				$address_data[ $schema_key ] = $settings[ $setting_key ];
			}
		}

		if ( empty( $address_data ) ) {
			return null;
		}

		return array_merge(
			[ '@type' => 'PostalAddress' ],
			$address_data
		);
	}
}