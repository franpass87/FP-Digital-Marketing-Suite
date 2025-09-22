<?php
/**
 * Cache Performance Admin Page
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\PerformanceCache;
use FP\DigitalMarketing\Helpers\CacheBenchmark;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Admin\MenuManager;

/**
 * Cache Performance admin page class
 */
class CachePerformance {

	/**
	 * Page slug
	 */
	private const PAGE_SLUG = 'fp-digital-marketing-cache-performance';

	/**
	 * Initialize the cache performance page
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
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
                        __( 'Cache Performance', 'fp-digital-marketing' ),
			__( '⚡ Cache Performance', 'fp-digital-marketing' ),
			Capabilities::MANAGE_SETTINGS,
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Handle admin actions
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) {
			return;
		}

		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return;
		}

		$action = $_GET['action'] ?? '';
		$nonce = $_GET['_wpnonce'] ?? '';

		if ( ! wp_verify_nonce( $nonce, 'cache_performance_action' ) ) {
			return;
		}

		$handled_action = false;

		switch ( $action ) {
			case 'run_benchmark':
				$this->run_performance_benchmark();
				$handled_action = true;
				break;
				
			case 'run_load_test':
				$this->run_load_test();
				$handled_action = true;
				break;
				
			case 'run_memory_test':
				$this->run_memory_test();
				$handled_action = true;
				break;
				
			case 'clear_cache':
				$this->clear_cache();
				$handled_action = true;
				break;
				
			case 'clear_stats':
				$this->clear_statistics();
				$handled_action = true;
				break;
		}

		if ( $handled_action ) {
			wp_safe_redirect( remove_query_arg( [ 'action', '_wpnonce' ] ) );
			exit;
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook The current admin page hook
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true );
		
		wp_add_inline_style( 'wp-admin', '
			.cache-performance-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
				gap: 20px;
				margin: 20px 0;
			}
			
			.cache-performance-card {
				background: #fff;
				border: 1px solid #ccd0d4;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				padding: 20px;
			}
			
			.cache-performance-card h3 {
				margin-top: 0;
				border-bottom: 1px solid #eee;
				padding-bottom: 10px;
			}
			
			.benchmark-actions {
				display: flex;
				gap: 10px;
				flex-wrap: wrap;
				margin: 20px 0;
			}
			
			.cache-stats-table {
				width: 100%;
				border-collapse: collapse;
			}
			
			.cache-stats-table th,
			.cache-stats-table td {
				padding: 8px;
				text-align: left;
				border-bottom: 1px solid #ddd;
			}
			
			.cache-stats-table th {
				background-color: #f1f1f1;
			}
			
			.performance-chart {
				max-width: 400px;
				margin: 20px auto;
			}
			
			.benchmark-result {
				background: #f9f9f9;
				border-left: 4px solid #00a0d2;
				padding: 15px;
				margin: 15px 0;
			}
			
			.benchmark-result.success {
				border-left-color: #46b450;
			}
			
			.benchmark-result.warning {
				border-left-color: #ffb900;
			}
		' );
	}

	/**
	 * Render the cache performance page
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}

		try {
			$cache_stats = PerformanceCache::get_cache_stats();
			$benchmark_history = CacheBenchmark::get_benchmark_history( 10 );
			$performance_report = CacheBenchmark::generate_performance_report();
			$cache_settings = PerformanceCache::get_cache_settings();
		} catch ( \Throwable $e ) {
			// Fallback to empty/default data if operations fail
			$cache_stats = [
				'total_requests' => 0,
				'cache_hits' => 0,
				'cache_misses' => 0,
				'hit_ratio' => 0.0,
				'groups' => []
			];
			$benchmark_history = [];
			$performance_report = [
				'cache_health_score' => 0,
				'recommendations' => [
					[
						'type' => 'warning',
						'message' => __( 'Cache performance monitoring non disponibile. Verifica la configurazione del sistema di cache.', 'fp-digital-marketing' )
					]
				]
			];
			$cache_settings = [
				'enabled' => false,
				'use_object_cache' => false,
				'use_transients' => false,
				'default_ttl' => 3600,
				'benchmark_enabled' => false
			];
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP Digital Marketing CachePerformance: Failed to load cache data - ' . $e->getMessage() );
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cache Performance - Analisi e Benchmark', 'fp-digital-marketing' ); ?></h1>
			
			<?php
			settings_errors( 'cache_performance' );
			settings_errors( 'cache_settings' );
			?>

			<?php $this->render_benchmark_actions(); ?>
			
			<div class="cache-performance-grid">
				<?php $this->render_cache_status_card( $cache_settings ); ?>
				<?php $this->render_cache_statistics_card( $cache_stats ); ?>
				<?php $this->render_performance_summary_card( $performance_report ); ?>
				<?php $this->render_recent_benchmarks_card( $benchmark_history ); ?>
			</div>
			
			<?php if ( ! empty( $benchmark_history ) ): ?>
				<?php $this->render_performance_charts( $benchmark_history ); ?>
			<?php endif; ?>
			
			<?php $this->render_recommendations( $performance_report['recommendations'] ?? [] ); ?>
		</div>
		<?php
	}

	/**
	 * Render benchmark action buttons
	 *
	 * @return void
	 */
	private function render_benchmark_actions(): void {
		$nonce = wp_create_nonce( 'cache_performance_action' );
		?>
		<div class="benchmark-actions">
			<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'run_benchmark', '_wpnonce' => $nonce ] ) ); ?>" 
			   class="button button-primary">
				<?php esc_html_e( 'Esegui Benchmark Performance', 'fp-digital-marketing' ); ?>
			</a>
			
			<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'run_load_test', '_wpnonce' => $nonce ] ) ); ?>" 
			   class="button">
				<?php esc_html_e( 'Test di Carico', 'fp-digital-marketing' ); ?>
			</a>
			
			<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'run_memory_test', '_wpnonce' => $nonce ] ) ); ?>" 
			   class="button">
				<?php esc_html_e( 'Test Memoria', 'fp-digital-marketing' ); ?>
			</a>
			
			<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'clear_cache', '_wpnonce' => $nonce ] ) ); ?>" 
			   class="button" 
			   onclick="return confirm('<?php esc_attr_e( 'Sei sicuro di voler invalidare tutta la cache?', 'fp-digital-marketing' ); ?>')">
				<?php esc_html_e( 'Invalida Cache', 'fp-digital-marketing' ); ?>
			</a>
			
			<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'clear_stats', '_wpnonce' => $nonce ] ) ); ?>" 
			   class="button" 
			   onclick="return confirm('<?php esc_attr_e( 'Sei sicuro di voler cancellare le statistiche?', 'fp-digital-marketing' ); ?>')">
				<?php esc_html_e( 'Cancella Statistiche', 'fp-digital-marketing' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render cache status card
	 *
	 * @param array $settings Cache settings
	 * @return void
	 */
	private function render_cache_status_card( array $settings ): void {
		?>
		<div class="cache-performance-card">
			<h3><?php esc_html_e( 'Stato Cache', 'fp-digital-marketing' ); ?></h3>
			
			<table class="cache-stats-table">
				<tr>
					<td><?php esc_html_e( 'Cache Abilitata:', 'fp-digital-marketing' ); ?></td>
					<td><?php echo $settings['enabled'] ? '✅ Sì' : '❌ No'; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Object Cache:', 'fp-digital-marketing' ); ?></td>
					<td><?php echo $settings['use_object_cache'] ? '✅ Attivo' : '❌ Disattivo'; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Transients:', 'fp-digital-marketing' ); ?></td>
					<td><?php echo $settings['use_transients'] ? '✅ Attivo' : '❌ Disattivo'; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'TTL Predefinito:', 'fp-digital-marketing' ); ?></td>
					<td><?php echo esc_html( $settings['default_ttl'] ); ?> <?php esc_html_e( 'secondi', 'fp-digital-marketing' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Benchmark Attivo:', 'fp-digital-marketing' ); ?></td>
					<td><?php echo $settings['benchmark_enabled'] ? '✅ Sì' : '❌ No'; ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render cache statistics card
	 *
	 * @param array $stats Cache statistics
	 * @return void
	 */
	private function render_cache_statistics_card( array $stats ): void {
		?>
		<div class="cache-performance-card">
			<h3><?php esc_html_e( 'Statistiche Cache', 'fp-digital-marketing' ); ?></h3>
			
			<table class="cache-stats-table">
				<tr>
					<td><?php esc_html_e( 'Richieste Totali:', 'fp-digital-marketing' ); ?></td>
					<td><?php echo esc_html( number_format( $stats['total_requests'] ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Cache Hits:', 'fp-digital-marketing' ); ?></td>
					<td><?php echo esc_html( number_format( $stats['cache_hits'] ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Cache Misses:', 'fp-digital-marketing' ); ?></td>
					<td><?php echo esc_html( number_format( $stats['cache_misses'] ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Hit Ratio:', 'fp-digital-marketing' ); ?></td>
					<td><strong><?php echo esc_html( number_format( $stats['hit_ratio'], 2 ) ); ?>%</strong></td>
				</tr>
			</table>
			
			<?php if ( ! empty( $stats['groups'] ) ): ?>
			<h4><?php esc_html_e( 'Per Gruppo', 'fp-digital-marketing' ); ?></h4>
			<table class="cache-stats-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Gruppo', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Richieste', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Hits', 'fp-digital-marketing' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stats['groups'] as $group => $group_stats ): ?>
					<tr>
						<td><?php echo esc_html( $group ); ?></td>
						<td><?php echo esc_html( number_format( $group_stats['requests'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $group_stats['hits'] ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render performance summary card
	 *
	 * @param array $report Performance report
	 * @return void
	 */
	private function render_performance_summary_card( array $report ): void {
		$health_score = $report['cache_health_score'];
		$health_class = '';
		
		if ( $health_score >= 80 ) {
			$health_class = 'success';
		} elseif ( $health_score >= 60 ) {
			$health_class = 'warning';
		}
		?>
		<div class="cache-performance-card">
			<h3><?php esc_html_e( 'Health Score Cache', 'fp-digital-marketing' ); ?></h3>
			
			<div class="benchmark-result <?php echo esc_attr( $health_class ); ?>">
				<h4><?php esc_html_e( 'Punteggio Complessivo:', 'fp-digital-marketing' ); ?> <?php echo esc_html( $health_score ); ?>/100</h4>
				
				<?php if ( $health_score >= 80 ): ?>
					<p><?php esc_html_e( 'Eccellente! Il sistema di cache sta funzionando ottimamente.', 'fp-digital-marketing' ); ?></p>
				<?php elseif ( $health_score >= 60 ): ?>
					<p><?php esc_html_e( 'Buono, ma ci sono margini di miglioramento.', 'fp-digital-marketing' ); ?></p>
				<?php else: ?>
					<p><?php esc_html_e( 'La cache necessita di ottimizzazioni.', 'fp-digital-marketing' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render recent benchmarks card
	 *
	 * @param array $benchmarks Recent benchmark results
	 * @return void
	 */
	private function render_recent_benchmarks_card( array $benchmarks ): void {
		?>
		<div class="cache-performance-card">
			<h3><?php esc_html_e( 'Benchmark Recenti', 'fp-digital-marketing' ); ?></h3>
			
			<?php if ( empty( $benchmarks ) ): ?>
				<p><?php esc_html_e( 'Nessun benchmark eseguito ancora.', 'fp-digital-marketing' ); ?></p>
			<?php else: ?>
				<table class="cache-stats-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Data', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Miglioramento', 'fp-digital-marketing' ); ?></th>
							<th><?php esc_html_e( 'Hit Ratio', 'fp-digital-marketing' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $benchmarks, 0, 5 ) as $benchmark ): ?>
						<tr>
							<td><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $benchmark['test_info']['timestamp'] ) ) ); ?></td>
							<td><?php echo esc_html( number_format( $benchmark['performance_improvement'], 2 ) ); ?>%</td>
							<td><?php echo esc_html( number_format( $benchmark['cache_hit_ratio'], 2 ) ); ?>%</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render performance charts
	 *
	 * @param array $benchmarks Benchmark data
	 * @return void
	 */
	private function render_performance_charts( array $benchmarks ): void {
		?>
		<div class="cache-performance-card">
			<h3><?php esc_html_e( 'Trend Performance', 'fp-digital-marketing' ); ?></h3>
			
			<canvas id="performanceChart" class="performance-chart"></canvas>
			
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				const ctx = document.getElementById('performanceChart').getContext('2d');
				const benchmarkData = <?php echo wp_json_encode( array_reverse( array_slice( $benchmarks, 0, 10 ) ) ); ?>;
				
				new Chart(ctx, {
					type: 'line',
					data: {
						labels: benchmarkData.map(b => new Date(b.test_info.timestamp).toLocaleDateString('it-IT')),
						datasets: [{
							label: 'Miglioramento Performance (%)',
							data: benchmarkData.map(b => b.performance_improvement),
							borderColor: '#00a0d2',
							backgroundColor: 'rgba(0, 160, 210, 0.1)',
							tension: 0.1
						}, {
							label: 'Cache Hit Ratio (%)',
							data: benchmarkData.map(b => b.cache_hit_ratio),
							borderColor: '#46b450',
							backgroundColor: 'rgba(70, 180, 80, 0.1)',
							tension: 0.1
						}]
					},
					options: {
						responsive: true,
						scales: {
							y: {
								beginAtZero: true,
								max: 100
							}
						},
						plugins: {
							legend: {
								position: 'bottom'
							}
						}
					}
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * Render recommendations
	 *
	 * @param array $recommendations Performance recommendations
	 * @return void
	 */
	private function render_recommendations( array $recommendations ): void {
		if ( empty( $recommendations ) ) {
			return;
		}
		?>
		<div class="cache-performance-card">
			<h3><?php esc_html_e( 'Raccomandazioni', 'fp-digital-marketing' ); ?></h3>
			
			<?php foreach ( $recommendations as $recommendation ): ?>
				<div class="benchmark-result <?php echo esc_attr( $recommendation['type'] ); ?>">
					<p><?php echo esc_html( $recommendation['message'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Run performance benchmark
	 *
	 * @return void
	 */
	private function run_performance_benchmark(): void {
		$results = CacheBenchmark::run_performance_benchmark( 10 );
		
		$message = sprintf(
			__( 'Benchmark completato. Miglioramento performance: %.2f%%, Cache hit ratio: %.2f%%', 'fp-digital-marketing' ),
			$results['performance_improvement'],
			$results['cache_hit_ratio']
		);
		
		add_settings_error( 'cache_performance', 'benchmark_completed', $message, 'updated' );
	}

	/**
	 * Run load test
	 *
	 * @return void
	 */
	private function run_load_test(): void {
		$results = CacheBenchmark::run_load_test( 3, 5 );
		
		$message = sprintf(
			__( 'Test di carico completato. Richieste totali: %d, Tempo medio risposta: %.4fs', 'fp-digital-marketing' ),
			$results['overall_stats']['total_requests'],
			$results['overall_stats']['avg_response_time']
		);
		
		add_settings_error( 'cache_performance', 'load_test_completed', $message, 'updated' );
	}

	/**
	 * Run memory test
	 *
	 * @return void
	 */
	private function run_memory_test(): void {
		$results = CacheBenchmark::run_memory_test( 2 );
		
		$message = sprintf(
			__( 'Test memoria completato. Efficienza cache: %.2f, Memoria utilizzata: %s', 'fp-digital-marketing' ),
			$results['memory_efficiency'],
			size_format( $results['cache_memory_usage'] )
		);
		
		add_settings_error( 'cache_performance', 'memory_test_completed', $message, 'updated' );
	}

	/**
	 * Clear all cache
	 *
	 * @return void
	 */
	private function clear_cache(): void {
		PerformanceCache::invalidate_all();
		add_settings_error( 'cache_performance', 'cache_cleared', __( 'Cache invalidata con successo.', 'fp-digital-marketing' ), 'updated' );
	}

	/**
	 * Clear cache statistics
	 *
	 * @return void
	 */
	private function clear_statistics(): void {
		PerformanceCache::clear_stats();
		CacheBenchmark::clear_benchmark_history();
		add_settings_error( 'cache_performance', 'stats_cleared', __( 'Statistiche cancellate con successo.', 'fp-digital-marketing' ), 'updated' );
	}

	/**
	 * Alias method for MenuManager compatibility
	 * Renders the performance page (same as render_page)
	 *
	 * @return void
	 */
	public function render_performance_page(): void {
		$this->render_page();
	}
}