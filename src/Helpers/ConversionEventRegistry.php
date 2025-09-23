<?php
/**
 * Conversion Event Registry
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Conversion Event Registry class
 * 
 * Manages and defines standard conversion event types and their mappings from external sources.
 */
class ConversionEventRegistry {

	/**
	 * Standard event types
	 */
	public const EVENT_SIGNUP = 'signup';
	public const EVENT_PURCHASE = 'purchase';
	public const EVENT_LEAD_SUBMIT = 'lead_submit';
	public const EVENT_DOWNLOAD = 'download';
	public const EVENT_SUBSCRIBE = 'subscribe';
	public const EVENT_CONTACT_FORM = 'contact_form';
	public const EVENT_PHONE_CALL = 'phone_call';
	public const EVENT_EMAIL_CLICK = 'email_click';
	public const EVENT_VIDEO_WATCH = 'video_watch';
	public const EVENT_ADD_TO_CART = 'add_to_cart';
	public const EVENT_BEGIN_CHECKOUT = 'begin_checkout';
	public const EVENT_BOOKING = 'booking';
	public const EVENT_QUOTE_REQUEST = 'quote_request';
	public const EVENT_DEMO_REQUEST = 'demo_request';
	public const EVENT_TRIAL_START = 'trial_start';

	/**
	 * Get all standard event type definitions
	 *
	 * @return array Event type definitions with metadata
	 */
	public static function get_event_types(): array {
		return [
			self::EVENT_SIGNUP => [
				'name' => __( 'Registrazione', 'fp-digital-marketing' ),
				'description' => __( 'Utente si registra sul sito', 'fp-digital-marketing' ),
				'category' => 'registration',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'email', 'registration_source', 'user_type' ],
				'icon' => 'dashicons-admin-users',
			],
			self::EVENT_PURCHASE => [
				'name' => __( 'Acquisto', 'fp-digital-marketing' ),
				'description' => __( 'Utente completa un acquisto', 'fp-digital-marketing' ),
				'category' => 'transaction',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'transaction_id', 'product_id', 'quantity', 'discount' ],
				'icon' => 'dashicons-cart',
			],
			self::EVENT_LEAD_SUBMIT => [
				'name' => __( 'Invio Lead', 'fp-digital-marketing' ),
				'description' => __( 'Utente invia un form di contatto/lead', 'fp-digital-marketing' ),
				'category' => 'lead_generation',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'form_name', 'lead_quality', 'contact_method' ],
				'icon' => 'dashicons-email-alt',
			],
			self::EVENT_DOWNLOAD => [
				'name' => __( 'Download', 'fp-digital-marketing' ),
				'description' => __( 'Utente scarica un file o risorsa', 'fp-digital-marketing' ),
				'category' => 'engagement',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'file_name', 'file_type', 'file_size' ],
				'icon' => 'dashicons-download',
			],
			self::EVENT_SUBSCRIBE => [
				'name' => __( 'Iscrizione Newsletter', 'fp-digital-marketing' ),
				'description' => __( 'Utente si iscrive alla newsletter', 'fp-digital-marketing' ),
				'category' => 'subscription',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'newsletter_type', 'subscription_source' ],
				'icon' => 'dashicons-email',
			],
			self::EVENT_CONTACT_FORM => [
				'name' => __( 'Form Contatto', 'fp-digital-marketing' ),
				'description' => __( 'Utente invia form di contatto', 'fp-digital-marketing' ),
				'category' => 'lead_generation',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'form_id', 'message_length', 'urgency' ],
				'icon' => 'dashicons-feedback',
			],
			self::EVENT_PHONE_CALL => [
				'name' => __( 'Chiamata', 'fp-digital-marketing' ),
				'description' => __( 'Utente effettua una chiamata', 'fp-digital-marketing' ),
				'category' => 'lead_generation',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'call_duration', 'phone_number', 'call_outcome' ],
				'icon' => 'dashicons-phone',
			],
			self::EVENT_EMAIL_CLICK => [
				'name' => __( 'Click Email', 'fp-digital-marketing' ),
				'description' => __( 'Utente clicca link in email', 'fp-digital-marketing' ),
				'category' => 'engagement',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'email_campaign', 'link_position', 'email_type' ],
				'icon' => 'dashicons-email-alt2',
			],
			self::EVENT_VIDEO_WATCH => [
				'name' => __( 'Visualizzazione Video', 'fp-digital-marketing' ),
				'description' => __( 'Utente guarda un video', 'fp-digital-marketing' ),
				'category' => 'engagement',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'video_id', 'watch_percentage', 'video_duration' ],
				'icon' => 'dashicons-video-alt3',
			],
			self::EVENT_ADD_TO_CART => [
				'name' => __( 'Aggiunta Carrello', 'fp-digital-marketing' ),
				'description' => __( 'Utente aggiunge prodotto al carrello', 'fp-digital-marketing' ),
				'category' => 'transaction',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'product_id', 'quantity', 'product_category' ],
				'icon' => 'dashicons-plus-alt',
			],
			self::EVENT_BEGIN_CHECKOUT => [
				'name' => __( 'Inizio Checkout', 'fp-digital-marketing' ),
				'description' => __( 'Utente inizia processo di checkout', 'fp-digital-marketing' ),
				'category' => 'transaction',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'cart_value', 'items_count', 'checkout_step' ],
				'icon' => 'dashicons-yes-alt',
			],
			self::EVENT_BOOKING => [
				'name' => __( 'Prenotazione', 'fp-digital-marketing' ),
				'description' => __( 'Utente effettua una prenotazione', 'fp-digital-marketing' ),
				'category' => 'booking',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'booking_type', 'booking_date', 'service_id' ],
				'icon' => 'dashicons-calendar-alt',
			],
			self::EVENT_QUOTE_REQUEST => [
				'name' => __( 'Richiesta Preventivo', 'fp-digital-marketing' ),
				'description' => __( 'Utente richiede un preventivo', 'fp-digital-marketing' ),
				'category' => 'lead_generation',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'service_type', 'budget_range', 'project_scope' ],
				'icon' => 'dashicons-money-alt',
			],
			self::EVENT_DEMO_REQUEST => [
				'name' => __( 'Richiesta Demo', 'fp-digital-marketing' ),
				'description' => __( 'Utente richiede una demo', 'fp-digital-marketing' ),
				'category' => 'lead_generation',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'demo_type', 'company_size', 'demo_date' ],
				'icon' => 'dashicons-desktop',
			],
			self::EVENT_TRIAL_START => [
				'name' => __( 'Inizio Trial', 'fp-digital-marketing' ),
				'description' => __( 'Utente inizia un periodo di prova', 'fp-digital-marketing' ),
				'category' => 'subscription',
				'default_value' => 0.0,
				'tracked_attributes' => [ 'trial_duration', 'plan_type', 'trial_features' ],
				'icon' => 'dashicons-clock',
			],
		];
	}

	/**
	 * Get event types by category
	 *
	 * @param string $category Category name
	 * @return array Event types in the specified category
	 */
	public static function get_event_types_by_category( string $category ): array {
		$event_types = self::get_event_types();
		$category_events = [];

		foreach ( $event_types as $type => $definition ) {
			if ( $definition['category'] === $category ) {
				$category_events[ $type ] = $definition;
			}
		}

		return $category_events;
	}

	/**
	 * Get all available categories
	 *
	 * @return array Category definitions
	 */
	public static function get_categories(): array {
		return [
			'registration' => [
				'name' => __( 'Registrazione', 'fp-digital-marketing' ),
				'description' => __( 'Eventi di registrazione utenti', 'fp-digital-marketing' ),
			],
			'transaction' => [
				'name' => __( 'Transazioni', 'fp-digital-marketing' ),
				'description' => __( 'Eventi di acquisto e transazioni', 'fp-digital-marketing' ),
			],
			'lead_generation' => [
				'name' => __( 'Generazione Lead', 'fp-digital-marketing' ),
				'description' => __( 'Eventi di generazione lead e contatti', 'fp-digital-marketing' ),
			],
			'engagement' => [
				'name' => __( 'Coinvolgimento', 'fp-digital-marketing' ),
				'description' => __( 'Eventi di coinvolgimento utenti', 'fp-digital-marketing' ),
			],
			'subscription' => [
				'name' => __( 'Iscrizioni', 'fp-digital-marketing' ),
				'description' => __( 'Eventi di iscrizione e abbonamenti', 'fp-digital-marketing' ),
			],
			'booking' => [
				'name' => __( 'Prenotazioni', 'fp-digital-marketing' ),
				'description' => __( 'Eventi di prenotazione servizi', 'fp-digital-marketing' ),
			],
		];
	}

	/**
	 * Get source mappings for external systems
	 *
	 * @return array Source-specific event mappings to standard types
	 */
	public static function get_source_mappings(): array {
		return [
			'google_analytics_4' => [
				'sign_up' => self::EVENT_SIGNUP,
				'purchase' => self::EVENT_PURCHASE,
				'generate_lead' => self::EVENT_LEAD_SUBMIT,
				'file_download' => self::EVENT_DOWNLOAD,
				'subscribe' => self::EVENT_SUBSCRIBE,
				'contact' => self::EVENT_CONTACT_FORM,
				'add_to_cart' => self::EVENT_ADD_TO_CART,
				'begin_checkout' => self::EVENT_BEGIN_CHECKOUT,
				'video_start' => self::EVENT_VIDEO_WATCH,
				'video_progress' => self::EVENT_VIDEO_WATCH,
				'video_complete' => self::EVENT_VIDEO_WATCH,
			],
			'facebook_ads' => [
				'CompleteRegistration' => self::EVENT_SIGNUP,
				'Purchase' => self::EVENT_PURCHASE,
				'Lead' => self::EVENT_LEAD_SUBMIT,
				'Contact' => self::EVENT_CONTACT_FORM,
				'AddToCart' => self::EVENT_ADD_TO_CART,
				'InitiateCheckout' => self::EVENT_BEGIN_CHECKOUT,
				'Subscribe' => self::EVENT_SUBSCRIBE,
				'ViewContent' => self::EVENT_VIDEO_WATCH,
			],
			'google_ads' => [
				'signup' => self::EVENT_SIGNUP,
				'purchase' => self::EVENT_PURCHASE,
				'submit_lead_form' => self::EVENT_LEAD_SUBMIT,
				'contact' => self::EVENT_CONTACT_FORM,
				'phone_call' => self::EVENT_PHONE_CALL,
				'download' => self::EVENT_DOWNLOAD,
			],
			'mailchimp' => [
				'subscribe' => self::EVENT_SUBSCRIBE,
				'unsubscribe' => 'unsubscribe', // Non-standard event
				'campaign_click' => self::EVENT_EMAIL_CLICK,
			],
			'contact_form_7' => [
				'mail_sent' => self::EVENT_CONTACT_FORM,
			],
			'gravity_forms' => [
				'form_submission' => self::EVENT_LEAD_SUBMIT,
			],
			'woocommerce' => [
				'order_completed' => self::EVENT_PURCHASE,
				'add_to_cart' => self::EVENT_ADD_TO_CART,
				'begin_checkout' => self::EVENT_BEGIN_CHECKOUT,
			],
			'custom' => [
				// Custom events can be mapped here
			],
		];
	}

	/**
	 * Normalize event type from external source
	 *
	 * @param string $source Source identifier
	 * @param string $event_type Original event type
	 * @return string Normalized event type
	 */
	public static function normalize_event_type( string $source, string $event_type ): string {
		$mappings = self::get_source_mappings();

		if ( isset( $mappings[ $source ][ $event_type ] ) ) {
			return $mappings[ $source ][ $event_type ];
		}

		// Return original event type if no mapping found
		return $event_type;
	}

	/**
	 * Validate if event type is standard
	 *
	 * @param string $event_type Event type to validate
	 * @return bool True if it's a standard event type
	 */
	public static function is_standard_event_type( string $event_type ): bool {
		$event_types = self::get_event_types();
		return array_key_exists( $event_type, $event_types );
	}

	/**
	 * Get event type definition
	 *
	 * @param string $event_type Event type
	 * @return array|null Event definition or null if not found
	 */
	public static function get_event_type_definition( string $event_type ): ?array {
		$event_types = self::get_event_types();
		return $event_types[ $event_type ] ?? null;
	}

	/**
	 * Get tracked attributes for event type
	 *
	 * @param string $event_type Event type
	 * @return array Tracked attributes
	 */
	public static function get_tracked_attributes( string $event_type ): array {
		$definition = self::get_event_type_definition( $event_type );
		return $definition['tracked_attributes'] ?? [];
	}

	/**
	 * Get default value for event type
	 *
	 * @param string $event_type Event type
	 * @return float Default value
	 */
	public static function get_default_value( string $event_type ): float {
		$definition = self::get_event_type_definition( $event_type );
		return $definition['default_value'] ?? 0.0;
	}

	/**
	 * Get human-readable name for event type
	 *
	 * @param string $event_type Event type
	 * @return string Human-readable name
	 */
	public static function get_event_type_name( string $event_type ): string {
		$definition = self::get_event_type_definition( $event_type );
		return $definition['name'] ?? ucfirst( str_replace( '_', ' ', $event_type ) );
	}

	/**
	 * Get icon for event type
	 *
	 * @param string $event_type Event type
	 * @return string Dashicons icon class
	 */
	public static function get_event_type_icon( string $event_type ): string {
		$definition = self::get_event_type_definition( $event_type );
		return $definition['icon'] ?? 'dashicons-analytics';
	}

	/**
	 * Create event from external source data
	 *
	 * @param string $source Source identifier
	 * @param array  $source_data Raw event data from source
	 * @param int    $client_id Client ID
	 * @return array Normalized event data
	 */
	public static function create_event_from_source( string $source, array $source_data, int $client_id ): array {
		// Get the event type mapping
		$original_type = $source_data['event_type'] ?? $source_data['event'] ?? 'unknown';
		$normalized_type = self::normalize_event_type( $source, $original_type );

		// Generate unique event ID if not provided
		$event_id = $source_data['event_id'] ?? uniqid( $normalized_type . '_', true );

		$created_at = self::normalize_event_timestamp( $source_data['timestamp'] ?? null );

		// Basic event data
		$event_data = [
			'event_id' => $event_id,
			'event_type' => $normalized_type,
			'event_name' => self::get_event_type_name( $normalized_type ),
			'client_id' => $client_id,
			'source' => $source,
			'source_event_id' => $source_data['source_event_id'] ?? $source_data['transaction_id'] ?? null,
			'event_value' => (float) ( $source_data['value'] ?? $source_data['event_value'] ?? self::get_default_value( $normalized_type ) ),
			'currency' => $source_data['currency'] ?? 'EUR',
			'created_at' => $created_at,
		];

		// UTM parameters
		$utm_fields = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];
		foreach ( $utm_fields as $field ) {
			if ( isset( $source_data[ $field ] ) ) {
				$event_data[ $field ] = $source_data[ $field ];
			}
		}

		// Additional tracking fields
		$tracking_fields = [ 'user_id', 'session_id', 'page_url', 'referrer_url', 'ip_address', 'user_agent' ];
		foreach ( $tracking_fields as $field ) {
			if ( isset( $source_data[ $field ] ) ) {
				$event_data[ $field ] = $source_data[ $field ];
			}
		}

		// Event attributes - extract relevant attributes based on event type
		$tracked_attributes = self::get_tracked_attributes( $normalized_type );
		$event_attributes = [];

		foreach ( $tracked_attributes as $attribute ) {
			if ( isset( $source_data[ $attribute ] ) ) {
				$event_attributes[ $attribute ] = $source_data[ $attribute ];
			}
		}

		// Add any additional attributes from source_data that don't have standard fields
		$standard_fields = array_merge( array_keys( $event_data ), $tracking_fields, $utm_fields );
		foreach ( $source_data as $key => $value ) {
			if ( ! in_array( $key, $standard_fields, true ) && ! empty( $value ) ) {
				$event_attributes[ $key ] = $value;
			}
		}

		if ( ! empty( $event_attributes ) ) {
			$event_data['event_attributes'] = $event_attributes;
		}

		return $event_data;
	}

	/**
	 * Normalize timestamp values from source data to MySQL datetime format.
	 *
	 * @param mixed $timestamp Timestamp value from source data.
	 * @return string Normalized MySQL datetime string.
	 */
	private static function normalize_event_timestamp( $timestamp ): string {
		$default = current_time( 'mysql' );

		if ( null === $timestamp || '' === $timestamp ) {
			return $default;
		}

		if ( is_numeric( $timestamp ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $timestamp );
		}

		if ( is_string( $timestamp ) ) {
			$sanitized_timestamp = \sanitize_text_field( $timestamp );

			if ( '' === $sanitized_timestamp ) {
				return $default;
			}

			if ( self::is_valid_mysql_datetime( $sanitized_timestamp ) ) {
				return $sanitized_timestamp;
			}

			$parsed = strtotime( $sanitized_timestamp );
			if ( false !== $parsed ) {
				return gmdate( 'Y-m-d H:i:s', $parsed );
			}
		}

		return $default;
	}

	/**
	 * Check if a string is a valid MySQL datetime format.
	 *
	 * @param string $datetime Datetime string to validate.
	 * @return bool True when string matches MySQL datetime format, false otherwise.
	 */
	private static function is_valid_mysql_datetime( string $datetime ): bool {
		$date = \DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime );

		return $date instanceof \DateTime && $date->format( 'Y-m-d H:i:s' ) === $datetime;
	}

	/**
	 * Register custom event type
	 *
	 * @param string $event_type Event type identifier
	 * @param array  $definition Event definition
	 * @return bool True on success
	 */
	public static function register_custom_event_type( string $event_type, array $definition ): bool {
		// Allow plugins to register custom event types
		$custom_events = get_option( 'fp_custom_conversion_events', [] );
		$custom_events[ $event_type ] = $definition;
		return update_option( 'fp_custom_conversion_events', $custom_events );
	}

	/**
	 * Get custom event types
	 *
	 * @return array Custom event types
	 */
	public static function get_custom_event_types(): array {
		return get_option( 'fp_custom_conversion_events', [] );
	}

	/**
	 * Get all event types (standard + custom)
	 *
	 * @return array All event types
	 */
	public static function get_all_event_types(): array {
		return array_merge( self::get_event_types(), self::get_custom_event_types() );
	}
}