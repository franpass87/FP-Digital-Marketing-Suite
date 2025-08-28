<?php
/**
 * Reports Page Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\DataSources;
use FP\DigitalMarketing\Helpers\ReportGenerator;
use FP\DigitalMarketing\Helpers\ReportScheduler;
use FP\DigitalMarketing\DataSources\GoogleAnalytics4;
use FP\DigitalMarketing\DataSources\GoogleOAuth;
use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Reports class for plugin administration
 * 
 * This class provides a debug interface to view registered data sources
 * and their configurations. It's useful for developers and administrators
 * to understand the current state of data source integrations.
 */
class Reports {

	/**
	 * Page slug for reports
	 */
	private const PAGE_SLUG = 'fp-digital-marketing-reports';

	/**
	 * Initialize the reports page
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_report_actions' ] );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'FP Digital Marketing Reports', 'fp-digital-marketing' ),
			__( 'DM Reports', 'fp-digital-marketing' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_reports_page' ],
			'dashicons-chart-line',
			25
		);
	}

	/**
	 * Handle report-related actions (download PDF, generate reports)
	 *
	 * @return void
	 */
	public function handle_report_actions(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle PDF download
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_pdf' && isset( $_GET['client_id'] ) ) {
			$this->download_pdf_report( intval( $_GET['client_id'] ) );
		}

