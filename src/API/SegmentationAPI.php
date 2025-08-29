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
		register_rest_route( self::NAMESPACE, '/segments', [
			'methods' => 'GET',
			'callback' => [ self::class, 'get_segments' ],
			'permission_callback' => [ self::class, 'check_read_permission' ],
			'args' => [
				'client_id' => [
					'type' => 'integer',
					'sanitize_callback' => 'absint',
				],
				'active_only' => [
					'type' => 'boolean',
					'default' => true,
				],
				'limit' => [
					'type' => 'integer',
					'default' => 50,
					'minimum' => 1,
					'maximum' => 100,
				],
				'offset' => [
					'type' => 'integer',
					'default' => 0,
					'minimum' => 0,
				],
			],
		] );

		// Get specific segment
		register_rest_route( self::NAMESPACE, '/segments/(?P<id>\d+)', [
			'methods' => 'GET',
			'callback' => [ self::class, 'get_segment' ],
			'permission_callback' => [ self::class, 'check_read_permission' ],
			'args' => [
				'id' => [
					'type' => 'integer',
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Get segment members
		register_rest_route( self::NAMESPACE, '/segments/(?P<id>\d+)/members', [
			'methods' => 'GET',
			'callback' => [ self::class, 'get_segment_members' ],
			'permission_callback' => [ self::class, 'check_read_permission' ],
			'args' => [
				'id' => [
					'type' => 'integer',
					'sanitize_callback' => 'absint',
				],
				'limit' => [
					'type' => 'integer',
					'default' => 50,
					'minimum' => 1,
					'maximum' => 100,
				],
				'offset' => [
					'type' => 'integer',
					'default' => 0,
					'minimum' => 0,
				],
			],
		] );

		// Create segment
		register_rest_route( self::NAMESPACE, '/segments', [
			'methods' => 'POST',
			'callback' => [ self::class, 'create_segment' ],
			'permission_callback' => [ self::class, 'check_write_permission' ],
			'args' => [
				'name' => [
					'type' => 'string',
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'description' => [
					'type' => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				],
				'client_id' => [
					'type' => 'integer',
					'required' => true,
					'sanitize_callback' => 'absint',
				],
				'rules' => [
					'type' => 'object',
					'required' => true,
				],
				'is_active' => [
					'type' => 'boolean',
					'default' => true,
				],
			],
		] );

		// Update segment
		register_rest_route( self::NAMESPACE, '/segments/(?P<id>\d+)', [
			'methods' => 'PUT',
			'callback' => [ self::class, 'update_segment' ],
			'permission_callback' => [ self::class, 'check_write_permission' ],
			'args' => [
				'id' => [
					'type' => 'integer',
					'sanitize_callback' => 'absint',
				],
				'name' => [
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'description' => [
					'type' => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				],
				'rules' => [
					'type' => 'object',
				],
				'is_active' => [
					'type' => 'boolean',
				],
			],
		] );

		// Delete segment
		register_rest_route( self::NAMESPACE, '/segments/(?P<id>\d+)', [
			'methods' => 'DELETE',
			'callback' => [ self::class, 'delete_segment' ],
			'permission_callback' => [ self::class, 'check_write_permission' ],
			'args' => [
				'id' => [
					'type' => 'integer',
					'sanitize_callback' => 'absint',
				],
			],
		] );
	}

	/**
	 * Get segments list
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function get_segments( WP_REST_Request $request ) {
		$client_id = $request->get_param( 'client_id' );
		$active_only = $request->get_param( 'active_only' );
		$limit = $request->get_param( 'limit' );
		$offset = $request->get_param( 'offset' );

		$criteria = [];
		
		if ( $client_id ) {
			$criteria['client_id'] = $client_id;
		}
		
		if ( $active_only ) {
			$criteria['is_active'] = 1;
		}

		$segments = AudienceSegmentTable::get_segments( $criteria, $limit, $offset );
		$total = AudienceSegmentTable::get_segments_count( $criteria );

		return new WP_REST_Response( [
			'segments' => $segments,
			'total' => $total,
			'limit' => $limit,
			'offset' => $offset,
		] );
	}

	/**
	 * Get specific segment
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function get_segment( WP_REST_Request $request ) {
		$segment_id = $request->get_param( 'id' );
		$segment = AudienceSegment::load_by_id( $segment_id );

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
		$limit = $request->get_param( 'limit' );
		$offset = $request->get_param( 'offset' );

		// Verify segment exists
		$segment = AudienceSegment::load_by_id( $segment_id );
		if ( ! $segment ) {
			return new WP_Error( 'segment_not_found', __( 'Segmento non trovato', 'fp-digital-marketing' ), [ 'status' => 404 ] );
		}

		$members = AudienceSegmentTable::get_segment_members( $segment_id, $limit, $offset );
		$total = AudienceSegmentTable::get_segment_member_count( $segment_id );

		return new WP_REST_Response( [
			'segment_id' => $segment_id,
			'segment_name' => $segment->get_name(),
			'members' => $members,
			'total' => $total,
			'limit' => $limit,
			'offset' => $offset,
		] );
	}

	/**
	 * Get segment members
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function create_segment( WP_REST_Request $request ) {
		$data = [
			'name' => $request->get_param( 'name' ),
			'description' => $request->get_param( 'description' ) ?: '',
			'client_id' => $request->get_param( 'client_id' ),
			'rules' => $request->get_param( 'rules' ),
			'is_active' => $request->get_param( 'is_active' ),
		];

		$segment = new AudienceSegment( $data );

		if ( $segment->save() ) {
			return new WP_REST_Response( $segment->to_array(), 201 );
		} else {
			return new WP_Error( 'creation_failed', __( 'Impossibile creare il segmento', 'fp-digital-marketing' ), [ 'status' => 500 ] );
		}
	}

	/**
	 * Update existing segment
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function update_segment( WP_REST_Request $request ) {
		$segment_id = $request->get_param( 'id' );
		$segment = AudienceSegment::load_by_id( $segment_id );

		if ( ! $segment ) {
			return new WP_Error( 'segment_not_found', __( 'Segmento non trovato', 'fp-digital-marketing' ), [ 'status' => 404 ] );
		}

		// Update fields if provided
		$name = $request->get_param( 'name' );
		if ( $name !== null ) {
			$segment->set_name( $name );
		}

		$description = $request->get_param( 'description' );
		if ( $description !== null ) {
			$segment->set_description( $description );
		}

		$rules = $request->get_param( 'rules' );
		if ( $rules !== null ) {
			$segment->set_rules( $rules );
		}

		$is_active = $request->get_param( 'is_active' );
		if ( $is_active !== null ) {
			$segment->set_active( $is_active );
		}

		if ( $segment->save() ) {
			return new WP_REST_Response( $segment->to_array() );
		} else {
			return new WP_Error( 'update_failed', __( 'Impossibile aggiornare il segmento', 'fp-digital-marketing' ), [ 'status' => 500 ] );
		}
	}

	/**
	 * Delete segment
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public static function delete_segment( WP_REST_Request $request ) {
		$segment_id = $request->get_param( 'id' );
		$segment = AudienceSegment::load_by_id( $segment_id );

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
		return current_user_can( Capabilities::get_capability( 'view_segments' ) );
	}

	/**
	 * Check write permission
	 *
	 * @return bool True if user has write permission
	 */
	public static function check_write_permission(): bool {
		return current_user_can( Capabilities::get_capability( 'manage_segments' ) );
	}
}