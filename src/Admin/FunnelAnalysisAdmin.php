<?php
/**
 * Funnel Analysis Admin
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Models\Funnel;
use FP\DigitalMarketing\Models\CustomerJourney;
use FP\DigitalMarketing\Database\FunnelTable;
use FP\DigitalMarketing\Database\CustomerJourneyTable;
use FP\DigitalMarketing\Helpers\Security;
use FP\DigitalMarketing\Helpers\Capabilities;

/**
 * Funnel Analysis admin class
 * 
 * Handles the admin interface for funnel analysis and customer journey tracking.
 */
class FunnelAnalysisAdmin {

	/**
	 * Page slug for funnel analysis
	 */
	private const PAGE_SLUG = 'fp-digital-marketing-funnel-analysis';

	/**
	 * Initialize the funnel analysis admin
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_funnel_actions' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'wp_ajax_fp_dms_get_funnel_data', [ $this, 'ajax_get_funnel_data' ] );
		add_action( 'wp_ajax_fp_dms_get_journey_data', [ $this, 'ajax_get_journey_data' ] );
	}

	/**
	 * Add admin menu page
	 * 
	 * Note: This method is disabled when MenuManager is active to prevent
	 * duplicate menu registrations in the rationalized menu structure.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Check if centralized MenuManager is active
		if ( class_exists( '\FP\DigitalMarketing\Admin\MenuManager' ) ) {
			// MenuManager will handle menu registration
			return;
		}

		// Legacy menu registration (fallback)
		add_submenu_page(
			'fp-digital-marketing',
			__( 'Funnel Analysis', 'fp-digital-marketing' ),
			__( 'Funnel Analysis', 'fp-digital-marketing' ),
			Capabilities::FUNNEL_ANALYSIS,
			self::PAGE_SLUG,
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Handle funnel-related form submissions
	 *
	 * @return void
	 */
	public function handle_funnel_actions(): void {
		if ( ! isset( $_POST['fp_dms_funnel_action'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['fp_dms_nonce'] ?? '', 'fp_dms_funnel_action' ) ) {
			wp_die( __( 'Security check failed.', 'fp-digital-marketing' ) );
		}

		if ( ! current_user_can( Capabilities::FUNNEL_ANALYSIS ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'fp-digital-marketing' ) );
		}

		$action = sanitize_text_field( $_POST['fp_dms_funnel_action'] );

		switch ( $action ) {
			case 'create_funnel':
				$this->handle_create_funnel();
				break;
			case 'update_funnel':
				$this->handle_update_funnel();
				break;
			case 'delete_funnel':
				$this->handle_delete_funnel();
				break;
			case 'add_stage':
				$this->handle_add_stage();
				break;
		}
	}

