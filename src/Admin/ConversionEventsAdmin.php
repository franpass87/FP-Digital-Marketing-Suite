<?php
/**
 * Conversion Events Admin Interface
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\ConversionEventManager;
use FP\DigitalMarketing\Helpers\ConversionEventRegistry;
use FP\DigitalMarketing\Models\ConversionEvent;
use FP\DigitalMarketing\Database\ConversionEventsTable;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Admin\MenuManager;

/**
 * Conversion Events Admin class
 * 
 * Provides admin interface for managing conversion events and goals.
 */
class ConversionEventsAdmin {

	/**
	 * Page slug
	 */
	public const PAGE_SLUG = 'fp-conversion-events';

	/**
	 * Nonce action
	 */
	public const NONCE_ACTION = 'fp_conversion_events_action';

	/**
	 * Initialize the admin interface
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
		add_action( 'wp_ajax_fp_conversion_event_action', [ $this, 'handle_ajax_request' ] );
		add_action( 'wp_ajax_fp_dms_download_export', [ $this, 'handle_download_export' ] );
		add_action( 'fp_dms_cleanup_export_file', [ $this, 'cleanup_export_file' ] );
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
                        __( 'Eventi Conversione', 'fp-digital-marketing' ),
			__( '🎯 Eventi Conversione', 'fp-digital-marketing' ),
			Capabilities::MANAGE_CONVERSIONS,
			self::PAGE_SLUG,
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wp-util' );

		// Conversion Events specific styles
		wp_add_inline_style( 'wp-admin', $this->get_inline_css() );

		// Conversion Events JavaScript
		wp_add_inline_script( 'jquery', $this->get_inline_js() );

		// Localize script for AJAX
		wp_localize_script( 'jquery', 'fpConversionAjax', [
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
			'strings'   => [
				'confirm_delete' => __( 'Sei sicuro di voler eliminare questo evento?', 'fp-digital-marketing' ),
				'processing'     => __( 'Elaborazione in corso...', 'fp-digital-marketing' ),
				'error'          => __( 'Si è verificato un errore. Riprova.', 'fp-digital-marketing' ),
				'success'        => __( 'Operazione completata con successo.', 'fp-digital-marketing' ),
			],
		] );
	}

	/**
	 * Handle form submissions
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		if ( ! isset( $_POST['fp_conversion_action'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', self::NONCE_ACTION ) ) {
			wp_die( __( 'Nonce di sicurezza non valido.', 'fp-digital-marketing' ) );
		}

		// Check capabilities
		if ( ! current_user_can( Capabilities::MANAGE_CONVERSIONS ) ) {
			wp_die( __( 'Non hai i permessi per eseguire questa azione.', 'fp-digital-marketing' ) );
		}

		$action = sanitize_text_field( $_POST['fp_conversion_action'] );

		switch ( $action ) {
			case 'delete_event':
				$this->handle_delete_event();
				break;
			case 'mark_duplicate':
				$this->handle_mark_duplicate();
				break;
			case 'bulk_action':
				$this->handle_bulk_action();
				break;
		}
	}

	/**
	 * Handle AJAX requests
	 *
	 * @return void
	 */
	public function handle_ajax_request(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', self::NONCE_ACTION ) ) {
			wp_send_json_error( __( 'Nonce di sicurezza non valido.', 'fp-digital-marketing' ) );
		}

		// Check capabilities
		if ( ! current_user_can( Capabilities::MANAGE_CONVERSIONS ) ) {
			wp_send_json_error( __( 'Non hai i permessi per eseguire questa azione.', 'fp-digital-marketing' ) );
		}

		$action = sanitize_text_field( $_POST['action_type'] ?? '' );

		switch ( $action ) {
			case 'get_event_details':
				$this->ajax_get_event_details();
				break;
			case 'get_funnel_data':
				$this->ajax_get_funnel_data();
				break;
			case 'export_events':
				$this->ajax_export_events();
				break;
		}

