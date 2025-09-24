<?php
/**
 * Platform Connections Admin Interface
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\ConnectionManager;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\DataSources\GoogleOAuth;
use FP\DigitalMarketing\Admin\MenuManager;

/**
 * PlatformConnections class for managing platform integrations
 */
class PlatformConnections {

	/**
	 * Page slug for platform connections
	 */
	public const PAGE_SLUG = 'fp-platform-connections';

	/**
	 * Initialize the platform connections interface
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! ( class_exists( MenuManager::class ) && MenuManager::is_initialized() ) ) {
			add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		}
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_init', [ $this, 'handle_connection_actions' ] );
		add_action( 'wp_ajax_fp_test_connection', [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_fp_refresh_connections', [ $this, 'ajax_refresh_connections' ] );
	}

	/**
	 * Add admin menu page
        *
         * @return void
         */
        public function add_admin_menu(): void {
                if ( class_exists( MenuManager::class ) && MenuManager::is_initialized() ) {
                        return;
                }

                add_submenu_page(
                        'fp-digital-marketing-dashboard',
                        __( 'Connessioni Piattaforme', 'fp-digital-marketing' ),
			__( '🔗 Connessioni', 'fp-digital-marketing' ),
			Capabilities::MANAGE_SETTINGS,
			self::PAGE_SLUG,
			[ $this, 'render_connections_page' ]
		);
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

                wp_enqueue_script(
                        'fp-platform-connections',
                        FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/admin/platform-connections.js',
                        [ 'jquery', 'wp-util', 'wp-api' ],
                        FP_DIGITAL_MARKETING_VERSION,
                        true
                );

		wp_localize_script(
			'fp-platform-connections',
			'fpPlatformConnections',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'fp_platform_connections' ),
				'strings' => [
					'testing' => __( 'Test in corso...', 'fp-digital-marketing' ),
					'testSuccess' => __( 'Test completato con successo', 'fp-digital-marketing' ),
					'testFailed' => __( 'Test fallito', 'fp-digital-marketing' ),
					'refreshing' => __( 'Aggiornamento...', 'fp-digital-marketing' ),
					'confirmRefresh' => __( 'Aggiornare lo stato delle connessioni?', 'fp-digital-marketing' ),
				],
			]
		);

		wp_enqueue_style(
			'fp-platform-connections',
			FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/admin/platform-connections.css',
			[],
			FP_DIGITAL_MARKETING_VERSION
		);
	}

	/**
	 * Handle connection actions
	 *
	 * @return void
	 */
	public function handle_connection_actions(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) {
			return;
		}

		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return;
		}

		// Handle refresh cache action
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'refresh_cache' && 
			 wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'refresh_cache' ) ) {
			ConnectionManager::invalidate_cache();
			wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&refreshed=1' ) );
			exit;
		}
	}

	/**
	 * AJAX handler for testing connections
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'fp_platform_connections', 'nonce' );

		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( __( 'Insufficient permissions', 'fp-digital-marketing' ) );
		}

		$platform_id = sanitize_text_field( $_POST['platform_id'] ?? '' );

		if ( empty( $platform_id ) ) {
			wp_send_json_error( __( 'Platform ID non specificato', 'fp-digital-marketing' ) );
		}

		$result = [ 'success' => false, 'message' => __( 'Test non implementato', 'fp-digital-marketing' ) ];

		switch ( $platform_id ) {
			case 'google_analytics_4':
				$result = ConnectionManager::test_ga4_connection();
				break;
			case 'google_search_console':
				// GSC shares the same OAuth as GA4
				$result = ConnectionManager::test_ga4_connection();
				if ( $result['success'] ) {
					$result['message'] = __( 'Connessione Google OAuth verificata', 'fp-digital-marketing' );
				}
				break;
			default:
				$result = [
					'success' => false,
					'message' => __( 'Test non disponibile per questa piattaforma', 'fp-digital-marketing' ),
				];
		}

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for refreshing connections
	 *
	 * @return void
	 */
	public function ajax_refresh_connections(): void {
		check_ajax_referer( 'fp_platform_connections', 'nonce' );

		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( __( 'Insufficient permissions', 'fp-digital-marketing' ) );
		}

		ConnectionManager::invalidate_cache();
		$connections = ConnectionManager::get_all_connections();
		$health = ConnectionManager::get_connection_health_score();

		wp_send_json_success([
			'connections' => $connections,
			'health' => $health,
			'html' => $this->render_connections_grid( $connections ),
		]);
	}

	/**
	 * Render the platform connections page
	 *
	 * @return void
	 */
	public function render_connections_page(): void {
		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}

		$connections = ConnectionManager::get_all_connections();
		$health = ConnectionManager::get_connection_health_score();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connessioni Piattaforme', 'fp-digital-marketing' ); ?></h1>

			<?php if ( isset( $_GET['refreshed'] ) ): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Cache delle connessioni aggiornata con successo.', 'fp-digital-marketing' ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Health Score -->
			<div class="fp-connection-health-card">
				<h2><?php esc_html_e( 'Stato Connessioni', 'fp-digital-marketing' ); ?></h2>
				<div class="health-score-container">
					<div class="health-score health-<?php echo esc_attr( $health['status'] ); ?>">
						<span class="score-number"><?php echo esc_html( $health['score'] ); ?>%</span>
						<span class="score-label"><?php esc_html_e( 'Health Score', 'fp-digital-marketing' ); ?></span>
					</div>
					<div class="health-details">
						<p>
							<?php
							echo esc_html( sprintf(
								/* translators: %1$d: connected platforms, %2$d: total platforms */
								__( '%1$d di %2$d piattaforme connesse', 'fp-digital-marketing' ),
								$health['connected_platforms'],
								$health['total_platforms']
							) );
							?>
						</p>
						<button type="button" class="button button-secondary" id="refresh-connections">
							<?php esc_html_e( 'Aggiorna Stato', 'fp-digital-marketing' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Recommendations -->
			<?php if ( ! empty( $health['recommendations'] ) ): ?>
				<div class="fp-recommendations-card">
					<h3><?php esc_html_e( 'Raccomandazioni', 'fp-digital-marketing' ); ?></h3>
					<ul class="recommendations-list">
						<?php foreach ( $health['recommendations'] as $recommendation ): ?>
							<li class="recommendation-item priority-<?php echo esc_attr( $recommendation['priority'] ); ?>">
								<strong><?php echo esc_html( $recommendation['title'] ); ?></strong>
								<p><?php echo esc_html( $recommendation['description'] ); ?></p>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<!-- Connections Grid -->
			<div class="fp-connections-grid" id="connections-grid">
				<?php echo $this->render_connections_grid( $connections ); ?>
			</div>

			<!-- Quick Actions -->
			<div class="fp-quick-actions">
				<h3><?php esc_html_e( 'Azioni Rapide', 'fp-digital-marketing' ); ?></h3>
				<div class="action-buttons">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Configura Impostazioni API', 'fp-digital-marketing' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Gestisci Clienti', 'fp-digital-marketing' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=refresh_cache' ), 'refresh_cache' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Aggiorna Cache', 'fp-digital-marketing' ); ?>
					</a>
				</div>
			</div>
		</div>

		<style>
		.fp-connection-health-card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 20px;
			margin: 20px 0;
		}

		.health-score-container {
			display: flex;
			align-items: center;
			gap: 20px;
		}

		.health-score {
			text-align: center;
			padding: 20px;
			border-radius: 50%;
			width: 120px;
			height: 120px;
			display: flex;
			flex-direction: column;
			justify-content: center;
			font-weight: bold;
		}

		.health-score.health-excellent {
			background-color: #00a32a;
			color: white;
		}

		.health-score.health-good {
			background-color: #dba617;
			color: white;
		}

		.health-score.health-fair {
			background-color: #d63638;
			color: white;
		}

		.health-score.health-poor {
			background-color: #8c8f94;
			color: white;
		}

		.score-number {
			font-size: 24px;
		}

		.score-label {
			font-size: 12px;
			margin-top: 5px;
		}

		.fp-recommendations-card {
			background: #fff3cd;
			border: 1px solid #ffeaa7;
			border-radius: 4px;
			padding: 15px;
			margin: 20px 0;
		}

		.recommendations-list {
			list-style: none;
			margin: 0;
			padding: 0;
		}

		.recommendation-item {
			padding: 10px;
			margin: 5px 0;
			border-radius: 4px;
		}

		.recommendation-item.priority-urgent {
			background: #f8d7da;
			border-left: 4px solid #d63638;
		}

		.recommendation-item.priority-high {
			background: #fff3cd;
			border-left: 4px solid #dba617;
		}

		.fp-connections-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin: 20px 0;
		}

		.connection-card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 20px;
			position: relative;
		}

		.connection-card.status-connected {
			border-left: 4px solid #00a32a;
		}

		.connection-card.status-disconnected {
			border-left: 4px solid #8c8f94;
		}

		.connection-card.status-error {
			border-left: 4px solid #d63638;
		}

		.connection-card.status-expired {
			border-left: 4px solid #dba617;
		}

		.connection-status {
			display: inline-block;
			padding: 4px 8px;
			border-radius: 12px;
			font-size: 12px;
			font-weight: bold;
			text-transform: uppercase;
		}

		.connection-status.status-connected {
			background: #00a32a;
			color: white;
		}

		.connection-status.status-disconnected {
			background: #8c8f94;
			color: white;
		}

		.connection-status.status-error {
			background: #d63638;
			color: white;
		}

		.connection-status.status-expired {
			background: #dba617;
			color: white;
		}

		.connection-actions {
			margin-top: 15px;
		}

		.fp-quick-actions {
			background: #f6f7f7;
			padding: 20px;
			border-radius: 4px;
			margin: 30px 0;
		}

		.action-buttons {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
		}

		.setup-steps {
			background: #f6f7f7;
			padding: 15px;
			border-radius: 4px;
			margin-top: 10px;
		}

		.setup-steps ol {
			margin: 10px 0 0 20px;
		}

		.setup-steps li {
			margin: 8px 0;
		}

		@media (max-width: 768px) {
			.fp-connections-grid {
				grid-template-columns: 1fr;
			}
			
			.health-score-container {
				flex-direction: column;
				text-align: center;
			}
		}
		</style>
		<?php
	}

	/**
	 * Render connections grid
	 *
	 * @param array $connections Platform connections
	 * @return string HTML for connections grid
	 */
	private function render_connections_grid( array $connections ): string {
		ob_start();

		foreach ( $connections as $connection ) {
			$this->render_connection_card( $connection );
		}

		return ob_get_clean();
	}

	/**
	 * Render individual connection card
	 *
	 * @param array $connection Connection data
	 * @return void
	 */
	private function render_connection_card( array $connection ): void {
		$status_class = 'status-' . $connection['status'];
		$status_label = $this->get_status_label( $connection['status'] );
		$has_setup_steps = ! empty( $connection['setup_steps'] );
		$test_available = $connection['test_available'] ?? false;
		?>

		<div class="connection-card <?php echo esc_attr( $status_class ); ?>">
			<div class="connection-header">
				<h3><?php echo esc_html( $connection['name'] ); ?></h3>
				<span class="connection-status <?php echo esc_attr( $status_class ); ?>">
					<?php echo esc_html( $status_label ); ?>
				</span>
			</div>

			<div class="connection-details">
				<p><strong><?php esc_html_e( 'Stato:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $connection['message'] ); ?></p>
				<p><small><?php esc_html_e( 'Ultimo controllo:', 'fp-digital-marketing' ); ?> <?php echo esc_html( $connection['last_check'] ); ?></small></p>
				
				<?php if ( isset( $connection['client_count'] ) ): ?>
					<p><strong><?php esc_html_e( 'Clienti configurati:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $connection['client_count'] ); ?></p>
				<?php endif; ?>

				<?php if ( isset( $connection['depends_on'] ) ): ?>
					<p><em><?php esc_html_e( 'Dipende da:', 'fp-digital-marketing' ); ?> <?php echo esc_html( $connection['depends_on'] ); ?></em></p>
				<?php endif; ?>
			</div>

			<div class="connection-actions">
				<?php if ( $test_available ): ?>
					<button type="button" class="button button-secondary test-connection" data-platform="<?php echo esc_attr( $connection['id'] ); ?>">
						<?php esc_html_e( 'Testa Connessione', 'fp-digital-marketing' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $connection['status'] === ConnectionManager::STATUS_DISCONNECTED && ! ( $connection['coming_soon'] ?? false ) ): ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Configura', 'fp-digital-marketing' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $connection['status'] === ConnectionManager::STATUS_EXPIRED ): ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Riconnetti', 'fp-digital-marketing' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $connection['id'] === 'microsoft_clarity' ): ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Configura Clienti', 'fp-digital-marketing' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( $has_setup_steps ): ?>
				<div class="setup-steps">
					<h4><?php esc_html_e( 'Procedura di configurazione:', 'fp-digital-marketing' ); ?></h4>
					<ol>
						<?php foreach ( $connection['setup_steps'] as $step ): ?>
							<li>
								<strong><?php echo esc_html( $step['title'] ); ?></strong>
								<?php if ( ! empty( $step['description'] ) ): ?>
									<br><small><?php echo esc_html( $step['description'] ); ?></small>
								<?php endif; ?>
								<?php if ( ! empty( $step['url'] ) ): ?>
									<br><a href="<?php echo esc_url( $step['url'] ); ?>" target="_blank"><?php esc_html_e( 'Apri link', 'fp-digital-marketing' ); ?> ↗</a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>

			<?php if ( $connection['coming_soon'] ?? false ): ?>
				<div class="coming-soon-notice">
					<p><em><?php esc_html_e( 'Questa integrazione sarà disponibile in una versione futura.', 'fp-digital-marketing' ); ?></em></p>
				</div>
			<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Get human-readable status label
	 *
	 * @param string $status Connection status
	 * @return string Status label
	 */
	private function get_status_label( string $status ): string {
		switch ( $status ) {
			case ConnectionManager::STATUS_CONNECTED:
				return __( 'Connesso', 'fp-digital-marketing' );
			case ConnectionManager::STATUS_DISCONNECTED:
				return __( 'Disconnesso', 'fp-digital-marketing' );
			case ConnectionManager::STATUS_ERROR:
				return __( 'Errore', 'fp-digital-marketing' );
			case ConnectionManager::STATUS_EXPIRED:
				return __( 'Scaduto', 'fp-digital-marketing' );
			case ConnectionManager::STATUS_TESTING:
				return __( 'Test in corso', 'fp-digital-marketing' );
			default:
				return __( 'Sconosciuto', 'fp-digital-marketing' );
		}
	}
}