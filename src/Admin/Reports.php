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
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Models\CustomReport;
use FP\DigitalMarketing\Models\SocialSentiment;
use FP\DigitalMarketing\Database\CustomReportsTable;
use FP\DigitalMarketing\Database\SocialSentimentTable;
use FP\DigitalMarketing\DataSources\GoogleAnalytics4;
use FP\DigitalMarketing\DataSources\GoogleOAuth;
use FP\DigitalMarketing\DataSources\GoogleSearchConsole;
use FP\DigitalMarketing\DataSources\MicrosoftClarity;
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
			'fp-digital-marketing-dashboard',
			__( 'Reports & Analytics', 'fp-digital-marketing' ),
			__( '📊 Reports', 'fp-digital-marketing' ),
			Capabilities::EXPORT_REPORTS,
			self::PAGE_SLUG,
			[ $this, 'render_reports_page' ]
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

		if ( ! Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) ) {
			return;
		}

                // Handle PDF download with nonce verification
                if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_pdf' && isset( $_GET['client_id'] ) ) {
                        $client_id = intval( $_GET['client_id'] );
                        $nonce     = sanitize_text_field( $_GET['_wpnonce'] ?? '' );

                        if ( ! wp_verify_nonce( $nonce, 'download_pdf_' . $client_id ) ) {
                                wp_die( esc_html__( 'Nonce non valido', 'fp-digital-marketing' ) );
                        }

                        if ( Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) ) {
                                $this->download_pdf_report( $client_id );
                        } else {
                                wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
                        }
                }

                // Handle CSV download with nonce verification
                if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_csv' && isset( $_GET['client_id'] ) ) {
                        $client_id = intval( $_GET['client_id'] );
                        $nonce     = sanitize_text_field( $_GET['_wpnonce'] ?? '' );

                        if ( ! wp_verify_nonce( $nonce, 'download_csv_' . $client_id ) ) {
                                wp_die( esc_html__( 'Nonce non valido', 'fp-digital-marketing' ) );
                        }

                        if ( Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) ) {
                                $separator = sanitize_text_field( $_GET['separator'] ?? ',' );
                                $this->download_csv_report( $client_id, $separator );
                        } else {
                                wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
                        }
                }

                // Handle custom report download with nonce verification
                if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_custom_report' && isset( $_GET['report_id'] ) ) {
                        $report_id = intval( $_GET['report_id'] );
                        $nonce     = sanitize_text_field( $_GET['_wpnonce'] ?? '' );

                        if ( ! wp_verify_nonce( $nonce, 'download_custom_report_' . $report_id ) ) {
                                wp_die( esc_html__( 'Nonce non valido', 'fp-digital-marketing' ) );
                        }

                        if ( Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) ) {
                                $this->download_custom_report( $report_id );
                        } else {
                                wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
                        }
                }

		// Handle custom report generation
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'generate_custom_report' && 
			 Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) &&
			 Security::verify_nonce_with_logging( 'generate_custom_report' ) ) {
			$this->handle_custom_report_generation();
		}

		// Handle new custom report creation
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'create_custom_report' && 
			 Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) &&
			 Security::verify_nonce_with_logging( 'create_custom_report' ) ) {
			$this->handle_custom_report_creation();
		}

		// Handle sentiment response
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'respond_to_sentiment' && 
			 Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) &&
			 Security::verify_nonce_with_logging( 'respond_to_sentiment' ) ) {
			$this->handle_sentiment_response();
		}

		// Handle generate sample sentiment data
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'generate_sample_sentiment' && 
			 Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) &&
			 Security::verify_nonce_with_logging( 'generate_sample_sentiment' ) ) {
			$this->handle_generate_sample_sentiment();
		}

		// Handle manual report generation
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'generate_reports' && 
			 Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) &&
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
	 * Handle custom report creation form submission
	 *
	 * @return void
	 */
	private function handle_custom_report_creation(): void {
		$client_id = intval( $_POST['client_id'] ?? 0 );
		$report_name = sanitize_text_field( $_POST['report_name'] ?? '' );
		$report_description = sanitize_textarea_field( $_POST['report_description'] ?? '' );
		$time_period = sanitize_text_field( $_POST['time_period'] ?? '30_days' );
		$selected_kpis = isset( $_POST['selected_kpis'] ) ? array_map( 'sanitize_text_field', $_POST['selected_kpis'] ) : [];
		$report_frequency = sanitize_text_field( $_POST['report_frequency'] ?? 'manual' );
		$email_recipients = isset( $_POST['email_recipients'] ) ? array_map( 'sanitize_email', explode( ',', $_POST['email_recipients'] ) ) : [];
		$auto_send = isset( $_POST['auto_send'] ) ? 1 : 0;

		if ( ! $client_id || ! $report_name ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Cliente e nome report sono obbligatori', 'fp-digital-marketing' );
				echo '</p></div>';
			} );
			return;
		}

		$custom_report = new CustomReport([
			'client_id' => $client_id,
			'report_name' => $report_name,
			'report_description' => $report_description,
			'time_period' => $time_period,
			'selected_kpis' => $selected_kpis,
			'report_frequency' => $report_frequency,
			'email_recipients' => array_filter( $email_recipients ),
			'auto_send' => $auto_send,
			'status' => 'active',
		]);

		if ( $custom_report->save() ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html__( 'Report personalizzato creato con successo!', 'fp-digital-marketing' );
				echo '</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Errore nella creazione del report personalizzato', 'fp-digital-marketing' );
				echo '</p></div>';
			} );
		}
	}

	/**
	 * Download custom report
	 *
	 * @param int $report_id Report ID
	 * @return void
	 */
	private function download_custom_report( int $report_id ): void {
		$report_data = CustomReportsTable::get_report( $report_id );
		if ( ! $report_data ) {
			wp_die( esc_html__( 'Report non trovato', 'fp-digital-marketing' ) );
		}

		$custom_report = new CustomReport( $report_data );
		$format = sanitize_text_field( $_GET['format'] ?? 'pdf' );
		$result = $custom_report->generate( $format );

		if ( ! $result['success'] ) {
			wp_die( esc_html( implode( ', ', $result['errors'] ) ) );
		}

		// Set headers and output
		switch ( $format ) {
			case 'csv':
				header( 'Content-Type: text/csv; charset=utf-8' );
				break;
			case 'html':
				header( 'Content-Type: text/html; charset=utf-8' );
				break;
			default:
				header( 'Content-Type: application/pdf' );
		}

		header( 'Content-Disposition: attachment; filename="' . $result['filename'] . '"' );
		header( 'Content-Length: ' . strlen( $result['content'] ) );

		echo $result['content'];
		exit;
	}

	/**
	 * Handle sentiment response submission
	 *
	 * @return void
	 */
	private function handle_sentiment_response(): void {
		$sentiment_id = intval( $_POST['sentiment_id'] ?? 0 );
		$response_text = sanitize_textarea_field( $_POST['response_text'] ?? '' );

		if ( ! $sentiment_id || ! $response_text ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'ID recensione e testo risposta sono obbligatori', 'fp-digital-marketing' );
				echo '</p></div>';
			} );
			return;
		}

		if ( SocialSentimentTable::mark_as_responded( $sentiment_id, $response_text ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html__( 'Risposta salvata con successo!', 'fp-digital-marketing' );
				echo '</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Errore nel salvare la risposta', 'fp-digital-marketing' );
				echo '</p></div>';
			} );
		}
	}

	/**
	 * Handle generate sample sentiment data
	 *
	 * @return void
	 */
	private function handle_generate_sample_sentiment(): void {
		$client_id = intval( $_POST['client_id'] ?? 0 );
		$count = intval( $_POST['sample_count'] ?? 20 );

		if ( ! $client_id ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Seleziona un cliente', 'fp-digital-marketing' );
				echo '</p></div>';
			} );
			return;
		}

		if ( SocialSentimentTable::generate_sample_data( $client_id, $count ) ) {
			add_action( 'admin_notices', function() use ( $count ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				printf( __( 'Generati %d dati demo per l\'analisi del sentiment!', 'fp-digital-marketing' ), $count );
				echo '</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Errore nella generazione dei dati demo', 'fp-digital-marketing' );
				echo '</p></div>';
			} );
		}
	}
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
		if ( ! Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}

		// Get clients for report generation
		$clientes = get_posts( [
			'post_type'      => 'cliente',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
		] );

		// Get current tab
		$current_tab = sanitize_text_field( $_GET['tab'] ?? 'standard_reports' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper" style="margin: 20px 0;">
				<a href="<?php echo esc_url( add_query_arg( ['tab' => 'standard_reports'], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'standard_reports' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Report Standard', 'fp-digital-marketing' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( ['tab' => 'custom_reports'], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'custom_reports' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Report Personalizzati', 'fp-digital-marketing' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( ['tab' => 'sentiment_analysis'], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'sentiment_analysis' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Analisi Sentiment', 'fp-digital-marketing' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( ['tab' => 'debug'], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'debug' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Debug & Analytics', 'fp-digital-marketing' ); ?>
				</a>
			</nav>

			<?php
			// Render the appropriate tab content
			switch ( $current_tab ) {
				case 'custom_reports':
					$this->render_custom_reports_tab( $clientes );
					break;
				case 'sentiment_analysis':
					$this->render_sentiment_analysis_tab( $clientes );
					break;
				case 'debug':
					$this->render_debug_tab( $clientes );
					break;
				default:
					$this->render_standard_reports_tab( $clientes );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render standard reports tab
	 *
	 * @param array $clientes Array of client posts
	 * @return void
	 */
	private function render_standard_reports_tab( array $clientes ): void {
		?>
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
                                                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'download_pdf', 'client_id' => $cliente->ID ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ), 'download_pdf_' . $cliente->ID ) ); ?>"
                                                                   class="button button-secondary">
                                                                        <?php esc_html_e( 'PDF', 'fp-digital-marketing' ); ?>
                                                                </a>
                                                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'download_csv', 'client_id' => $cliente->ID ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ), 'download_csv_' . $cliente->ID ) ); ?>"
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
		<?php
	}

	/**
	 * Render custom reports tab
	 *
	 * @param array $clientes Array of client posts
	 * @return void
	 */
	private function render_custom_reports_tab( array $clientes ): void {
		// Get existing custom reports
		$custom_reports = CustomReportsTable::get_all_reports( ['limit' => 100] );
		$available_kpis = CustomReport::get_available_kpis();
		$time_periods = CustomReportsTable::get_available_time_periods();
		$frequencies = CustomReportsTable::get_available_frequencies();

		?>
		<!-- Create New Custom Report Section -->
		<div class="fp-dms-reports-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Crea Nuovo Report Personalizzato', 'fp-digital-marketing' ); ?></h2>
			<p><?php esc_html_e( 'Configura report personalizzati con periodi specifici, KPI selezionati e invio automatico via email.', 'fp-digital-marketing' ); ?></p>
			
			<form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
				<?php wp_nonce_field( 'create_custom_report' ); ?>
				<input type="hidden" name="action" value="create_custom_report">
				
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
					<label for="report_name"><strong><?php esc_html_e( 'Nome Report', 'fp-digital-marketing' ); ?></strong></label>
					<input type="text" name="report_name" id="report_name" required style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="<?php esc_attr_e( 'es. Report Mensile Performance', 'fp-digital-marketing' ); ?>">
				</div>

				<div class="form-group">
					<label for="time_period"><strong><?php esc_html_e( 'Periodo Temporale', 'fp-digital-marketing' ); ?></strong></label>
					<select name="time_period" id="time_period" style="width: 100%; padding: 8px; margin-top: 5px;">
						<?php foreach ( $time_periods as $period_key => $period_label ) : ?>
							<option value="<?php echo esc_attr( $period_key ); ?>" <?php selected( $period_key, '30_days' ); ?>>
								<?php echo esc_html( $period_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="form-group">
					<label for="report_frequency"><strong><?php esc_html_e( 'Frequenza Generazione', 'fp-digital-marketing' ); ?></strong></label>
					<select name="report_frequency" id="report_frequency" style="width: 100%; padding: 8px; margin-top: 5px;">
						<?php foreach ( $frequencies as $freq_key => $freq_label ) : ?>
							<option value="<?php echo esc_attr( $freq_key ); ?>">
								<?php echo esc_html( $freq_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="form-group" style="grid-column: 1 / -1;">
					<label for="report_description"><strong><?php esc_html_e( 'Descrizione (opzionale)', 'fp-digital-marketing' ); ?></strong></label>
					<textarea name="report_description" id="report_description" rows="3" style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="<?php esc_attr_e( 'Descrizione del report e obiettivi...', 'fp-digital-marketing' ); ?>"></textarea>
				</div>

				<!-- KPI Selection -->
				<div class="form-group" style="grid-column: 1 / -1;">
					<label><strong><?php esc_html_e( 'KPI da Includere', 'fp-digital-marketing' ); ?></strong></label>
					<?php foreach ( $available_kpis as $category_key => $category ) : ?>
						<div style="margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
							<h4 style="margin-top: 0;"><?php echo esc_html( $category['label'] ); ?></h4>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
								<?php foreach ( $category['kpis'] as $kpi_key => $kpi_label ) : ?>
									<label style="display: flex; align-items: center; padding: 8px; background: #f8f9fa; border-radius: 4px;">
										<input type="checkbox" name="selected_kpis[]" value="<?php echo esc_attr( $kpi_key ); ?>" style="margin-right: 8px;">
										<?php echo esc_html( $kpi_label ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<!-- Email Settings -->
				<div class="form-group" style="grid-column: 1 / -1;">
					<label for="email_recipients"><strong><?php esc_html_e( 'Email Recipients (opzionale)', 'fp-digital-marketing' ); ?></strong></label>
					<input type="text" name="email_recipients" id="email_recipients" style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="<?php esc_attr_e( 'email1@example.com, email2@example.com', 'fp-digital-marketing' ); ?>">
					<small style="color: #666; display: block; margin-top: 5px;">
						<?php esc_html_e( 'Separa gli indirizzi email con virgole. Lascia vuoto per non inviare automaticamente.', 'fp-digital-marketing' ); ?>
					</small>
				</div>

				<div class="form-group" style="grid-column: 1 / -1;">
					<label style="display: flex; align-items: center;">
						<input type="checkbox" name="auto_send" value="1" style="margin-right: 8px;">
						<?php esc_html_e( 'Invia automaticamente secondo la frequenza selezionata', 'fp-digital-marketing' ); ?>
					</label>
				</div>

				<div class="form-group" style="grid-column: 1 / -1;">
					<button type="submit" class="button button-primary" style="padding: 10px 20px;">
						<?php esc_html_e( 'Crea Report Personalizzato', 'fp-digital-marketing' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Existing Custom Reports -->
		<div class="fp-dms-reports-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Report Personalizzati Esistenti', 'fp-digital-marketing' ); ?></h2>
			
			<?php if ( ! empty( $custom_reports ) ) : ?>
				<div style="overflow-x: auto;">
					<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nome Report', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Periodo', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Frequenza', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'KPI', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Auto-Send', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Ultimo Generato', 'fp-digital-marketing' ); ?></th>
								<th><?php esc_html_e( 'Azioni', 'fp-digital-marketing' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $custom_reports as $report ) : ?>
								<?php $client_post = get_post( $report['client_id'] ); ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $report['report_name'] ); ?></strong>
										<?php if ( ! empty( $report['report_description'] ) ) : ?>
											<br><small style="color: #666;"><?php echo esc_html( wp_trim_words( $report['report_description'], 10 ) ); ?></small>
										<?php endif; ?>
									</td>
									<td><?php echo $client_post ? esc_html( $client_post->post_title ) : 'Cliente #' . $report['client_id']; ?></td>
									<td><?php echo esc_html( $time_periods[ $report['time_period'] ] ?? $report['time_period'] ); ?></td>
									<td><?php echo esc_html( $frequencies[ $report['report_frequency'] ] ?? $report['report_frequency'] ); ?></td>
									<td>
										<small><?php echo esc_html( count( $report['selected_kpis'] ) ); ?> KPI</small>
										<details style="margin-top: 5px;">
											<summary style="cursor: pointer; font-size: 11px;"><?php esc_html_e( 'Dettagli', 'fp-digital-marketing' ); ?></summary>
											<div style="font-size: 11px; margin-top: 5px;">
												<?php foreach ( $report['selected_kpis'] as $kpi ) : ?>
													<span style="background: #f0f0f0; padding: 2px 5px; margin: 2px; border-radius: 3px; display: inline-block;"><?php echo esc_html( $kpi ); ?></span>
												<?php endforeach; ?>
											</div>
										</details>
									</td>
									<td>
										<?php if ( $report['auto_send'] ) : ?>
											<span style="color: #00a32a;">✓ Sì</span>
											<?php if ( ! empty( $report['email_recipients'] ) ) : ?>
												<br><small style="color: #666;"><?php echo esc_html( count( $report['email_recipients'] ) ); ?> destinatari</small>
											<?php endif; ?>
										<?php else : ?>
											<span style="color: #666;">No</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $report['last_generated'] ) : ?>
											<?php echo esc_html( date( 'd/m/Y H:i', strtotime( $report['last_generated'] ) ) ); ?>
										<?php else : ?>
											<span style="color: #666;"><?php esc_html_e( 'Mai generato', 'fp-digital-marketing' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                                                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'download_custom_report', 'report_id' => $report['id'], 'format' => 'pdf' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ), 'download_custom_report_' . $report['id'] ) ); ?>"
                                                                                           class="button button-small">PDF</a>
                                                                                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'download_custom_report', 'report_id' => $report['id'], 'format' => 'csv' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ), 'download_custom_report_' . $report['id'] ) ); ?>"
                                                                                           class="button button-small">CSV</a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Nessun report personalizzato creato ancora. Usa il modulo sopra per crearne uno.', 'fp-digital-marketing' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render sentiment analysis tab
	 *
	 * @param array $clientes Array of client posts
	 * @return void
	 */
	private function render_sentiment_analysis_tab( array $clientes ): void {
		// Get current filter parameters
		$selected_client = intval( $_GET['filter_client'] ?? 0 );
		$sentiment_filter = sanitize_text_field( $_GET['sentiment_filter'] ?? '' );
		$action_required_filter = isset( $_GET['action_required'] ) ? intval( $_GET['action_required'] ) : null;

		// Get sentiment data
		$sentiment_data = [];
		$sentiment_summary = [];
		$top_issues = [];

		if ( $selected_client ) {
			$sentiment_data = SocialSentimentTable::get_client_sentiment( $selected_client, [
				'limit' => 50,
				'sentiment_label' => $sentiment_filter ?: null,
				'action_required' => $action_required_filter,
			] );
			$sentiment_summary = SocialSentimentTable::get_sentiment_summary( $selected_client );
			$top_issues = SocialSentimentTable::get_top_issues( $selected_client );
		}

		$platforms = SocialSentimentTable::get_available_platforms();

		?>
		<!-- Generate Sample Data Section -->
		<div class="fp-dms-reports-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Genera Dati Demo per l\'Analisi Sentiment', 'fp-digital-marketing' ); ?></h2>
			<p><?php esc_html_e( 'Genera dati di esempio per testare il sistema di analisi del sentiment sociale.', 'fp-digital-marketing' ); ?></p>
			
			<form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
				<?php wp_nonce_field( 'generate_sample_sentiment' ); ?>
				<input type="hidden" name="action" value="generate_sample_sentiment">
				
				<div class="form-group">
					<label for="client_id_sample"><strong><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?></strong></label>
					<select name="client_id" id="client_id_sample" required style="width: 100%; padding: 8px; margin-top: 5px;">
						<option value=""><?php esc_html_e( 'Seleziona cliente...', 'fp-digital-marketing' ); ?></option>
						<?php foreach ( $clientes as $cliente ) : ?>
							<option value="<?php echo esc_attr( $cliente->ID ); ?>">
								<?php echo esc_html( $cliente->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="form-group">
					<label for="sample_count"><strong><?php esc_html_e( 'Numero di Recensioni Demo', 'fp-digital-marketing' ); ?></strong></label>
					<select name="sample_count" id="sample_count" style="width: 100%; padding: 8px; margin-top: 5px;">
						<option value="10">10 recensioni</option>
						<option value="20" selected>20 recensioni</option>
						<option value="50">50 recensioni</option>
						<option value="100">100 recensioni</option>
					</select>
				</div>

				<div class="form-group">
					<button type="submit" class="button button-primary" style="padding: 10px 20px;">
						<?php esc_html_e( 'Genera Dati Demo', 'fp-digital-marketing' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Sentiment Analysis Filters -->
		<div class="fp-dms-reports-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Analisi Sentiment Sociale', 'fp-digital-marketing' ); ?></h2>
			<p><?php esc_html_e( 'Monitora e analizza il sentiment delle recensioni online dei tuoi clienti con intelligenza artificiale.', 'fp-digital-marketing' ); ?></p>
			
			<!-- Filters -->
			<form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<input type="hidden" name="tab" value="sentiment_analysis">
				
				<div>
					<label for="filter_client"><strong><?php esc_html_e( 'Cliente', 'fp-digital-marketing' ); ?></strong></label>
					<select name="filter_client" id="filter_client" style="width: 100%; padding: 8px; margin-top: 5px;">
						<option value=""><?php esc_html_e( 'Tutti i clienti', 'fp-digital-marketing' ); ?></option>
						<?php foreach ( $clientes as $cliente ) : ?>
							<option value="<?php echo esc_attr( $cliente->ID ); ?>" <?php selected( $selected_client, $cliente->ID ); ?>>
								<?php echo esc_html( $cliente->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label for="sentiment_filter"><strong><?php esc_html_e( 'Sentiment', 'fp-digital-marketing' ); ?></strong></label>
					<select name="sentiment_filter" id="sentiment_filter" style="width: 100%; padding: 8px; margin-top: 5px;">
						<option value=""><?php esc_html_e( 'Tutti', 'fp-digital-marketing' ); ?></option>
						<option value="positive" <?php selected( $sentiment_filter, 'positive' ); ?>><?php esc_html_e( 'Positivo', 'fp-digital-marketing' ); ?></option>
						<option value="negative" <?php selected( $sentiment_filter, 'negative' ); ?>><?php esc_html_e( 'Negativo', 'fp-digital-marketing' ); ?></option>
						<option value="neutral" <?php selected( $sentiment_filter, 'neutral' ); ?>><?php esc_html_e( 'Neutro', 'fp-digital-marketing' ); ?></option>
					</select>
				</div>

				<div>
					<label for="action_required"><strong><?php esc_html_e( 'Azione Richiesta', 'fp-digital-marketing' ); ?></strong></label>
					<select name="action_required" id="action_required" style="width: 100%; padding: 8px; margin-top: 5px;">
						<option value=""><?php esc_html_e( 'Tutti', 'fp-digital-marketing' ); ?></option>
						<option value="1" <?php selected( $action_required_filter, 1 ); ?>><?php esc_html_e( 'Richiede attenzione', 'fp-digital-marketing' ); ?></option>
						<option value="0" <?php selected( $action_required_filter, 0 ); ?>><?php esc_html_e( 'Nessuna azione', 'fp-digital-marketing' ); ?></option>
					</select>
				</div>

				<div style="display: flex; align-items: end;">
					<button type="submit" class="button button-secondary" style="padding: 8px 15px;">
						<?php esc_html_e( 'Filtra', 'fp-digital-marketing' ); ?>
					</button>
				</div>
			</form>

			<?php if ( $selected_client && ! empty( $sentiment_summary ) ) : ?>
				<!-- Sentiment Summary -->
				<div style="margin: 20px 0;">
					<h3><?php esc_html_e( 'Riepilogo Sentiment', 'fp-digital-marketing' ); ?></h3>
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
						<div style="background: #f0fff0; border: 1px solid #00a32a; padding: 15px; border-radius: 4px; text-align: center;">
							<div style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo esc_html( $sentiment_summary['positive_reviews'] ); ?></div>
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Positive', 'fp-digital-marketing' ); ?> (<?php echo esc_html( $sentiment_summary['positive_percentage'] ); ?>%)</div>
						</div>
						<div style="background: #fff0f0; border: 1px solid #d63638; padding: 15px; border-radius: 4px; text-align: center;">
							<div style="font-size: 24px; font-weight: bold; color: #d63638;"><?php echo esc_html( $sentiment_summary['negative_reviews'] ); ?></div>
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Negative', 'fp-digital-marketing' ); ?> (<?php echo esc_html( $sentiment_summary['negative_percentage'] ); ?>%)</div>
						</div>
						<div style="background: #fff8e1; border: 1px solid #dba617; padding: 15px; border-radius: 4px; text-align: center;">
							<div style="font-size: 24px; font-weight: bold; color: #dba617;"><?php echo esc_html( $sentiment_summary['neutral_reviews'] ); ?></div>
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Neutral', 'fp-digital-marketing' ); ?> (<?php echo esc_html( $sentiment_summary['neutral_percentage'] ); ?>%)</div>
						</div>
						<div style="background: #f0f0f0; border: 1px solid #666; padding: 15px; border-radius: 4px; text-align: center;">
							<div style="font-size: 24px; font-weight: bold; color: #333;"><?php echo esc_html( number_format( $sentiment_summary['avg_sentiment_score'], 2 ) ); ?></div>
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Score Medio', 'fp-digital-marketing' ); ?></div>
						</div>
					</div>

					<?php if ( ! empty( $top_issues ) ) : ?>
						<div style="margin: 20px 0;">
							<h4><?php esc_html_e( 'Problematiche Principali', 'fp-digital-marketing' ); ?></h4>
							<div style="display: flex; flex-wrap: wrap; gap: 10px;">
								<?php foreach ( $top_issues as $issue => $count ) : ?>
									<span style="background: #ffe6e6; color: #d63638; padding: 5px 10px; border-radius: 15px; font-size: 12px;">
										<?php echo esc_html( $issue ); ?> (<?php echo esc_html( $count ); ?>)
									</span>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Sentiment Data Table -->
			<?php if ( $selected_client ) : ?>
				<?php if ( ! empty( $sentiment_data ) ) : ?>
					<div style="overflow-x: auto; margin: 20px 0;">
						<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
							<thead>
								<tr>
									<th style="width: 100px;"><?php esc_html_e( 'Data', 'fp-digital-marketing' ); ?></th>
									<th style="width: 100px;"><?php esc_html_e( 'Piattaforma', 'fp-digital-marketing' ); ?></th>
									<th style="width: 80px;"><?php esc_html_e( 'Rating', 'fp-digital-marketing' ); ?></th>
									<th style="width: 100px;"><?php esc_html_e( 'Sentiment', 'fp-digital-marketing' ); ?></th>
									<th><?php esc_html_e( 'Recensione', 'fp-digital-marketing' ); ?></th>
									<th style="width: 150px;"><?php esc_html_e( 'AI Summary', 'fp-digital-marketing' ); ?></th>
									<th style="width: 100px;"><?php esc_html_e( 'Azioni', 'fp-digital-marketing' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $sentiment_data as $item ) : ?>
									<tr>
										<td><?php echo esc_html( date( 'd/m/Y', strtotime( $item['review_date'] ) ) ); ?></td>
										<td>
											<span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
												<?php echo esc_html( $platforms[ $item['review_platform'] ] ?? $item['review_platform'] ); ?>
											</span>
										</td>
										<td>
											<?php if ( $item['review_rating'] ) : ?>
												<div style="color: #f4b942;">
													<?php echo str_repeat( '★', intval( $item['review_rating'] ) ); ?>
													<?php echo str_repeat( '☆', 5 - intval( $item['review_rating'] ) ); ?>
												</div>
											<?php else : ?>
												<span style="color: #666;">-</span>
											<?php endif; ?>
										</td>
										<td>
											<?php
											$sentiment_colors = [
												'positive' => '#00a32a',
												'negative' => '#d63638',
												'neutral' => '#dba617',
											];
											$color = $sentiment_colors[ $item['sentiment_label'] ] ?? '#666';
											?>
											<div style="color: <?php echo esc_attr( $color ); ?>; font-weight: bold;">
												<?php echo esc_html( ucfirst( $item['sentiment_label'] ) ); ?>
											</div>
											<small style="color: #666;">
												<?php echo esc_html( number_format( $item['sentiment_score'], 2 ) ); ?>
												(<?php echo esc_html( number_format( $item['sentiment_confidence'] * 100, 0 ) ); ?>%)
											</small>
										</td>
										<td>
											<div style="max-width: 300px; overflow: hidden;">
												<div style="font-size: 13px; line-height: 1.4;">
													<?php echo esc_html( wp_trim_words( $item['review_text'], 20 ) ); ?>
												</div>
												<?php if ( ! empty( $item['key_issues'] ) ) : ?>
													<div style="margin-top: 5px;">
														<?php foreach ( $item['key_issues'] as $issue ) : ?>
															<span style="background: #ffe6e6; color: #d63638; padding: 2px 5px; border-radius: 3px; font-size: 10px; margin-right: 3px;">
																<?php echo esc_html( $issue ); ?>
															</span>
														<?php endforeach; ?>
													</div>
												<?php endif; ?>
											</div>
										</td>
										<td>
											<div style="font-size: 12px; line-height: 1.3;">
												<?php echo esc_html( wp_trim_words( $item['ai_summary'], 15 ) ); ?>
											</div>
											<?php if ( $item['action_required'] ) : ?>
												<div style="margin-top: 5px;">
													<span style="background: #d63638; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px;">
														<?php esc_html_e( 'Attenzione!', 'fp-digital-marketing' ); ?>
													</span>
												</div>
											<?php endif; ?>
										</td>
										<td>
											<div style="display: flex; flex-direction: column; gap: 5px;">
												<?php if ( ! $item['responded'] ) : ?>
													<button class="button button-small respond-btn" 
															data-sentiment-id="<?php echo esc_attr( $item['id'] ); ?>"
															data-suggested-response="<?php echo esc_attr( ( new SocialSentiment( $item ) )->suggest_response() ); ?>">
														<?php esc_html_e( 'Rispondi', 'fp-digital-marketing' ); ?>
													</button>
												<?php else : ?>
													<span style="color: #00a32a; font-size: 11px;">✓ Risposto</span>
												<?php endif; ?>
												
												<?php if ( ! empty( $item['review_url'] ) ) : ?>
													<a href="<?php echo esc_url( $item['review_url'] ); ?>" target="_blank" class="button button-small">
														<?php esc_html_e( 'Vedi', 'fp-digital-marketing' ); ?>
													</a>
												<?php endif; ?>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="notice notice-info inline">
						<p><?php esc_html_e( 'Nessun dato sentiment trovato per i filtri selezionati. Prova a generare dati demo per testare il sistema.', 'fp-digital-marketing' ); ?></p>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Seleziona un cliente per visualizzare l\'analisi sentiment.', 'fp-digital-marketing' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<!-- Response Modal -->
		<div id="response-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
			<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 600px;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Rispondi alla Recensione', 'fp-digital-marketing' ); ?></h3>
				<form method="post">
					<?php wp_nonce_field( 'respond_to_sentiment' ); ?>
					<input type="hidden" name="action" value="respond_to_sentiment">
					<input type="hidden" name="sentiment_id" id="modal-sentiment-id">
					
					<div style="margin: 15px 0;">
						<label for="response_text"><strong><?php esc_html_e( 'Testo Risposta', 'fp-digital-marketing' ); ?></strong></label>
						<textarea name="response_text" id="response_text" rows="5" style="width: 100%; padding: 8px; margin-top: 5px;" required></textarea>
						<small style="color: #666; display: block; margin-top: 5px;">
							<?php esc_html_e( 'La risposta suggerita dall\'AI è già stata inserita. Puoi modificarla prima di inviarla.', 'fp-digital-marketing' ); ?>
						</small>
					</div>
					
					<div style="text-align: right; margin-top: 20px;">
						<button type="button" class="button" onclick="closeResponseModal()">
							<?php esc_html_e( 'Annulla', 'fp-digital-marketing' ); ?>
						</button>
						<button type="submit" class="button button-primary" style="margin-left: 10px;">
							<?php esc_html_e( 'Invia Risposta', 'fp-digital-marketing' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>

		<script>
		function openResponseModal(sentimentId, suggestedResponse) {
			document.getElementById('modal-sentiment-id').value = sentimentId;
			document.getElementById('response_text').value = suggestedResponse;
			document.getElementById('response-modal').style.display = 'block';
		}

		function closeResponseModal() {
			document.getElementById('response-modal').style.display = 'none';
		}

		document.addEventListener('DOMContentLoaded', function() {
			// Attach click handlers to respond buttons
			document.querySelectorAll('.respond-btn').forEach(function(button) {
				button.addEventListener('click', function() {
					const sentimentId = this.dataset.sentimentId;
					const suggestedResponse = this.dataset.suggestedResponse;
					openResponseModal(sentimentId, suggestedResponse);
				});
			});

			// Close modal when clicking outside
			document.getElementById('response-modal').addEventListener('click', function(e) {
				if (e.target === this) {
					closeResponseModal();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render debug tab
	 *
	 * @param array $clientes Array of client posts
	 * @return void
	 */
	private function render_debug_tab( array $clientes ): void {
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
		<!-- Sync Engine Status Section -->
		<?php $this->render_sync_status_section(); ?>

		<!-- GA4 Metrics Section -->
		<?php $this->render_ga4_metrics_section(); ?>

		<!-- GSC Metrics Section -->
		<?php $this->render_gsc_metrics_section(); ?>

		<!-- Microsoft Clarity Metrics Section -->
		<?php $this->render_clarity_metrics_section(); ?>

		<!-- Metrics Aggregator Section -->
		<?php $this->render_aggregator_section(); ?>

		<!-- Report Preview Section -->
		<div class="fp-dms-preview-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Anteprima Report Demo', 'fp-digital-marketing' ); ?></h2>
			<p><?php esc_html_e( 'Questo è un\'anteprima del template del report con dati mock.', 'fp-digital-marketing' ); ?></p>
			
			<div style="border: 1px solid #ddd; margin: 20px 0; max-height: 600px; overflow-y: auto;">
				<?php echo $demo_html; ?>
			</div>
			
                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'download_pdf', 'client_id' => 1 ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ), 'download_pdf_1' ) ); ?>"
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
		<?php
	}
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

	/**
	 * Render Microsoft Clarity metrics section
	 *
	 * @return void
	 */
	private function render_clarity_metrics_section(): void {
		// Get all clients with Clarity Project IDs configured
		$clients_with_clarity = get_posts([
			'post_type' => 'cliente',
			'meta_query' => [
				[
					'key' => \FP\DigitalMarketing\Admin\ClienteMeta::META_CLARITY_PROJECT_ID,
					'value' => '',
					'compare' => '!='
				]
			],
			'post_status' => 'publish',
			'numberposts' => -1
		]);

		?>
		<div class="fp-dms-clarity-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
			<h2><?php esc_html_e( 'Microsoft Clarity - Analisi Comportamento Utenti (Per Cliente)', 'fp-digital-marketing' ); ?></h2>
			
			<div class="clarity-connection-info" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Stato Configurazione', 'fp-digital-marketing' ); ?></h3>
				<p class="clarity-status" style="<?php echo count( $clients_with_clarity ) > 0 ? 'color: #00a32a;' : 'color: #d63638;'; ?>">
					<span class="status-indicator">●</span>
					<?php 
					if ( count( $clients_with_clarity ) > 0 ) {
						/* translators: %d: number of clients with Clarity configured */
						echo esc_html( sprintf( _n( '%d cliente configurato', '%d clienti configurati', count( $clients_with_clarity ), 'fp-digital-marketing' ), count( $clients_with_clarity ) ) );
					} else {
						esc_html_e( 'Nessun cliente configurato', 'fp-digital-marketing' );
					}
					?>
				</p>
				
				<div class="notice notice-info inline" style="margin: 10px 0;">
					<p>
						<strong><?php esc_html_e( 'Nuovo approccio:', 'fp-digital-marketing' ); ?></strong>
						<?php esc_html_e( 'Microsoft Clarity ora monitora i siti web dei clienti individualmente, non il sito dell\'agenzia dove è installato questo plugin.', 'fp-digital-marketing' ); ?>
					</p>
				</div>
			</div>

			<?php if ( count( $clients_with_clarity ) > 0 ): ?>
				<?php $this->render_per_client_clarity_metrics( $clients_with_clarity ); ?>
				<?php $this->render_cached_clarity_metrics(); ?>
			<?php else: ?>
				<div class="notice notice-warning inline">
					<p>
						<?php esc_html_e( 'Per visualizzare le metriche Microsoft Clarity, configura il Project ID per i tuoi clienti.', 'fp-digital-marketing' ); ?>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente' ) ); ?>">
							<?php esc_html_e( 'Vai ai Clienti', 'fp-digital-marketing' ); ?>
						</a>
						<?php esc_html_e( 'e modifica ogni cliente per aggiungere il loro Project ID di Microsoft Clarity.', 'fp-digital-marketing' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Microsoft Clarity demo metrics
	 *
	 * @param string $project_id Clarity project ID
	 * @return void
	 */
	private function render_clarity_demo_metrics( string $project_id ): void {
		$clarity = new MicrosoftClarity( $project_id );
		
		// Demo Client ID for demo purposes
		$demo_client_id = 1;
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date = date( 'Y-m-d' );
		
		$metrics = $clarity->fetch_metrics( $demo_client_id, $start_date, $end_date );
		
		if ( $metrics ) {
			?>
			<div class="clarity-demo-metrics">
				<h3><?php esc_html_e( 'Metriche Demo (ultimi 30 giorni)', 'fp-digital-marketing' ); ?></h3>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
					<div class="metric-card" style="background: #f0f6ff; border: 1px solid #b3d7ff; border-radius: 4px; padding: 15px; text-align: center;">
						<div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo esc_html( number_format( $metrics['sessions'] ) ); ?></div>
						<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Sessioni', 'fp-digital-marketing' ); ?></div>
					</div>
					<div class="metric-card" style="background: #f0fff0; border: 1px solid #b3ffb3; border-radius: 4px; padding: 15px; text-align: center;">
						<div style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo esc_html( number_format( $metrics['page_views'] ) ); ?></div>
						<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Pagine Viste', 'fp-digital-marketing' ); ?></div>
					</div>
					<div class="metric-card" style="background: #fff0f0; border: 1px solid #ffb3b3; border-radius: 4px; padding: 15px; text-align: center;">
						<div style="font-size: 24px; font-weight: bold; color: #dc3545;"><?php echo esc_html( number_format( $metrics['recordings_available'] ) ); ?></div>
						<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Registrazioni', 'fp-digital-marketing' ); ?></div>
					</div>
					<div class="metric-card" style="background: #f8f0ff; border: 1px solid #d4b3ff; border-radius: 4px; padding: 15px; text-align: center;">
						<div style="font-size: 24px; font-weight: bold; color: #6f42c1;"><?php echo esc_html( number_format( $metrics['heatmaps_generated'] ) ); ?></div>
						<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Heatmap', 'fp-digital-marketing' ); ?></div>
					</div>
					<div class="metric-card" style="background: #fff8e1; border: 1px solid #ffd54f; border-radius: 4px; padding: 15px; text-align: center;">
						<div style="font-size: 24px; font-weight: bold; color: #f57c00;"><?php echo esc_html( number_format( $metrics['rage_clicks'] ) ); ?></div>
						<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Rage Clicks', 'fp-digital-marketing' ); ?></div>
					</div>
					<div class="metric-card" style="background: #fce4ec; border: 1px solid #f8bbd9; border-radius: 4px; padding: 15px; text-align: center;">
						<div style="font-size: 24px; font-weight: bold; color: #ad1457;"><?php echo esc_html( number_format( $metrics['dead_clicks'] ) ); ?></div>
						<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Dead Clicks', 'fp-digital-marketing' ); ?></div>
					</div>
					<div class="metric-card" style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 4px; padding: 15px; text-align: center;">
						<div style="font-size: 24px; font-weight: bold; color: #1976d2;"><?php echo esc_html( number_format( $metrics['scroll_depth_avg'] ) ); ?>%</div>
						<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Scroll Depth', 'fp-digital-marketing' ); ?></div>
					</div>
					<div class="metric-card" style="background: #e8f5e8; border: 1px solid #a5d6a7; border-radius: 4px; padding: 15px; text-align: center;">
						<div style="font-size: 24px; font-weight: bold; color: #388e3c;"><?php echo esc_html( number_format( $metrics['time_to_click_avg'], 1 ) ); ?>s</div>
						<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Time to Click', 'fp-digital-marketing' ); ?></div>
					</div>
				</div>
				
				<div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px;">
					<p style="margin: 0; font-size: 14px;">
						<strong><?php esc_html_e( 'Nota:', 'fp-digital-marketing' ); ?></strong>
						<?php esc_html_e( 'Questi sono dati demo generati automaticamente. Una volta configurata la connessione reale con Microsoft Clarity, verranno mostrati i dati effettivi del vostro sito.', 'fp-digital-marketing' ); ?>
					</p>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="notice notice-error inline">
				<p><?php esc_html_e( 'Errore nel recupero delle metriche demo di Microsoft Clarity.', 'fp-digital-marketing' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Render per-client Microsoft Clarity metrics
	 *
	 * @param array $clients_with_clarity Array of client posts with Clarity configured
	 * @return void
	 */
	private function render_per_client_clarity_metrics( array $clients_with_clarity ): void {
		?>
		<div class="clarity-per-client-metrics">
			<h3><?php esc_html_e( 'Metriche Demo per Cliente (ultimi 30 giorni)', 'fp-digital-marketing' ); ?></h3>
			
			<?php foreach ( $clients_with_clarity as $client ): ?>
				<?php 
				$clarity = MicrosoftClarity::for_client( $client->ID );
				if ( ! $clarity ) {
					continue;
				}
				
				$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
				$end_date = date( 'Y-m-d' );
				$metrics = $clarity->fetch_metrics( $client->ID, $start_date, $end_date );
				$project_id = MicrosoftClarity::get_client_project_id( $client->ID );
				?>
				
				<div style="border: 1px solid #ddd; border-radius: 8px; margin: 20px 0; overflow: hidden;">
					<div style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd;">
						<h4 style="margin: 0; color: #0073aa;">
							<?php echo esc_html( $client->post_title ); ?>
							<span style="font-size: 14px; font-weight: normal; color: #666; margin-left: 10px;">
								Project ID: <?php echo esc_html( $project_id ); ?>
							</span>
						</h4>
					</div>
					
					<div style="padding: 15px;">
						<?php if ( $metrics ): ?>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin: 15px 0;">
								<div class="metric-card" style="background: #f0f6ff; border: 1px solid #b3d7ff; border-radius: 4px; padding: 12px; text-align: center;">
									<div style="font-size: 20px; font-weight: bold; color: #0073aa;"><?php echo esc_html( number_format( $metrics['sessions'] ) ); ?></div>
									<div style="font-size: 12px; color: #666;"><?php esc_html_e( 'Sessioni', 'fp-digital-marketing' ); ?></div>
								</div>
								<div class="metric-card" style="background: #f0fff0; border: 1px solid #b3ffb3; border-radius: 4px; padding: 12px; text-align: center;">
									<div style="font-size: 20px; font-weight: bold; color: #00a32a;"><?php echo esc_html( number_format( $metrics['page_views'] ) ); ?></div>
									<div style="font-size: 12px; color: #666;"><?php esc_html_e( 'Pagine Viste', 'fp-digital-marketing' ); ?></div>
								</div>
								<div class="metric-card" style="background: #fff5f0; border: 1px solid #ffccb3; border-radius: 4px; padding: 12px; text-align: center;">
									<div style="font-size: 20px; font-weight: bold; color: #d63638;"><?php echo esc_html( number_format( $metrics['recordings_available'] ) ); ?></div>
									<div style="font-size: 12px; color: #666;"><?php esc_html_e( 'Registrazioni', 'fp-digital-marketing' ); ?></div>
								</div>
								<div class="metric-card" style="background: #f5f0ff; border: 1px solid #ccb3ff; border-radius: 4px; padding: 12px; text-align: center;">
									<div style="font-size: 20px; font-weight: bold; color: #7c3aed;"><?php echo esc_html( number_format( $metrics['heatmaps_generated'] ) ); ?></div>
									<div style="font-size: 12px; color: #666;"><?php esc_html_e( 'Heatmaps', 'fp-digital-marketing' ); ?></div>
								</div>
								<div class="metric-card" style="background: #fefff0; border: 1px solid #fff3b3; border-radius: 4px; padding: 12px; text-align: center;">
									<div style="font-size: 20px; font-weight: bold; color: #b45309;"><?php echo esc_html( number_format( $metrics['rage_clicks'] ) ); ?></div>
									<div style="font-size: 12px; color: #666;"><?php esc_html_e( 'Rage Clicks', 'fp-digital-marketing' ); ?></div>
								</div>
								<div class="metric-card" style="background: #f8f8f8; border: 1px solid #ccc; border-radius: 4px; padding: 12px; text-align: center;">
									<div style="font-size: 20px; font-weight: bold; color: #666;"><?php echo esc_html( $metrics['scroll_depth_avg'] . '%' ); ?></div>
									<div style="font-size: 12px; color: #666;"><?php esc_html_e( 'Scroll Depth', 'fp-digital-marketing' ); ?></div>
								</div>
							</div>
						<?php else: ?>
							<div class="notice notice-error inline" style="margin: 0;">
								<p><?php esc_html_e( 'Errore nel recupero delle metriche demo per questo cliente.', 'fp-digital-marketing' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			
			<div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px;">
				<p style="margin: 0; font-size: 14px;">
					<strong><?php esc_html_e( 'Nota:', 'fp-digital-marketing' ); ?></strong>
					<?php esc_html_e( 'Questi sono dati demo generati automaticamente per ogni cliente. Una volta configurata la connessione reale con Microsoft Clarity per ogni cliente, verranno mostrati i dati effettivi dei loro siti web.', 'fp-digital-marketing' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render cached Microsoft Clarity metrics
	 *
	 * @return void
	 */
	private function render_cached_clarity_metrics(): void {
		$cache = new MetricsCache();
		$metrics = $cache->get_metrics_by_source( MicrosoftClarity::SOURCE_ID );
		
		if ( empty( $metrics ) ) {
			?>
			<div class="clarity-cached-metrics" style="margin-top: 30px;">
				<h3><?php esc_html_e( 'Metriche dalla Cache', 'fp-digital-marketing' ); ?></h3>
				<p style="color: #666; font-style: italic;">
					<?php esc_html_e( 'Nessuna metrica Microsoft Clarity trovata nella cache. I dati verranno popolati automaticamente durante le sincronizzazioni.', 'fp-digital-marketing' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		?>
		<div class="clarity-cached-metrics" style="margin-top: 30px;">
			<h3><?php esc_html_e( 'Metriche dalla Cache', 'fp-digital-marketing' ); ?></h3>
			<p><?php printf( esc_html__( 'Trovate %d metriche Microsoft Clarity nella cache:', 'fp-digital-marketing' ), count( $metrics ) ); ?></p>
			
			<div style="overflow-x: auto;">
				<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
					<thead>
						<tr>
							<th style="width: 80px;"><?php esc_html_e( 'Client ID', 'fp-digital-marketing' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'KPI', 'fp-digital-marketing' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Valore', 'fp-digital-marketing' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'Data', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Metadata', 'fp-digital-marketing' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $metrics, 0, 10 ) as $metric ) : ?>
							<tr>
								<td><?php echo esc_html( $metric['client_id'] ); ?></td>
								<td><code><?php echo esc_html( $metric['kpi'] ); ?></code></td>
								<td><?php echo esc_html( number_format( $metric['value'], 2 ) ); ?></td>
								<td><?php echo esc_html( date( 'd/m/Y', strtotime( $metric['date'] ) ) ); ?></td>
								<td style="font-size: 11px;">
									<?php 
									$metadata = is_string( $metric['metadata'] ) ? json_decode( $metric['metadata'], true ) : $metric['metadata'];
									if ( is_array( $metadata ) ) {
										foreach ( $metadata as $key => $value ) {
											echo esc_html( $key . ': ' . $value ) . '<br>';
										}
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
						<?php if ( count( $metrics ) > 10 ) : ?>
							<tr>
								<td colspan="5" style="text-align: center; font-style: italic; color: #666;">
									<?php printf( esc_html__( '... e altre %d metriche', 'fp-digital-marketing' ), count( $metrics ) - 10 ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}