		wp_send_json_error( __( 'Azione non riconosciuta.', 'fp-digital-marketing' ) );
	}

	/**
	 * Render the admin page
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'events';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=events' ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'events' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Eventi', 'fp-digital-marketing' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=analytics' ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Analisi', 'fp-digital-marketing' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=funnel' ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'funnel' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Funnel', 'fp-digital-marketing' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=settings' ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Impostazioni', 'fp-digital-marketing' ); ?>
				</a>
			</h2>

			<div class="fp-conversion-events-content">
				<?php
				switch ( $current_tab ) {
					case 'analytics':
						$this->render_analytics_tab();
						break;
					case 'funnel':
						$this->render_funnel_tab();
						break;
					case 'settings':
						$this->render_settings_tab();
						break;
					default:
						$this->render_events_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render events tab
	 *
	 * @return void
	 */
	private function render_events_tab(): void {
		// Get filter parameters
		$client_id = isset( $_GET['client_id'] ) ? (int) $_GET['client_id'] : 0;
		$event_type = isset( $_GET['event_type'] ) ? sanitize_text_field( $_GET['event_type'] ) : '';
		$source = isset( $_GET['source'] ) ? sanitize_text_field( $_GET['source'] ) : '';
		$period_start = isset( $_GET['period_start'] ) ? sanitize_text_field( $_GET['period_start'] ) : '';
		$period_end = isset( $_GET['period_end'] ) ? sanitize_text_field( $_GET['period_end'] ) : '';

		// Build criteria
		$criteria = [];
		if ( $client_id > 0 ) $criteria['client_id'] = $client_id;
		if ( ! empty( $event_type ) ) $criteria['event_type'] = $event_type;
		if ( ! empty( $source ) ) $criteria['source'] = $source;
		if ( ! empty( $period_start ) ) $criteria['period_start'] = $period_start . ' 00:00:00';
		if ( ! empty( $period_end ) ) $criteria['period_end'] = $period_end . ' 23:59:59';

		// Get events
		$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page = 20;
		$offset = ( $page - 1 ) * $per_page;

		$options = [
			'limit' => $per_page,
			'offset' => $offset,
			'include_summary' => true,
		];

		$results = ConversionEventManager::query_events( $criteria, $options );
		?>
		<div class="fp-events-filters">
			<form method="get" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<input type="hidden" name="tab" value="events" />
				
				<div class="filters-row">
					<label for="client_id"><?php esc_html_e( 'Cliente:', 'fp-digital-marketing' ); ?></label>
					<select name="client_id" id="client_id">
						<option value="0"><?php esc_html_e( 'Tutti i clienti', 'fp-digital-marketing' ); ?></option>
						<?php
						// Get clients (assuming clients are stored as posts)
						$clients = get_posts( [
							'post_type' => 'cliente',
							'numberposts' => -1,
							'post_status' => 'publish',
						] );
						foreach ( $clients as $client ) {
							echo '<option value="' . esc_attr( $client->ID ) . '"' . selected( $client_id, $client->ID, false ) . '>' . esc_html( $client->post_title ) . '</option>';
						}
						?>
					</select>

					<label for="event_type"><?php esc_html_e( 'Tipo Evento:', 'fp-digital-marketing' ); ?></label>
					<select name="event_type" id="event_type">
						<option value=""><?php esc_html_e( 'Tutti i tipi', 'fp-digital-marketing' ); ?></option>
						<?php
						$event_types = ConversionEventRegistry::get_all_event_types();
						foreach ( $event_types as $type => $definition ) {
							echo '<option value="' . esc_attr( $type ) . '"' . selected( $event_type, $type, false ) . '>' . esc_html( $definition['name'] ) . '</option>';
						}
						?>
					</select>

					<label for="period_start"><?php esc_html_e( 'Dal:', 'fp-digital-marketing' ); ?></label>
					<input type="date" name="period_start" id="period_start" value="<?php echo esc_attr( $period_start ); ?>" />

					<label for="period_end"><?php esc_html_e( 'Al:', 'fp-digital-marketing' ); ?></label>
					<input type="date" name="period_end" id="period_end" value="<?php echo esc_attr( $period_end ); ?>" />

					<button type="submit" class="button"><?php esc_html_e( 'Filtra', 'fp-digital-marketing' ); ?></button>
				</div>
			</form>
		</div>

		<?php if ( ! empty( $results['summary'] ) ) : ?>
		<div class="fp-events-summary">
			<div class="summary-cards">
				<div class="summary-card">
					<h3><?php echo esc_html( number_format_i18n( $results['summary']['total_events'] ) ); ?></h3>
					<p><?php esc_html_e( 'Eventi Totali', 'fp-digital-marketing' ); ?></p>
				</div>
				<div class="summary-card">
					<h3><?php echo esc_html( number_format_i18n( $results['summary']['total_value'], 2 ) ); ?> €</h3>
					<p><?php esc_html_e( 'Valore Totale', 'fp-digital-marketing' ); ?></p>
				</div>
				<div class="summary-card">
					<h3><?php echo esc_html( number_format_i18n( $results['summary']['unique_event_types'] ) ); ?></h3>
					<p><?php esc_html_e( 'Tipi Evento', 'fp-digital-marketing' ); ?></p>
				</div>
				<div class="summary-card">
					<h3><?php echo esc_html( number_format_i18n( $results['summary']['duplicate_count'] ) ); ?></h3>
					<p><?php esc_html_e( 'Duplicati', 'fp-digital-marketing' ); ?></p>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<div class="fp-events-table-container">
			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="fp_conversion_action" value="bulk_action" />

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="bulk_action">
							<option value=""><?php esc_html_e( 'Azioni in blocco', 'fp-digital-marketing' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Elimina', 'fp-digital-marketing' ); ?></option>
							<option value="mark_duplicate"><?php esc_html_e( 'Marca come duplicato', 'fp-digital-marketing' ); ?></option>
						</select>
						<button type="submit" class="button action"><?php esc_html_e( 'Applica', 'fp-digital-marketing' ); ?></button>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="cb-select-all-1" />
							</td>
							<th><?php esc_html_e( 'Evento', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Tipo', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Valore', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Sorgente', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Data', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Azioni', 'fp-digital-marketing' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $results['events'] ) ) : ?>
							<?php foreach ( $results['events'] as $event_data ) : ?>
								<tr <?php echo $event_data['is_duplicate'] ? 'class="duplicate-row"' : ''; ?>>
									<th class="check-column">
										<input type="checkbox" name="event_ids[]" value="<?php echo esc_attr( $event_data['id'] ); ?>" />
									</th>
									<td>
										<strong><?php echo esc_html( $event_data['event_name'] ); ?></strong>
										<?php if ( $event_data['is_duplicate'] ) : ?>
											<span class="duplicate-badge"><?php esc_html_e( 'Duplicato', 'fp-digital-marketing' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php
										$client = get_post( $event_data['client_id'] );
										echo $client ? esc_html( $client->post_title ) : __( 'Sconosciuto', 'fp-digital-marketing' );
										?>
									</td>
									<td>
										<?php
										$type_def = ConversionEventRegistry::get_event_type_definition( $event_data['event_type'] );
										echo esc_html( $type_def['name'] ?? $event_data['event_type'] );
										?>
									</td>
									<td><?php echo esc_html( number_format_i18n( $event_data['event_value'], 2 ) . ' ' . $event_data['currency'] ); ?></td>
									<td><?php echo esc_html( $event_data['source'] ); ?></td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $event_data['created_at'] ) ) ); ?></td>
									<td>
										<button type="button" class="button button-small view-event-details" data-event-id="<?php echo esc_attr( $event_data['id'] ); ?>">
											<?php esc_html_e( 'Dettagli', 'fp-digital-marketing' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="8" class="no-events-message">
									<?php esc_html_e( 'Nessun evento trovato con i filtri selezionati.', 'fp-digital-marketing' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>

				<?php
				// Pagination
				if ( $results['page_count'] > 1 ) {
					$pagination_args = [
						'base' => add_query_arg( 'paged', '%#%' ),
						'format' => '',
						'prev_text' => __( '&laquo; Precedente' ),
						'next_text' => __( 'Successivo &raquo;' ),
						'total' => $results['page_count'],
						'current' => $page,
					];
					echo '<div class="tablenav bottom">';
					echo '<div class="tablenav-pages">';
					echo paginate_links( $pagination_args );
					echo '</div>';
					echo '</div>';
				}
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render analytics tab
	 *
	 * @return void
	 */
	private function render_analytics_tab(): void {
		echo '<div class="fp-analytics-tab">';
		echo '<h2>' . esc_html__( 'Analisi Eventi Conversione', 'fp-digital-marketing' ) . '</h2>';
		echo '<p>' . esc_html__( 'Qui saranno mostrati grafici e statistiche sugli eventi di conversione.', 'fp-digital-marketing' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render funnel tab
	 *
	 * @return void
	 */
	private function render_funnel_tab(): void {
		echo '<div class="fp-funnel-tab">';
		echo '<h2>' . esc_html__( 'Analisi Funnel Conversione', 'fp-digital-marketing' ) . '</h2>';
		echo '<p>' . esc_html__( 'Qui sarà mostrata l\'analisi del funnel di conversione.', 'fp-digital-marketing' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render settings tab
	 *
	 * @return void
	 */
	private function render_settings_tab(): void {
		echo '<div class="fp-settings-tab">';
		echo '<h2>' . esc_html__( 'Impostazioni Eventi Conversione', 'fp-digital-marketing' ) . '</h2>';
		echo '<p>' . esc_html__( 'Qui saranno configurabili le impostazioni per la gestione degli eventi.', 'fp-digital-marketing' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Get inline CSS for the admin interface
	 *
	 * @return string CSS styles
	 */
	private function get_inline_css(): string {
		return '
		.fp-events-filters { margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; }
		.filters-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
		.filters-row label { font-weight: bold; }
		.filters-row input, .filters-row select { margin-right: 10px; }
		.fp-events-summary { margin: 20px 0; }
		.summary-cards { display: flex; gap: 20px; flex-wrap: wrap; }
		.summary-card { background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; text-align: center; min-width: 150px; }
		.summary-card h3 { margin: 0 0 10px 0; font-size: 24px; color: #0073aa; }
		.summary-card p { margin: 0; color: #666; }
		.duplicate-row { background-color: #fff2cd !important; }
		.duplicate-badge { background: #d63638; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px; }
		.no-events-message { text-align: center; padding: 40px; color: #666; font-style: italic; }
		.fp-events-table-container { margin-top: 20px; }
		
		/* Event Details Modal */
		.fp-modal { position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
		.fp-modal-content { background-color: #fefefe; margin: 5% auto; padding: 0; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); }
		.fp-modal-header { padding: 20px; background-color: #f1f1f1; border-bottom: 1px solid #ddd; border-radius: 5px 5px 0 0; display: flex; justify-content: space-between; align-items: center; }
		.fp-modal-header h3 { margin: 0; color: #23282d; font-size: 18px; }
		.fp-modal-close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
		.fp-modal-close:hover, .fp-modal-close:focus { color: #000; text-decoration: none; }
		.fp-modal-body { padding: 20px; max-height: 400px; overflow-y: auto; }
		.event-details { }
		.event-detail-row { margin-bottom: 15px; display: flex; align-items: flex-start; }
		.event-detail-row strong { width: 120px; flex-shrink: 0; color: #23282d; }
		.event-detail-row span { flex: 1; }
		.event-detail-row pre { background: #f6f7f7; padding: 10px; border-radius: 3px; border: 1px solid #ddd; font-size: 12px; margin: 0; max-height: 200px; overflow: auto; }
		.event-status { padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
		.event-status-active { background: #d4edda; color: #155724; }
		.event-status-inactive { background: #f8d7da; color: #721c24; }
		.event-status-pending { background: #fff3cd; color: #856404; }
		.fp-loading { text-align: center; padding: 20px; color: #666; }
		';
	}

	/**
	 * Get inline JavaScript for the admin interface
	 *
	 * @return string JavaScript code
	 */
	private function get_inline_js(): string {
		return '
		jQuery(document).ready(function($) {
			// Select all checkbox functionality
			$("#cb-select-all-1").on("change", function() {
				$("input[name=\'event_ids[]\']").prop("checked", this.checked);
			});

			// View event details
			$(".view-event-details").on("click", function() {
				var eventId = $(this).data("event-id");
				showEventDetailsModal(eventId);
			});

			// Bulk actions confirmation
			$("form").on("submit", function(e) {
				var bulkAction = $("select[name=\'bulk_action\']").val();
				var checkedEvents = $("input[name=\'event_ids[]\']:checked").length;

				if (bulkAction && checkedEvents > 0) {
					if (bulkAction === "delete") {
						if (!confirm(fpConversionAjax.strings.confirm_delete)) {
							e.preventDefault();
							return false;
						}
					}
				}
			});
		});

		// Event details modal functionality
		function showEventDetailsModal(eventId) {
			// Create modal if it doesn\'t exist
			if (!$("#event-details-modal").length) {
				$("body").append(`
					<div id="event-details-modal" class="fp-modal" style="display: none;">
						<div class="fp-modal-content">
							<div class="fp-modal-header">
								<h3 id="modal-title">Dettagli Evento</h3>
								<span class="fp-modal-close">&times;</span>
							</div>
							<div class="fp-modal-body" id="modal-body">
								<div class="fp-loading">Caricamento...</div>
							</div>
						</div>
					</div>
				`);

				// Close modal events
				$(document).on("click", ".fp-modal-close, .fp-modal", function(e) {
					if (e.target === this) {
						$("#event-details-modal").hide();
					}
				});

				// Escape key closes modal
				$(document).on("keyup", function(e) {
					if (e.keyCode === 27) { // ESC key
						$("#event-details-modal").hide();
					}
				});
			}

			// Show modal and load data
			$("#event-details-modal").show();
			$("#modal-body").html(`<div class="fp-loading">Caricamento...</div>`);

			// AJAX call to get event details
			$.post(fpConversionAjax.ajax_url, {
				action: "fp_conversion_event_action",
				action_type: "get_event_details",
				event_id: eventId,
				nonce: fpConversionAjax.nonce
			})
			.done(function(response) {
				if (response.success) {
					displayEventDetails(response.data);
				} else {
					$("#modal-body").html(`<div class="notice notice-error"><p>${response.data || "Errore nel caricamento dei dati"}</p></div>`);
				}
			})
			.fail(function() {
				$("#modal-body").html(`<div class="notice notice-error"><p>Errore di connessione</p></div>`);
			});
		}

		function displayEventDetails(event) {
			const html = `
				<div class="event-details">
					<div class="event-detail-row">
						<strong>Nome:</strong>
						<span>${event.name}</span>
					</div>
					<div class="event-detail-row">
						<strong>Tipo:</strong>
						<span>${event.event_type}</span>
					</div>
					<div class="event-detail-row">
						<strong>Cliente:</strong>
						<span>${event.client_id}</span>
					</div>
					<div class="event-detail-row">
						<strong>Valore:</strong>
						<span>${event.value || "N/A"}</span>
					</div>
					<div class="event-detail-row">
						<strong>Data Creazione:</strong>
						<span>${new Date(event.created_at).toLocaleString()}</span>
					</div>
					<div class="event-detail-row">
						<strong>Stato:</strong>
						<span class="event-status event-status-${event.status}">${event.status}</span>
					</div>
					${event.description ? `
						<div class="event-detail-row">
							<strong>Descrizione:</strong>
							<span>${event.description}</span>
						</div>
					` : ""}
					${event.metadata ? `
						<div class="event-detail-row">
							<strong>Metadati:</strong>
							<pre>${JSON.stringify(JSON.parse(event.metadata), null, 2)}</pre>
						</div>
					` : ""}
				</div>
			`;
			$("#modal-body").html(html);
		}
		';
	}

	/**
	 * Handle delete event action
	 *
	 * @return void
	 */
	private function handle_delete_event(): void {
		$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;

               $referer = esc_url_raw( wp_get_referer() );
               if ( empty( $referer ) ) {
                       $referer = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
               }

               if ( $event_id <= 0 ) {
                       wp_safe_redirect( add_query_arg( 'error', 'invalid_id', $referer ) );
                       exit;
               }

               $event = ConversionEvent::load_by_id( $event_id );
               if ( $event && $event->delete() ) {
                       wp_safe_redirect( add_query_arg( 'message', 'deleted', $referer ) );
               } else {
                       wp_safe_redirect( add_query_arg( 'error', 'delete_failed', $referer ) );
               }
               exit;
       }

	/**
	 * Handle mark as duplicate action
	 *
	 * @return void
	 */
	private function handle_mark_duplicate(): void {
               $event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;

               $referer = esc_url_raw( wp_get_referer() );
               if ( empty( $referer ) ) {
                       $referer = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
               }

               if ( $event_id <= 0 ) {
                       wp_safe_redirect( add_query_arg( 'error', 'invalid_id', $referer ) );
                       exit;
               }

               $event = ConversionEvent::load_by_id( $event_id );
               if ( $event && $event->mark_as_duplicate() ) {
                       wp_safe_redirect( add_query_arg( 'message', 'marked_duplicate', $referer ) );
               } else {
                       wp_safe_redirect( add_query_arg( 'error', 'mark_failed', $referer ) );
               }
               exit;
       }

	/**
	 * Handle bulk actions
	 *
	 * @return void
	 */
	private function handle_bulk_action(): void {
		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';
		$event_ids = isset( $_POST['event_ids'] ) ? array_map( 'intval', $_POST['event_ids'] ) : [];

		if ( empty( $bulk_action ) || empty( $event_ids ) ) {
			wp_redirect( add_query_arg( 'error', 'no_selection', wp_get_referer() ) );
			exit;
		}

		$success_count = 0;

		foreach ( $event_ids as $event_id ) {
			$event = ConversionEvent::load_by_id( $event_id );
			if ( ! $event ) {
				continue;
			}

			switch ( $bulk_action ) {
				case 'delete':
					if ( $event->delete() ) {
						$success_count++;
					}
					break;
				case 'mark_duplicate':
					if ( $event->mark_as_duplicate() ) {
						$success_count++;
					}
					break;
			}
		}

		wp_redirect( add_query_arg( [
			'message' => 'bulk_action_completed',
			'count' => $success_count,
		], wp_get_referer() ) );
		exit;
	}

	/**
	 * AJAX: Get event details
	 *
	 * @return void
	 */
	private function ajax_get_event_details(): void {
		$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;

		if ( $event_id <= 0 ) {
			wp_send_json_error( __( 'ID evento non valido.', 'fp-digital-marketing' ) );
		}

		$event = ConversionEvent::load_by_id( $event_id );
		if ( ! $event ) {
			wp_send_json_error( __( 'Evento non trovato.', 'fp-digital-marketing' ) );
		}

		wp_send_json_success( $event->to_array() );
	}

	/**
	 * AJAX: Get funnel data
	 *
	 * @return void
	 */
	private function ajax_get_funnel_data(): void {
		$client_id = isset( $_POST['client_id'] ) ? (int) $_POST['client_id'] : 0;
		$funnel_steps = isset( $_POST['funnel_steps'] ) ? array_map( 'sanitize_text_field', $_POST['funnel_steps'] ) : [];

		if ( $client_id <= 0 || empty( $funnel_steps ) ) {
			wp_send_json_error( __( 'Parametri non validi per l\'analisi funnel.', 'fp-digital-marketing' ) );
		}

		$funnel_data = ConversionEventManager::get_conversion_funnel( $client_id, $funnel_steps );
		wp_send_json_success( $funnel_data );
	}

	/**
	 * AJAX: Export events
	 *
	 * @return void
	 */
	private function ajax_export_events(): void {
		$criteria = isset( $_POST['criteria'] ) ? $_POST['criteria'] : [];
		
		try {
			// Sanitize export criteria
			$client_id = isset( $criteria['client_id'] ) ? (int) $criteria['client_id'] : 0;
			$event_type = isset( $criteria['event_type'] ) ? sanitize_text_field( $criteria['event_type'] ) : '';
			$start_date = isset( $criteria['start_date'] ) ? sanitize_text_field( $criteria['start_date'] ) : '';
			$end_date = isset( $criteria['end_date'] ) ? sanitize_text_field( $criteria['end_date'] ) : '';

                        // Generate unique filename
                        $filename = 'conversion_events_' . date( 'Y-m-d_H-i-s' ) . '.csv';
			$upload_dir = wp_upload_dir();
			$exports_dir = $upload_dir['basedir'] . '/fp-dms-exports/';
			
			// Create exports directory if it doesn't exist
			if ( ! file_exists( $exports_dir ) ) {
				wp_mkdir_p( $exports_dir );
				// Add .htaccess to protect directory
				file_put_contents( $exports_dir . '.htaccess', "Deny from all\n" );
			}
			
			$file_path = $exports_dir . $filename;
			
			// Get events based on criteria
                        $events = $this->get_events_for_export( $client_id, $event_type, $start_date, $end_date );
			
			// Generate CSV content
			$csv_content = $this->generate_csv_content( $events );
			
			// Write to file
			if ( file_put_contents( $file_path, $csv_content ) === false ) {
				wp_send_json_error( __( 'Errore nella creazione del file CSV.', 'fp-digital-marketing' ) );
				return;
			}
			
			// Generate download URL with security token
			$token = wp_create_nonce( 'fp_dms_export_' . $filename );
			$download_url = add_query_arg( [
				'action' => 'fp_dms_download_export',
				'file' => $filename,
				'token' => $token,
			], admin_url( 'admin-ajax.php' ) );
			
			// Schedule file cleanup (remove after 1 hour)
			wp_schedule_single_event( time() + 3600, 'fp_dms_cleanup_export_file', [ $file_path ] );
			
			wp_send_json_success( [ 
				'download_url' => $download_url,
				'filename' => $filename,
				'count' => count( $events ),
			] );
			
		} catch ( \Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FP Digital Marketing: Export error - ' . $e->getMessage() );
			}
			wp_send_json_error( __( 'Si è verificato un errore durante l\'esportazione.', 'fp-digital-marketing' ) );
		}
	}
	
        /**
         * Get events for export based on criteria
         *
         * @param int    $client_id Client ID filter
         * @param string $event_type Event type filter
         * @param string $start_date Start date filter
         * @param string $end_date End date filter
         * @return array Events data
         */
        private function get_events_for_export( int $client_id, string $event_type, string $start_date, string $end_date ): array {
                // This would typically query your ConversionEvent model with the criteria
                // For now, we'll use a mock implementation that shows the structure
                return ConversionEvent::get_events_for_export( [
                        'client_id' => $client_id,
                        'event_type' => $event_type,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                ] );
        }
	
	/**
	 * Generate CSV content from events data
	 *
	 * @param array $events Events data
	 * @return string CSV content
	 */
	private function generate_csv_content( array $events ): string {
		$csv_lines = [];
		
		// CSV headers
                $headers = [
                        __( 'ID', 'fp-digital-marketing' ),
                        __( 'ID Evento', 'fp-digital-marketing' ),
                        __( 'Nome Evento', 'fp-digital-marketing' ),
                        __( 'Tipo', 'fp-digital-marketing' ),
                        __( 'Cliente ID', 'fp-digital-marketing' ),
                        __( 'Sorgente', 'fp-digital-marketing' ),
                        __( 'ID Evento Sorgente', 'fp-digital-marketing' ),
                        __( 'Valore Evento', 'fp-digital-marketing' ),
                        __( 'Valuta', 'fp-digital-marketing' ),
                        __( 'Duplicato', 'fp-digital-marketing' ),
                        __( 'Data Creazione', 'fp-digital-marketing' ),
                        __( 'Data Elaborazione', 'fp-digital-marketing' ),
                ];

                $csv_lines[] = $this->array_to_csv_line( $headers );

                // Add event data
                foreach ( $events as $event ) {
                        $is_duplicate = '';

                        if ( isset( $event['is_duplicate'] ) ) {
                                $is_duplicate = ( (int) $event['is_duplicate'] ) === 1
                                        ? __( 'Sì', 'fp-digital-marketing' )
                                        : __( 'No', 'fp-digital-marketing' );
                        }

                        $row = [
                                $event['id'] ?? '',
                                $event['event_id'] ?? '',
                                $event['event_name'] ?? '',
                                $event['event_type'] ?? '',
                                $event['client_id'] ?? '',
                                $event['source'] ?? '',
                                $event['source_event_id'] ?? '',
                                $event['event_value'] ?? '',
                                $event['currency'] ?? '',
                                $is_duplicate,
                                $event['created_at'] ?? '',
                                $event['processed_at'] ?? '',
                        ];

                        $csv_lines[] = $this->array_to_csv_line( $row );
                }
		
		return implode( "\n", $csv_lines );
	}
	
	/**
	 * Convert array to CSV line
	 *
	 * @param array $data Data array
	 * @return string CSV line
	 */
	private function array_to_csv_line( array $data ): string {
                $escaped_data = array_map( function( $field ) {
                        // Escape quotes and wrap in quotes if necessary
                        $field = (string) $field;
                        $quote = '"';
                        $field = str_replace( $quote, $quote . $quote, $field );
                        if ( strpos( $field, ',' ) !== false || strpos( $field, $quote ) !== false || strpos( $field, "\n" ) !== false ) {
                                $field = $quote . $field . $quote;
                        }
                        return $field;
                }, $data );
		
		return implode( ',', $escaped_data );
	}
	
	/**
	 * Handle export file download
	 *
	 * @return void
	 */
	public function handle_download_export(): void {
		// Check user capabilities
		if ( ! current_user_can( Capabilities::MANAGE_CONVERSIONS ) ) {
			wp_die( __( 'Non hai i permessi per accedere a questo file.', 'fp-digital-marketing' ) );
		}
		
		$filename = sanitize_file_name( $_GET['file'] ?? '' );
		$token = sanitize_text_field( $_GET['token'] ?? '' );
		
		// Verify security token
		if ( ! wp_verify_nonce( $token, 'fp_dms_export_' . $filename ) ) {
			wp_die( __( 'Token di sicurezza non valido.', 'fp-digital-marketing' ) );
		}
		
		$upload_dir = wp_upload_dir();
		$file_path = $upload_dir['basedir'] . '/fp-dms-exports/' . $filename;
		
		// Check if file exists
		if ( ! file_exists( $file_path ) ) {
			wp_die( __( 'File non trovato o scaduto.', 'fp-digital-marketing' ) );
		}
		
		// Set headers for download
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		
		// Output file content
		readfile( $file_path );
		
		// Clean up file after download
		unlink( $file_path );
		
		exit;
	}
	
	/**
	 * Cleanup export file (scheduled task)
	 *
	 * @param string $file_path File path to cleanup
	 * @return void
	 */
	public function cleanup_export_file( string $file_path ): void {
		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}
	}
}