		// Handle manual report generation
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'generate_reports' && wp_verify_nonce( $_POST['_wpnonce'], 'generate_reports' ) ) {
			$count = ReportScheduler::trigger_manual_generation();
			add_action( 'admin_notices', function() use ( $count ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				printf( __( 'Generati %d report con successo!', 'fp-digital-marketing' ), $count );
				echo '</p></div>';
			} );
		}
	}

	/**
	 * Download PDF report for a client
	 *
	 * @param int $client_id Client ID
	 * @return void
	 */
	private function download_pdf_report( int $client_id ): void {
		$report_data = ReportGenerator::generate_demo_report_data( $client_id );
		$pdf_content = ReportGenerator::generate_pdf_report( $report_data );

		$filename = sprintf( 'digital-marketing-report-%d-%s.pdf', $client_id, date( 'Y-m-d' ) );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $pdf_content ) );

		echo $pdf_content;
		exit;
	}

	/**
	 * Render the reports page
	 *
	 * @return void
	 */
	public function render_reports_page(): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}

		// Get clients for report generation
		$clientes = get_posts( [
			'post_type'      => 'cliente',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		] );

		// Get data sources for debug output.
		$all_data_sources = fp_dms_get_data_sources();
		$analytics_sources = fp_dms_get_data_sources( DataSources::TYPE_ANALYTICS );
		$available_sources = DataSources::get_data_sources_by_status( 'available' );
		$planned_sources = DataSources::get_data_sources_by_status( 'planned' );
		$data_source_types = DataSources::get_data_source_types();

		// Generate demo report for preview
		$demo_report_data = ReportGenerator::generate_demo_report_data( 1 );
		$demo_html = ReportGenerator::generate_html_report( $demo_report_data );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<!-- Report Generation Section -->
			<div class="fp-dms-reports-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
				<h2><?php esc_html_e( 'Generazione Report Automatici', 'fp-digital-marketing' ); ?></h2>
				
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
					<div class="fp-dms-card" style="background: #f8f9fa; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
						<h3 style="margin-top: 0;"><?php esc_html_e( 'Scheduler Status', 'fp-digital-marketing' ); ?></h3>
						<?php if ( ReportScheduler::is_scheduled() ) : ?>
							<p><span style="color: #00a32a;">●</span> <?php esc_html_e( 'Attivo', 'fp-digital-marketing' ); ?></p>
							<p><strong><?php esc_html_e( 'Prossima esecuzione:', 'fp-digital-marketing' ); ?></strong><br>
							<?php echo esc_html( ReportScheduler::get_next_scheduled_time() ); ?></p>
						<?php else : ?>
							<p><span style="color: #d63638;">●</span> <?php esc_html_e( 'Non programmato', 'fp-digital-marketing' ); ?></p>
						<?php endif; ?>
					</div>
					
					<div class="fp-dms-card" style="background: #f8f9fa; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
						<h3 style="margin-top: 0;"><?php esc_html_e( 'Clienti Disponibili', 'fp-digital-marketing' ); ?></h3>
						<p><strong><?php echo count( $clientes ); ?></strong> <?php esc_html_e( 'clienti trovati', 'fp-digital-marketing' ); ?></p>
					</div>
				</div>

				<div style="margin: 20px 0;">
					<form method="post" style="display: inline-block;">
						<?php wp_nonce_field( 'generate_reports' ); ?>
						<input type="hidden" name="action" value="generate_reports">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Genera Report Manualmente', 'fp-digital-marketing' ); ?>
						</button>
					</form>
				</div>

				<?php if ( ! empty( $clientes ) ) : ?>
					<h3><?php esc_html_e( 'Download Report PDF', 'fp-digital-marketing' ); ?></h3>
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
						<?php foreach ( $clientes as $cliente ) : ?>
							<div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
								<h4 style="margin: 0 0 10px 0;"><?php echo esc_html( $cliente->post_title ); ?></h4>
								<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'download_pdf', 'client_id' => $cliente->ID ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>" 
								   class="button button-secondary">
									<?php esc_html_e( 'Scarica PDF', 'fp-digital-marketing' ); ?>
								</a>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- GA4 Metrics Section -->
			<?php $this->render_ga4_metrics_section(); ?>

			<!-- Report Preview Section -->
			<div class="fp-dms-preview-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
				<h2><?php esc_html_e( 'Anteprima Report Demo', 'fp-digital-marketing' ); ?></h2>
				<p><?php esc_html_e( 'Questo è un\'anteprima del template del report con dati mock.', 'fp-digital-marketing' ); ?></p>
				
				<div style="border: 1px solid #ddd; margin: 20px 0; max-height: 600px; overflow-y: auto;">
					<?php echo $demo_html; ?>
				</div>
				
				<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'download_pdf', 'client_id' => 1 ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>" 
				   class="button button-primary" target="_blank">
					<?php esc_html_e( 'Scarica Report Demo PDF', 'fp-digital-marketing' ); ?>
				</a>
			</div>

			<!-- Debug Section - Data Sources -->
			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Debug Output - Data Sources Registry', 'fp-digital-marketing' ); ?></strong><br>
					<?php esc_html_e( 'Questa sezione mostra lo stato attuale del registro delle sorgenti dati.', 'fp-digital-marketing' ); ?>
				</p>
			</div>

			<!-- Summary Cards -->
			<div class="fp-dms-summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
				<div class="fp-dms-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Totali', 'fp-digital-marketing' ); ?></h3>
					<p><strong><?php echo esc_html( count( $all_data_sources ) ); ?></strong> <?php esc_html_e( 'sorgenti totali', 'fp-digital-marketing' ); ?></p>
					<p><strong><?php echo esc_html( count( $available_sources ) ); ?></strong> <?php esc_html_e( 'disponibili', 'fp-digital-marketing' ); ?></p>
					<p><strong><?php echo esc_html( count( $planned_sources ) ); ?></strong> <?php esc_html_e( 'pianificate', 'fp-digital-marketing' ); ?></p>
				</div>
				
				<div class="fp-dms-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Tipi Disponibili', 'fp-digital-marketing' ); ?></h3>
					<?php foreach ( $data_source_types as $type => $label ) : ?>
						<?php $count = count( fp_dms_get_data_sources( $type ) ); ?>
						<p><strong><?php echo esc_html( $count ); ?></strong> <?php echo esc_html( $label ); ?></p>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Available Data Sources -->
			<h2><?php esc_html_e( 'Sorgenti Dati Disponibili', 'fp-digital-marketing' ); ?></h2>
			<?php if ( empty( $available_sources ) ) : ?>
				<p><?php esc_html_e( 'Nessuna sorgente dati attualmente disponibile.', 'fp-digital-marketing' ); ?></p>
			<?php else : ?>
				<div class="fp-dms-sources-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
					<?php foreach ( $available_sources as $source ) : ?>
						<div class="fp-dms-source-card" style="background: #fff; border: 1px solid #00a32a; border-radius: 4px; padding: 20px;">
							<h4 style="margin-top: 0; color: #00a32a;">
								<?php echo esc_html( $source['name'] ); ?>
								<span style="background: #00a32a; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">
									<?php echo esc_html( strtoupper( $source['status'] ) ); ?>
								</span>
							</h4>
							<p><strong><?php esc_html_e( 'Tipo:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $data_source_types[ $source['type'] ] ?? $source['type'] ); ?></p>
							<p><?php echo esc_html( $source['description'] ); ?></p>
							<p><strong><?php esc_html_e( 'Capacità:', 'fp-digital-marketing' ); ?></strong></p>
							<ul style="margin-left: 20px;">
								<?php foreach ( $source['capabilities'] as $capability ) : ?>
									<li><?php echo esc_html( $capability ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Planned Data Sources -->
			<h2><?php esc_html_e( 'Sorgenti Dati Pianificate', 'fp-digital-marketing' ); ?></h2>
			<?php if ( empty( $planned_sources ) ) : ?>
				<p><?php esc_html_e( 'Nessuna sorgente dati pianificata.', 'fp-digital-marketing' ); ?></p>
			<?php else : ?>
				<div class="fp-dms-sources-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
					<?php foreach ( $planned_sources as $source ) : ?>
						<div class="fp-dms-source-card" style="background: #fff; border: 1px solid #0073aa; border-radius: 4px; padding: 20px;">
							<h4 style="margin-top: 0; color: #0073aa;">
								<?php echo esc_html( $source['name'] ); ?>
								<span style="background: #0073aa; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">
									<?php echo esc_html( strtoupper( $source['status'] ) ); ?>
								</span>
							</h4>
							<p><strong><?php esc_html_e( 'Tipo:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $data_source_types[ $source['type'] ] ?? $source['type'] ); ?></p>
							<p><?php echo esc_html( $source['description'] ); ?></p>
							<p><strong><?php esc_html_e( 'Credenziali Richieste:', 'fp-digital-marketing' ); ?></strong></p>
							<ul style="margin-left: 20px;">
								<?php foreach ( $source['required_credentials'] as $credential ) : ?>
									<li><code><?php echo esc_html( $credential ); ?></code></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Debug Raw Data -->
			<h2><?php esc_html_e( 'Debug: Dati Grezzi', 'fp-digital-marketing' ); ?></h2>
			<details style="margin: 20px 0;">
				<summary style="cursor: pointer; padding: 10px; background: #f1f1f1; border-radius: 4px;">
					<strong><?php esc_html_e( 'Visualizza dati grezzi JSON', 'fp-digital-marketing' ); ?></strong>
				</summary>
				<div style="margin-top: 10px;">
					<h4><?php esc_html_e( 'Tutte le sorgenti dati:', 'fp-digital-marketing' ); ?></h4>
					<pre style="background: #f9f9f9; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px;"><?php echo esc_html( wp_json_encode( $all_data_sources, JSON_PRETTY_PRINT ) ); ?></pre>
					
					<h4><?php esc_html_e( 'Solo Analytics:', 'fp-digital-marketing' ); ?></h4>
					<pre style="background: #f9f9f9; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px;"><?php echo esc_html( wp_json_encode( $analytics_sources, JSON_PRETTY_PRINT ) ); ?></pre>
				</div>
			</details>

			<!-- Developer Information -->
			<div class="notice notice-info" style="margin-top: 30px;">
				<h3><?php esc_html_e( 'Informazioni per Sviluppatori', 'fp-digital-marketing' ); ?></h3>
				<p><strong><?php esc_html_e( 'Funzione Helper:', 'fp-digital-marketing' ); ?></strong></p>
				<p><?php esc_html_e( 'Utilizza la funzione', 'fp-digital-marketing' ); ?> <code>fp_dms_get_data_sources()</code> <?php esc_html_e( 'per ottenere l\'elenco delle sorgenti dati registrate.', 'fp-digital-marketing' ); ?></p>
				
				<p><strong><?php esc_html_e( 'Esempi di utilizzo:', 'fp-digital-marketing' ); ?></strong></p>
				<pre style="background: #f9f9f9; padding: 10px; border-radius: 4px;">
// Ottieni tutte le sorgenti dati
$all_sources = fp_dms_get_data_sources();

// Ottieni solo sorgenti di tipo analytics
$analytics_sources = fp_dms_get_data_sources( 'analytics' );

// Verifica se una sorgente è disponibile
$is_available = \FP\DigitalMarketing\Helpers\DataSources::is_data_source_available( 'google_analytics_4' );
				</pre>
				
				<p><strong><?php esc_html_e( 'Hook per estensioni:', 'fp-digital-marketing' ); ?></strong></p>
				<p><?php esc_html_e( 'Utilizza il filtro', 'fp-digital-marketing' ); ?> <code>fp_dms_data_sources</code> <?php esc_html_e( 'per aggiungere nuove sorgenti dati da plugin o temi.', 'fp-digital-marketing' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render GA4 metrics section
	 *
	 * @return void
	 */
	private function render_ga4_metrics_section(): void {
		$oauth = new GoogleOAuth();
		$connection_status = $oauth->get_connection_status();
		$api_keys = get_option( 'fp_digital_marketing_api_keys', [] );
		$property_id = $api_keys['ga4_property_id'] ?? '';

		?>
		<div class="fp-dms-ga4-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Google Analytics 4 - Metriche Live', 'fp-digital-marketing' ); ?></h2>
			
			<div class="ga4-connection-info" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Stato Connessione', 'fp-digital-marketing' ); ?></h3>
				<p class="ga4-status <?php echo esc_attr( $connection_status['class'] ); ?>">
					<span class="status-indicator" style="<?php echo $connection_status['connected'] ? 'color: #00a32a;' : 'color: #d63638;'; ?>">●</span>
					<?php echo esc_html( $connection_status['status'] ); ?>
				</p>
				
				<?php if ( ! empty( $property_id ) ): ?>
					<p><strong><?php esc_html_e( 'Property ID:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $property_id ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $connection_status['connected'] && ! empty( $property_id ) ): ?>
				<?php $this->render_ga4_demo_metrics( $property_id ); ?>
				<?php $this->render_cached_ga4_metrics(); ?>
			<?php else: ?>
				<div class="notice notice-warning inline">
					<p>
						<?php esc_html_e( 'Per visualizzare le metriche GA4, configura prima la connessione nelle', 'fp-digital-marketing' ); ?>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=fp-digital-marketing-settings' ) ); ?>">
							<?php esc_html_e( 'Impostazioni', 'fp-digital-marketing' ); ?>
						</a>.
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render GA4 demo metrics
	 *
	 * @param string $property_id GA4 property ID
	 * @return void
	 */
	private function render_ga4_demo_metrics( string $property_id ): void {
		// Create GA4 instance and fetch demo data
		$ga4 = new GoogleAnalytics4( $property_id );
		$end_date = date( 'Y-m-d' );
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		
		// For demo purposes, we'll show mock data even if not fully connected
		$demo_metrics = [
			'sessions' => rand( 1500, 3500 ),
			'users' => rand( 1200, 2800 ),
			'conversions' => rand( 25, 85 ),
			'revenue' => rand( 2500, 8500 ),
		];

		?>
		<div class="ga4-demo-metrics">
			<h3><?php esc_html_e( 'Metriche Demo (Ultimi 30 giorni)', 'fp-digital-marketing' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Questi sono dati dimostrativi. In produzione verrebbero mostrate le metriche reali da GA4.', 'fp-digital-marketing' ); ?></p>
			
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
				<div class="metric-card" style="background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 4px; text-align: center;">
					<h4 style="margin: 0 0 10px 0; color: #1e40af;"><?php esc_html_e( 'Sessioni', 'fp-digital-marketing' ); ?></h4>
					<div style="font-size: 2em; font-weight: bold; color: #333;"><?php echo number_format( $demo_metrics['sessions'] ); ?></div>
				</div>
				
				<div class="metric-card" style="background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 4px; text-align: center;">
					<h4 style="margin: 0 0 10px 0; color: #16a34a;"><?php esc_html_e( 'Utenti', 'fp-digital-marketing' ); ?></h4>
					<div style="font-size: 2em; font-weight: bold; color: #333;"><?php echo number_format( $demo_metrics['users'] ); ?></div>
				</div>
				
				<div class="metric-card" style="background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 4px; text-align: center;">
					<h4 style="margin: 0 0 10px 0; color: #dc2626;"><?php esc_html_e( 'Conversioni', 'fp-digital-marketing' ); ?></h4>
					<div style="font-size: 2em; font-weight: bold; color: #333;"><?php echo number_format( $demo_metrics['conversions'] ); ?></div>
				</div>
				
				<div class="metric-card" style="background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 4px; text-align: center;">
					<h4 style="margin: 0 0 10px 0; color: #7c3aed;"><?php esc_html_e( 'Ricavi', 'fp-digital-marketing' ); ?></h4>
					<div style="font-size: 2em; font-weight: bold; color: #333;">€<?php echo number_format( $demo_metrics['revenue'] ); ?></div>
				</div>
			</div>

			<div style="margin-top: 20px;">
				<button type="button" class="button button-primary" onclick="location.reload();">
					<?php esc_html_e( 'Aggiorna Metriche', 'fp-digital-marketing' ); ?>
				</button>
				<small style="margin-left: 10px; color: #666;">
					<?php esc_html_e( 'Ultimo aggiornamento:', 'fp-digital-marketing' ); ?> <?php echo esc_html( current_time( 'H:i:s' ) ); ?>
				</small>
			</div>
		</div>
		<?php
	}

	/**
	 * Render cached GA4 metrics from database
	 *
	 * @return void
	 */
	private function render_cached_ga4_metrics(): void {
		// Get recent cached metrics
		$recent_metrics = MetricsCache::get_metrics([
			'source' => GoogleAnalytics4::SOURCE_ID,
			'limit' => 10,
			'order_by' => 'fetched_at'
		]);

		if ( empty( $recent_metrics ) ) {
			return;
		}

		?>
		<div class="ga4-cached-metrics" style="margin-top: 30px;">
			<h3><?php esc_html_e( 'Metriche Cache Database', 'fp-digital-marketing' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Dati storici salvati nel database per reportistica.', 'fp-digital-marketing' ); ?></p>
			
			<div style="overflow-x: auto;">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Metrica', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Valore', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Periodo', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Recuperato', 'fp-digital-marketing' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_metrics as $metric ): ?>
							<tr>
								<td><strong><?php echo esc_html( $metric->metric ); ?></strong></td>
								<td><?php echo esc_html( number_format( (float) $metric->value ) ); ?></td>
								<td>
									<?php 
									echo esc_html( date( 'd/m/Y', strtotime( $metric->period_start ) ) );
									echo ' - ';
									echo esc_html( date( 'd/m/Y', strtotime( $metric->period_end ) ) );
									?>
								</td>
								<td><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $metric->fetched_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<p style="margin-top: 15px;">
				<strong><?php esc_html_e( 'Totale record cache:', 'fp-digital-marketing' ); ?></strong>
				<?php 
				$total_cached = MetricsCache::count(['source' => GoogleAnalytics4::SOURCE_ID]);
				echo esc_html( number_format( $total_cached ) );
				?>
			</p>
		</div>
		<?php
	}
}