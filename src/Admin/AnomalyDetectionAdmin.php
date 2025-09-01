<?php
/**
 * Anomaly Detection Admin Interface
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Models\AnomalyRule;
use FP\DigitalMarketing\Models\DetectedAnomaly;
use FP\DigitalMarketing\Helpers\AlertEngine;
use FP\DigitalMarketing\Helpers\AnomalyDetector;
use FP\DigitalMarketing\Helpers\MetricsSchema;
use FP\DigitalMarketing\Helpers\Capabilities;

/**
 * AnomalyDetectionAdmin class for managing anomaly detection interface
 */
class AnomalyDetectionAdmin {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		// Constructor intentionally left empty - initialization happens in init()
	}

	/**
	 * Initialize the admin interface
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
		add_action( 'admin_notices', [ $this, 'display_anomaly_notices' ] );
		add_action( 'wp_ajax_dismiss_anomaly_notice', [ $this, 'dismiss_anomaly_notice' ] );
		add_action( 'wp_ajax_acknowledge_anomaly', [ $this, 'acknowledge_anomaly' ] );
		add_action( 'wp_ajax_silence_anomaly_rule', [ $this, 'silence_anomaly_rule' ] );
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
			__( 'Rilevazione Anomalie', 'fp-digital-marketing' ),
			__( 'Rilevazione Anomalie', 'fp-digital-marketing' ),
			Capabilities::MANAGE_ALERTS,
			'fp-digital-marketing-anomalies',
			[ $this, 'display_admin_page' ]
		);
	}

	/**
	 * Display the admin page
	 *
	 * @return void
	 */
	public function display_admin_page(): void {
		$action = $_GET['action'] ?? 'list';
		$rule_id = (int) ( $_GET['rule_id'] ?? 0 );

		switch ( $action ) {
			case 'add':
				$this->display_add_rule_form();
				break;
			case 'edit':
				$this->display_edit_rule_form( $rule_id );
				break;
			case 'anomalies':
				$this->display_anomalies_list();
				break;
			case 'statistics':
				$this->display_statistics();
				break;
			default:
				$this->display_rules_list();
				break;
		}
	}

	/**
	 * Display anomaly detection rules list
	 *
	 * @return void
	 */
	private function display_rules_list(): void {
		$rules = AnomalyRule::get_rules();
		$kpi_definitions = MetricsSchema::get_kpi_definitions();

		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Regole di Rilevazione Anomalie', 'fp-digital-marketing' ); ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies&action=add' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Aggiungi Nuova', 'fp-digital-marketing' ); ?>
				</a>
			</h1>

			<div class="tablenav top">
				<div class="alignleft actions">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies&action=anomalies' ) ); ?>" class="button">
						<?php esc_html_e( 'Anomalie Rilevate', 'fp-digital-marketing' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies&action=statistics' ) ); ?>" class="button">
						<?php esc_html_e( 'Statistiche', 'fp-digital-marketing' ); ?>
					</a>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nome Regola', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Metrica', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Metodo', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Stato', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Ultimo Trigger', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Azioni', 'fp-digital-marketing' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rules ) ): ?>
						<tr>
							<td colspan="7" style="text-align: center;">
								<?php esc_html_e( 'Nessuna regola di rilevazione anomalie configurata.', 'fp-digital-marketing' ); ?>
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ( $rules as $rule ): ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $rule->name ); ?></strong>
									<?php if ( $rule->description ): ?>
										<br><small><?php echo esc_html( $rule->description ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( get_the_title( $rule->client_id ) ?: __( 'Cliente sconosciuto', 'fp-digital-marketing' ) ); ?></td>
								<td><?php echo esc_html( $kpi_definitions[ $rule->metric ]['name'] ?? $rule->metric ); ?></td>
								<td><?php echo esc_html( AnomalyRule::get_detection_methods()[ $rule->detection_method ] ?? $rule->detection_method ); ?></td>
								<td>
									<?php if ( $rule->is_active ): ?>
										<?php if ( $rule->silence_until && strtotime( $rule->silence_until ) > time() ): ?>
											<span class="fp-status-silenced">
												<?php esc_html_e( 'Silenziata', 'fp-digital-marketing' ); ?>
												<small>(<?php echo esc_html( date_i18n( 'j M H:i', strtotime( $rule->silence_until ) ) ); ?>)</small>
											</span>
										<?php else: ?>
											<span class="fp-status-active"><?php esc_html_e( 'Attiva', 'fp-digital-marketing' ); ?></span>
										<?php endif; ?>
									<?php else: ?>
										<span class="fp-status-inactive"><?php esc_html_e( 'Inattiva', 'fp-digital-marketing' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $rule->last_triggered ): ?>
										<?php echo esc_html( date_i18n( 'j M Y H:i', strtotime( $rule->last_triggered ) ) ); ?>
										<br><small><?php printf( esc_html__( '%d volte', 'fp-digital-marketing' ), $rule->triggered_count ); ?></small>
									<?php else: ?>
										<em><?php esc_html_e( 'Mai', 'fp-digital-marketing' ); ?></em>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies&action=edit&rule_id=' . $rule->id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Modifica', 'fp-digital-marketing' ); ?>
									</a>
									<?php if ( $rule->is_active && ( ! $rule->silence_until || strtotime( $rule->silence_until ) <= time() ) ): ?>
										<button type="button" class="button button-small silence-rule" data-rule-id="<?php echo esc_attr( $rule->id ); ?>">
											<?php esc_html_e( 'Silenzia', 'fp-digital-marketing' ); ?>
										</button>
									<?php elseif ( $rule->silence_until && strtotime( $rule->silence_until ) > time() ): ?>
										<button type="button" class="button button-small unsilence-rule" data-rule-id="<?php echo esc_attr( $rule->id ); ?>">
											<?php esc_html_e( 'Riattiva', 'fp-digital-marketing' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<style>
			.fp-status-active { color: #007cba; font-weight: bold; }
			.fp-status-inactive { color: #dba617; font-weight: bold; }
			.fp-status-silenced { color: #d63638; font-weight: bold; }
		</style>
		<?php
	}

	/**
	 * Display detected anomalies list
	 *
	 * @return void
	 */
	private function display_anomalies_list(): void {
		$filters = [
			'days_back' => (int) ( $_GET['days_back'] ?? 7 ),
			'limit' => 100,
		];

		if ( isset( $_GET['client_id'] ) && $_GET['client_id'] !== '' ) {
			$filters['client_id'] = (int) $_GET['client_id'];
		}

		if ( isset( $_GET['metric'] ) && $_GET['metric'] !== '' ) {
			$filters['metric'] = sanitize_text_field( $_GET['metric'] );
		}

		if ( isset( $_GET['severity'] ) && $_GET['severity'] !== '' ) {
			$filters['severity'] = sanitize_text_field( $_GET['severity'] );
		}

		if ( isset( $_GET['acknowledged'] ) && $_GET['acknowledged'] !== '' ) {
			$filters['acknowledged'] = $_GET['acknowledged'] === '1';
		}

		$anomalies = DetectedAnomaly::get_recent_anomalies( $filters );
		$statistics = DetectedAnomaly::get_statistics( $filters );
		$kpi_definitions = MetricsSchema::get_kpi_definitions();

		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Anomalie Rilevate', 'fp-digital-marketing' ); ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Torna alle Regole', 'fp-digital-marketing' ); ?>
				</a>
			</h1>

			<!-- Statistics Cards -->
			<div class="fp-stats-cards" style="display: flex; gap: 20px; margin: 20px 0;">
				<div class="card" style="flex: 1; padding: 15px;">
					<h3><?php esc_html_e( 'Totale Anomalie', 'fp-digital-marketing' ); ?></h3>
					<p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo esc_html( $statistics['total_anomalies'] ); ?></p>
				</div>
				<div class="card" style="flex: 1; padding: 15px;">
					<h3><?php esc_html_e( 'Non Riconosciute', 'fp-digital-marketing' ); ?></h3>
					<p style="font-size: 24px; font-weight: bold; margin: 0; color: #d63638;"><?php echo esc_html( $statistics['unacknowledged_count'] ); ?></p>
				</div>
				<div class="card" style="flex: 1; padding: 15px;">
					<h3><?php esc_html_e( 'Metriche Interessate', 'fp-digital-marketing' ); ?></h3>
					<p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo esc_html( $statistics['affected_metrics'] ); ?></p>
				</div>
				<div class="card" style="flex: 1; padding: 15px;">
					<h3><?php esc_html_e( 'Critiche', 'fp-digital-marketing' ); ?></h3>
					<p style="font-size: 24px; font-weight: bold; margin: 0; color: #d63638;"><?php echo esc_html( $statistics['severity_distribution']['critical'] ); ?></p>
				</div>
			</div>

			<!-- Filters -->
			<form method="get" class="alignleft actions">
				<input type="hidden" name="post_type" value="cliente">
				<input type="hidden" name="page" value="fp-digital-marketing-anomalies">
				<input type="hidden" name="action" value="anomalies">

				<select name="days_back">
					<option value="7" <?php selected( $filters['days_back'], 7 ); ?>><?php esc_html_e( 'Ultimi 7 giorni', 'fp-digital-marketing' ); ?></option>
					<option value="30" <?php selected( $filters['days_back'], 30 ); ?>><?php esc_html_e( 'Ultimi 30 giorni', 'fp-digital-marketing' ); ?></option>
					<option value="90" <?php selected( $filters['days_back'], 90 ); ?>><?php esc_html_e( 'Ultimi 90 giorni', 'fp-digital-marketing' ); ?></option>
				</select>

				<select name="severity">
					<option value=""><?php esc_html_e( 'Tutte le gravità', 'fp-digital-marketing' ); ?></option>
					<option value="critical" <?php selected( $filters['severity'] ?? '', 'critical' ); ?>><?php esc_html_e( 'Critica', 'fp-digital-marketing' ); ?></option>
					<option value="high" <?php selected( $filters['severity'] ?? '', 'high' ); ?>><?php esc_html_e( 'Alta', 'fp-digital-marketing' ); ?></option>
					<option value="medium" <?php selected( $filters['severity'] ?? '', 'medium' ); ?>><?php esc_html_e( 'Media', 'fp-digital-marketing' ); ?></option>
					<option value="low" <?php selected( $filters['severity'] ?? '', 'low' ); ?>><?php esc_html_e( 'Bassa', 'fp-digital-marketing' ); ?></option>
				</select>

				<select name="acknowledged">
					<option value=""><?php esc_html_e( 'Tutte', 'fp-digital-marketing' ); ?></option>
					<option value="0" <?php selected( $filters['acknowledged'] ?? '', false ); ?>><?php esc_html_e( 'Non riconosciute', 'fp-digital-marketing' ); ?></option>
					<option value="1" <?php selected( $filters['acknowledged'] ?? '', true ); ?>><?php esc_html_e( 'Riconosciute', 'fp-digital-marketing' ); ?></option>
				</select>

				<input type="submit" class="button" value="<?php esc_attr_e( 'Filtra', 'fp-digital-marketing' ); ?>">
			</form>

			<div class="clear"></div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Metrica', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Valore Attuale', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Valore Atteso', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Metodo', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Gravità', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Rilevata', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Azioni', 'fp-digital-marketing' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $anomalies ) ): ?>
						<tr>
							<td colspan="8" style="text-align: center;">
								<?php esc_html_e( 'Nessuna anomalia rilevata nel periodo selezionato.', 'fp-digital-marketing' ); ?>
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ( $anomalies as $anomaly ): ?>
							<tr class="<?php echo $anomaly->acknowledged ? 'acknowledged' : 'unacknowledged'; ?>">
								<td>
									<strong><?php echo esc_html( $kpi_definitions[ $anomaly->metric ]['name'] ?? $anomaly->metric ); ?></strong>
								</td>
								<td><?php echo esc_html( get_the_title( $anomaly->client_id ) ?: __( 'Cliente sconosciuto', 'fp-digital-marketing' ) ); ?></td>
								<td>
									<span class="current-value"><?php echo esc_html( number_format( $anomaly->current_value, 2 ) ); ?></span>
									<?php if ( $anomaly->deviation_type === 'positive' ): ?>
										<span class="dashicons dashicons-arrow-up-alt" style="color: #d63638;"></span>
									<?php elseif ( $anomaly->deviation_type === 'negative' ): ?>
										<span class="dashicons dashicons-arrow-down-alt" style="color: #d63638;"></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $anomaly->expected_value ): ?>
										<?php echo esc_html( number_format( $anomaly->expected_value, 2 ) ); ?>
									<?php else: ?>
										<em><?php esc_html_e( 'N/A', 'fp-digital-marketing' ); ?></em>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $anomaly->detection_method ); ?></td>
								<td>
									<span class="severity-<?php echo esc_attr( $anomaly->severity ); ?>">
										<?php
										switch ( $anomaly->severity ) {
											case 'critical':
												esc_html_e( 'Critica', 'fp-digital-marketing' );
												break;
											case 'high':
												esc_html_e( 'Alta', 'fp-digital-marketing' );
												break;
											case 'medium':
												esc_html_e( 'Media', 'fp-digital-marketing' );
												break;
											case 'low':
												esc_html_e( 'Bassa', 'fp-digital-marketing' );
												break;
											default:
												echo esc_html( $anomaly->severity );
												break;
										}
										?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( 'j M Y H:i', strtotime( $anomaly->detected_at ) ) ); ?></td>
								<td>
									<?php if ( ! $anomaly->acknowledged ): ?>
										<button type="button" class="button button-small acknowledge-anomaly" data-anomaly-id="<?php echo esc_attr( $anomaly->id ); ?>">
											<?php esc_html_e( 'Riconosci', 'fp-digital-marketing' ); ?>
										</button>
									<?php else: ?>
										<span class="dashicons dashicons-yes" style="color: #007cba;" title="<?php esc_attr_e( 'Riconosciuta', 'fp-digital-marketing' ); ?>"></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<style>
			.severity-critical { color: #d63638; font-weight: bold; }
			.severity-high { color: #dba617; font-weight: bold; }
			.severity-medium { color: #00a0d2; font-weight: bold; }
			.severity-low { color: #007cba; }
			.acknowledged { opacity: 0.7; }
		</style>
		<?php
	}

	/**
	 * Display add rule form
	 *
	 * @return void
	 */
	private function display_add_rule_form(): void {
		$this->display_rule_form();
	}

	/**
	 * Display edit rule form
	 *
	 * @param int $rule_id Rule ID to edit
	 * @return void
	 */
	private function display_edit_rule_form( int $rule_id ): void {
		$rule = AnomalyRule::get_by_id( $rule_id );
		
		if ( ! $rule ) {
			wp_die( __( 'Regola non trovata.', 'fp-digital-marketing' ) );
		}

		$this->display_rule_form( $rule );
	}

	/**
	 * Display rule form (add/edit)
	 *
	 * @param object|null $rule Rule object for editing, null for adding
	 * @return void
	 */
	private function display_rule_form( ?object $rule = null ): void {
		$is_edit = $rule !== null;
		$kpi_definitions = MetricsSchema::get_kpi_definitions();
		$supported_metrics = AnomalyDetector::get_supported_metrics();
		$detection_methods = AnomalyRule::get_detection_methods();

		// Get clients list
		$clients = get_posts( [
			'post_type' => 'cliente',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'ASC',
		] );

		?>
		<div class="wrap">
			<h1>
				<?php 
				if ( $is_edit ) {
					esc_html_e( 'Modifica Regola di Rilevazione Anomalia', 'fp-digital-marketing' );
				} else {
					esc_html_e( 'Aggiungi Regola di Rilevazione Anomalia', 'fp-digital-marketing' );
				}
				?>
			</h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'fp_dms_anomaly_rule', 'fp_dms_anomaly_rule_nonce' ); ?>
				
				<?php if ( $is_edit ): ?>
					<input type="hidden" name="action" value="edit_anomaly_rule">
					<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>">
				<?php else: ?>
					<input type="hidden" name="action" value="add_anomaly_rule">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="client_id"><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?> *</label>
						</th>
						<td>
							<select name="client_id" id="client_id" required>
								<option value=""><?php esc_html_e( 'Seleziona cliente', 'fp-digital-marketing' ); ?></option>
								<?php foreach ( $clients as $client ): ?>
									<option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( $rule->client_id ?? '', $client->ID ); ?>>
										<?php echo esc_html( $client->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="name"><?php esc_html_e( 'Nome Regola', 'fp-digital-marketing' ); ?> *</label>
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
								<?php foreach ( $supported_metrics as $metric ): ?>
									<option value="<?php echo esc_attr( $metric ); ?>" <?php selected( $rule->metric ?? '', $metric ); ?>>
										<?php echo esc_html( $kpi_definitions[ $metric ]['name'] ?? $metric ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="detection_method"><?php esc_html_e( 'Metodo di Rilevazione', 'fp-digital-marketing' ); ?> *</label>
						</th>
						<td>
							<select name="detection_method" id="detection_method" required>
								<option value=""><?php esc_html_e( 'Seleziona metodo', 'fp-digital-marketing' ); ?></option>
								<?php foreach ( $detection_methods as $method => $label ): ?>
									<option value="<?php echo esc_attr( $method ); ?>" <?php selected( $rule->detection_method ?? '', $method ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="z_score_params" style="display: none;">
						<th scope="row">
							<label for="z_score_threshold"><?php esc_html_e( 'Soglia Z-Score', 'fp-digital-marketing' ); ?></label>
						</th>
						<td>
							<input type="number" name="z_score_threshold" id="z_score_threshold" step="0.1" min="1" value="<?php echo esc_attr( $rule->z_score_threshold ?? '2.0' ); ?>">
							<p class="description"><?php esc_html_e( 'Valore predefinito: 2.0 (95.4% di confidenza)', 'fp-digital-marketing' ); ?></p>
						</td>
					</tr>
					<tr id="moving_avg_params" style="display: none;">
						<th scope="row">
							<label for="band_deviations"><?php esc_html_e( 'Deviazioni Standard per Bande', 'fp-digital-marketing' ); ?></label>
						</th>
						<td>
							<input type="number" name="band_deviations" id="band_deviations" step="0.1" min="1" value="<?php echo esc_attr( $rule->band_deviations ?? '2.0' ); ?>">
							<p class="description"><?php esc_html_e( 'Valore predefinito: 2.0', 'fp-digital-marketing' ); ?></p>
						</td>
					</tr>
					<tr id="window_size_params" style="display: none;">
						<th scope="row">
							<label for="window_size"><?php esc_html_e( 'Finestra Mobile (giorni)', 'fp-digital-marketing' ); ?></label>
						</th>
						<td>
							<input type="number" name="window_size" id="window_size" min="3" max="30" value="<?php echo esc_attr( $rule->window_size ?? '7' ); ?>">
							<p class="description"><?php esc_html_e( 'Valore predefinito: 7 giorni', 'fp-digital-marketing' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="historical_days"><?php esc_html_e( 'Giorni Storici', 'fp-digital-marketing' ); ?></label>
						</th>
						<td>
							<input type="number" name="historical_days" id="historical_days" min="7" max="90" value="<?php echo esc_attr( $rule->historical_days ?? '30' ); ?>">
							<p class="description"><?php esc_html_e( 'Numero di giorni di dati storici da analizzare (predefinito: 30)', 'fp-digital-marketing' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="notification_email"><?php esc_html_e( 'Email di Notifica', 'fp-digital-marketing' ); ?></label>
						</th>
						<td>
							<input type="email" name="notification_email" id="notification_email" class="regular-text" value="<?php echo esc_attr( $rule->notification_email ?? '' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Notifiche Admin', 'fp-digital-marketing' ); ?>
						</th>
						<td>
							<label class="switch">
								<input type="checkbox" name="notification_admin_notice" value="1" <?php checked( $rule->notification_admin_notice ?? 1, 1 ); ?>>
								<span class="slider"></span>
							</label>
							<span><?php esc_html_e( 'Mostra notifiche nell\'area admin', 'fp-digital-marketing' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Stato', 'fp-digital-marketing' ); ?>
						</th>
						<td>
							<label class="switch">
								<input type="checkbox" name="is_active" value="1" <?php checked( $rule->is_active ?? 1, 1 ); ?>>
								<span class="slider"></span>
							</label>
							<span><?php esc_html_e( 'Regola attiva', 'fp-digital-marketing' ); ?></span>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="submit" class="button-primary" value="<?php echo $is_edit ? esc_attr__( 'Aggiorna Regola', 'fp-digital-marketing' ) : esc_attr__( 'Crea Regola', 'fp-digital-marketing' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies' ) ); ?>" class="button">
						<?php esc_html_e( 'Annulla', 'fp-digital-marketing' ); ?>
					</a>
				</p>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Show/hide parameter fields based on detection method
			$('#detection_method').on('change', function() {
				var method = $(this).val();
				
				// Hide all parameter rows
				$('#z_score_params, #moving_avg_params, #window_size_params').hide();
				
				// Show relevant parameter rows
				switch(method) {
					case 'z_score':
						$('#z_score_params').show();
						break;
					case 'moving_average':
						$('#moving_avg_params, #window_size_params').show();
						break;
					case 'combined':
						$('#z_score_params, #moving_avg_params, #window_size_params').show();
						break;
				}
			}).trigger('change');
		});
		</script>

		<style>
			.switch {
				position: relative;
				display: inline-block;
				width: 40px;
				height: 20px;
				margin-right: 10px;
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
		</style>
		<?php
	}

	/**
	 * Display statistics page
	 *
	 * @return void
	 */
	private function display_statistics(): void {
		$days_back = (int) ( $_GET['days_back'] ?? 30 );
		$statistics = DetectedAnomaly::get_statistics( [ 'days_back' => $days_back ] );

		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Statistiche Rilevazione Anomalie', 'fp-digital-marketing' ); ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Torna alle Regole', 'fp-digital-marketing' ); ?>
				</a>
			</h1>

			<form method="get" style="margin: 20px 0;">
				<input type="hidden" name="post_type" value="cliente">
				<input type="hidden" name="page" value="fp-digital-marketing-anomalies">
				<input type="hidden" name="action" value="statistics">
				
				<select name="days_back" onchange="this.form.submit()">
					<option value="7" <?php selected( $days_back, 7 ); ?>><?php esc_html_e( 'Ultimi 7 giorni', 'fp-digital-marketing' ); ?></option>
					<option value="30" <?php selected( $days_back, 30 ); ?>><?php esc_html_e( 'Ultimi 30 giorni', 'fp-digital-marketing' ); ?></option>
					<option value="90" <?php selected( $days_back, 90 ); ?>><?php esc_html_e( 'Ultimi 90 giorni', 'fp-digital-marketing' ); ?></option>
				</select>
			</form>

			<div class="fp-statistics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
				<div class="card" style="padding: 20px;">
					<h2><?php esc_html_e( 'Riepilogo Generale', 'fp-digital-marketing' ); ?></h2>
					<table class="wp-list-table widefat">
						<tr>
							<td><?php esc_html_e( 'Totale Anomalie', 'fp-digital-marketing' ); ?></td>
							<td><strong><?php echo esc_html( $statistics['total_anomalies'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Metriche Interessate', 'fp-digital-marketing' ); ?></td>
							<td><strong><?php echo esc_html( $statistics['affected_metrics'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Riconosciute', 'fp-digital-marketing' ); ?></td>
							<td><strong><?php echo esc_html( $statistics['acknowledged_count'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Non Riconosciute', 'fp-digital-marketing' ); ?></td>
							<td><strong style="color: #d63638;"><?php echo esc_html( $statistics['unacknowledged_count'] ); ?></strong></td>
						</tr>
					</table>
				</div>

				<div class="card" style="padding: 20px;">
					<h2><?php esc_html_e( 'Distribuzione per Gravità', 'fp-digital-marketing' ); ?></h2>
					<table class="wp-list-table widefat">
						<tr>
							<td><?php esc_html_e( 'Critica', 'fp-digital-marketing' ); ?></td>
							<td><strong style="color: #d63638;"><?php echo esc_html( $statistics['severity_distribution']['critical'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Alta', 'fp-digital-marketing' ); ?></td>
							<td><strong style="color: #dba617;"><?php echo esc_html( $statistics['severity_distribution']['high'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Media', 'fp-digital-marketing' ); ?></td>
							<td><strong style="color: #00a0d2;"><?php echo esc_html( $statistics['severity_distribution']['medium'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Bassa', 'fp-digital-marketing' ); ?></td>
							<td><strong style="color: #007cba;"><?php echo esc_html( $statistics['severity_distribution']['low'] ); ?></strong></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle form submissions
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		if ( ! isset( $_POST['action'] ) || ! wp_verify_nonce( $_POST['fp_dms_anomaly_rule_nonce'] ?? '', 'fp_dms_anomaly_rule' ) ) {
			return;
		}

		$action = sanitize_text_field( $_POST['action'] );

		switch ( $action ) {
			case 'add_anomaly_rule':
				$this->handle_add_rule();
				break;
			case 'edit_anomaly_rule':
				$this->handle_edit_rule();
				break;
		}
	}

	/**
	 * Handle add rule form submission
	 *
	 * @return void
	 */
	private function handle_add_rule(): void {
		$client_id = (int) $_POST['client_id'];
		$name = sanitize_text_field( $_POST['name'] );
		$description = sanitize_textarea_field( $_POST['description'] );
		$metric = sanitize_text_field( $_POST['metric'] );
		$detection_method = sanitize_text_field( $_POST['detection_method'] );
		$notification_email = sanitize_email( $_POST['notification_email'] );
		$notification_admin_notice = isset( $_POST['notification_admin_notice'] );
		$is_active = isset( $_POST['is_active'] );

		$parameters = [
			'z_score_threshold' => (float) ( $_POST['z_score_threshold'] ?? 2.0 ),
			'band_deviations' => (float) ( $_POST['band_deviations'] ?? 2.0 ),
			'window_size' => (int) ( $_POST['window_size'] ?? 7 ),
			'historical_days' => (int) ( $_POST['historical_days'] ?? 30 ),
		];

		$rule_id = AnomalyRule::create(
			$client_id,
			$name,
			$description,
			$metric,
			$detection_method,
			$parameters,
			$notification_email,
			$notification_admin_notice,
			$is_active
		);

		if ( $rule_id ) {
			wp_redirect( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies&message=created' ) );
			exit;
		} else {
			wp_redirect( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies&message=error' ) );
			exit;
		}
	}

	/**
	 * Handle edit rule form submission
	 *
	 * @return void
	 */
	private function handle_edit_rule(): void {
		$rule_id = (int) $_POST['rule_id'];
		
		$data = [
			'client_id' => (int) $_POST['client_id'],
			'name' => sanitize_text_field( $_POST['name'] ),
			'description' => sanitize_textarea_field( $_POST['description'] ),
			'metric' => sanitize_text_field( $_POST['metric'] ),
			'detection_method' => sanitize_text_field( $_POST['detection_method'] ),
			'z_score_threshold' => (float) ( $_POST['z_score_threshold'] ?? 2.0 ),
			'band_deviations' => (float) ( $_POST['band_deviations'] ?? 2.0 ),
			'window_size' => (int) ( $_POST['window_size'] ?? 7 ),
			'historical_days' => (int) ( $_POST['historical_days'] ?? 30 ),
			'notification_email' => sanitize_email( $_POST['notification_email'] ),
			'notification_admin_notice' => isset( $_POST['notification_admin_notice'] ),
			'is_active' => isset( $_POST['is_active'] ),
		];

		$success = AnomalyRule::update( $rule_id, $data );

		if ( $success ) {
			wp_redirect( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies&message=updated' ) );
			exit;
		} else {
			wp_redirect( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies&message=error' ) );
			exit;
		}
	}

	/**
	 * Display anomaly notices in admin
	 *
	 * @return void
	 */
	public function display_anomaly_notices(): void {
		$notices = AlertEngine::get_pending_admin_notices();
		$kpi_definitions = MetricsSchema::get_kpi_definitions();

		foreach ( $notices as $notice_key => $notice_data ) {
			// Only show anomaly notices
			if ( ! isset( $notice_data['type'] ) || $notice_data['type'] !== 'anomaly' ) {
				continue;
			}

			$metric_name = $kpi_definitions[ $notice_data['metric'] ]['name'] ?? $notice_data['metric'];
			$client_name = get_the_title( $notice_data['client_id'] ) ?: __( 'Cliente sconosciuto', 'fp-digital-marketing' );
			
			?>
			<div class="notice notice-error is-dismissible fp-anomaly-notice" data-notice-key="<?php echo esc_attr( $notice_key ); ?>">
				<p>
					<strong><?php esc_html_e( 'Anomalia Rilevata:', 'fp-digital-marketing' ); ?></strong>
					<?php echo esc_html( $notice_data['rule_name'] ); ?>
				</p>
				<p>
					<?php printf(
						/* translators: 1: Client name, 2: Metric name, 3: Current value, 4: Detection method, 5: Confidence, 6: Severity */
						esc_html__( 'Cliente: %1$s | Metrica: %2$s | Valore: %3$s | Metodo: %4$s | Confidenza: %5$s | Gravità: %6$s', 'fp-digital-marketing' ),
						esc_html( $client_name ),
						esc_html( $metric_name ),
						esc_html( number_format( $notice_data['current_value'], 2 ) ),
						esc_html( $notice_data['detection_method'] ),
						esc_html( $notice_data['confidence'] ),
						esc_html( $notice_data['severity'] )
					); ?>
				</p>
				<?php if ( isset( $notice_data['anomaly_id'] ) ): ?>
					<p>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente&page=fp-digital-marketing-anomalies&action=anomalies&anomaly_id=' . $notice_data['anomaly_id'] ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Visualizza Dettagli', 'fp-digital-marketing' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Handle AJAX request to dismiss anomaly notice
	 *
	 * @return void
	 */
	public function dismiss_anomaly_notice(): void {
		check_ajax_referer( 'fp_dms_admin_nonce', 'nonce' );

		$notice_key = sanitize_text_field( $_POST['notice_key'] ?? '' );
		
		if ( $notice_key ) {
			$success = AlertEngine::clear_admin_notice( $notice_key );
			wp_send_json_success( [ 'dismissed' => $success ] );
		} else {
			wp_send_json_error( [ 'message' => 'Invalid notice key' ] );
		}
	}

	/**
	 * Handle AJAX request to acknowledge anomaly
	 *
	 * @return void
	 */
	public function acknowledge_anomaly(): void {
		check_ajax_referer( 'fp_dms_admin_nonce', 'nonce' );

		$anomaly_id = (int) ( $_POST['anomaly_id'] ?? 0 );
		
		if ( $anomaly_id ) {
			$success = DetectedAnomaly::acknowledge( $anomaly_id, get_current_user_id() );
			wp_send_json_success( [ 'acknowledged' => $success ] );
		} else {
			wp_send_json_error( [ 'message' => 'Invalid anomaly ID' ] );
		}
	}

	/**
	 * Handle AJAX request to silence anomaly rule
	 *
	 * @return void
	 */
	public function silence_anomaly_rule(): void {
		check_ajax_referer( 'fp_dms_admin_nonce', 'nonce' );

		$rule_id = (int) ( $_POST['rule_id'] ?? 0 );
		$hours = (int) ( $_POST['hours'] ?? 24 );
		
		if ( $rule_id ) {
			$success = AnomalyRule::silence_rule( $rule_id, $hours );
			wp_send_json_success( [ 'silenced' => $success ] );
		} else {
			wp_send_json_error( [ 'message' => 'Invalid rule ID' ] );
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook_suffix Current admin page hook suffix
	 * @return void
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( strpos( $hook_suffix, 'fp-digital-marketing-anomalies' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'fp-dms-anomaly-admin',
			FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/js/anomaly-admin.js',
			[ 'jquery' ],
			FP_DIGITAL_MARKETING_VERSION,
			true
		);

		wp_localize_script( 'fp-dms-anomaly-admin', 'fp_dms_anomaly_admin', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'fp_dms_admin_nonce' ),
			'strings' => [
				'confirm_silence' => __( 'Per quante ore vuoi silenziare questa regola?', 'fp-digital-marketing' ),
				'confirm_acknowledge' => __( 'Sei sicuro di voler riconoscere questa anomalia?', 'fp-digital-marketing' ),
			],
		] );
	}
}