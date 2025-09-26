<?php
/**
 * Segmentation API
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\API;

use FP\DigitalMarketing\Models\AudienceSegment;
use FP\DigitalMarketing\Database\AudienceSegmentTable;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Helpers\SegmentationRulesValidator;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Segmentation API class
 *
 * Provides REST API endpoints for audience segmentation functionality.
 */
class SegmentationAPI {

		/**
		 * Maximum allowed characters for a segment name.
		 */
	private const MAX_NAME_LENGTH = 120;

		/**
		 * Maximum allowed characters for a segment description.
		 */
	private const MAX_DESCRIPTION_LENGTH = 1000;

	/**
	 * API namespace
	 */
	public const NAMESPACE = 'fp-dms/v1';

	/**
	 * Initialize the API
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register REST routes
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		// Get segments list
		register_rest_route(
			self::NAMESPACE,
			'/segments',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'get_segments' ],
				'permission_callback' => [ self::class, 'check_read_permission' ],
				'args'                => [
					'client_id'   => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'active_only' => [
						'type'    => 'boolean',
						'default' => true,
					],
					'limit'       => [
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 100,
					],
					'offset'      => [
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					],
				],
			]
		);

		// Get specific segment
		register_rest_route(
			self::NAMESPACE,
			'/segments/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'get_segment' ],
				'permission_callback' => [ self::class, 'check_read_permission' ],
				'args'                => [
					'id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Get segment members
		register_rest_route(
			self::NAMESPACE,
			'/segments/(?P<id>\d+)/members',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'get_segment_members' ],
				'permission_callback' => [ self::class, 'check_read_permission' ],
				'args'                => [
					'id'     => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'limit'  => [
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 100,
					],
					'offset' => [
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					],
				],
			]
		);

		// Create segment
		register_rest_route(
			self::NAMESPACE,
			'/segments',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'create_segment' ],
				'permission_callback' => [ self::class, 'check_write_permission' ],
				'args'                => [
					'name'        => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'description' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'client_id'   => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'rules'       => [
						'type'     => 'object',
						'required' => true,
					],
					'is_active'   => [
						'type'    => 'boolean',
						'default' => true,
					],
				],
			]
		);

		// Update segment
		register_rest_route(
			self::NAMESPACE,
			'/segments/(?P<id>\d+)',
			[
				'methods'             => 'PUT',
				'callback'            => [ self::class, 'update_segment' ],
				'permission_callback' => [ self::class, 'check_write_permission' ],
				'args'                => [
					'id'          => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'name'        => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'description' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'rules'       => [
						'type' => 'object',
					],
					'is_active'   => [
						'type' => 'boolean',
					],
				],
			]
		);

		// Delete segment
		register_rest_route(
			self::NAMESPACE,
			'/segments/(?P<id>\d+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ self::class, 'delete_segment' ],
				'permission_callback' => [ self::class, 'check_write_permission' ],
				'args'                => [
					'id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Get segments list
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function get_segments( WP_REST_Request $request ) {
		$client_id   = $request->get_param( 'client_id' );
		$active_only = $request->get_param( 'active_only' );
		$limit       = $request->get_param( 'limit' );
		$offset      = $request->get_param( 'offset' );

		$criteria = [];

		if ( $client_id ) {
			$criteria['client_id'] = $client_id;
		}

		if ( $active_only ) {
			$criteria['is_active'] = 1;
		}

		$segments = AudienceSegmentTable::get_segments( $criteria, $limit, $offset );
		$total    = AudienceSegmentTable::get_segments_count( $criteria );

		return new WP_REST_Response(
			[
				'segments' => $segments,
				'total'    => $total,
				'limit'    => $limit,
				'offset'   => $offset,
			]
		);
	}

	/**
	 * Get specific segment
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function get_segment( WP_REST_Request $request ) {
		$segment_id = $request->get_param( 'id' );
		$segment    = AudienceSegment::load_by_id( $segment_id );

		if ( ! $segment ) {
			return new WP_Error( 'segment_not_found', __( 'Segmento non trovato', 'fp-digital-marketing' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response( $segment->to_array() );
	}

	/**
	 * Get segment members
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function get_segment_members( WP_REST_Request $request ) {
		$segment_id = $request->get_param( 'id' );
		$limit      = $request->get_param( 'limit' );
		$offset     = $request->get_param( 'offset' );

		// Verify segment exists
		$segment = AudienceSegment::load_by_id( $segment_id );
		if ( ! $segment ) {
			return new WP_Error( 'segment_not_found', __( 'Segmento non trovato', 'fp-digital-marketing' ), [ 'status' => 404 ] );
		}

		$members = AudienceSegmentTable::get_segment_members( $segment_id, $limit, $offset );
		$total   = AudienceSegmentTable::get_segment_member_count( $segment_id );

		return new WP_REST_Response(
			[
				'segment_id'   => $segment_id,
				'segment_name' => $segment->get_name(),
				'members'      => $members,
				'total'        => $total,
				'limit'        => $limit,
				'offset'       => $offset,
			]
		);
	}

	/**
	 * Get segment members
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function create_segment( WP_REST_Request $request ) {
			$segment_data = self::build_segment_payload( $request );

		if ( is_wp_error( $segment_data ) ) {
				return $segment_data;
		}

			$segment = new AudienceSegment( $segment_data );

		if ( $segment->save() ) {
				return new WP_REST_Response( $segment->to_array(), 201 );
		}

			return new WP_Error( 'creation_failed', __( 'Impossibile creare il segmento', 'fp-digital-marketing' ), [ 'status' => 500 ] );
	}

	/**
	 * Update existing segment
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function update_segment( WP_REST_Request $request ) {
			$segment_id = $request->get_param( 'id' );
			$segment    = AudienceSegment::load_by_id( (int) $segment_id );

		if ( ! $segment ) {
				return new WP_Error( 'segment_not_found', __( 'Segmento non trovato', 'fp-digital-marketing' ), [ 'status' => 404 ] );
		}

			$segment_data = self::build_segment_payload( $request, $segment );

		if ( is_wp_error( $segment_data ) ) {
				return $segment_data;
		}

			$segment->set_name( $segment_data['name'] );
			$segment->set_description( $segment_data['description'] );
			$segment->set_client_id( $segment_data['client_id'] );
			$segment->set_rules( $segment_data['rules'] );
			$segment->set_active( $segment_data['is_active'] );

		if ( $segment->save() ) {
				return new WP_REST_Response( $segment->to_array() );
		}

			return new WP_Error( 'update_failed', __( 'Impossibile aggiornare il segmento', 'fp-digital-marketing' ), [ 'status' => 500 ] );
	}

		/**
		 * Build a sanitized payload for segment creation or update.
		 *
		 * @param WP_REST_Request      $request  Incoming REST request.
		 * @param AudienceSegment|null $existing Existing segment when updating.
		 * @return array|WP_Error Sanitized payload ready to be persisted.
		 */
	private static function build_segment_payload( WP_REST_Request $request, ?AudienceSegment $existing = null ) {
			$name_param = $request->get_param( 'name' );

		if ( null === $name_param ) {
			if ( null === $existing ) {
				return new WP_Error( 'missing_segment_name', __( 'Il nome del segmento è obbligatorio.', 'fp-digital-marketing' ), [ 'status' => 400 ] );
			}

				$name = $existing->get_name();
		} else {
				$name = self::sanitize_segment_name( $name_param );

			if ( is_wp_error( $name ) ) {
					return $name;
			}
		}

			$description_param = $request->get_param( 'description' );
			$description       = null === $description_param
					? ( $existing ? $existing->get_description() : '' )
					: self::sanitize_segment_description( $description_param );

			$client_param = $request->get_param( 'client_id' );
		if ( null === $client_param ) {
			if ( null === $existing ) {
					return new WP_Error( 'missing_client_id', __( 'Seleziona un cliente valido prima di salvare il segmento.', 'fp-digital-marketing' ), [ 'status' => 400 ] );
			}

				$client_id = $existing->get_client_id();
		} else {
				$client_id = self::sanitize_client_id( $client_param );

			if ( is_wp_error( $client_id ) ) {
					return $client_id;
			}
		}

			$rules_param = $request->get_param( 'rules' );
		if ( null === $rules_param ) {
			if ( null === $existing ) {
					return new WP_Error( 'missing_rules', __( 'Definisci almeno una condizione per il segmento.', 'fp-digital-marketing' ), [ 'status' => 400 ] );
			}

				$rules = $existing->get_rules();
		} else {
				$rules = SegmentationRulesValidator::validate( $rules_param );

			if ( is_wp_error( $rules ) ) {
					return $rules;
			}
		}

			$is_active_param = $request->get_param( 'is_active' );
			$is_active       = null === $is_active_param
					? ( $existing ? $existing->is_active() : true )
					: (bool) $is_active_param;

			return [
				'name'        => $name,
				'description' => $description,
				'client_id'   => $client_id,
				'rules'       => $rules,
				'is_active'   => $is_active,
			];
	}

