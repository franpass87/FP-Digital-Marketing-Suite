<?php
/**
 * Reports Page Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\DataSources;

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
	 * Render the reports page
	 *
	 * @return void
	 */
	public function render_reports_page(): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}

		// Get data sources for debug output.
		$all_data_sources = fp_dms_get_data_sources();
		$analytics_sources = fp_dms_get_data_sources( DataSources::TYPE_ANALYTICS );
		$available_sources = DataSources::get_data_sources_by_status( 'available' );
		$planned_sources = DataSources::get_data_sources_by_status( 'planned' );
		$data_source_types = DataSources::get_data_source_types();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Debug Output - Data Sources Registry', 'fp-digital-marketing' ); ?></strong><br>
					<?php esc_html_e( 'Questa pagina mostra lo stato attuale del registro delle sorgenti dati.', 'fp-digital-marketing' ); ?>
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
}