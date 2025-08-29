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
use FP\DigitalMarketing\Helpers\MetricsAggregator;
use FP\DigitalMarketing\Helpers\MetricsSchema;
use FP\DigitalMarketing\Helpers\SyncEngine;
use FP\DigitalMarketing\Helpers\Security;
use FP\DigitalMarketing\DataSources\GoogleAnalytics4;
use FP\DigitalMarketing\DataSources\GoogleOAuth;
use FP\DigitalMarketing\DataSources\GoogleSearchConsole;
use FP\DigitalMarketing\Models\MetricsCache;
use FP\DigitalMarketing\Models\SyncLog;

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
		// Handle PDF download with security verification
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_pdf' && isset( $_GET['client_id'] ) ) {
			if ( Security::verify_capability_with_logging( 'manage_options' ) ) {
				$this->download_pdf_report( intval( $_GET['client_id'] ) );
			} else {
				wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
			}
		}

		// Handle CSV download
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_csv' && isset( $_GET['client_id'] ) ) {
			if ( Security::verify_capability_with_logging( 'manage_options' ) ) {
				$separator = sanitize_text_field( $_GET['separator'] ?? ',' );
				$this->download_csv_report( intval( $_GET['client_id'] ), $separator );
			} else {
				wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
			}
		}

		// Handle custom report generation
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'generate_custom_report' && 
			 Security::verify_capability_with_logging( 'manage_options' ) &&
			 Security::verify_nonce_with_logging( 'generate_custom_report' ) ) {
			$this->handle_custom_report_generation();
		}

		// Handle manual report generation
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'generate_reports' && 
			 Security::verify_capability_with_logging( 'manage_options' ) &&
			 Security::verify_nonce_with_logging( 'generate_reports' ) ) {
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
		
		// Validate report data
		$validation = ReportGenerator::validate_report_data( $report_data );
		if ( ! $validation['valid'] ) {
			wp_die( esc_html( implode( ', ', $validation['errors'] ) ) );
		}

		try {
			$pdf_content = ReportGenerator::generate_pdf_report( $report_data );
			$filename = sprintf( 'digital-marketing-report-%d-%s.pdf', $client_id, date( 'Y-m-d' ) );

			// Log the report generation
			ReportGenerator::log_report_generation( $client_id, 'pdf', strlen( $pdf_content ), true );

			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . strlen( $pdf_content ) );

			echo $pdf_content;
			exit;
		} catch ( Exception $e ) {
			// Log the error
			ReportGenerator::log_report_generation( $client_id, 'pdf', 0, false, $e->getMessage() );
			wp_die( esc_html__( 'Errore nella generazione del report PDF', 'fp-digital-marketing' ) );
		}
	}

	/**
	 * Download CSV report for a client
	 *
	 * @param int $client_id Client ID
	 * @param string $separator CSV separator
	 * @return void
	 */
	private function download_csv_report( int $client_id, string $separator = ',' ): void {
		$report_data = ReportGenerator::generate_demo_report_data( $client_id );
		
		// Validate report data
		$validation = ReportGenerator::validate_report_data( $report_data );
		if ( ! $validation['valid'] ) {
			wp_die( esc_html( implode( ', ', $validation['errors'] ) ) );
		}

		try {
			$csv_content = ReportGenerator::generate_csv_report( $report_data, $separator );
			$filename = sprintf( 'digital-marketing-report-%d-%s.csv', $client_id, date( 'Y-m-d' ) );

			// Log the report generation
			ReportGenerator::log_report_generation( $client_id, 'csv', strlen( $csv_content ), true );

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . strlen( $csv_content ) );

			echo $csv_content;
			exit;
		} catch ( Exception $e ) {
			// Log the error
			ReportGenerator::log_report_generation( $client_id, 'csv', 0, false, $e->getMessage() );
			wp_die( esc_html__( 'Errore nella generazione del report CSV', 'fp-digital-marketing' ) );
		}
	}

	/**
	 * Handle custom report generation form submission
	 *
	 * @return void
	 */
	private function handle_custom_report_generation(): void {
		$client_id = intval( $_POST['client_id'] ?? 0 );
		$format = sanitize_text_field( $_POST['format'] ?? 'pdf' );
		$period_start = sanitize_text_field( $_POST['period_start'] ?? '' );
		$period_end = sanitize_text_field( $_POST['period_end'] ?? '' );
		$selected_kpis = isset( $_POST['selected_kpis'] ) ? array_map( 'sanitize_text_field', $_POST['selected_kpis'] ) : [];
		$csv_separator = sanitize_text_field( $_POST['csv_separator'] ?? ',' );

		if ( ! $client_id ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Cliente non selezionato', 'fp-digital-marketing' );
				echo '</p></div>';
			} );
			return;
		}

		try {
			// Get actual data using MetricsAggregator if real data is needed
			// For now, using demo data but filtering KPIs if specified
			$report_data = ReportGenerator::generate_demo_report_data( $client_id );
			
			// Override period if specified
			if ( $period_start && $period_end ) {
				$report_data['period_start'] = $period_start;
				$report_data['period_end'] = $period_end;
			}

			// Filter KPIs if specific ones were selected
			if ( ! empty( $selected_kpis ) ) {
				$filtered_kpis = [];
				foreach ( $selected_kpis as $kpi ) {
					if ( isset( $report_data['kpis'][ $kpi ] ) ) {
						$filtered_kpis[ $kpi ] = $report_data['kpis'][ $kpi ];
					}
				}
				$report_data['kpis'] = $filtered_kpis;
			}

			// Generate and download based on format
			if ( $format === 'csv' ) {
				$this->download_csv_report_with_data( $report_data, $csv_separator );
			} else {
				$this->download_pdf_report_with_data( $report_data );
			}
		} catch ( Exception $e ) {
			add_action( 'admin_notices', function() use ( $e ) {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Errore nella generazione del report: ', 'fp-digital-marketing' ) . esc_html( $e->getMessage() );
				echo '</p></div>';
			} );
		}
	}

	/**
	 * Download PDF report with custom data
	 *
	 * @param array $report_data Report data
	 * @return void
	 */
	private function download_pdf_report_with_data( array $report_data ): void {
		$validation = ReportGenerator::validate_report_data( $report_data );
		if ( ! $validation['valid'] ) {
			wp_die( esc_html( implode( ', ', $validation['errors'] ) ) );
		}

		$pdf_content = ReportGenerator::generate_pdf_report( $report_data );
		$filename = sprintf( 'custom-report-%d-%s.pdf', $report_data['client_id'], date( 'Y-m-d-H-i' ) );

		ReportGenerator::log_report_generation( $report_data['client_id'], 'pdf', strlen( $pdf_content ), true );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $pdf_content ) );

		echo $pdf_content;
		exit;
	}

	/**
	 * Download CSV report with custom data
	 *
	 * @param array $report_data Report data
	 * @param string $separator CSV separator
	 * @return void
	 */
	private function download_csv_report_with_data( array $report_data, string $separator = ',' ): void {
		$validation = ReportGenerator::validate_report_data( $report_data );
		if ( ! $validation['valid'] ) {
			wp_die( esc_html( implode( ', ', $validation['errors'] ) ) );
		}

		$csv_content = ReportGenerator::generate_csv_report( $report_data, $separator );
		$filename = sprintf( 'custom-report-%d-%s.csv', $report_data['client_id'], date( 'Y-m-d-H-i' ) );

		ReportGenerator::log_report_generation( $report_data['client_id'], 'csv', strlen( $csv_content ), true );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $csv_content ) );

		echo $csv_content;
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
								<div style="display: flex; gap: 5px; flex-wrap: wrap;">
									<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'download_pdf', 'client_id' => $cliente->ID ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>" 
									   class="button button-secondary">
										<?php esc_html_e( 'PDF', 'fp-digital-marketing' ); ?>
									</a>
									<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'download_csv', 'client_id' => $cliente->ID ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>" 
									   class="button button-secondary">
										<?php esc_html_e( 'CSV', 'fp-digital-marketing' ); ?>
									</a>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Custom Report Generation Section -->
			<div class="fp-dms-reports-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
				<h2><?php esc_html_e( 'Generazione Report Personalizzati', 'fp-digital-marketing' ); ?></h2>
				<p><?php esc_html_e( 'Crea report personalizzati selezionando i KPI, il periodo e il formato desiderati.', 'fp-digital-marketing' ); ?></p>
				
				<form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
					<?php wp_nonce_field( 'generate_custom_report' ); ?>
					<input type="hidden" name="action" value="generate_custom_report">
					
					<div class="form-group">
						<label for="client_id"><strong><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?></strong></label>
						<select name="client_id" id="client_id" required style="width: 100%; padding: 8px; margin-top: 5px;">
							<option value=""><?php esc_html_e( 'Seleziona cliente...', 'fp-digital-marketing' ); ?></option>
							<?php foreach ( $clientes as $cliente ) : ?>
								<option value="<?php echo esc_attr( $cliente->ID ); ?>">
									<?php echo esc_html( $cliente->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="form-group">
						<label for="period_start"><strong><?php esc_html_e( 'Periodo', 'fp-digital-marketing' ); ?></strong></label>
						<div style="display: flex; gap: 10px; margin-top: 5px;">
							<input type="date" name="period_start" id="period_start" style="flex: 1; padding: 8px;">
							<input type="date" name="period_end" id="period_end" style="flex: 1; padding: 8px;">
						</div>
						<small style="color: #666;"><?php esc_html_e( 'Lascia vuoto per utilizzare il periodo predefinito', 'fp-digital-marketing' ); ?></small>
					</div>

					<div class="form-group">
						<label for="format"><strong><?php esc_html_e( 'Formato', 'fp-digital-marketing' ); ?></strong></label>
						<div style="margin-top: 5px;">
							<label style="display: block; margin: 5px 0;">
								<input type="radio" name="format" value="pdf" checked> PDF
							</label>
							<label style="display: block; margin: 5px 0;">
								<input type="radio" name="format" value="csv"> CSV
							</label>
						</div>
						
						<div id="csv_options" style="margin-top: 10px; display: none;">
							<label for="csv_separator" style="font-size: 12px;"><?php esc_html_e( 'Separatore CSV:', 'fp-digital-marketing' ); ?></label>
							<select name="csv_separator" id="csv_separator" style="width: 100%; margin-top: 3px;">
								<option value=","><?php esc_html_e( 'Virgola (,)', 'fp-digital-marketing' ); ?></option>
								<option value=";"><?php esc_html_e( 'Punto e virgola (;)', 'fp-digital-marketing' ); ?></option>
								<option value="\t"><?php esc_html_e( 'Tab', 'fp-digital-marketing' ); ?></option>
							</select>
						</div>
					</div>

					<div class="form-group" style="grid-column: 1 / -1;">
						<label><strong><?php esc_html_e( 'KPI da includere', 'fp-digital-marketing' ); ?></strong></label>
						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
							<?php
							$available_kpis = [
								'sessions' => __( 'Sessioni', 'fp-digital-marketing' ),
								'users' => __( 'Utenti', 'fp-digital-marketing' ),
								'conversion_rate' => __( 'Tasso di Conversione', 'fp-digital-marketing' ),
								'revenue' => __( 'Fatturato', 'fp-digital-marketing' ),
							];
							foreach ( $available_kpis as $kpi_key => $kpi_label ) :
							?>
								<label style="display: flex; align-items: center; padding: 8px; background: #f8f9fa; border-radius: 4px;">
									<input type="checkbox" name="selected_kpis[]" value="<?php echo esc_attr( $kpi_key ); ?>" checked style="margin-right: 8px;">
									<?php echo esc_html( $kpi_label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<small style="color: #666; display: block; margin-top: 5px;">
							<?php esc_html_e( 'Lascia tutto selezionato per includere tutti i KPI disponibili', 'fp-digital-marketing' ); ?>
						</small>
					</div>

					<div class="form-group" style="grid-column: 1 / -1;">
						<button type="submit" class="button button-primary" style="padding: 10px 20px;">
							<?php esc_html_e( 'Genera Report Personalizzato', 'fp-digital-marketing' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Report Logs Section -->
			<div class="fp-dms-reports-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
				<h2><?php esc_html_e( 'Log Report Generati', 'fp-digital-marketing' ); ?></h2>
				<?php
				$logs = ReportGenerator::get_report_logs( 20 );
				if ( ! empty( $logs ) ) :
				?>
					<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Data/Ora', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Formato', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Dimensione', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Stato', 'fp-digital-marketing' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td><?php echo esc_html( $log['timestamp'] ); ?></td>
									<td><?php echo esc_html( $log['client_id'] ); ?></td>
									<td><?php echo esc_html( strtoupper( $log['format'] ) ); ?></td>
									<td><?php echo $log['file_size'] > 0 ? esc_html( size_format( $log['file_size'] ) ) : '-'; ?></td>
									<td>
										<?php if ( $log['success'] ) : ?>
											<span style="color: #00a32a;">✓ <?php esc_html_e( 'Successo', 'fp-digital-marketing' ); ?></span>
										<?php else : ?>
											<span style="color: #d63638;">✗ <?php esc_html_e( 'Errore', 'fp-digital-marketing' ); ?></span>
											<?php if ( ! empty( $log['error_message'] ) ) : ?>
												<br><small style="color: #666;"><?php echo esc_html( $log['error_message'] ); ?></small>
											<?php endif; ?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'Nessun report generato ancora.', 'fp-digital-marketing' ); ?></p>
				<?php endif; ?>
			</div>

			<script>
			document.addEventListener('DOMContentLoaded', function() {
				const formatRadios = document.querySelectorAll('input[name="format"]');
				const csvOptions = document.getElementById('csv_options');
				
				function toggleCSVOptions() {
					const selectedFormat = document.querySelector('input[name="format"]:checked').value;
					csvOptions.style.display = selectedFormat === 'csv' ? 'block' : 'none';
				}
				
				formatRadios.forEach(radio => {
					radio.addEventListener('change', toggleCSVOptions);
				});
				
				toggleCSVOptions(); // Initial setup
			});
			</script>

			<!-- Sync Engine Status Section -->
			<?php $this->render_sync_status_section(); ?>

			<!-- GA4 Metrics Section -->
			<?php $this->render_ga4_metrics_section(); ?>

			<!-- GSC Metrics Section -->
			<?php $this->render_gsc_metrics_section(); ?>

			<!-- Metrics Aggregator Section -->
			<?php $this->render_aggregator_section(); ?>

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
	 * Render GSC metrics section
	 *
	 * @return void
	 */
	private function render_gsc_metrics_section(): void {
		$oauth = new GoogleOAuth();
		$connection_status = $oauth->get_connection_status();
		$api_keys = get_option( 'fp_digital_marketing_api_keys', [] );
		$site_url = $api_keys['gsc_site_url'] ?? '';

		?>
		<div class="fp-dms-gsc-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Google Search Console - Metriche SEO', 'fp-digital-marketing' ); ?></h2>
			
			<div class="gsc-connection-info" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Stato Connessione', 'fp-digital-marketing' ); ?></h3>
				<p class="gsc-status" style="<?php echo ( $connection_status['connected'] && ! empty( $site_url ) ) ? 'color: #00a32a;' : 'color: #d63638;'; ?>">
					<span class="status-indicator">●</span>
					<?php 
					if ( $connection_status['connected'] && ! empty( $site_url ) ) {
						esc_html_e( 'Connesso', 'fp-digital-marketing' );
					} else {
						esc_html_e( 'Non connesso', 'fp-digital-marketing' );
					}
					?>
				</p>
				
				<?php if ( ! empty( $site_url ) ): ?>
					<p><strong><?php esc_html_e( 'Site URL:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $site_url ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $connection_status['connected'] && ! empty( $site_url ) ): ?>
				<?php $this->render_gsc_demo_metrics( $site_url ); ?>
				<?php $this->render_cached_gsc_metrics(); ?>
			<?php else: ?>
				<div class="notice notice-warning inline">
					<p>
						<?php esc_html_e( 'Per visualizzare le metriche Search Console, configura prima la connessione nelle', 'fp-digital-marketing' ); ?>
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

	/**
	 * Render GSC demo metrics
	 *
	 * @param string $site_url GSC site URL
	 * @return void
	 */
	private function render_gsc_demo_metrics( string $site_url ): void {
		// Create GSC instance and fetch demo data
		$gsc = new GoogleSearchConsole( $site_url );
		$end_date = date( 'Y-m-d' );
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );

		// Fetch demo metrics (mock data)
		$metrics = $gsc->fetch_metrics( 1, $start_date, $end_date );

		if ( $metrics ) {
			?>
			<div class="gsc-demo-metrics" style="margin: 15px 0;">
				<h3><?php esc_html_e( 'Metriche SEO Demo (Ultimi 30 giorni)', 'fp-digital-marketing' ); ?></h3>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
					<div style="background: #f8f9fa; padding: 15px; border-radius: 4px; text-align: center;">
						<h4 style="margin: 0 0 10px 0; color: #1e73be;"><?php esc_html_e( 'Impressioni', 'fp-digital-marketing' ); ?></h4>
						<p style="font-size: 24px; font-weight: bold; margin: 0; color: #2c3e50;"><?php echo esc_html( number_format( (int) $metrics['impressions'] ) ); ?></p>
					</div>
					<div style="background: #f8f9fa; padding: 15px; border-radius: 4px; text-align: center;">
						<h4 style="margin: 0 0 10px 0; color: #27ae60;"><?php esc_html_e( 'Clic', 'fp-digital-marketing' ); ?></h4>
						<p style="font-size: 24px; font-weight: bold; margin: 0; color: #2c3e50;"><?php echo esc_html( number_format( (int) $metrics['clicks'] ) ); ?></p>
					</div>
					<div style="background: #f8f9fa; padding: 15px; border-radius: 4px; text-align: center;">
						<h4 style="margin: 0 0 10px 0; color: #e67e22;"><?php esc_html_e( 'CTR', 'fp-digital-marketing' ); ?></h4>
						<p style="font-size: 24px; font-weight: bold; margin: 0; color: #2c3e50;"><?php echo esc_html( $metrics['ctr'] ); ?>%</p>
					</div>
					<div style="background: #f8f9fa; padding: 15px; border-radius: 4px; text-align: center;">
						<h4 style="margin: 0 0 10px 0; color: #8e44ad;"><?php esc_html_e( 'Posizione Media', 'fp-digital-marketing' ); ?></h4>
						<p style="font-size: 24px; font-weight: bold; margin: 0; color: #2c3e50;"><?php echo esc_html( $metrics['position'] ); ?></p>
					</div>
				</div>
				<p style="margin: 15px 0 0 0; color: #666; font-style: italic;">
					<?php esc_html_e( '* Dati demo generati automaticamente. Verranno sostituiti con dati reali quando la connessione API sarà attiva.', 'fp-digital-marketing' ); ?>
				</p>
			</div>
			<?php
		} else {
			?>
			<div class="notice notice-error inline">
				<p><?php esc_html_e( 'Errore nel recupero delle metriche Search Console demo.', 'fp-digital-marketing' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Render cached GSC metrics from database
	 *
	 * @return void
	 */
	private function render_cached_gsc_metrics(): void {
		$cached_metrics = MetricsCache::get_metrics([
			'source' => GoogleSearchConsole::SOURCE_ID,
			'limit' => 10,
			'order_by' => 'period_start',
			'order' => 'DESC'
		]);

		?>
		<div class="gsc-cached-metrics" style="margin: 20px 0;">
			<h3><?php esc_html_e( 'Cronologia Metriche Search Console (Cache)', 'fp-digital-marketing' ); ?></h3>
			<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
				<table class="wp-list-table widefat fixed striped" style="margin: 0;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Data', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Metrica', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Valore', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Periodo', 'fp-digital-marketing' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $cached_metrics ) ): ?>
							<?php foreach ( $cached_metrics as $metric ): ?>
								<tr>
									<td><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $metric['created_at'] ) ) ); ?></td>
									<td>
										<strong><?php echo esc_html( ucfirst( $metric['metric_name'] ) ); ?></strong>
										<?php if ( ! empty( $metric['metadata']['site_url'] ) ): ?>
											<br><small style="color: #666;"><?php echo esc_html( $metric['metadata']['site_url'] ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<span style="font-weight: bold; color: #2c3e50;">
											<?php 
											if ( $metric['metric_name'] === 'ctr' ) {
												echo esc_html( $metric['value'] ) . '%';
											} elseif ( $metric['metric_name'] === 'position' ) {
												echo esc_html( $metric['value'] );
											} else {
												echo esc_html( number_format( (int) $metric['value'] ) );
											}
											?>
										</span>
									</td>
									<td>
										<small style="color: #666;">
											<?php echo esc_html( date( 'd/m', strtotime( $metric['period_start'] ) ) ); ?> - 
											<?php echo esc_html( date( 'd/m', strtotime( $metric['period_end'] ) ) ); ?>
										</small>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="4" style="text-align: center; padding: 20px; color: #666;">
									<?php esc_html_e( 'Nessuna metrica Search Console in cache. Le metriche appariranno qui dopo la prima sincronizzazione.', 'fp-digital-marketing' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<p style="margin-top: 15px;">
				<strong><?php esc_html_e( 'Totale record cache:', 'fp-digital-marketing' ); ?></strong>
				<?php 
				$total_cached = MetricsCache::count(['source' => GoogleSearchConsole::SOURCE_ID]);
				echo esc_html( number_format( $total_cached ) );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render metrics aggregator section
	 *
	 * @return void
	 */
	private function render_aggregator_section(): void {
		?>
		<div class="fp-dms-aggregator-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Sistema di Aggregazione Metriche', 'fp-digital-marketing' ); ?></h2>
			<p><?php esc_html_e( 'Dimostrazione del layer di aggregazione e normalizzazione per unificare metriche da diverse sorgenti dati.', 'fp-digital-marketing' ); ?></p>

			<!-- Common Schema Documentation -->
			<div class="schema-documentation" style="margin: 20px 0;">
				<h3><?php esc_html_e( 'Schema Comune delle Metriche', 'fp-digital-marketing' ); ?></h3>
				<p><?php esc_html_e( 'Il sistema normalizza metriche da diverse sorgenti in KPI standardizzati:', 'fp-digital-marketing' ); ?></p>
				
				<?php $categories = MetricsSchema::get_categories(); ?>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 15px 0;">
					<?php foreach ( $categories as $category_id => $category_info ) : ?>
						<?php $category_kpis = MetricsSchema::get_kpis_by_category( $category_id ); ?>
						<div style="border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
							<h4 style="margin-top: 0; color: #0073aa;"><?php echo esc_html( $category_info['name'] ); ?></h4>
							<p style="font-size: 12px; color: #666; margin: 5px 0;"><?php echo esc_html( $category_info['description'] ); ?></p>
							<ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
								<?php foreach ( $category_kpis as $kpi ) : ?>
									<?php $kpi_def = MetricsSchema::get_kpi_definitions()[ $kpi ] ?? []; ?>
									<li style="margin: 3px 0;">
										<strong><?php echo esc_html( $kpi ); ?></strong>
										<?php if ( ! empty( $kpi_def['name'] ) ) : ?>
											<br><span style="color: #666; font-size: 11px;"><?php echo esc_html( $kpi_def['name'] ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Mock Aggregated Data Demo -->
			<div class="aggregated-demo" style="margin: 20px 0;">
				<h3><?php esc_html_e( 'Demo API Interna - Dati Aggregati', 'fp-digital-marketing' ); ?></h3>
				<p><?php esc_html_e( 'Esempio di interrogazione del sistema di aggregazione con dati mock:', 'fp-digital-marketing' ); ?></p>

				<?php
				// Generate mock aggregated data for demo
				$demo_client_id = 999;
				$period_start = '2024-01-01 00:00:00';
				$period_end = '2024-01-31 23:59:59';
				$mock_aggregated = MetricsAggregator::generate_mock_data( $demo_client_id, $period_start, $period_end );
				?>

				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
					<?php foreach ( $mock_aggregated as $kpi => $data ) : ?>
						<?php $kpi_def = MetricsSchema::get_kpi_definitions()[ $kpi ] ?? []; ?>
						<div class="kpi-card" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px; text-align: center;">
							<h4 style="margin-top: 0; color: #0073aa; font-size: 14px;">
								<?php echo esc_html( $kpi_def['name'] ?? $kpi ); ?>
							</h4>
							<div style="font-size: 24px; font-weight: bold; color: #333; margin: 10px 0;">
								<?php
								$format = $kpi_def['format'] ?? 'number';
								$value = $data['total_value'];
								switch ( $format ) {
									case 'percentage':
										echo esc_html( number_format( $value * 100, 2 ) . '%' );
										break;
									case 'currency':
										echo esc_html( '€' . number_format( $value, 2 ) );
										break;
									default:
										echo esc_html( number_format( $value ) );
								}
								?>
							</div>
							<div style="font-size: 11px; color: #666;">
								<?php printf( esc_html__( 'Aggregazione: %s', 'fp-digital-marketing' ), esc_html( $kpi_def['aggregation'] ?? 'sum' ) ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Source Mappings -->
			<div class="source-mappings" style="margin: 20px 0;">
				<h3><?php esc_html_e( 'Mappature Sorgenti Dati', 'fp-digital-marketing' ); ?></h3>
				<p><?php esc_html_e( 'Come le metriche specifiche di ogni sorgente vengono mappate ai KPI standardizzati:', 'fp-digital-marketing' ); ?></p>

				<?php $source_mappings = MetricsSchema::get_source_mappings(); ?>
				<div style="overflow-x: auto;">
					<table class="wp-list-table widefat fixed striped" style="margin: 15px 0;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Sorgente', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Metrica Originale', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'KPI Standardizzato', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Formato', 'fp-digital-marketing' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $source_mappings as $source_id => $mappings ) : ?>
								<?php foreach ( $mappings as $original_metric => $standard_kpi ) : ?>
									<?php $kpi_def = MetricsSchema::get_kpi_definitions()[ $standard_kpi ] ?? []; ?>
									<tr>
										<td><strong><?php echo esc_html( $source_id ); ?></strong></td>
										<td><code><?php echo esc_html( $original_metric ); ?></code></td>
										<td>
											<code><?php echo esc_html( $standard_kpi ); ?></code>
											<?php if ( ! empty( $kpi_def['name'] ) ) : ?>
												<br><small style="color: #666;"><?php echo esc_html( $kpi_def['name'] ); ?></small>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $kpi_def['format'] ?? 'number' ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- API Usage Examples -->
			<div class="api-examples" style="margin: 20px 0;">
				<h3><?php esc_html_e( 'Esempi di Utilizzo API Interna', 'fp-digital-marketing' ); ?></h3>
				<p><?php esc_html_e( 'Come utilizzare il sistema di aggregazione nel codice:', 'fp-digital-marketing' ); ?></p>

				<pre style="background: #f9f9f9; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;"><?php
echo esc_html( '
// Ottenere metriche aggregate per un cliente
$aggregated = MetricsAggregator::get_aggregated_metrics(
    $client_id, 
    \'2024-01-01 00:00:00\', 
    \'2024-01-31 23:59:59\'
);

// Ottenere sommario KPI per categoria
$traffic_summary = MetricsAggregator::get_kpi_summary(
    $client_id, 
    \'2024-01-01 00:00:00\', 
    \'2024-01-31 23:59:59\',
    MetricsSchema::CATEGORY_TRAFFIC
);

// Confronto tra periodi
$comparison = MetricsAggregator::get_period_comparison(
    $client_id,
    \'2024-02-01 00:00:00\', \'2024-02-29 23:59:59\', // Periodo corrente
    \'2024-01-01 00:00:00\', \'2024-01-31 23:59:59\'  // Periodo precedente
);

// Controllo qualità dati
$quality_report = MetricsAggregator::get_data_quality_report(
    $client_id, 
    \'2024-01-01 00:00:00\', 
    \'2024-01-31 23:59:59\'
);
' ); ?></pre>
			</div>

			<!-- Fallback System Demo -->
			<div class="fallback-demo" style="margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Sistema di Fallback', 'fp-digital-marketing' ); ?></h3>
				<p><?php esc_html_e( 'Il sistema gestisce automaticamente i dati mancanti:', 'fp-digital-marketing' ); ?></p>
				<ul style="margin: 10px 0; padding-left: 20px;">
					<li><?php esc_html_e( '🔄 Valori di fallback per metriche non disponibili', 'fp-digital-marketing' ); ?></li>
					<li><?php esc_html_e( '📊 Aggregazione intelligente da sorgenti multiple', 'fp-digital-marketing' ); ?></li>
					<li><?php esc_html_e( '⚠️ Segnalazione lacune nei dati', 'fp-digital-marketing' ); ?></li>
					<li><?php esc_html_e( '🎯 Raccomandazioni per migliorare la copertura', 'fp-digital-marketing' ); ?></li>
				</ul>
			</div>

			<div style="margin-top: 20px; padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px;">
				<strong><?php esc_html_e( 'Stato Implementazione:', 'fp-digital-marketing' ); ?></strong>
				<ul style="margin: 10px 0; padding-left: 20px;">
					<li>✅ <?php esc_html_e( 'Schema comune per normalizzazione metriche', 'fp-digital-marketing' ); ?></li>
					<li>✅ <?php esc_html_e( 'API interna per interrogazioni aggregate', 'fp-digital-marketing' ); ?></li>
					<li>✅ <?php esc_html_e( 'Sistema di fallback per dati incompleti', 'fp-digital-marketing' ); ?></li>
					<li>✅ <?php esc_html_e( 'Test unitari per aggregazione e fallback', 'fp-digital-marketing' ); ?></li>
					<li>🔧 <?php esc_html_e( 'Pronto per estensione con nuove sorgenti dati', 'fp-digital-marketing' ); ?></li>
			</div>
		</div>
		<?php
	}

	/**
	 * Render sync status section
	 *
	 * @return void
	 */
	private function render_sync_status_section(): void {
		$sync_stats = SyncLog::get_sync_stats( 7 );
		$recent_logs = SyncLog::get_all_logs( 10 );
		$error_logs = SyncLog::get_error_logs( 5 );
		?>
		<div class="fp-dms-sync-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Stato Sync Engine', 'fp-digital-marketing' ); ?></h2>
			
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
				<div class="fp-dms-card" style="background: #f8f9fa; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Stato Sync', 'fp-digital-marketing' ); ?></h3>
					<?php if ( SyncEngine::is_scheduled() && SyncEngine::is_sync_enabled() ) : ?>
						<p><span style="color: #00a32a;">●</span> <?php esc_html_e( 'Attivo', 'fp-digital-marketing' ); ?></p>
						<p><strong><?php esc_html_e( 'Prossima esecuzione:', 'fp-digital-marketing' ); ?></strong><br>
						<?php echo esc_html( SyncEngine::get_next_scheduled_time() ); ?></p>
					<?php elseif ( SyncEngine::is_scheduled() ) : ?>
						<p><span style="color: #dba617;">●</span> <?php esc_html_e( 'Programmato ma disabilitato', 'fp-digital-marketing' ); ?></p>
					<?php else : ?>
						<p><span style="color: #d63638;">●</span> <?php esc_html_e( 'Non programmato', 'fp-digital-marketing' ); ?></p>
					<?php endif; ?>
				</div>
				
				<div class="fp-dms-card" style="background: #f8f9fa; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Statistiche (7 giorni)', 'fp-digital-marketing' ); ?></h3>
					<p><strong><?php esc_html_e( 'Sync totali:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $sync_stats['total_syncs'] ); ?></p>
					<p><strong><?php esc_html_e( 'Successi:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $sync_stats['successful_syncs'] ); ?></p>
					<p><strong><?php esc_html_e( 'Errori:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $sync_stats['failed_syncs'] ); ?></p>
					<p><strong><?php esc_html_e( 'Tasso errori:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $sync_stats['error_rate'] ); ?>%</p>
				</div>

				<div class="fp-dms-card" style="background: #f8f9fa; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Ultimo Sync', 'fp-digital-marketing' ); ?></h3>
					<?php if ( $sync_stats['last_sync'] ) : ?>
						<p><strong><?php esc_html_e( 'Ultimo sync:', 'fp-digital-marketing' ); ?></strong><br>
						<?php echo esc_html( date( 'd/m/Y H:i', strtotime( $sync_stats['last_sync'] ) ) ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Nessun sync eseguito', 'fp-digital-marketing' ); ?></p>
					<?php endif; ?>
					
					<?php if ( $sync_stats['last_successful_sync'] ) : ?>
						<p><strong><?php esc_html_e( 'Ultimo successo:', 'fp-digital-marketing' ); ?></strong><br>
						<?php echo esc_html( date( 'd/m/Y H:i', strtotime( $sync_stats['last_successful_sync'] ) ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Recent Sync Logs -->
			<div style="margin-top: 30px;">
				<h3><?php esc_html_e( 'Log Sync Recenti', 'fp-digital-marketing' ); ?></h3>
				<?php if ( ! empty( $recent_logs ) ) : ?>
					<div style="overflow-x: auto;">
						<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
							<thead>
								<tr>
									<th style="width: 150px;"><?php esc_html_e( 'Data/Ora', 'fp-digital-marketing' ); ?></th>
									<th style="width: 80px;"><?php esc_html_e( 'Tipo', 'fp-digital-marketing' ); ?></th>
									<th style="width: 100px;"><?php esc_html_e( 'Stato', 'fp-digital-marketing' ); ?></th>
									<th><?php esc_html_e( 'Messaggio', 'fp-digital-marketing' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_logs as $log ) : ?>
									<tr>
										<td><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $log['started_at'] ) ) ); ?></td>
										<td><?php echo esc_html( ucfirst( $log['sync_type'] ) ); ?></td>
										<td>
											<?php
											$status_colors = [
												'success' => '#00a32a',
												'error' => '#d63638',
												'warning' => '#dba617',
												'running' => '#0073aa',
											];
											$color = $status_colors[ $log['status'] ] ?? '#666';
											?>
											<span style="color: <?php echo esc_attr( $color ); ?>;">
												<?php echo esc_html( ucfirst( $log['status'] ) ); ?>
											</span>
										</td>
										<td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
											<?php echo esc_html( $log['message'] ?? '-' ); ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'Nessun log di sincronizzazione disponibile.', 'fp-digital-marketing' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Error Logs -->
			<?php if ( ! empty( $error_logs ) ) : ?>
				<div style="margin-top: 30px;">
					<h3 style="color: #d63638;"><?php esc_html_e( 'Log Errori Recenti', 'fp-digital-marketing' ); ?></h3>
					<div style="background: #fef7f7; border: 1px solid #e5c1c1; border-radius: 4px; padding: 15px;">
						<?php foreach ( $error_logs as $error_log ) : ?>
							<div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e5c1c1;">
								<strong><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $error_log['started_at'] ) ) ); ?></strong><br>
								<span style="color: #d63638;"><?php echo esc_html( $error_log['message'] ?? 'Errore non specificato' ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Implementation Status -->
			<div style="margin-top: 20px; padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px;">
				<strong><?php esc_html_e( 'Stato Implementazione Sync Engine:', 'fp-digital-marketing' ); ?></strong>
				<ul style="margin: 10px 0; padding-left: 20px;">
					<li>✅ <?php esc_html_e( 'Scheduler con frequenza configurabile', 'fp-digital-marketing' ); ?></li>
					<li>✅ <?php esc_html_e( 'Sincronizzazione incrementale cache metriche', 'fp-digital-marketing' ); ?></li>
					<li>✅ <?php esc_html_e( 'Log errori e report sincronizzazioni', 'fp-digital-marketing' ); ?></li>
					<li>✅ <?php esc_html_e( 'Opzioni configurabili in admin (Settings)', 'fp-digital-marketing' ); ?></li>
					<li>✅ <?php esc_html_e( 'Demo funzionante ogni ora', 'fp-digital-marketing' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}
}