<?php
/**
 * Dashboard Admin Page
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\MetricsAggregator;
use FP\DigitalMarketing\Helpers\MetricsSchema;
use FP\DigitalMarketing\Helpers\DataSources;
use FP\DigitalMarketing\Models\SyncLog;
use FP\DigitalMarketing\Helpers\Security;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Helpers\CoreWebVitalsHelper;
use FP\DigitalMarketing\DataSources\CoreWebVitals;

/**
 * Dashboard class for admin overview
 * 
 * Provides a user-friendly dashboard interface with KPIs, charts, 
 * filters and sync status monitoring.
 */
class Dashboard {

	/**
	 * Page slug for dashboard
	 */
	private const PAGE_SLUG = 'fp-digital-marketing-dashboard';

	/**
	 * Initialize the dashboard page
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dashboard_assets' ] );
		add_action( 'wp_ajax_fp_dms_get_dashboard_data', [ $this, 'handle_ajax_dashboard_data' ] );
		add_action( 'wp_ajax_fp_dms_get_chart_data', [ $this, 'handle_ajax_chart_data' ] );
		add_action( 'wp_ajax_fp_dms_get_core_web_vitals', [ $this, 'handle_ajax_core_web_vitals' ] );
		add_action( 'wp_ajax_fp_dms_record_client_vital', [ $this, 'handle_ajax_record_client_vital' ] );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Main menu
		add_menu_page(
			__( 'FP Digital Marketing Suite', 'fp-digital-marketing' ),
			__( 'FP Digital Marketing', 'fp-digital-marketing' ),
			Capabilities::VIEW_DASHBOARD,
			self::PAGE_SLUG,
			[ $this, 'render_dashboard_page' ],
			'dashicons-chart-area',
			20
		);
		
		// Dashboard submenu (main page)
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Dashboard', 'fp-digital-marketing' ),
			__( '🏠 Dashboard', 'fp-digital-marketing' ),
			Capabilities::VIEW_DASHBOARD,
			self::PAGE_SLUG,
			[ $this, 'render_dashboard_page' ]
		);
	}

	/**
	 * Enqueue dashboard assets
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_dashboard_assets( string $hook ): void {
		// Enqueue admin menu styles globally for all admin pages
		wp_enqueue_style(
			'fp-dms-admin-menu',
			FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/css/admin-menu.css',
			[],
			FP_DIGITAL_MARKETING_VERSION
		);

		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'toplevel_page_' . self::PAGE_SLUG ) {
			return;
		}

		// Enqueue dashboard-specific styles
		wp_enqueue_style(
			'fp-dms-dashboard',
			FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/css/dashboard.css',
			[ 'fp-dms-admin-menu' ],
			FP_DIGITAL_MARKETING_VERSION
		);

		// Enqueue Chart.js from CDN
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
			[],
			'4.4.0',
			true
		);

		// Enqueue dashboard script
		wp_enqueue_script(
			'fp-dms-dashboard',
			plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/js/dashboard.js',
			[ 'jquery', 'chartjs' ],
			'1.0.0',
			true
		);

		// Enqueue dashboard styles
		wp_enqueue_style(
			'fp-dms-dashboard',
			plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/css/dashboard.css',
			[],
			'1.0.0'
		);

		// Localize script for AJAX
		wp_localize_script( 'fp-dms-dashboard', 'fpDmsDashboard', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'fp_dms_dashboard' ),
			'strings' => [
				'loading' => __( 'Loading...', 'fp-digital-marketing' ),
				'error' => __( 'Error loading data', 'fp-digital-marketing' ),
				'no_data' => __( 'No data available', 'fp-digital-marketing' ),
				'sessions' => __( 'Sessions', 'fp-digital-marketing' ),
				'users' => __( 'Users', 'fp-digital-marketing' ),
				'impressions' => __( 'Impressions', 'fp-digital-marketing' ),
				'clicks' => __( 'Clicks', 'fp-digital-marketing' ),
				'ctr' => __( 'CTR', 'fp-digital-marketing' ),
				'revenue' => __( 'Revenue', 'fp-digital-marketing' ),
				'lcp' => __( 'LCP', 'fp-digital-marketing' ),
				'inp' => __( 'INP', 'fp-digital-marketing' ),
				'cls' => __( 'CLS', 'fp-digital-marketing' ),
				'good' => __( 'Buono', 'fp-digital-marketing' ),
				'needs_improvement' => __( 'Da migliorare', 'fp-digital-marketing' ),
				'poor' => __( 'Scarso', 'fp-digital-marketing' ),
				'recommendations' => __( 'Raccomandazioni', 'fp-digital-marketing' ),
				'performance_score' => __( 'Punteggio Performance', 'fp-digital-marketing' ),
			],
		] );

		// Localize script for Core Web Vitals client-side collection
		wp_localize_script( 'fp-dms-dashboard', 'fpDmsVitals', [
			'nonce' => wp_create_nonce( 'fp_dms_client_vitals' ),
		] );
	}

	/**
	 * Handle AJAX request for dashboard data
	 *
	 * @return void
	 */
	public function handle_ajax_dashboard_data(): void {
		// Verify nonce and capabilities
		if ( ! Security::verify_nonce_with_logging( 'fp_dms_dashboard' ) ) {
			wp_die( 'Invalid nonce' );
		}

		if ( ! Capabilities::current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$client_id = intval( $_GET['client_id'] ?? 0 );
		$period_start = sanitize_text_field( $_GET['period_start'] ?? '' );
		$period_end = sanitize_text_field( $_GET['period_end'] ?? '' );
		$sources = isset( $_GET['sources'] ) ? array_map( 'sanitize_text_field', $_GET['sources'] ) : [];

		// Default to last 30 days if no period specified
		if ( empty( $period_start ) || empty( $period_end ) ) {
			$period_end = date( 'Y-m-d H:i:s' );
			$period_start = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		}

		$dashboard_data = $this->get_dashboard_data( $client_id, $period_start, $period_end, $sources );

		wp_send_json_success( $dashboard_data );
	}

	/**
	 * Handle AJAX request for chart data
	 *
	 * @return void
	 */
	public function handle_ajax_chart_data(): void {
		// Verify nonce and capabilities
		if ( ! Security::verify_nonce_with_logging( 'fp_dms_dashboard' ) ) {
			wp_die( 'Invalid nonce' );
		}

		if ( ! Capabilities::current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$metric = sanitize_text_field( $_GET['metric'] ?? 'sessions' );
		$client_id = intval( $_GET['client_id'] ?? 0 );
		$period_start = sanitize_text_field( $_GET['period_start'] ?? '' );
		$period_end = sanitize_text_field( $_GET['period_end'] ?? '' );
		$sources = isset( $_GET['sources'] ) ? array_map( 'sanitize_text_field', $_GET['sources'] ) : [];

		// Default to last 30 days if no period specified
		if ( empty( $period_start ) || empty( $period_end ) ) {
			$period_end = date( 'Y-m-d H:i:s' );
			$period_start = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		}

		$chart_data = $this->get_chart_data( $metric, $client_id, $period_start, $period_end, $sources );

		wp_send_json_success( $chart_data );
	}

	/**
	 * Get dashboard data
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date
	 * @param string $period_end End date
	 * @param array $sources Data sources filter
	 * @return array Dashboard data
	 */
	private function get_dashboard_data( int $client_id, string $period_start, string $period_end, array $sources = [] ): array {
		// Get KPI summary
		$kpi_summary = MetricsAggregator::get_kpi_summary( $client_id, $period_start, $period_end );

		// Get sync status
		$sync_stats = SyncLog::get_sync_stats( 7 );
		$recent_errors = SyncLog::get_error_logs( 5 );

		// Get available data sources
		$available_sources = DataSources::get_data_sources_by_status( 'available' );

		// Calculate period comparison (previous period)
		$period_duration = strtotime( $period_end ) - strtotime( $period_start );
		$prev_period_start = date( 'Y-m-d H:i:s', strtotime( $period_start ) - $period_duration );
		$prev_period_end = $period_start;
		
		$comparison_data = MetricsAggregator::get_period_comparison( 
			$client_id, 
			$period_start, 
			$period_end, 
			$prev_period_start, 
			$prev_period_end 
		);

		return [
			'kpis' => $kpi_summary,
			'sync_status' => $sync_stats,
			'recent_errors' => $recent_errors,
			'available_sources' => $available_sources,
			'comparison' => $comparison_data,
			'period' => [
				'start' => $period_start,
				'end' => $period_end,
			],
		];
	}

	/**
	 * Get chart data for specific metric
	 *
	 * @param string $metric Metric name
	 * @param int $client_id Client ID
	 * @param string $period_start Start date
	 * @param string $period_end End date
	 * @param array $sources Data sources filter
	 * @return array Chart data
	 */
	private function get_chart_data( string $metric, int $client_id, string $period_start, string $period_end, array $sources = [] ): array {
		// Generate daily data points for the period
		$start_time = strtotime( $period_start );
		$end_time = strtotime( $period_end );
		$dates = [];
		$values = [];

		// For demo purposes, generate mock trend data
		// In production, this would query actual daily metrics
		$current_time = $start_time;
		while ( $current_time <= $end_time ) {
			$dates[] = date( 'Y-m-d', $current_time );
			// Generate realistic mock data with trend
			$base_value = $this->get_base_value_for_metric( $metric );
			$trend_factor = ( $current_time - $start_time ) / ( $end_time - $start_time );
			$random_factor = 0.8 + ( rand( 0, 40 ) / 100 ); // ±20% variation
			$values[] = round( $base_value * ( 1 + $trend_factor * 0.2 ) * $random_factor );
			
			$current_time += 86400; // Add 1 day
		}

		return [
			'labels' => $dates,
			'data' => $values,
			'metric' => $metric,
		];
	}

	/**
	 * Get base value for metric (for demo data generation)
	 *
	 * @param string $metric Metric name
	 * @return int Base value
	 */
	private function get_base_value_for_metric( string $metric ): int {
		$base_values = [
			'sessions' => 1500,
			'users' => 1200,
			'pageviews' => 4500,
			'impressions' => 25000,
			'clicks' => 850,
			'conversions' => 45,
			'revenue' => 2500,
			'organic_clicks' => 650,
			'organic_impressions' => 15000,
		];

		return $base_values[ $metric ] ?? 100;
	}

	/**
	 * Render the dashboard page
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		// Check user capabilities
		if ( ! Capabilities::current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}

		// Get clients for filter
		$clients = get_posts( [
			'post_type'      => 'cliente',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		// Get available data sources
		$available_sources = DataSources::get_data_sources_by_status( 'available' );

		?>
		<div class="wrap fp-dms-dashboard">
			<div class="fp-dms-page-header">
				<h1><?php esc_html_e( 'FP Digital Marketing Suite', 'fp-digital-marketing' ); ?></h1>
				<p class="description"><?php esc_html_e( 'Dashboard principale per il monitoraggio e la gestione delle attività di digital marketing', 'fp-digital-marketing' ); ?></p>
			</div>

			<!-- Global Filters -->
			<div class="fp-dms-filters">
				<div class="fp-dms-filter-row">
					<div class="fp-dms-filter-group">
						<label for="client-filter"><?php esc_html_e( 'Cliente:', 'fp-digital-marketing' ); ?></label>
						<select id="client-filter" class="fp-dms-filter-select">
							<option value="0"><?php esc_html_e( 'Tutti i clienti', 'fp-digital-marketing' ); ?></option>
							<?php foreach ( $clients as $client ) : ?>
								<option value="<?php echo esc_attr( $client->ID ); ?>">
									<?php echo esc_html( $client->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="fp-dms-filter-group">
						<label for="date-start"><?php esc_html_e( 'Data inizio:', 'fp-digital-marketing' ); ?></label>
						<input type="date" id="date-start" class="fp-dms-filter-input" value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>">
					</div>

					<div class="fp-dms-filter-group">
						<label for="date-end"><?php esc_html_e( 'Data fine:', 'fp-digital-marketing' ); ?></label>
						<input type="date" id="date-end" class="fp-dms-filter-input" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
					</div>

					<div class="fp-dms-filter-group">
						<label for="source-filter"><?php esc_html_e( 'Sorgente:', 'fp-digital-marketing' ); ?></label>
						<select id="source-filter" class="fp-dms-filter-select" multiple>
							<?php foreach ( $available_sources as $source_id => $source ) : ?>
								<option value="<?php echo esc_attr( $source_id ); ?>">
									<?php echo esc_html( $source['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="fp-dms-filter-group">
						<button id="apply-filters" class="button button-primary">
							<?php esc_html_e( 'Applica Filtri', 'fp-digital-marketing' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Loading State -->
			<div id="dashboard-loading" class="fp-dms-loading">
				<div class="fp-dms-skeleton-grid">
					<?php for ( $i = 0; $i < 6; $i++ ) : ?>
						<div class="fp-dms-skeleton-card">
							<div class="fp-dms-skeleton-title"></div>
							<div class="fp-dms-skeleton-value"></div>
							<div class="fp-dms-skeleton-change"></div>
						</div>
					<?php endfor; ?>
				</div>
			</div>

			<!-- Dashboard Content -->
			<div id="dashboard-content" class="fp-dms-dashboard-content" style="display: none;">
				
				<!-- KPI Cards -->
				<div class="fp-dms-kpi-grid" id="kpi-cards">
					<!-- KPI cards will be populated by JavaScript -->
				</div>

				<!-- Chart Section -->
				<div class="fp-dms-chart-section">
					<div class="fp-dms-chart-header">
						<h2><?php esc_html_e( 'Trend Metriche', 'fp-digital-marketing' ); ?></h2>
						<div class="fp-dms-chart-controls">
							<label for="chart-metric"><?php esc_html_e( 'Metrica:', 'fp-digital-marketing' ); ?></label>
							<select id="chart-metric" class="fp-dms-metric-select">
								<option value="sessions"><?php esc_html_e( 'Sessioni', 'fp-digital-marketing' ); ?></option>
								<option value="users"><?php esc_html_e( 'Utenti', 'fp-digital-marketing' ); ?></option>
								<option value="pageviews"><?php esc_html_e( 'Visualizzazioni Pagina', 'fp-digital-marketing' ); ?></option>
								<option value="impressions"><?php esc_html_e( 'Impressioni', 'fp-digital-marketing' ); ?></option>
								<option value="clicks"><?php esc_html_e( 'Click', 'fp-digital-marketing' ); ?></option>
								<option value="conversions"><?php esc_html_e( 'Conversioni', 'fp-digital-marketing' ); ?></option>
								<option value="revenue"><?php esc_html_e( 'Fatturato', 'fp-digital-marketing' ); ?></option>
								<option value="lcp"><?php esc_html_e( 'LCP (ms)', 'fp-digital-marketing' ); ?></option>
								<option value="inp"><?php esc_html_e( 'INP (ms)', 'fp-digital-marketing' ); ?></option>
								<option value="cls"><?php esc_html_e( 'CLS', 'fp-digital-marketing' ); ?></option>
							</select>
						</div>
					</div>
					<div class="fp-dms-chart-container">
						<canvas id="trend-chart" aria-label="<?php esc_attr_e( 'Grafico trend metriche', 'fp-digital-marketing' ); ?>"></canvas>
					</div>
				</div>

				<!-- Core Web Vitals Section -->
				<div class="fp-dms-cwv-section">
					<div class="fp-dms-cwv-header">
						<h2><?php esc_html_e( 'Core Web Vitals', 'fp-digital-marketing' ); ?></h2>
						<div class="fp-dms-cwv-info">
							<span class="fp-dms-info-icon">ℹ️</span>
							<span><?php esc_html_e( 'Dati ultimi 28 giorni (75° percentile)', 'fp-digital-marketing' ); ?></span>
						</div>
					</div>
					<div class="fp-dms-cwv-widgets" id="cwv-widgets">
						<!-- Core Web Vitals widgets will be populated by JavaScript -->
					</div>
					<div class="fp-dms-cwv-recommendations" id="cwv-recommendations" style="display: none;">
						<!-- Performance recommendations will be populated by JavaScript -->
					</div>
				</div>

				<!-- Sync Status Section -->
				<div class="fp-dms-sync-status" id="sync-status">
					<!-- Sync status will be populated by JavaScript -->
				</div>

			</div>

			<!-- Empty State -->
			<div id="dashboard-empty" class="fp-dms-empty-state" style="display: none;">
				<div class="fp-dms-empty-icon">📊</div>
				<h3><?php esc_html_e( 'Nessun dato disponibile', 'fp-digital-marketing' ); ?></h3>
				<p><?php esc_html_e( 'Non sono stati trovati dati per il periodo e i filtri selezionati.', 'fp-digital-marketing' ); ?></p>
				<p><?php esc_html_e( 'Verifica che le sorgenti dati siano configurate correttamente.', 'fp-digital-marketing' ); ?></p>
			</div>

		</div>
		<?php
	}

	/**
	 * Handle AJAX request for Core Web Vitals data
	 *
	 * @return void
	 */
	public function handle_ajax_core_web_vitals(): void {
		// Verify nonce and capabilities
		if ( ! Security::verify_nonce_with_logging( 'fp_dms_dashboard' ) ) {
			wp_die( 'Invalid nonce' );
		}

		if ( ! Capabilities::current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$client_id = intval( $_GET['client_id'] ?? 0 );
		$origin_url = sanitize_url( $_GET['origin_url'] ?? get_site_url() );

		// Get Core Web Vitals data
		$cwv = new CoreWebVitals( $origin_url );
		$period_end = date( 'Y-m-d H:i:s' );
		$period_start = date( 'Y-m-d H:i:s', strtotime( '-28 days' ) );

		$metrics = $cwv->fetch_metrics( $client_id, $period_start, $period_end );

		if ( $metrics ) {
			// Get performance recommendations
			$recommendations = CoreWebVitalsHelper::get_performance_recommendations( $metrics );
			
			// Calculate performance score
			$score = CoreWebVitalsHelper::calculate_performance_score( $metrics );

			// Get status for each metric
			$statuses = [];
			foreach ( $metrics as $metric => $value ) {
				$kpi = MetricsSchema::normalize_metric_name( 'core_web_vitals', $metric );
				$statuses[ $metric ] = [
					'status' => MetricsSchema::get_performance_status( $kpi, (float) $value ),
					'color' => MetricsSchema::get_performance_color( 
						MetricsSchema::get_performance_status( $kpi, (float) $value )
					),
					'formatted_value' => CoreWebVitalsHelper::format_metric_value( $metric, $value ),
				];
			}

			wp_send_json_success([
				'metrics' => $metrics,
				'statuses' => $statuses,
				'recommendations' => $recommendations,
				'score' => $score,
				'origin_url' => $origin_url,
			]);
		} else {
			wp_send_json_error( __( 'Unable to fetch Core Web Vitals data', 'fp-digital-marketing' ) );
		}
	}

	/**
	 * Handle AJAX request to record client-side vital
	 *
	 * @return void
	 */
	public function handle_ajax_record_client_vital(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'fp_dms_client_vitals' ) ) {
			wp_die( 'Invalid nonce' );
		}

		$metric = sanitize_text_field( $_POST['metric'] ?? '' );
		$value = (float) ( $_POST['value'] ?? 0 );
		$url = sanitize_url( $_POST['url'] ?? '' );

		// Store client-side vital in database
		if ( in_array( $metric, [ 'lcp', 'inp', 'cls', 'fid' ], true ) && $value > 0 ) {
			// You could store this in a separate table for real-time monitoring
			// For now, we'll just log it
			error_log( sprintf( 
				'Client-side vital: %s = %s on %s', 
				$metric, 
				$value, 
				$url 
			) );

			wp_send_json_success( [ 'recorded' => true ] );
		} else {
			wp_send_json_error( 'Invalid metric data' );
		}
	}
}