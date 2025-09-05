<?php
/**
 * Dashboard Widgets Helper
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Helpers\Capabilities;

/**
 * Dashboard Widgets class
 * 
 * This class provides WordPress dashboard widgets for the FP Digital Marketing Suite
 */
class DashboardWidgets {

	/**
	 * Initialize dashboard widgets
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_dashboard_setup', [ __CLASS__, 'add_dashboard_widgets' ] );
	}

	/**
	 * Add dashboard widgets
	 *
	 * @return void
	 */
	public static function add_dashboard_widgets(): void {
		// Only show to users with proper capabilities
		if ( ! Capabilities::current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			return;
		}

		// Marketing Performance Widget
		wp_add_dashboard_widget(
			'fp_dms_performance_widget',
			__( '📊 Marketing Performance', 'fp-digital-marketing' ),
			[ __CLASS__, 'render_performance_widget' ]
		);

		// Quick Actions Widget
		wp_add_dashboard_widget(
			'fp_dms_quick_actions_widget',
			__( '⚡ Quick Actions', 'fp-digital-marketing' ),
			[ __CLASS__, 'render_quick_actions_widget' ]
		);

		// Cache Status Widget
		if ( Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_add_dashboard_widget(
				'fp_dms_cache_status_widget',
				__( '🚀 Cache Status', 'fp-digital-marketing' ),
				[ __CLASS__, 'render_cache_status_widget' ]
			);
		}
	}

	/**
	 * Render marketing performance widget
	 *
	 * @return void
	 */
	public static function render_performance_widget(): void {
		// Get cached performance data
		$performance_data = PerformanceCache::get(
			'dashboard_performance',
			PerformanceCache::CACHE_GROUP_REPORTS,
			function() {
				return self::get_performance_data();
			},
			900 // 15 minutes cache
		);

		?>
		<div class="fp-dms-dashboard-widget">
			<div class="fp-dms-metrics-grid">
				<div class="fp-dms-metric">
					<span class="fp-dms-metric-value"><?php echo esc_html( number_format( $performance_data['sessions'] ) ); ?></span>
					<span class="fp-dms-metric-label"><?php esc_html_e( 'Sessions', 'fp-digital-marketing' ); ?></span>
				</div>
				<div class="fp-dms-metric">
					<span class="fp-dms-metric-value"><?php echo esc_html( number_format( $performance_data['users'] ) ); ?></span>
					<span class="fp-dms-metric-label"><?php esc_html_e( 'Users', 'fp-digital-marketing' ); ?></span>
				</div>
				<div class="fp-dms-metric">
					<span class="fp-dms-metric-value"><?php echo esc_html( number_format( $performance_data['pageviews'] ) ); ?></span>
					<span class="fp-dms-metric-label"><?php esc_html_e( 'Pageviews', 'fp-digital-marketing' ); ?></span>
				</div>
				<div class="fp-dms-metric">
					<span class="fp-dms-metric-value"><?php echo esc_html( number_format( $performance_data['bounce_rate'], 1 ) ); ?>%</span>
					<span class="fp-dms-metric-label"><?php esc_html_e( 'Bounce Rate', 'fp-digital-marketing' ); ?></span>
				</div>
			</div>

			<div class="fp-dms-widget-footer">
				<p class="fp-dms-last-updated">
					<?php 
					printf( 
						esc_html__( 'Ultimo aggiornamento: %s', 'fp-digital-marketing' ),
						esc_html( date_i18n( get_option( 'time_format' ), $performance_data['last_updated'] ) )
					); 
					?>
				</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-dashboard' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Dashboard Completa', 'fp-digital-marketing' ); ?>
				</a>
			</div>
		</div>

