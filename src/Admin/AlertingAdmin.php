<?php
/**
 * Alerting Admin Interface
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Models\AlertRule;
use FP\DigitalMarketing\Helpers\AlertEngine;
use FP\DigitalMarketing\Helpers\MetricsSchema;
use FP\DigitalMarketing\Helpers\Capabilities;

/**
 * AlertingAdmin class for managing alert rules interface
 */
class AlertingAdmin {

	/**
	 * Initialize the admin interface
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
		add_action( 'admin_notices', [ $this, 'display_alert_notices' ] );
		add_action( 'wp_ajax_dismiss_alert_notice', [ $this, 'dismiss_alert_notice' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=cliente',
			__( 'Alert e Notifiche', 'fp-digital-marketing' ),
			__( 'Alert e Notifiche', 'fp-digital-marketing' ),
			Capabilities::MANAGE_ALERTS,
			'fp-digital-marketing-alerts',
			[ $this, 'display_admin_page' ]
		);
	}

	/**
	 * Handle form submissions
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		if ( ! isset( $_POST['fp_dms_nonce'] ) || ! wp_verify_nonce( $_POST['fp_dms_nonce'], 'fp_dms_alerts' ) ) {
			return;
		}

		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_ALERTS ) ) {
			return;
		}

		$action = $_POST['action'] ?? '';

		switch ( $action ) {
			case 'add_rule':
				$this->handle_add_rule();
				break;
			case 'edit_rule':
				$this->handle_edit_rule();
				break;
			case 'delete_rule':
				$this->handle_delete_rule();
				break;
			case 'toggle_rule':
				$this->handle_toggle_rule();
				break;
		}
	}

	/**
	 * Handle adding a new rule
	 *
	 * @return void
	 */
	private function handle_add_rule(): void {
		$client_id = (int) ( $_POST['client_id'] ?? 0 );
		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$metric = sanitize_text_field( $_POST['metric'] ?? '' );
		$condition = sanitize_text_field( $_POST['condition'] ?? '' );
		$threshold_value = (float) ( $_POST['threshold_value'] ?? 0 );
		$description = sanitize_textarea_field( $_POST['description'] ?? '' );
		$notification_email = sanitize_email( $_POST['notification_email'] ?? '' );
		$notification_admin_notice = isset( $_POST['notification_admin_notice'] );

		// Validation
		if ( empty( $name ) || empty( $metric ) || empty( $condition ) || $client_id <= 0 ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Tutti i campi obbligatori devono essere compilati.', 'fp-digital-marketing' ) . '</p></div>';
			} );
			return;
		}

		if ( ! AlertRule::is_valid_condition( $condition ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Condizione non valida.', 'fp-digital-marketing' ) . '</p></div>';
			} );
			return;
		}

		$rule_id = AlertRule::save(
			$client_id,
			$name,
			$metric,
			$condition,
			$threshold_value,
			$description,
			$notification_email,
			$notification_admin_notice
		);

		if ( $rule_id ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Regola di alert creata con successo.', 'fp-digital-marketing' ) . '</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Errore durante la creazione della regola.', 'fp-digital-marketing' ) . '</p></div>';
			} );
		}
	}

	/**
	 * Handle editing a rule
	 *
	 * @return void
	 */
	private function handle_edit_rule(): void {
		$rule_id = (int) ( $_POST['rule_id'] ?? 0 );
		
		if ( $rule_id <= 0 ) {
			return;
		}

		$data = [
			'name' => sanitize_text_field( $_POST['name'] ?? '' ),
			'metric' => sanitize_text_field( $_POST['metric'] ?? '' ),
			'condition' => sanitize_text_field( $_POST['condition'] ?? '' ),
			'threshold_value' => (float) ( $_POST['threshold_value'] ?? 0 ),
			'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'notification_email' => sanitize_email( $_POST['notification_email'] ?? '' ),
			'notification_admin_notice' => isset( $_POST['notification_admin_notice'] ),
		];

		if ( AlertRule::update( $rule_id, $data ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Regola aggiornata con successo.', 'fp-digital-marketing' ) . '</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Errore durante l\'aggiornamento della regola.', 'fp-digital-marketing' ) . '</p></div>';
			} );
		}
	}

	/**
	 * Handle deleting a rule
	 *
	 * @return void
	 */
	private function handle_delete_rule(): void {
		$rule_id = (int) ( $_POST['rule_id'] ?? 0 );
		
		if ( $rule_id <= 0 ) {
			return;
		}

		if ( AlertRule::delete( $rule_id ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Regola eliminata con successo.', 'fp-digital-marketing' ) . '</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Errore durante l\'eliminazione della regola.', 'fp-digital-marketing' ) . '</p></div>';
			} );
		}
	}

	/**
	 * Handle toggling rule active status
	 *
	 * @return void
	 */
	private function handle_toggle_rule(): void {
		$rule_id = (int) ( $_POST['rule_id'] ?? 0 );
		$is_active = isset( $_POST['is_active'] );
		
		if ( $rule_id <= 0 ) {
			return;
		}

		if ( AlertRule::update( $rule_id, [ 'is_active' => $is_active ] ) ) {
			$status = $is_active ? __( 'attivata', 'fp-digital-marketing' ) : __( 'disattivata', 'fp-digital-marketing' );
			add_action( 'admin_notices', function() use ( $status ) {
				echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Regola %s con successo.', 'fp-digital-marketing' ), esc_html( $status ) ) . '</p></div>';
			} );
		}
	}

	/**
	 * Display the admin page
	 *
	 * @return void
	 */
	public function display_admin_page(): void {
		// Check user capabilities
		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_ALERTS ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}

		$tab = $_GET['tab'] ?? 'rules';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Alert e Notifiche', 'fp-digital-marketing' ); ?></h1>
			
			<nav class="nav-tab-wrapper">
				<a href="?post_type=cliente&page=fp-digital-marketing-alerts&tab=rules" class="nav-tab <?php echo $tab === 'rules' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Regole di Alert', 'fp-digital-marketing' ); ?>
				</a>
				<a href="?post_type=cliente&page=fp-digital-marketing-alerts&tab=logs" class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Log degli Alert', 'fp-digital-marketing' ); ?>
				</a>
			</nav>

			<?php
			switch ( $tab ) {
				case 'logs':
					$this->display_logs_tab();
					break;
				case 'rules':
				default:
					$this->display_rules_tab();
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Display rules tab
	 *
	 * @return void
	 */
	private function display_rules_tab(): void {
		$edit_rule_id = (int) ( $_GET['edit'] ?? 0 );
		$edit_rule = $edit_rule_id > 0 ? AlertRule::get_by_id( $edit_rule_id ) : null;

		if ( $edit_rule ) {
			$this->display_rule_form( $edit_rule );
		} else {
			$this->display_rule_form();
		}

		$this->display_rules_list();
	}

	/**
	 * Display rule form
	 *
	 * @param object|null $rule Rule to edit, null for new rule
	 * @return void
	 */
	private function display_rule_form( ?object $rule = null ): void {
		$is_edit = $rule !== null;
		$action = $is_edit ? 'edit_rule' : 'add_rule';
		$submit_text = $is_edit ? __( 'Aggiorna Regola', 'fp-digital-marketing' ) : __( 'Crea Regola', 'fp-digital-marketing' );

		// Get clients
		$clients = get_posts( [
			'post_type' => 'cliente',
			'numberposts' => -1,
			'post_status' => 'publish',
		] );

		// Get available metrics
		$kpi_definitions = MetricsSchema::get_kpi_definitions();
		$conditions = AlertRule::get_condition_operators();
		?>
		<div class="card">
			<h2><?php echo $is_edit ? esc_html__( 'Modifica Regola di Alert', 'fp-digital-marketing' ) : esc_html__( 'Nuova Regola di Alert', 'fp-digital-marketing' ); ?></h2>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'fp_dms_alerts', 'fp_dms_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
				<?php if ( $is_edit ): ?>
					<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="client_id"><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?> *</label>
						</th>
						<td>
							<select name="client_id" id="client_id" required <?php echo $is_edit ? 'disabled' : ''; ?>>
								<option value=""><?php esc_html_e( 'Seleziona cliente', 'fp-digital-marketing' ); ?></option>
								<?php foreach ( $clients as $client ): ?>
									<option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( $is_edit ? $rule->client_id : '', $client->ID ); ?>>
										<?php echo esc_html( $client->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( $is_edit ): ?>
								<input type="hidden" name="client_id" value="<?php echo esc_attr( $rule->client_id ); ?>">
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="name"><?php esc_html_e( 'Nome della Regola', 'fp-digital-marketing' ); ?> *</label>
						</th>
						<td>
							<input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr( $rule->name ?? '' ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="description"><?php esc_html_e( 'Descrizione', 'fp-digital-marketing' ); ?></label>
						</th>
						<td>
							<textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $rule->description ?? '' ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="metric"><?php esc_html_e( 'Metrica', 'fp-digital-marketing' ); ?> *</label>
						</th>
						<td>
							<select name="metric" id="metric" required>
								<option value=""><?php esc_html_e( 'Seleziona metrica', 'fp-digital-marketing' ); ?></option>
								<?php foreach ( $kpi_definitions as $kpi => $definition ): ?>
									<option value="<?php echo esc_attr( $kpi ); ?>" <?php selected( $rule->metric ?? '', $kpi ); ?>>
										<?php echo esc_html( $definition['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="condition"><?php esc_html_e( 'Condizione', 'fp-digital-marketing' ); ?> *</label>
						</th>
						<td>
							<select name="condition" id="condition" required>
								<option value=""><?php esc_html_e( 'Seleziona condizione', 'fp-digital-marketing' ); ?></option>
								<?php foreach ( $conditions as $value => $label ): ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $rule->condition ?? '', $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="threshold_value"><?php esc_html_e( 'Valore Soglia', 'fp-digital-marketing' ); ?> *</label>
						</th>
						<td>
							<input type="number" name="threshold_value" id="threshold_value" step="0.01" class="small-text" value="<?php echo esc_attr( $rule->threshold_value ?? '' ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="notification_email"><?php esc_html_e( 'Email per Notifiche', 'fp-digital-marketing' ); ?></label>
						</th>
						<td>
							<input type="email" name="notification_email" id="notification_email" class="regular-text" value="<?php echo esc_attr( $rule->notification_email ?? '' ); ?>">
							<p class="description"><?php esc_html_e( 'Lascia vuoto per non inviare email di notifica.', 'fp-digital-marketing' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Notifiche', 'fp-digital-marketing' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="notification_admin_notice" value="1" <?php checked( $rule->notification_admin_notice ?? true ); ?>>
								<?php esc_html_e( 'Mostra notifica nell\'amministrazione', 'fp-digital-marketing' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( $submit_text ); ?>
				
				<?php if ( $is_edit ): ?>
					<a href="?post_type=cliente&page=fp-digital-marketing-alerts" class="button button-secondary">
						<?php esc_html_e( 'Annulla', 'fp-digital-marketing' ); ?>
					</a>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Display rules list
	 *
	 * @return void
	 */
	private function display_rules_list(): void {
		$client_id = (int) ( $_GET['client_filter'] ?? 0 );
		
		if ( $client_id > 0 ) {
			$rules = AlertRule::get_by_client( $client_id );
		} else {
			// Get all rules with client info
			global $wpdb;
			$table_name = \FP\DigitalMarketing\Database\AlertRulesTable::get_table_name();
			$rules = $wpdb->get_results(
				"SELECT r.*, p.post_title as client_name 
				FROM $table_name r 
				LEFT JOIN {$wpdb->posts} p ON r.client_id = p.ID 
				ORDER BY r.created_at DESC"
			);
		}

		$kpi_definitions = MetricsSchema::get_kpi_definitions();
		$conditions = AlertRule::get_condition_operators();
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Regole di Alert Esistenti', 'fp-digital-marketing' ); ?></h2>
			
			<?php if ( empty( $rules ) ): ?>
				<p><?php esc_html_e( 'Nessuna regola di alert configurata.', 'fp-digital-marketing' ); ?></p>
			<?php else: ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Nome', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Metrica', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Condizione', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Attiva', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Ultimo Trigger', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Azioni', 'fp-digital-marketing' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rules as $rule ): ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $rule->name ); ?></strong>
									<?php if ( ! empty( $rule->description ) ): ?>
										<br><small><?php echo esc_html( $rule->description ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $rule->client_name ?? get_the_title( $rule->client_id ) ); ?></td>
								<td><?php echo esc_html( $kpi_definitions[ $rule->metric ]['name'] ?? $rule->metric ); ?></td>
								<td>
									<?php echo esc_html( $conditions[ $rule->condition ] ?? $rule->condition ); ?>
									<?php echo esc_html( $rule->threshold_value ); ?>
								</td>
								<td>
									<form method="post" style="display: inline;">
										<?php wp_nonce_field( 'fp_dms_alerts', 'fp_dms_nonce' ); ?>
										<input type="hidden" name="action" value="toggle_rule">
										<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>">
										<label class="switch">
											<input type="checkbox" name="is_active" value="1" <?php checked( $rule->is_active ); ?> onchange="this.form.submit()">
											<span class="slider"></span>
										</label>
									</form>
								</td>
								<td>
									<?php if ( $rule->last_triggered ): ?>
										<?php echo esc_html( $rule->last_triggered ); ?>
										<br><small>(<?php echo esc_html( $rule->triggered_count ); ?> volte)</small>
									<?php else: ?>
										<?php esc_html_e( 'Mai', 'fp-digital-marketing' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<a href="?post_type=cliente&page=fp-digital-marketing-alerts&edit=<?php echo esc_attr( $rule->id ); ?>" class="button button-small">
										<?php esc_html_e( 'Modifica', 'fp-digital-marketing' ); ?>
									</a>
									<form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Sei sicuro di voler eliminare questa regola?', 'fp-digital-marketing' ); ?>')">
										<?php wp_nonce_field( 'fp_dms_alerts', 'fp_dms_nonce' ); ?>
										<input type="hidden" name="action" value="delete_rule">
										<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>">
										<button type="submit" class="button button-small button-link-delete">
											<?php esc_html_e( 'Elimina', 'fp-digital-marketing' ); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display logs tab
	 *
	 * @return void
	 */
	private function display_logs_tab(): void {
		$logs = AlertEngine::get_alert_logs( 50 );
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Log degli Alert', 'fp-digital-marketing' ); ?></h2>
			
			<?php if ( empty( $logs ) ): ?>
				<p><?php esc_html_e( 'Nessun log disponibile.', 'fp-digital-marketing' ); ?></p>
			<?php else: ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Data/Ora', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Controllate', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Attivate', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Notifiche Inviate', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Errori', 'fp-digital-marketing' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ): ?>
							<tr>
								<td><?php echo esc_html( $log['timestamp'] ); ?></td>
								<td><?php echo esc_html( $log['results']['checked'] ); ?></td>
								<td><?php echo esc_html( $log['results']['triggered'] ); ?></td>
								<td><?php echo esc_html( $log['results']['notifications_sent'] ); ?></td>
								<td><?php echo esc_html( $log['results']['errors'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display alert notices in admin
	 *
	 * @return void
	 */
	public function display_alert_notices(): void {
		$notices = AlertEngine::get_pending_admin_notices();
		$kpi_definitions = MetricsSchema::get_kpi_definitions();

		foreach ( $notices as $notice_key => $notice_data ) {
			$metric_name = $kpi_definitions[ $notice_data['metric'] ]['name'] ?? $notice_data['metric'];
			$client_name = get_the_title( $notice_data['client_id'] ) ?: __( 'Cliente sconosciuto', 'fp-digital-marketing' );
			
			?>
			<div class="notice notice-warning is-dismissible fp-alert-notice" data-notice-key="<?php echo esc_attr( $notice_key ); ?>">
				<p>
					<strong><?php esc_html_e( 'Alert attivato:', 'fp-digital-marketing' ); ?></strong>
					<?php echo esc_html( $notice_data['rule_name'] ); ?>
				</p>
				<p>
					<?php printf(
						/* translators: 1: Client name, 2: Metric name, 3: Current value, 4: Condition, 5: Threshold value */
						esc_html__( 'Cliente: %1$s | Metrica: %2$s | Valore attuale: %3$s %4$s %5$s', 'fp-digital-marketing' ),
						esc_html( $client_name ),
						esc_html( $metric_name ),
						esc_html( number_format( $notice_data['current_value'] ) ),
						esc_html( $notice_data['condition'] ),
						esc_html( number_format( $notice_data['threshold_value'] ) )
					); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Handle AJAX dismissal of alert notices
	 *
	 * @return void
	 */
	public function dismiss_alert_notice(): void {
		check_ajax_referer( 'fp_dms_dismiss_alert', 'nonce' );

		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_ALERTS ) ) {
			wp_die( __( 'Permessi insufficienti.', 'fp-digital-marketing' ) );
		}

		$notice_key = sanitize_text_field( $_POST['notice_key'] ?? '' );

		if ( $notice_key && AlertEngine::clear_admin_notice( $notice_key ) ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( strpos( $hook, 'fp-digital-marketing-alerts' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'fp-dms-alerts',
			FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/js/alerts-admin.js',
			[ 'jquery' ],
			FP_DIGITAL_MARKETING_VERSION,
			true
		);

		wp_localize_script( 'fp-dms-alerts', 'fpDmsAlerts', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'fp_dms_dismiss_alert' ),
		] );

		// Add CSS for toggle switches
		wp_add_inline_style( 'wp-admin', '
			.switch {
				position: relative;
				display: inline-block;
				width: 40px;
				height: 20px;
			}
			.switch input {
				opacity: 0;
				width: 0;
				height: 0;
			}
			.slider {
				position: absolute;
				cursor: pointer;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background-color: #ccc;
				transition: .4s;
				border-radius: 20px;
			}
			.slider:before {
				position: absolute;
				content: "";
				height: 16px;
				width: 16px;
				left: 2px;
				bottom: 2px;
				background-color: white;
				transition: .4s;
				border-radius: 50%;
			}
			input:checked + .slider {
				background-color: #2196F3;
			}
			input:checked + .slider:before {
				transform: translateX(20px);
			}
		' );
	}
}