		/**
		 * Sanitize and validate the provided segment name.
		 *
		 * @param mixed $value Raw value from the request.
		 * @return string|WP_Error Sanitized name or error when invalid.
		 */
	private static function sanitize_segment_name( $value ) {
		if ( ! is_scalar( $value ) ) {
				return new WP_Error( 'invalid_segment_name', __( 'Il nome del segmento non è valido.', 'fp-digital-marketing' ), [ 'status' => 400 ] );
		}

			$name = trim( sanitize_text_field( (string) $value ) );

		if ( '' === $name ) {
				return new WP_Error( 'invalid_segment_name', __( 'Il nome del segmento non può essere vuoto.', 'fp-digital-marketing' ), [ 'status' => 400 ] );
		}

		if ( mb_strlen( $name ) > self::MAX_NAME_LENGTH ) {
				return new WP_Error( 'invalid_segment_name', __( 'Il nome del segmento è troppo lungo.', 'fp-digital-marketing' ), [ 'status' => 400 ] );
		}

			return $name;
	}

		/**
		 * Sanitize the segment description.
		 *
		 * @param mixed $value Raw value from the request.
		 * @return string Sanitized description.
		 */
	private static function sanitize_segment_description( $value ): string {
			$description = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';

		if ( mb_strlen( $description ) > self::MAX_DESCRIPTION_LENGTH ) {
				$description = mb_substr( $description, 0, self::MAX_DESCRIPTION_LENGTH );
		}

			return $description;
	}