		<style>
		.fp-dms-dashboard-widget {
			padding: 10px 0;
		}
		.fp-dms-metrics-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
			gap: 15px;
			margin-bottom: 20px;
		}
		.fp-dms-metric {
			text-align: center;
			padding: 15px;
			background: #f8f9fa;
			border-radius: 6px;
			border-left: 4px solid #0073aa;
		}
		.fp-dms-metric-value {
			display: block;
			font-size: 24px;
			font-weight: bold;
			color: #1d2327;
			line-height: 1.2;
		}
		.fp-dms-metric-label {
			display: block;
			font-size: 12px;
			color: #646970;
			margin-top: 5px;
			text-transform: uppercase;
			font-weight: 600;
		}
		.fp-dms-widget-footer {
			border-top: 1px solid #ddd;
			padding-top: 15px;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.fp-dms-last-updated {
			margin: 0;
			font-size: 12px;
			color: #646970;
		}
		</style>
		<?php
	}

	/**
	 * Render quick actions widget
	 *
	 * @return void
	 */
	public static function render_quick_actions_widget(): void {
		?>
		<div class="fp-dms-dashboard-widget">
			<div class="fp-dms-quick-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-dashboard' ) ); ?>" class="fp-dms-action-link">
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e( 'Dashboard', 'fp-digital-marketing' ); ?>
				</a>
				
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-settings' ) ); ?>" class="fp-dms-action-link">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Impostazioni', 'fp-digital-marketing' ); ?>
				</a>
				
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente' ) ); ?>" class="fp-dms-action-link">
					<span class="dashicons dashicons-groups"></span>
					<?php esc_html_e( 'Clienti', 'fp-digital-marketing' ); ?>
				</a>
				
				<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" class="fp-dms-action-link" target="_blank">
					<span class="dashicons dashicons-networking"></span>
					<?php esc_html_e( 'Sitemap XML', 'fp-digital-marketing' ); ?>
				</a>
			</div>

			<?php if ( Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) ): ?>
			<div class="fp-dms-admin-actions">
				<button type="button" class="button" id="fp-dms-quick-cache-clear">
					<?php esc_html_e( 'Svuota Cache', 'fp-digital-marketing' ); ?>
				</button>
				<button type="button" class="button" id="fp-dms-quick-cache-warmup">
					<?php esc_html_e( 'Pre-carica Cache', 'fp-digital-marketing' ); ?>
				</button>
			</div>
			<?php endif; ?>
		</div>

		<style>
		.fp-dms-quick-actions {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
			gap: 10px;
			margin-bottom: 15px;
		}
		.fp-dms-action-link {
			display: flex;
			align-items: center;
			padding: 12px;
			text-decoration: none;
			background: #f8f9fa;
			border: 1px solid #ddd;
			border-radius: 4px;
			color: #1d2327;
			transition: all 0.2s ease;
		}
		.fp-dms-action-link:hover {
			background: #fff;
			border-color: #0073aa;
			color: #0073aa;
			text-decoration: none;
		}
		.fp-dms-action-link .dashicons {
			margin-right: 8px;
			font-size: 16px;
		}
		.fp-dms-admin-actions {
			border-top: 1px solid #ddd;
			padding-top: 15px;
			display: flex;
			gap: 10px;
		}
		.fp-dms-admin-actions .button {
			flex: 1;
		}
		</style>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#fp-dms-quick-cache-clear').on('click', function() {
				if (confirm('<?php esc_html_e( 'Svuotare la cache?', 'fp-digital-marketing' ); ?>')) {
					window.location.href = '<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=fp-digital-marketing-settings&action=invalidate_cache' ), 'fp_digital_marketing_settings_nonce' ) ); ?>';
				}
			});

			$('#fp-dms-quick-cache-warmup').on('click', function() {
				const $button = $(this);
				const originalText = $button.text();
				
				$button.prop('disabled', true).text('<?php esc_html_e( 'Pre-caricando...', 'fp-digital-marketing' ); ?>');
				
				$.post(ajaxurl, {
					action: 'fp_warmup_cache',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fp_dms_settings_nonce' ) ); ?>'
				}, function(response) {
					$button.prop('disabled', false).text(originalText);
					if (response.success) {
						// Create WordPress-style admin notice
						$('<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cache pre-caricata con successo!', 'fp-digital-marketing' ); ?></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>')
							.insertAfter('.wrap h1').hide().fadeIn();
					} else {
						$('<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Errore durante il pre-caricamento.', 'fp-digital-marketing' ); ?></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>')
							.insertAfter('.wrap h1').hide().fadeIn();
					}
					
					// Auto-dismiss after 5 seconds
					setTimeout(function() {
						$('.notice.is-dismissible').fadeOut();
					}, 5000);
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render cache status widget
	 *
	 * @return void
	 */
	public static function render_cache_status_widget(): void {
		$cache_stats = PerformanceCache::get_cache_statistics();
		$cache_settings = PerformanceCache::get_cache_settings();

		?>
		<div class="fp-dms-dashboard-widget">
			<div class="fp-dms-cache-status">
				<div class="fp-dms-status-item">
					<span class="fp-dms-status-label"><?php esc_html_e( 'Cache:', 'fp-digital-marketing' ); ?></span>
					<span class="fp-dms-status-value <?php echo $cache_settings['enabled'] ? 'enabled' : 'disabled'; ?>">
						<?php echo $cache_settings['enabled'] ? '✅ ' . esc_html__( 'Abilitato', 'fp-digital-marketing' ) : '❌ ' . esc_html__( 'Disabilitato', 'fp-digital-marketing' ); ?>
					</span>
				</div>

				<div class="fp-dms-status-item">
					<span class="fp-dms-status-label"><?php esc_html_e( 'Object Cache:', 'fp-digital-marketing' ); ?></span>
					<span class="fp-dms-status-value <?php echo $cache_stats['object_cache_available'] ? 'enabled' : 'disabled'; ?>">
						<?php echo $cache_stats['object_cache_available'] ? '✅ ' . esc_html__( 'Disponibile', 'fp-digital-marketing' ) : '❌ ' . esc_html__( 'Non Disponibile', 'fp-digital-marketing' ); ?>
					</span>
				</div>

				<div class="fp-dms-status-item">
					<span class="fp-dms-status-label"><?php esc_html_e( 'Transients:', 'fp-digital-marketing' ); ?></span>
					<span class="fp-dms-status-value"><?php echo esc_html( number_format( $cache_stats['transients_count'] ) ); ?></span>
				</div>

				<div class="fp-dms-status-item">
					<span class="fp-dms-status-label"><?php esc_html_e( 'Ultimo Warmup:', 'fp-digital-marketing' ); ?></span>
					<span class="fp-dms-status-value">
						<?php 
						if ( $cache_stats['last_warmup'] > 0 ) {
							echo esc_html( human_time_diff( $cache_stats['last_warmup'] ) . ' fa' );
						} else {
							esc_html_e( 'Mai', 'fp-digital-marketing' );
						}
						?>
					</span>
				</div>
			</div>
		</div>

		<style>
		.fp-dms-cache-status {
			display: flex;
			flex-direction: column;
			gap: 10px;
		}
		.fp-dms-status-item {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 8px 12px;
			background: #f8f9fa;
			border-radius: 4px;
		}
		.fp-dms-status-label {
			font-weight: 600;
			color: #646970;
		}
		.fp-dms-status-value {
			font-weight: 600;
		}
		.fp-dms-status-value.enabled {
			color: #008a00;
		}
		.fp-dms-status-value.disabled {
			color: #cc0000;
		}
		</style>
		<?php
	}

	/**
	 * Get performance data for dashboard widget
	 *
	 * @return array Performance data
	 */
	private static function get_performance_data(): array {
		// This would typically fetch real data from analytics APIs
		// For now, return demo data
		return [
			'sessions' => rand( 800, 1200 ),
			'users' => rand( 600, 900 ),
			'pageviews' => rand( 2000, 3000 ),
			'bounce_rate' => rand( 35, 55 ) + ( rand( 0, 9 ) / 10 ),
			'last_updated' => time(),
		];
	}
}