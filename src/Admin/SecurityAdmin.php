<?php
/**
 * Security Admin Page
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\Security;
use FP\DigitalMarketing\Helpers\Capabilities;

/**
 * Security admin page for monitoring and auditing
 */
class SecurityAdmin {

	/**
	 * Page slug
	 */
	private const PAGE_SLUG = 'fp-digital-marketing-security';

	/**
	 * Initialize the security admin page
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_security_actions' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_security_scripts' ] );
	}

	/**
	 * Add security admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'fp-digital-marketing-dashboard',
			__( 'Security Settings', 'fp-digital-marketing' ),
			__( '🔒 Security', 'fp-digital-marketing' ),
			Capabilities::MANAGE_SETTINGS,
			self::PAGE_SLUG,
			[ $this, 'render_security_page' ]
		);
	}

	/**
	 * Handle security actions
	 *
	 * @return void
	 */
	public function handle_security_actions(): void {
		if ( ! isset( $_POST['action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

		switch ( $action ) {
			case 'run_security_audit':
				if ( Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) && 
					 Security::verify_nonce_with_logging( 'run_security_audit' ) ) {
					$this->run_security_audit();
				}
				break;

			case 'clear_security_logs':
				if ( Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) && 
					 Security::verify_nonce_with_logging( 'clear_security_logs' ) ) {
					$this->clear_security_logs();
				}
				break;
		}
	}

	/**
	 * Enqueue security admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_security_scripts( string $hook ): void {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		wp_enqueue_style( 'fp-dms-security-admin', 'data:text/css;base64,' . base64_encode('
			.fp-security-dashboard { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
			.fp-security-card { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; }
			.fp-security-score { font-size: 48px; font-weight: bold; text-align: center; margin: 20px 0; }
			.fp-security-score.good { color: #00a32a; }
			.fp-security-score.warning { color: #dba617; }
			.fp-security-score.critical { color: #d63638; }
			.fp-security-check { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; }
			.fp-security-check.pass { border-left-color: #00a32a; background: #f7fcf7; }
			.fp-security-check.warning { border-left-color: #dba617; background: #fffbf0; }
			.fp-security-check.fail { border-left-color: #d63638; background: #fcf2f3; }
			.fp-security-log { font-family: monospace; font-size: 12px; background: #f9f9f9; padding: 10px; margin: 5px 0; border-radius: 3px; }
			.fp-security-actions { margin: 20px 0; }
			.fp-security-actions .button { margin-right: 10px; }
		') );
	}

	/**
	 * Render the security page
	 *
	 * @return void
	 */
	public function render_security_page(): void {
		// Check user capabilities
		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}
		$audit_results = get_transient( 'fp_dms_security_audit' );
		$security_logs = Security::get_security_logs( 20 );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FP Digital Marketing Suite - Security Dashboard', 'fp-digital-marketing' ); ?></h1>

			<div class="fp-security-actions">
				<form method="post" style="display: inline;">
					<?php wp_nonce_field( 'run_security_audit' ); ?>
					<input type="hidden" name="action" value="run_security_audit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Esegui Audit Sicurezza', 'fp-digital-marketing' ); ?>
					</button>
				</form>

				<form method="post" style="display: inline;">
					<?php wp_nonce_field( 'clear_security_logs' ); ?>
					<input type="hidden" name="action" value="clear_security_logs">
					<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Confermi la cancellazione dei log di sicurezza?', 'fp-digital-marketing' ) ); ?>')">>
						<?php esc_html_e( 'Cancella Log Sicurezza', 'fp-digital-marketing' ); ?>
					</button>
				</form>
			</div>

			<?php if ( $audit_results ) : ?>
				<div class="fp-security-dashboard">
					<div class="fp-security-card">
						<h2><?php esc_html_e( 'Punteggio Sicurezza', 'fp-digital-marketing' ); ?></h2>
						
						<?php
						$score_class = 'good';
						if ( $audit_results['overall_score'] < 80 ) {
							$score_class = 'warning';
						}
						if ( $audit_results['overall_score'] < 60 || $audit_results['critical_issues'] > 0 ) {
							$score_class = 'critical';
						}
						?>
						
						<div class="fp-security-score <?php echo esc_attr( $score_class ); ?>">
							<?php echo esc_html( $audit_results['overall_score'] ); ?>%
						</div>