		/**
		 * Validate the client identifier provided with the request.
		 *
		 * @param mixed $value Raw value from the request.
		 * @return int|WP_Error Sanitized client id or error when invalid.
		 */
	private static function sanitize_client_id( $value ) {
		if ( ! is_scalar( $value ) ) {
				return new WP_Error( 'invalid_client_id', __( 'ID cliente non valido.', 'fp-digital-marketing' ), [ 'status' => 400 ] );
		}

			$client_id = absint( $value );

		if ( $client_id <= 0 ) {
				return new WP_Error( 'invalid_client_id', __( 'Seleziona un cliente valido prima di salvare il segmento.', 'fp-digital-marketing' ), [ 'status' => 400 ] );
		}

			return $client_id;
	}

	/**
	 * Delete segment
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function delete_segment( WP_REST_Request $request ) {
		$segment_id = $request->get_param( 'id' );
		$segment    = AudienceSegment::load_by_id( $segment_id );

		if ( ! $segment ) {
			return new WP_Error( 'segment_not_found', __( 'Segmento non trovato', 'fp-digital-marketing' ), [ 'status' => 404 ] );
		}

		if ( $segment->delete() ) {
			return new WP_REST_Response( [ 'deleted' => true ] );
		} else {
			return new WP_Error( 'deletion_failed', __( 'Impossibile eliminare il segmento', 'fp-digital-marketing' ), [ 'status' => 500 ] );
		}
	}

	/**
	 * Check read permission
	 *
	 * @return bool True if user has read permission
	 */
	public static function check_read_permission(): bool {
		return current_user_can( Capabilities::VIEW_SEGMENTS );
	}

	/**
	 * Check write permission
	 *
	 * @return bool True if user has write permission
	 */
	public static function check_write_permission(): bool {
		return current_user_can( Capabilities::MANAGE_SEGMENTS );
	}
}
