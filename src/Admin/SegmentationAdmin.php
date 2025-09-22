<?php
/**
 * Segmentation Admin Interface
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Models\AudienceSegment;
use FP\DigitalMarketing\Database\AudienceSegmentTable;
use FP\DigitalMarketing\Helpers\SegmentationEngine;
use FP\DigitalMarketing\Helpers\ConversionEventRegistry;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\PostTypes\ClientePostType;
use FP\DigitalMarketing\Admin\MenuManager;

/**
 * Segmentation Admin class
 * 
 * Provides admin interface for managing audience segments.
 */
class SegmentationAdmin {

	/**
	 * Page slug
	 */
	public const PAGE_SLUG = 'fp-audience-segments';

	/**
	 * Nonce action
	 */
	public const NONCE_ACTION = 'fp_segmentation_action';

	/**
	 * Initialize the admin interface
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
		add_action( 'wp_ajax_fp_segmentation_action', [ $this, 'handle_ajax_request' ] );
	}

	/**
	 * Add admin menu
        *
         * @return void
         */
        public function add_admin_menu(): void {
                if ( class_exists( MenuManager::class ) && MenuManager::is_initialized() ) {
                        return;
                }

                add_submenu_page(
                        'fp-digital-marketing-dashboard',
                        __( 'Segmentazione Audience', 'fp-digital-marketing' ),
			__( '👥 Segmentazione', 'fp-digital-marketing' ),
			Capabilities::MANAGE_SEGMENTS,
			self::PAGE_SLUG,
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook_suffix Current admin page hook suffix
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( strpos( $hook_suffix, self::PAGE_SLUG ) === false ) {
			return;
		}

                wp_enqueue_style(
                        'fp-segmentation-admin',
                        FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/css/segmentation-admin.css',
                        [],
                        '1.0.0'
                );

                wp_enqueue_script(
                        'fp-segmentation-admin',
                        FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/js/segmentation-admin.js',
                        [ 'jquery', 'wp-util' ],
                        '1.0.0',
                        true
                );

		wp_localize_script( 'fp-segmentation-admin', 'fpSegmentation', [
			'nonce' => wp_create_nonce( self::NONCE_ACTION ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'strings' => [
				'confirm_delete' => __( 'Sei sicuro di voler eliminare questo segmento?', 'fp-digital-marketing' ),
				'evaluating' => __( 'Valutazione in corso...', 'fp-digital-marketing' ),
				'error' => __( 'Errore durante l\'operazione', 'fp-digital-marketing' ),
			],
		] );
	}

	/**
	 * Handle form submission
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		if ( ! isset( $_POST['fp_segmentation_nonce'] ) || 
			 ! wp_verify_nonce( $_POST['fp_segmentation_nonce'], self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_SEGMENTS ) ) {
			wp_die( __( 'Non hai i permessi per eseguire questa azione.', 'fp-digital-marketing' ) );
		}

		$action = sanitize_text_field( $_POST['action'] ?? '' );

		switch ( $action ) {
			case 'create_segment':
				$this->handle_create_segment();
				break;
			case 'update_segment':
				$this->handle_update_segment();
				break;
			case 'delete_segment':
				$this->handle_delete_segment();
				break;
		}
	}

	/**
	 * Handle AJAX requests
	 *
	 * @return void
	 */
	public function handle_ajax_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Capabilities::MANAGE_SEGMENTS ) ) {
			wp_send_json_error( __( 'Permessi insufficienti', 'fp-digital-marketing' ) );
		}

		$action = sanitize_text_field( $_POST['action_type'] ?? '' );

		switch ( $action ) {
			case 'evaluate_segment':
				$this->ajax_evaluate_segment();
				break;
			case 'get_segment_members':
				$this->ajax_get_segment_members();
				break;
			case 'preview_segment':
				$this->ajax_preview_segment();
				break;
			default:
				wp_send_json_error( __( 'Azione non valida', 'fp-digital-marketing' ) );
		}
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$segment_id = isset( $_GET['segment_id'] ) ? intval( $_GET['segment_id'] ) : 0;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Segmentazione Audience', 'fp-digital-marketing' ) . '</h1>';