						<p><strong><?php esc_html_e( 'Problemi Critici:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $audit_results['critical_issues'] ); ?></p>
						<p><strong><?php esc_html_e( 'Avvisi:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $audit_results['warnings'] ); ?></p>
						<p><strong><?php esc_html_e( 'Ultimo Audit:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $audit_results['timestamp'] ); ?></p>
					</div>

					<div class="fp-security-card">
						<h2><?php esc_html_e( 'Informazioni Sistema', 'fp-digital-marketing' ); ?></h2>
						<p><strong><?php esc_html_e( 'Versione Plugin:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $audit_results['plugin_version'] ); ?></p>
						<p><strong><?php esc_html_e( 'Versione WordPress:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $audit_results['wp_version'] ); ?></p>
						<p><strong><?php esc_html_e( 'Versione PHP:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $audit_results['php_version'] ); ?></p>
					</div>
				</div>

				<div class="fp-security-card">
					<h2><?php esc_html_e( 'Risultati Controlli Sicurezza', 'fp-digital-marketing' ); ?></h2>
					
					<?php foreach ( $audit_results['checks'] as $check ) : ?>
						<div class="fp-security-check <?php echo esc_attr( $check['status'] ); ?>">
							<h4><?php echo esc_html( $check['name'] ); ?></h4>
							<p><?php echo esc_html( $check['message'] ); ?></p>
							<?php if ( $check['severity'] !== 'info' ) : ?>
								<small><strong><?php esc_html_e( 'Livello:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( ucfirst( $check['severity'] ) ); ?></small>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'Nessun audit di sicurezza disponibile. Esegui un audit per vedere i risultati.', 'fp-digital-marketing' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $security_logs ) ) : ?>
				<div class="fp-security-card">
					<h2><?php esc_html_e( 'Log Eventi Sicurezza (Ultimi 20)', 'fp-digital-marketing' ); ?></h2>
					
					<?php foreach ( $security_logs as $log ) : ?>
						<div class="fp-security-log">
							<strong><?php echo esc_html( $log['timestamp'] ); ?></strong> - 
							<?php echo esc_html( $log['event_type'] ); ?>
							<?php if ( ! empty( $log['context'] ) ) : ?>
								<br><small><?php echo esc_html( wp_json_encode( $log['context'] ) ); ?></small>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="fp-security-card">
				<h2><?php esc_html_e( 'Raccomandazioni Sicurezza', 'fp-digital-marketing' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Mantieni sempre aggiornato WordPress e i plugin', 'fp-digital-marketing' ); ?></li>
					<li><?php esc_html_e( 'Usa password forti e abilita l\'autenticazione a due fattori', 'fp-digital-marketing' ); ?></li>
					<li><?php esc_html_e( 'Controlla regolarmente i log di sicurezza per attività sospette', 'fp-digital-marketing' ); ?></li>
					<li><?php esc_html_e( 'Esegui backup regolari del sito e del database', 'fp-digital-marketing' ); ?></li>
					<li><?php esc_html_e( 'Limita i tentativi di login e usa plugin di sicurezza', 'fp-digital-marketing' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Run security audit
	 *
	 * @return void
	 */
	private function run_security_audit(): void {
		$results = Security::run_security_audit();
		set_transient( 'fp_dms_security_audit', $results, HOUR_IN_SECONDS );

		$message = sprintf(
			__( 'Audit di sicurezza completato. Punteggio: %d%% - %d problemi critici, %d avvisi', 'fp-digital-marketing' ),
			$results['overall_score'],
			$results['critical_issues'],
			$results['warnings']
		);

		$type = 'success';
		if ( $results['critical_issues'] > 0 ) {
			$type = 'error';
		} elseif ( $results['warnings'] > 0 ) {
			$type = 'warning';
		}

		add_settings_error( 'fp_security_audit', 'audit_completed', $message, $type );
	}

	/**
	 * Clear security logs
	 *
	 * @return void
	 */
	private function clear_security_logs(): void {
		Security::clear_security_logs();
		add_settings_error( 
			'fp_security_logs', 
			'logs_cleared', 
			__( 'Log di sicurezza cancellati con successo.', 'fp-digital-marketing' ), 
			'success' 
		);
	}
}