	/**
	 * Handle funnel creation
	 *
	 * @return void
	 */
	private function handle_create_funnel(): void {
		$funnel_data = [
			'name' => sanitize_text_field( $_POST['funnel_name'] ?? '' ),
			'description' => sanitize_textarea_field( $_POST['funnel_description'] ?? '' ),
			'client_id' => (int) ( $_POST['client_id'] ?? 0 ),
			'status' => sanitize_text_field( $_POST['funnel_status'] ?? 'draft' ),
			'conversion_window_days' => (int) ( $_POST['conversion_window_days'] ?? 30 ),
			'attribution_model' => sanitize_text_field( $_POST['attribution_model'] ?? 'last_click' ),
		];

		if ( empty( $funnel_data['name'] ) || empty( $funnel_data['client_id'] ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__( 'Funnel name and client selection are required.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
			return;
		}

		$funnel = new Funnel( $funnel_data );
		
		if ( $funnel->save() ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . 
					esc_html__( 'Funnel created successfully.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__( 'Failed to create funnel.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
		}
	}

	/**
	 * Handle funnel update
	 *
	 * @return void
	 */
	private function handle_update_funnel(): void {
		$funnel_id = (int) ( $_POST['funnel_id'] ?? 0 );
		$funnel = Funnel::load_by_id( $funnel_id );

		if ( ! $funnel ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__( 'Funnel not found.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
			return;
		}

		$funnel->set_name( sanitize_text_field( $_POST['funnel_name'] ?? '' ) );
		$funnel->set_description( sanitize_textarea_field( $_POST['funnel_description'] ?? '' ) );
		$funnel->set_status( sanitize_text_field( $_POST['funnel_status'] ?? 'draft' ) );
		$funnel->set_conversion_window_days( (int) ( $_POST['conversion_window_days'] ?? 30 ) );
		$funnel->set_attribution_model( sanitize_text_field( $_POST['attribution_model'] ?? 'last_click' ) );

		if ( $funnel->save() ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . 
					esc_html__( 'Funnel updated successfully.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__( 'Failed to update funnel.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
		}
	}

	/**
	 * Handle funnel deletion
	 *
	 * @return void
	 */
	private function handle_delete_funnel(): void {
		$funnel_id = (int) ( $_POST['funnel_id'] ?? 0 );
		$funnel = Funnel::load_by_id( $funnel_id );

		if ( ! $funnel ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__( 'Funnel not found.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
			return;
		}

		if ( $funnel->delete() ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . 
					esc_html__( 'Funnel deleted successfully.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__( 'Failed to delete funnel.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
		}
	}

	/**
	 * Handle adding stage to funnel
	 *
	 * @return void
	 */
	private function handle_add_stage(): void {
		$funnel_id = (int) ( $_POST['funnel_id'] ?? 0 );
		$funnel = Funnel::load_by_id( $funnel_id );

		if ( ! $funnel ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__( 'Funnel not found.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
			return;
		}

		$stage_data = [
			'name' => sanitize_text_field( $_POST['stage_name'] ?? '' ),
			'description' => sanitize_textarea_field( $_POST['stage_description'] ?? '' ),
			'event_type' => sanitize_text_field( $_POST['stage_event_type'] ?? '' ),
			'event_conditions' => $_POST['stage_conditions'] ?? [],
			'required_attributes' => $_POST['stage_attributes'] ?? [],
		];

		if ( empty( $stage_data['name'] ) || empty( $stage_data['event_type'] ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__( 'Stage name and event type are required.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
			return;
		}

		if ( $funnel->add_stage( $stage_data ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . 
					esc_html__( 'Stage added successfully.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__( 'Failed to add stage.', 'fp-digital-marketing' ) . 
					'</p></div>';
			} );
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook_suffix Current admin page hook suffix
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook_suffix ): void {
		if ( strpos( $hook_suffix, self::PAGE_SLUG ) === false ) {
			return;
		}

		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true );
		wp_enqueue_script(
			'fp-dms-funnel-analysis',
			FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/js/funnel-analysis.js',
			[ 'jquery', 'chart-js' ],
			FP_DIGITAL_MARKETING_VERSION,
			true
		);

		wp_localize_script( 'fp-dms-funnel-analysis', 'fpDmsFunnelAjax', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'fp_dms_funnel_ajax' ),
		] );

		wp_enqueue_style(
			'fp-dms-funnel-analysis',
			FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/css/funnel-analysis.css',
			[],
			FP_DIGITAL_MARKETING_VERSION
		);
	}

	/**
	 * AJAX handler for getting funnel data
	 *
	 * @return void
	 */
	public function ajax_get_funnel_data(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'fp_dms_funnel_ajax' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( Capabilities::FUNNEL_ANALYSIS ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$funnel_id = (int) ( $_POST['funnel_id'] ?? 0 );
		$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
		$end_date = sanitize_text_field( $_POST['end_date'] ?? '' );

		$funnel = Funnel::load_by_id( $funnel_id );
		
		if ( ! $funnel ) {
			wp_send_json_error( 'Funnel not found' );
		}

		$filters = [];
		if ( $start_date ) {
			$filters['start_date'] = $start_date;
		}
		if ( $end_date ) {
			$filters['end_date'] = $end_date;
		}

		$conversion_data = $funnel->get_conversion_analysis( $filters );
		$dropoff_data = $funnel->get_dropoff_analysis( $filters );
		$time_analysis = $funnel->get_time_analysis( $filters );

		wp_send_json_success( [
			'conversion_data' => $conversion_data,
			'dropoff_data' => $dropoff_data,
			'time_analysis' => $time_analysis,
		] );
	}

	/**
	 * AJAX handler for getting journey data
	 *
	 * @return void
	 */
	public function ajax_get_journey_data(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'fp_dms_funnel_ajax' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( Capabilities::FUNNEL_ANALYSIS ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
		$client_id = (int) ( $_POST['client_id'] ?? 0 );

		if ( empty( $session_id ) || empty( $client_id ) ) {
			wp_send_json_error( 'Session ID and Client ID are required' );
		}

		$journey = CustomerJourney::load_by_session( $session_id, $client_id );
		
		if ( ! $journey ) {
			wp_send_json_error( 'Journey not found' );
		}

		wp_send_json_success( $journey->to_array() );
	}

	/**
	 * Render the admin page
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		$current_tab = sanitize_text_field( $_GET['tab'] ?? 'funnels' );
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Funnel Analysis & Customer Journey', 'fp-digital-marketing' ); ?></h1>
			
			<nav class="nav-tab-wrapper">
				<a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=funnels" 
				   class="nav-tab <?php echo $current_tab === 'funnels' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Funnels', 'fp-digital-marketing' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=journeys" 
				   class="nav-tab <?php echo $current_tab === 'journeys' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Customer Journeys', 'fp-digital-marketing' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=analytics" 
				   class="nav-tab <?php echo $current_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Analytics', 'fp-digital-marketing' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $current_tab ) {
					case 'funnels':
						$this->render_funnels_tab();
						break;
					case 'journeys':
						$this->render_journeys_tab();
						break;
					case 'analytics':
						$this->render_analytics_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render funnels management tab
	 *
	 * @return void
	 */
	private function render_funnels_tab(): void {
		$action = sanitize_text_field( $_GET['action'] ?? 'list' );
		$funnel_id = (int) ( $_GET['funnel_id'] ?? 0 );

		switch ( $action ) {
			case 'edit':
				$this->render_edit_funnel_form( $funnel_id );
				break;
			case 'create':
				$this->render_create_funnel_form();
				break;
			default:
				$this->render_funnels_list();
				break;
		}
	}

	/**
	 * Render funnels list
	 *
	 * @return void
	 */
	private function render_funnels_list(): void {
		// Get all clients for filtering
		$clients = get_posts( [
			'post_type' => 'cliente',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		$selected_client = (int) ( $_GET['client_id'] ?? 0 );
		$funnels = [];

		if ( $selected_client ) {
			$funnels = Funnel::get_client_funnels( $selected_client );
		}

		?>
		<div class="funnel-list-header">
			<div class="alignleft">
				<h2><?php esc_html_e( 'Conversion Funnels', 'fp-digital-marketing' ); ?></h2>
			</div>
			<div class="alignright">
				<a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=funnels&action=create" 
				   class="button button-primary">
					<?php esc_html_e( 'Create New Funnel', 'fp-digital-marketing' ); ?>
				</a>
			</div>
			<div class="clear"></div>
		</div>

		<form method="get" class="funnel-filter-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
			<input type="hidden" name="tab" value="funnels">
			
			<label for="client_id"><?php esc_html_e( 'Select Client:', 'fp-digital-marketing' ); ?></label>
			<select name="client_id" id="client_id" onchange="this.form.submit();">
				<option value=""><?php esc_html_e( 'Select a client...', 'fp-digital-marketing' ); ?></option>
				<?php foreach ( $clients as $client ) : ?>
					<option value="<?php echo esc_attr( $client->ID ); ?>" 
							<?php selected( $selected_client, $client->ID ); ?>>
						<?php echo esc_html( $client->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>

		<?php if ( $selected_client && ! empty( $funnels ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Stages', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Status', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Attribution Model', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Created', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'fp-digital-marketing' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $funnels as $funnel ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $funnel->get_name() ); ?></strong>
								<?php if ( $funnel->get_description() ) : ?>
									<br><small><?php echo esc_html( $funnel->get_description() ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo count( $funnel->get_stages() ); ?></td>
							<td>
								<span class="status status-<?php echo esc_attr( $funnel->get_status() ); ?>">
									<?php echo esc_html( ucfirst( $funnel->get_status() ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $funnel->get_attribution_model() ) ) ); ?></td>
							<td><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $funnel->get_created_at() ) ) ); ?></td>
							<td>
								<a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=funnels&action=edit&funnel_id=<?php echo esc_attr( $funnel->get_id() ); ?>" 
								   class="button button-small">
									<?php esc_html_e( 'Edit', 'fp-digital-marketing' ); ?>
								</a>
								<button type="button" class="button button-small view-funnel-analysis" 
										data-funnel-id="<?php echo esc_attr( $funnel->get_id() ); ?>">
									<?php esc_html_e( 'Analyze', 'fp-digital-marketing' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php elseif ( $selected_client ) : ?>
			<p><?php esc_html_e( 'No funnels found for this client.', 'fp-digital-marketing' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Please select a client to view their funnels.', 'fp-digital-marketing' ); ?></p>
		<?php endif; ?>

		<div id="funnel-analysis-modal" class="fp-modal" style="display: none;">
			<div class="fp-modal-content">
				<span class="fp-modal-close">&times;</span>
				<h2><?php esc_html_e( 'Funnel Analysis', 'fp-digital-marketing' ); ?></h2>
				<div class="funnel-analysis-content">
					<div class="date-filters">
						<label for="analysis-start-date"><?php esc_html_e( 'Start Date:', 'fp-digital-marketing' ); ?></label>
						<input type="date" id="analysis-start-date" value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>">
						
						<label for="analysis-end-date"><?php esc_html_e( 'End Date:', 'fp-digital-marketing' ); ?></label>
						<input type="date" id="analysis-end-date" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
						
						<button type="button" id="refresh-analysis" class="button button-primary">
							<?php esc_html_e( 'Refresh Analysis', 'fp-digital-marketing' ); ?>
						</button>
					</div>
					
					<div class="analysis-charts">
						<div class="chart-container">
							<h3><?php esc_html_e( 'Conversion Funnel', 'fp-digital-marketing' ); ?></h3>
							<canvas id="funnel-chart"></canvas>
						</div>
						
						<div class="chart-container">
							<h3><?php esc_html_e( 'Drop-off Analysis', 'fp-digital-marketing' ); ?></h3>
							<canvas id="dropoff-chart"></canvas>
						</div>
						
						<div class="time-analysis">
							<h3><?php esc_html_e( 'Time to Conversion', 'fp-digital-marketing' ); ?></h3>
							<div id="time-analysis-content"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render create funnel form
	 *
	 * @return void
	 */
	private function render_create_funnel_form(): void {
		$clients = get_posts( [
			'post_type' => 'cliente',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		?>
		<h2><?php esc_html_e( 'Create New Funnel', 'fp-digital-marketing' ); ?></h2>
		
		<form method="post" action="">
			<?php wp_nonce_field( 'fp_dms_funnel_action', 'fp_dms_nonce' ); ?>
			<input type="hidden" name="fp_dms_funnel_action" value="create_funnel">
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="funnel_name"><?php esc_html_e( 'Funnel Name', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input type="text" id="funnel_name" name="funnel_name" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="funnel_description"><?php esc_html_e( 'Description', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<textarea id="funnel_description" name="funnel_description" rows="3" class="large-text"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="client_id"><?php esc_html_e( 'Client', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<select id="client_id" name="client_id" required>
							<option value=""><?php esc_html_e( 'Select a client...', 'fp-digital-marketing' ); ?></option>
							<?php foreach ( $clients as $client ) : ?>
								<option value="<?php echo esc_attr( $client->ID ); ?>">
									<?php echo esc_html( $client->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="funnel_status"><?php esc_html_e( 'Status', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<select id="funnel_status" name="funnel_status">
							<?php foreach ( Funnel::get_statuses() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>">
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="conversion_window_days"><?php esc_html_e( 'Conversion Window (Days)', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input type="number" id="conversion_window_days" name="conversion_window_days" value="30" min="1" max="365">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="attribution_model"><?php esc_html_e( 'Attribution Model', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<select id="attribution_model" name="attribution_model">
							<?php foreach ( Funnel::get_attribution_models() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>">
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Create Funnel', 'fp-digital-marketing' ); ?>">
				<a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=funnels" class="button">
					<?php esc_html_e( 'Cancel', 'fp-digital-marketing' ); ?>
				</a>
			</p>
		</form>
		<?php
	}

	/**
	 * Render edit funnel form
	 *
	 * @param int $funnel_id Funnel ID
	 * @return void
	 */
	private function render_edit_funnel_form( int $funnel_id ): void {
		$funnel = Funnel::load_by_id( $funnel_id );
		
		if ( ! $funnel ) {
			echo '<p>' . esc_html__( 'Funnel not found.', 'fp-digital-marketing' ) . '</p>';
			return;
		}

		?>
		<h2><?php esc_html_e( 'Edit Funnel', 'fp-digital-marketing' ); ?>: <?php echo esc_html( $funnel->get_name() ); ?></h2>
		
		<form method="post" action="">
			<?php wp_nonce_field( 'fp_dms_funnel_action', 'fp_dms_nonce' ); ?>
			<input type="hidden" name="fp_dms_funnel_action" value="update_funnel">
			<input type="hidden" name="funnel_id" value="<?php echo esc_attr( $funnel->get_id() ); ?>">
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="funnel_name"><?php esc_html_e( 'Funnel Name', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input type="text" id="funnel_name" name="funnel_name" class="regular-text" 
							   value="<?php echo esc_attr( $funnel->get_name() ); ?>" required>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="funnel_description"><?php esc_html_e( 'Description', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<textarea id="funnel_description" name="funnel_description" rows="3" class="large-text"><?php echo esc_textarea( $funnel->get_description() ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="funnel_status"><?php esc_html_e( 'Status', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<select id="funnel_status" name="funnel_status">
							<?php foreach ( Funnel::get_statuses() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" 
										<?php selected( $funnel->get_status(), $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="conversion_window_days"><?php esc_html_e( 'Conversion Window (Days)', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input type="number" id="conversion_window_days" name="conversion_window_days" 
							   value="<?php echo esc_attr( $funnel->get_conversion_window_days() ); ?>" min="1" max="365">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="attribution_model"><?php esc_html_e( 'Attribution Model', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<select id="attribution_model" name="attribution_model">
							<?php foreach ( Funnel::get_attribution_models() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" 
										<?php selected( $funnel->get_attribution_model(), $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Update Funnel', 'fp-digital-marketing' ); ?>">
				<a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=funnels" class="button">
					<?php esc_html_e( 'Cancel', 'fp-digital-marketing' ); ?>
				</a>
			</p>
		</form>

		<h3><?php esc_html_e( 'Funnel Stages', 'fp-digital-marketing' ); ?></h3>
		
		<?php $stages = $funnel->get_stages(); ?>
		<?php if ( ! empty( $stages ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Name', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Event Type', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'fp-digital-marketing' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stages as $stage ) : ?>
						<tr>
							<td><?php echo esc_html( $stage['stage_order'] ); ?></td>
							<td>
								<strong><?php echo esc_html( $stage['name'] ); ?></strong>
								<?php if ( $stage['description'] ) : ?>
									<br><small><?php echo esc_html( $stage['description'] ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $stage['event_type'] ); ?></td>
							<td>
								<form method="post" style="display: inline;" 
									  onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this stage?', 'fp-digital-marketing' ); ?>');">
									<?php wp_nonce_field( 'fp_dms_funnel_action', 'fp_dms_nonce' ); ?>
									<input type="hidden" name="fp_dms_funnel_action" value="delete_stage">
									<input type="hidden" name="stage_id" value="<?php echo esc_attr( $stage['id'] ); ?>">
									<input type="submit" class="button button-small button-link-delete" 
										   value="<?php esc_attr_e( 'Delete', 'fp-digital-marketing' ); ?>">
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No stages defined for this funnel yet.', 'fp-digital-marketing' ); ?></p>
		<?php endif; ?>

		<h4><?php esc_html_e( 'Add New Stage', 'fp-digital-marketing' ); ?></h4>
		<form method="post" action="">
			<?php wp_nonce_field( 'fp_dms_funnel_action', 'fp_dms_nonce' ); ?>
			<input type="hidden" name="fp_dms_funnel_action" value="add_stage">
			<input type="hidden" name="funnel_id" value="<?php echo esc_attr( $funnel->get_id() ); ?>">
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="stage_name"><?php esc_html_e( 'Stage Name', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input type="text" id="stage_name" name="stage_name" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="stage_description"><?php esc_html_e( 'Description', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<textarea id="stage_description" name="stage_description" rows="2" class="large-text"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="stage_event_type"><?php esc_html_e( 'Event Type', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<select id="stage_event_type" name="stage_event_type" required>
							<option value=""><?php esc_html_e( 'Select event type...', 'fp-digital-marketing' ); ?></option>
							<option value="pageview"><?php esc_html_e( 'Page View', 'fp-digital-marketing' ); ?></option>
							<option value="signup"><?php esc_html_e( 'Sign Up', 'fp-digital-marketing' ); ?></option>
							<option value="lead_submit"><?php esc_html_e( 'Lead Submit', 'fp-digital-marketing' ); ?></option>
							<option value="purchase"><?php esc_html_e( 'Purchase', 'fp-digital-marketing' ); ?></option>
							<option value="conversion"><?php esc_html_e( 'Conversion', 'fp-digital-marketing' ); ?></option>
							<option value="custom"><?php esc_html_e( 'Custom Event', 'fp-digital-marketing' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" name="submit" class="button button-secondary" value="<?php esc_attr_e( 'Add Stage', 'fp-digital-marketing' ); ?>">
			</p>
		</form>
		<?php
	}

	/**
	 * Render customer journeys tab
	 *
	 * @return void
	 */
	private function render_journeys_tab(): void {
		// Get all clients for filtering
		$clients = get_posts( [
			'post_type' => 'cliente',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		$selected_client = (int) ( $_GET['client_id'] ?? 0 );
		$start_date = sanitize_text_field( $_GET['start_date'] ?? date( 'Y-m-d', strtotime( '-7 days' ) ) );
		$end_date = sanitize_text_field( $_GET['end_date'] ?? date( 'Y-m-d' ) );

		?>
		<h2><?php esc_html_e( 'Customer Journeys', 'fp-digital-marketing' ); ?></h2>
		
		<form method="get" class="journey-filter-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
			<input type="hidden" name="tab" value="journeys">
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="client_id"><?php esc_html_e( 'Client', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<select name="client_id" id="client_id">
							<option value=""><?php esc_html_e( 'Select a client...', 'fp-digital-marketing' ); ?></option>
							<?php foreach ( $clients as $client ) : ?>
								<option value="<?php echo esc_attr( $client->ID ); ?>" 
										<?php selected( $selected_client, $client->ID ); ?>>
									<?php echo esc_html( $client->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="start_date"><?php esc_html_e( 'Start Date', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input type="date" id="start_date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="end_date"><?php esc_html_e( 'End Date', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input type="date" id="end_date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>">
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Filter Journeys', 'fp-digital-marketing' ); ?>">
			</p>
		</form>

		<?php if ( $selected_client ) : ?>
			<?php
			$sessions = CustomerJourneyTable::get_journey_sessions( [
				'client_id' => $selected_client,
				'start_date' => $start_date,
				'end_date' => $end_date,
			], 50 );
			?>
			
			<?php if ( ! empty( $sessions ) ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Session ID', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'User ID', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Events', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Duration', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Value', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Converted', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Acquisition', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'fp-digital-marketing' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sessions as $session ) : ?>
							<tr>
								<td>
									<code><?php echo esc_html( substr( $session['session_id'], 0, 8 ) ); ?>...</code>
								</td>
								<td><?php echo esc_html( $session['user_id'] ?: '-' ); ?></td>
								<td><?php echo esc_html( $session['total_events'] ); ?></td>
								<td><?php echo esc_html( gmdate( 'H:i:s', $session['session_duration_seconds'] ) ); ?></td>
								<td>
									<?php echo esc_html( number_format( $session['total_value'], 2 ) ); ?> 
									<?php echo esc_html( $session['currency'] ); ?>
								</td>
								<td>
									<?php if ( $session['converted'] ) : ?>
										<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
									<?php else : ?>
										<span class="dashicons dashicons-no-alt" style="color: red;"></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$acquisition = $session['acquisition_source'] ?? 'direct';
									if ( $session['acquisition_medium'] ) {
										$acquisition .= '/' . $session['acquisition_medium'];
									}
									echo esc_html( $acquisition );
									?>
								</td>
								<td>
									<button type="button" class="button button-small view-journey-details" 
											data-session-id="<?php echo esc_attr( $session['session_id'] ); ?>"
											data-client-id="<?php echo esc_attr( $selected_client ); ?>">
										<?php esc_html_e( 'View Details', 'fp-digital-marketing' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No customer journeys found for the selected criteria.', 'fp-digital-marketing' ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Please select a client to view customer journeys.', 'fp-digital-marketing' ); ?></p>
		<?php endif; ?>

		<div id="journey-details-modal" class="fp-modal" style="display: none;">
			<div class="fp-modal-content">
				<span class="fp-modal-close">&times;</span>
				<h2><?php esc_html_e( 'Customer Journey Details', 'fp-digital-marketing' ); ?></h2>
				<div id="journey-details-content">
					<!-- Journey details will be loaded here via AJAX -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render analytics tab
	 *
	 * @return void
	 */
	private function render_analytics_tab(): void {
		?>
		<h2><?php esc_html_e( 'Analytics Dashboard', 'fp-digital-marketing' ); ?></h2>
		<p><?php esc_html_e( 'This section will contain analytics and insights about funnel performance and customer journey patterns.', 'fp-digital-marketing' ); ?></p>
		
		<div class="analytics-widgets">
			<div class="analytics-widget">
				<h3><?php esc_html_e( 'Funnel Performance Overview', 'fp-digital-marketing' ); ?></h3>
				<p><?php esc_html_e( 'Coming soon...', 'fp-digital-marketing' ); ?></p>
			</div>
			
			<div class="analytics-widget">
				<h3><?php esc_html_e( 'Customer Journey Insights', 'fp-digital-marketing' ); ?></h3>
				<p><?php esc_html_e( 'Coming soon...', 'fp-digital-marketing' ); ?></p>
			</div>
			
			<div class="analytics-widget">
				<h3><?php esc_html_e( 'Attribution Analysis', 'fp-digital-marketing' ); ?></h3>
				<p><?php esc_html_e( 'Coming soon...', 'fp-digital-marketing' ); ?></p>
			</div>
		</div>
		<?php
	}
}