		switch ( $action ) {
			case 'edit':
				if ( $segment_id > 0 ) {
					$this->render_edit_segment( $segment_id );
				} else {
					$this->render_create_segment();
				}
				break;
			case 'create':
				$this->render_create_segment();
				break;
			case 'view':
				$this->render_view_segment( $segment_id );
				break;
			default:
				$this->render_segments_list();
		}

		echo '</div>';
	}

	/**
	 * Render segments list
	 *
	 * @return void
	 */
	private function render_segments_list(): void {
		try {
			$segments = AudienceSegmentTable::get_segments( [], 50, 0 );
		} catch ( \Throwable $e ) {
			// Fallback to empty array if database operation fails
			$segments = [];
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP Digital Marketing SegmentationAdmin: Failed to load segments - ' . $e->getMessage() );
			}
		}

		echo '<div class="segments-header">';
		echo '<a href="' . esc_url( add_query_arg( 'action', 'create' ) ) . '" class="button button-primary">';
		echo esc_html__( 'Crea Nuovo Segmento', 'fp-digital-marketing' );
		echo '</a>';
		echo '</div>';

		if ( empty( $segments ) ) {
			echo '<div class="notice notice-info"><p>';
			echo esc_html__( 'Nessun segmento definito. Crea il tuo primo segmento per iniziare la segmentazione audience.', 'fp-digital-marketing' );
			echo '</p></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Nome', 'fp-digital-marketing' ) . '</th>';
		echo '<th>' . esc_html__( 'Cliente', 'fp-digital-marketing' ) . '</th>';
		echo '<th>' . esc_html__( 'Membri', 'fp-digital-marketing' ) . '</th>';
		echo '<th>' . esc_html__( 'Stato', 'fp-digital-marketing' ) . '</th>';
		echo '<th>' . esc_html__( 'Ultima Valutazione', 'fp-digital-marketing' ) . '</th>';
		echo '<th>' . esc_html__( 'Azioni', 'fp-digital-marketing' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $segments as $segment ) {
			$client_name = get_the_title( $segment['client_id'] ) ?: __( 'Cliente Sconosciuto', 'fp-digital-marketing' );
			$edit_url = add_query_arg( [ 'action' => 'edit', 'segment_id' => $segment['id'] ] );
			$view_url = add_query_arg( [ 'action' => 'view', 'segment_id' => $segment['id'] ] );
			$delete_url = add_query_arg( [ 'action' => 'delete_segment', 'segment_id' => $segment['id'] ] );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $segment['name'] ) . '</strong>';
			if ( ! empty( $segment['description'] ) ) {
				echo '<br><small>' . esc_html( $segment['description'] ) . '</small>';
			}
			echo '</td>';
			echo '<td>' . esc_html( $client_name ) . '</td>';
			echo '<td>' . number_format( $segment['member_count'] ) . '</td>';
			echo '<td>';
			if ( $segment['is_active'] ) {
				echo '<span class="status-active">' . esc_html__( 'Attivo', 'fp-digital-marketing' ) . '</span>';
			} else {
				echo '<span class="status-inactive">' . esc_html__( 'Inattivo', 'fp-digital-marketing' ) . '</span>';
			}
			echo '</td>';
			echo '<td>';
			if ( $segment['last_evaluated_at'] ) {
				echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $segment['last_evaluated_at'] ) ) );
			} else {
				echo __( 'Mai', 'fp-digital-marketing' );
			}
			echo '</td>';
			echo '<td>';
			echo '<a href="' . esc_url( $view_url ) . '" class="button button-small">' . esc_html__( 'Visualizza', 'fp-digital-marketing' ) . '</a> ';
			echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Modifica', 'fp-digital-marketing' ) . '</a> ';
                       echo '<button class="button button-small evaluate-segment" data-segment-id="' . esc_attr( $segment['id'] ) . '">' . esc_html__( 'Valuta', 'fp-digital-marketing' ) . '</button> ';
                       echo '<a href="' . esc_url( wp_nonce_url( $delete_url, 'fp_delete_segment', 'segment_nonce' ) ) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Sei sicuro di voler eliminare questo segmento?', 'fp-digital-marketing' ) ) . '\')">' . esc_html__( 'Elimina', 'fp-digital-marketing' ) . '</a>';
                       echo '</td>';
                       echo '</tr>';
               }

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Render create segment form
	 *
	 * @return void
	 */
	private function render_create_segment(): void {
		$this->render_segment_form();
	}

	/**
	 * Render edit segment form
	 *
	 * @param int $segment_id Segment ID
	 * @return void
	 */
	private function render_edit_segment( int $segment_id ): void {
		$segment = AudienceSegment::load_by_id( $segment_id );
		
		if ( ! $segment ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Segmento non trovato.', 'fp-digital-marketing' );
			echo '</p></div>';
			return;
		}

		$this->render_segment_form( $segment );
	}

	/**
	 * Render segment form
	 *
	 * @param AudienceSegment|null $segment Segment to edit, null for create
	 * @return void
	 */
	private function render_segment_form( ?AudienceSegment $segment = null ): void {
		$is_edit = $segment !== null;
		$form_action = $is_edit ? 'update_segment' : 'create_segment';
		$page_title = $is_edit ? __( 'Modifica Segmento', 'fp-digital-marketing' ) : __( 'Crea Nuovo Segmento', 'fp-digital-marketing' );

		echo '<h2>' . esc_html( $page_title ) . '</h2>';

		echo '<form method="post" id="segment-form">';
		wp_nonce_field( self::NONCE_ACTION, 'fp_segmentation_nonce' );
		echo '<input type="hidden" name="action" value="' . esc_attr( $form_action ) . '">';
		
		if ( $is_edit ) {
			echo '<input type="hidden" name="segment_id" value="' . esc_attr( $segment->get_id() ) . '">';
		}

		echo '<table class="form-table">';

		// Name field
		echo '<tr>';
		echo '<th scope="row"><label for="segment_name">' . esc_html__( 'Nome Segmento', 'fp-digital-marketing' ) . '</label></th>';
		echo '<td><input type="text" id="segment_name" name="segment_name" value="' . esc_attr( $segment ? $segment->get_name() : '' ) . '" class="regular-text" required></td>';
		echo '</tr>';

		// Description field
		echo '<tr>';
		echo '<th scope="row"><label for="segment_description">' . esc_html__( 'Descrizione', 'fp-digital-marketing' ) . '</label></th>';
		echo '<td><textarea id="segment_description" name="segment_description" rows="3" class="regular-text">' . esc_textarea( $segment ? $segment->get_description() : '' ) . '</textarea></td>';
		echo '</tr>';

		// Client field
		echo '<tr>';
		echo '<th scope="row"><label for="client_id">' . esc_html__( 'Cliente', 'fp-digital-marketing' ) . '</label></th>';
		echo '<td>';
		$this->render_client_dropdown( $segment ? $segment->get_client_id() : 0 );
		echo '</td>';
		echo '</tr>';

		// Active status
		echo '<tr>';
		echo '<th scope="row"><label for="is_active">' . esc_html__( 'Stato', 'fp-digital-marketing' ) . '</label></th>';
		echo '<td>';
		echo '<label><input type="checkbox" id="is_active" name="is_active" value="1"' . checked( $segment ? $segment->is_active() : true, true, false ) . '> ';
		echo esc_html__( 'Segmento attivo', 'fp-digital-marketing' ) . '</label>';
		echo '</td>';
		echo '</tr>';

		echo '</table>';

		// Rules builder
		echo '<h3>' . esc_html__( 'Regole di Segmentazione', 'fp-digital-marketing' ) . '</h3>';
		echo '<div id="rules-builder">';
		
		$rules = $segment ? $segment->get_rules() : [ 'logic' => 'AND', 'conditions' => [] ];
		$this->render_rules_builder( $rules );
		
		echo '</div>';

		// Submit button
		echo '<p class="submit">';
		echo '<input type="submit" class="button-primary" value="' . esc_attr( $is_edit ? __( 'Aggiorna Segmento', 'fp-digital-marketing' ) : __( 'Crea Segmento', 'fp-digital-marketing' ) ) . '">';
		echo ' <a href="' . esc_url( remove_query_arg( [ 'action', 'segment_id' ] ) ) . '" class="button">' . esc_html__( 'Annulla', 'fp-digital-marketing' ) . '</a>';
		echo '</p>';

		echo '</form>';
	}

	/**
	 * Render client dropdown
	 *
	 * @param int $selected_client_id Selected client ID
	 * @return void
	 */
	private function render_client_dropdown( int $selected_client_id = 0 ): void {
		$clients = get_posts( [
			'post_type' => ClientePostType::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		] );

		echo '<select id="client_id" name="client_id" required>';
		echo '<option value="">' . esc_html__( 'Seleziona Cliente', 'fp-digital-marketing' ) . '</option>';

		foreach ( $clients as $client ) {
			echo '<option value="' . esc_attr( $client->ID ) . '"' . selected( $selected_client_id, $client->ID, false ) . '>';
			echo esc_html( $client->post_title );
			echo '</option>';
		}

		echo '</select>';
	}

	/**
	 * Render rules builder
	 *
	 * @param array $rules Current rules
	 * @return void
	 */
	private function render_rules_builder( array $rules ): void {
		$logic = $rules['logic'] ?? 'AND';
		$conditions = $rules['conditions'] ?? [];

		echo '<div class="rules-logic">';
		echo '<label>' . esc_html__( 'Logica tra regole:', 'fp-digital-marketing' ) . ' ';
		echo '<select name="rules[logic]">';
		echo '<option value="AND"' . selected( $logic, 'AND', false ) . '>' . esc_html__( 'E (tutte le regole devono essere soddisfatte)', 'fp-digital-marketing' ) . '</option>';
		echo '<option value="OR"' . selected( $logic, 'OR', false ) . '>' . esc_html__( 'O (almeno una regola deve essere soddisfatta)', 'fp-digital-marketing' ) . '</option>';
		echo '</select>';
		echo '</label>';
		echo '</div>';

		echo '<div id="conditions-container">';
		
		if ( empty( $conditions ) ) {
			$this->render_condition_row( 0, [] );
		} else {
			foreach ( $conditions as $index => $condition ) {
				$this->render_condition_row( $index, $condition );
			}
		}
		
		echo '</div>';

		echo '<button type="button" id="add-condition" class="button">' . esc_html__( 'Aggiungi Regola', 'fp-digital-marketing' ) . '</button>';
	}

	/**
	 * Render a single condition row
	 *
	 * @param int   $index Condition index
	 * @param array $condition Condition data
	 * @return void
	 */
	private function render_condition_row( int $index, array $condition ): void {
		$rule_types = SegmentationEngine::get_rule_types();
		$operators = SegmentationEngine::get_operators();

		echo '<div class="condition-row" data-index="' . esc_attr( $index ) . '">';
		
		// Rule type
		echo '<select name="rules[conditions][' . $index . '][type]" class="rule-type">';
		echo '<option value="">' . esc_html__( 'Seleziona tipo regola', 'fp-digital-marketing' ) . '</option>';
		foreach ( $rule_types as $type => $label ) {
			echo '<option value="' . esc_attr( $type ) . '"' . selected( $condition['type'] ?? '', $type, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		// Field (depends on rule type)
		echo '<select name="rules[conditions][' . $index . '][field]" class="rule-field">';
		echo '<option value="">' . esc_html__( 'Seleziona campo', 'fp-digital-marketing' ) . '</option>';
		echo '</select>';

		// Operator
		echo '<select name="rules[conditions][' . $index . '][operator]" class="rule-operator">';
		echo '<option value="">' . esc_html__( 'Seleziona operatore', 'fp-digital-marketing' ) . '</option>';
		foreach ( $operators as $op => $label ) {
			echo '<option value="' . esc_attr( $op ) . '"' . selected( $condition['operator'] ?? '', $op, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		// Value
		echo '<input type="text" name="rules[conditions][' . $index . '][value]" value="' . esc_attr( $condition['value'] ?? '' ) . '" placeholder="' . esc_attr__( 'Valore', 'fp-digital-marketing' ) . '" class="rule-value">';

		// Remove button
		echo '<button type="button" class="button remove-condition">' . esc_html__( 'Rimuovi', 'fp-digital-marketing' ) . '</button>';

		echo '</div>';
	}

	/**
	 * Handle create segment
	 *
	 * @return void
	 */
	private function handle_create_segment(): void {
		$segment_data = $this->sanitize_segment_data( $_POST );

		$segment = new AudienceSegment( $segment_data );
		
		if ( $segment->save() ) {
			wp_redirect( add_query_arg( [ 'message' => 'created' ], remove_query_arg( [ 'action' ] ) ) );
			exit;
		} else {
			wp_redirect( add_query_arg( [ 'message' => 'error' ], remove_query_arg( [ 'action' ] ) ) );
			exit;
		}
	}

	/**
	 * Handle update segment
	 *
	 * @return void
	 */
	private function handle_update_segment(): void {
		$segment_id = (int) $_POST['segment_id'];
		$segment = AudienceSegment::load_by_id( $segment_id );

		if ( ! $segment ) {
			wp_redirect( add_query_arg( [ 'message' => 'not_found' ], remove_query_arg( [ 'action', 'segment_id' ] ) ) );
			exit;
		}

		$segment_data = $this->sanitize_segment_data( $_POST );

		foreach ( $segment_data as $key => $value ) {
			$method = 'set_' . $key;
			if ( method_exists( $segment, $method ) ) {
				$segment->$method( $value );
			}
		}

		if ( $segment->save() ) {
			wp_redirect( add_query_arg( [ 'message' => 'updated' ], remove_query_arg( [ 'action' ] ) ) );
			exit;
		} else {
			wp_redirect( add_query_arg( [ 'message' => 'error' ], remove_query_arg( [ 'action' ] ) ) );
			exit;
		}
	}

	/**
	 * Handle delete segment
	 *
	 * @return void
	 */
       private function handle_delete_segment(): void {
               if ( ! wp_verify_nonce( $_GET['segment_nonce'] ?? '', 'fp_delete_segment' ) ) {
                       wp_redirect( add_query_arg( [ 'message' => 'error' ], remove_query_arg( [ 'action', 'segment_id', 'segment_nonce' ] ) ) );
                       exit;
               }

               if ( ! isset( $_GET['segment_id'] ) ) {
                       wp_redirect( add_query_arg( [ 'message' => 'error' ], remove_query_arg( [ 'action', 'segment_id', 'segment_nonce' ] ) ) );
                       exit;
               }

               $segment_id = (int) $_GET['segment_id'];
               $segment = AudienceSegment::load_by_id( $segment_id );

               if ( ! $segment ) {
                       wp_redirect( add_query_arg( [ 'message' => 'not_found' ], remove_query_arg( [ 'action', 'segment_id', 'segment_nonce' ] ) ) );
                       exit;
               }

               if ( $segment->delete() ) {
                       wp_redirect( add_query_arg( [ 'message' => 'deleted' ], remove_query_arg( [ 'action', 'segment_id', 'segment_nonce' ] ) ) );
                       exit;
               } else {
                       wp_redirect( add_query_arg( [ 'message' => 'error' ], remove_query_arg( [ 'action', 'segment_id', 'segment_nonce' ] ) ) );
                       exit;
               }
       }

	/**
	 * Sanitize segment data from form
	 *
	 * @param array $data Raw form data
	 * @return array Sanitized data
	 */
	private function sanitize_segment_data( array $data ): array {
		return [
			'name' => sanitize_text_field( $data['segment_name'] ?? '' ),
			'description' => sanitize_textarea_field( $data['segment_description'] ?? '' ),
			'client_id' => (int) ( $data['client_id'] ?? 0 ),
			'is_active' => isset( $data['is_active'] ) && $data['is_active'] === '1',
			'rules' => $this->sanitize_rules( $data['rules'] ?? [] ),
		];
	}

	/**
	 * Sanitize rules data
	 *
	 * @param array $rules Raw rules data
	 * @return array Sanitized rules
	 */
	private function sanitize_rules( array $rules ): array {
		$sanitized = [
			'logic' => in_array( $rules['logic'] ?? '', [ 'AND', 'OR' ] ) ? $rules['logic'] : 'AND',
			'conditions' => [],
		];

		if ( isset( $rules['conditions'] ) && is_array( $rules['conditions'] ) ) {
			foreach ( $rules['conditions'] as $condition ) {
				if ( ! empty( $condition['type'] ) && ! empty( $condition['field'] ) && ! empty( $condition['operator'] ) ) {
					$sanitized['conditions'][] = [
						'type' => sanitize_text_field( $condition['type'] ),
						'field' => sanitize_text_field( $condition['field'] ),
						'operator' => sanitize_text_field( $condition['operator'] ),
						'value' => sanitize_text_field( $condition['value'] ?? '' ),
					];
				}
			}
		}

		return $sanitized;
	}

	/**
	 * AJAX evaluate segment
	 *
	 * @return void
	 */
	private function ajax_evaluate_segment(): void {
		$segment_id = (int) $_POST['segment_id'];
		$segment = AudienceSegment::load_by_id( $segment_id );

		if ( ! $segment ) {
			wp_send_json_error( __( 'Segmento non trovato', 'fp-digital-marketing' ) );
		}

		$results = SegmentationEngine::evaluate_segment( $segment );

		wp_send_json_success( [
			'message' => sprintf(
				__( 'Segmento valutato. %d membri trovati.', 'fp-digital-marketing' ),
				$results['member_count']
			),
			'results' => $results,
		] );
	}

	/**
	 * AJAX get segment members
	 *
	 * @return void
	 */
	private function ajax_get_segment_members(): void {
		$segment_id = (int) $_POST['segment_id'];
		$page = (int) ( $_POST['page'] ?? 1 );
		$per_page = 20;
		$offset = ( $page - 1 ) * $per_page;

		$members = AudienceSegmentTable::get_segment_members( $segment_id, $per_page, $offset );
		$total_count = AudienceSegmentTable::get_segment_member_count( $segment_id );

		wp_send_json_success( [
			'members' => $members,
			'total' => $total_count,
			'page' => $page,
			'per_page' => $per_page,
		] );
	}

	/**
	 * AJAX preview segment
	 *
	 * @return void
	 */
	private function ajax_preview_segment(): void {
		$rules = $this->sanitize_rules( $_POST['rules'] ?? [] );
		$client_id = (int) $_POST['client_id'];

		if ( empty( $rules['conditions'] ) || ! $client_id ) {
			wp_send_json_error( __( 'Regole o cliente non validi', 'fp-digital-marketing' ) );
		}

		// Create temporary segment for preview
		$temp_segment = new AudienceSegment( [
			'name' => 'Preview',
			'client_id' => $client_id,
			'rules' => $rules,
			'is_active' => true,
		] );

		// Get a small sample of users to estimate
		$sample_users = array_slice( SegmentationEngine::get_unique_users_for_client( $client_id ), 0, 100 );
		$matching_users = 0;

		foreach ( $sample_users as $user_id ) {
			if ( SegmentationEngine::evaluate_user_against_segment( $user_id, $temp_segment ) ) {
				$matching_users++;
			}
		}

		$estimated_total = count( $sample_users ) > 0 
			? round( ( $matching_users / count( $sample_users ) ) * count( SegmentationEngine::get_unique_users_for_client( $client_id ) ) )
			: 0;

		wp_send_json_success( [
			'preview' => sprintf(
				__( 'Anteprima: circa %d utenti corrispondono ai criteri (basato su campione di %d utenti)', 'fp-digital-marketing' ),
				$estimated_total,
				count( $sample_users )
			),
		] );
	}

	/**
	 * Alias method for MenuManager compatibility
	 * Renders the segmentation page (same as render_admin_page)
	 *
	 * @return void
	 */
	public function render_segmentation_page(): void {
		$this->render_admin_page();
	}
}