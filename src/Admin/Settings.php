<?php
/**
 * Settings Page Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\DataSources\GoogleOAuth;
use FP\DigitalMarketing\DataSources\GoogleAnalytics4;
use FP\DigitalMarketing\Helpers\SyncEngine;
use FP\DigitalMarketing\Helpers\Security;

/**
 * Settings class for plugin administration
 */
class Settings {

	/**
	 * Option group name
	 */
	private const OPTION_GROUP = 'fp_digital_marketing_settings';

	/**
	 * Options page slug
	 */
	private const PAGE_SLUG = 'fp-digital-marketing-settings';

	/**
	 * General settings section
	 */
	private const SECTION_GENERAL = 'fp_digital_marketing_general';

	/**
	 * API Keys settings section
	 */
	private const SECTION_API_KEYS = 'fp_digital_marketing_api_keys';

	/**
	 * Sync settings section
	 */
	private const SECTION_SYNC = 'fp_digital_marketing_sync';

	/**
	 * Demo option name
	 */
	private const OPTION_DEMO = 'fp_digital_marketing_demo_option';

	/**
	 * API Keys option name
	 */
	private const OPTION_API_KEYS = 'fp_digital_marketing_api_keys';

	/**
	 * Sync settings option name
	 */
	private const OPTION_SYNC = 'fp_digital_marketing_sync_settings';

	/**
	 * Nonce action for settings
	 */
	private const NONCE_ACTION = 'fp_digital_marketing_settings_nonce';

	/**
	 * Initialize the settings page
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'handle_ga4_oauth_callback' ] );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_options_page(
			__( 'FP Digital Marketing Settings', 'fp-digital-marketing' ),
			__( 'FP Digital Marketing', 'fp-digital-marketing' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings and sections
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// Register settings.
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_DEMO,
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_API_KEYS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_api_keys' ],
				'default'           => [],
			]
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_SYNC,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_sync_settings' ],
				'default'           => [],
			]
		);

		// Add General section.
		add_settings_section(
			self::SECTION_GENERAL,
			__( 'Impostazioni Generali', 'fp-digital-marketing' ),
			[ $this, 'render_general_section' ],
			self::PAGE_SLUG
		);

		// Add demo option field.
		add_settings_field(
			'demo_option',
			__( 'Opzione Demo', 'fp-digital-marketing' ),
			[ $this, 'render_demo_option_field' ],
			self::PAGE_SLUG,
			self::SECTION_GENERAL
		);

		// Add API Keys section.
		add_settings_section(
			self::SECTION_API_KEYS,
			__( 'Chiavi API', 'fp-digital-marketing' ),
			[ $this, 'render_api_keys_section' ],
			self::PAGE_SLUG
		);

		// Add API key placeholder field.
		add_settings_field(
			'api_keys_placeholder',
			__( 'Configurazione API', 'fp-digital-marketing' ),
			[ $this, 'render_api_keys_field' ],
			self::PAGE_SLUG,
			self::SECTION_API_KEYS
		);

		// Add Sync section.
		add_settings_section(
			self::SECTION_SYNC,
			__( 'Sincronizzazione Automatica', 'fp-digital-marketing' ),
			[ $this, 'render_sync_section' ],
			self::PAGE_SLUG
		);

		// Add sync settings field.
		add_settings_field(
			'sync_settings',
			__( 'Impostazioni Sync', 'fp-digital-marketing' ),
			[ $this, 'render_sync_settings_field' ],
			self::PAGE_SLUG,
			self::SECTION_SYNC
		);
	}

	/**
	 * Render the settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php settings_errors(); ?>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				wp_nonce_field( self::NONCE_ACTION, '_wpnonce_settings' );
				submit_button( __( 'Salva Impostazioni', 'fp-digital-marketing' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general section description
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configurazioni generali per il plugin FP Digital Marketing Suite.', 'fp-digital-marketing' ) . '</p>';
	}

	/**
	 * Render demo option field
	 *
	 * @return void
	 */
	public function render_demo_option_field(): void {
		$value = get_option( self::OPTION_DEMO, '' );
		?>
		<input 
			type="text" 
			id="<?php echo esc_attr( self::OPTION_DEMO ); ?>" 
			name="<?php echo esc_attr( self::OPTION_DEMO ); ?>" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text" 
			placeholder="<?php esc_attr_e( 'Inserisci valore demo...', 'fp-digital-marketing' ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'Questo è un campo di esempio per testare il salvataggio delle opzioni.', 'fp-digital-marketing' ); ?>
		</p>
		<?php
	}

	/**
	 * Render API keys section description
	 *
	 * @return void
	 */
	public function render_api_keys_section(): void {
		echo '<p>' . esc_html__( 'Configurazione delle chiavi API per i servizi esterni. Questa sezione sarà espansa in future versioni.', 'fp-digital-marketing' ) . '</p>';
	}

	/**
	 * Render API keys field
	 *
	 * @return void
	 */
	public function render_api_keys_field(): void {
		$api_keys = get_option( self::OPTION_API_KEYS, [] );
		$oauth = new GoogleOAuth();
		$connection_status = $oauth->get_connection_status();
		?>
		<div class="ga4-configuration">
			<h4><?php esc_html_e( 'Google Analytics 4', 'fp-digital-marketing' ); ?></h4>
			
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Client ID', 'fp-digital-marketing' ); ?></th>
					<td>
						<input 
							type="text" 
							name="<?php echo esc_attr( self::OPTION_API_KEYS ); ?>[google_client_id]"
							value="<?php echo esc_attr( $api_keys['google_client_id'] ?? '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Google OAuth Client ID', 'fp-digital-marketing' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'ID client OAuth per Google Analytics 4 API', 'fp-digital-marketing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Client Secret', 'fp-digital-marketing' ); ?></th>
					<td>
						<input 
							type="password" 
							name="<?php echo esc_attr( self::OPTION_API_KEYS ); ?>[google_client_secret]"
							value="<?php echo esc_attr( $api_keys['google_client_secret'] ?? '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Google OAuth Client Secret', 'fp-digital-marketing' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Secret client OAuth per Google Analytics 4 API', 'fp-digital-marketing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Property ID', 'fp-digital-marketing' ); ?></th>
					<td>
						<input 
							type="text" 
							name="<?php echo esc_attr( self::OPTION_API_KEYS ); ?>[ga4_property_id]"
							value="<?php echo esc_attr( $api_keys['ga4_property_id'] ?? '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'GA4 Property ID (es: 123456789)', 'fp-digital-marketing' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'ID della proprietà Google Analytics 4', 'fp-digital-marketing' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="ga4-connection-status">
				<h4><?php esc_html_e( 'Stato Connessione', 'fp-digital-marketing' ); ?></h4>
				<p class="ga4-status <?php echo esc_attr( $connection_status['class'] ); ?>">
					<span class="status-indicator"></span>
					<?php echo esc_html( $connection_status['status'] ); ?>
				</p>

				<?php if ( $oauth->is_configured() ): ?>
					<?php if ( ! $connection_status['connected'] ): ?>
						<a href="<?php echo esc_url( $oauth->get_authorization_url() ); ?>" class="button button-primary">
							<?php esc_html_e( 'Connetti a Google Analytics', 'fp-digital-marketing' ); ?>
						</a>
					<?php else: ?>
						<form method="post" style="display: inline;">
							<?php wp_nonce_field( 'ga4_disconnect', 'ga4_disconnect_nonce' ); ?>
							<input type="hidden" name="action" value="ga4_disconnect">
							<button type="submit" class="button button-secondary">
								<?php esc_html_e( 'Disconnetti', 'fp-digital-marketing' ); ?>
							</button>
						</form>
						<?php if ( isset( $connection_status['expires_at'] ) ): ?>
							<p class="description">
								<?php 
								printf( 
									esc_html__( 'Token scade il: %s', 'fp-digital-marketing' ),
									esc_html( $connection_status['expires_at'] )
								); 
								?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				<?php else: ?>
					<p class="description">
						<?php esc_html_e( 'Salva prima le credenziali Client ID e Client Secret per abilitare la connessione.', 'fp-digital-marketing' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<style>
				.ga4-status.connected .status-indicator { color: #00a32a; }
				.ga4-status.disconnected .status-indicator { color: #d63638; }
				.ga4-status.expired .status-indicator { color: #dba617; }
				.ga4-status .status-indicator:before { content: "●"; margin-right: 5px; }
				.ga4-configuration { margin-bottom: 20px; }
			</style>
		</div>
		<?php
	}

	/**
	 * Sanitize API keys data with encryption
	 *
	 * Security Enhancement: This method now encrypts sensitive API keys before storage.
	 * Implemented security measures:
	 * - Encryption of sensitive API keys using AES-256-CBC
	 * - Enhanced input sanitization
	 * - Security audit logging for API key changes
	 * - Nonce verification for all API key operations
	 *
	 * @param mixed $input The input data.
	 * @return array Sanitized and encrypted API keys array.
	 */
	public function sanitize_api_keys( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		// Verify nonce for API key changes
		if ( ! Security::verify_nonce_with_logging( self::NONCE_ACTION, '_wpnonce_settings' ) ) {
			wp_die( esc_html__( 'Errore di sicurezza: token non valido', 'fp-digital-marketing' ) );
		}

		// Verify user capability
		if ( ! Security::verify_capability_with_logging( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
		}

		$current_keys = get_option( self::OPTION_API_KEYS, [] );
		$sanitized = [];
		$sensitive_keys = [ 'google_client_secret', 'api_token', 'secret_key' ];

		foreach ( $input as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			$sanitized_value = sanitize_text_field( $value );

			// Encrypt sensitive API keys
			if ( in_array( $sanitized_key, $sensitive_keys, true ) && ! empty( $sanitized_value ) ) {
				// Only encrypt if value has changed to avoid double encryption
				if ( ! isset( $current_keys[ $sanitized_key ] ) || 
					 Security::decrypt_sensitive_data( $current_keys[ $sanitized_key ] ) !== $sanitized_value ) {
					$sanitized[ $sanitized_key ] = Security::encrypt_sensitive_data( $sanitized_value );
					
					// Log API key change
					error_log( sprintf( 
						'FP Digital Marketing: API key %s updated by user %d', 
						$sanitized_key, 
						get_current_user_id() 
					) );
				} else {
					// Keep existing encrypted value
					$sanitized[ $sanitized_key ] = $current_keys[ $sanitized_key ];
				}
			} else {
				// Non-sensitive keys stored as-is (already sanitized)
				$sanitized[ $sanitized_key ] = $sanitized_value;
			}
		}

		return $sanitized;
	}

	/**
	 * Get demo option value
	 *
	 * @return string The demo option value.
	 */
	public function get_demo_option(): string {
		return get_option( self::OPTION_DEMO, '' );
	}

	/**
	 * Get API keys with decryption for sensitive values
	 *
	 * @param bool $decrypt_sensitive Whether to decrypt sensitive keys for display.
	 * @return array The API keys array with sensitive values decrypted if requested.
	 */
	public function get_api_keys( bool $decrypt_sensitive = true ): array {
		$api_keys = get_option( self::OPTION_API_KEYS, [] );
		
		if ( ! $decrypt_sensitive ) {
			return $api_keys;
		}

		$sensitive_keys = [ 'google_client_secret', 'api_token', 'secret_key' ];
		$decrypted_keys = [];

		foreach ( $api_keys as $key => $value ) {
			if ( in_array( $key, $sensitive_keys, true ) && ! empty( $value ) ) {
				$decrypted_keys[ $key ] = Security::decrypt_sensitive_data( $value );
			} else {
				$decrypted_keys[ $key ] = $value;
			}
		}

		return $decrypted_keys;
	}

	/**
	 * Handle GA4 OAuth callback with enhanced security
	 *
	 * @return void
	 */
	public function handle_ga4_oauth_callback(): void {
		// Handle OAuth callback
		if ( isset( $_GET['ga4_callback'] ) && $_GET['ga4_callback'] === '1' ) {
			// Enhanced capability verification with logging
			if ( ! Security::verify_capability_with_logging( 'manage_options' ) ) {
				wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
			}

			// Verify state parameter with enhanced security
			$state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
			$stored_state = get_option( 'fp_dms_oauth_state', '' );
			
			// Enhanced nonce verification
			if ( ! wp_verify_nonce( $state, 'ga4_oauth_state' ) || $state !== $stored_state ) {
				// Log security event
				error_log( sprintf(
					'FP Digital Marketing Security: Invalid OAuth state from IP %s, User ID: %d',
					$_SERVER['REMOTE_ADDR'] ?? 'unknown',
					get_current_user_id()
				) );
				
				add_settings_error( 
					'ga4_oauth', 
					'invalid_state', 
					__( 'Errore di sicurezza OAuth. Riprova.', 'fp-digital-marketing' ),
					'error'
				);
				return;
			}

			// Clean up state
			delete_option( 'fp_dms_oauth_state' );

			if ( isset( $_GET['code'] ) ) {
				$oauth = new GoogleOAuth();
				if ( $oauth->exchange_code_for_tokens( $_GET['code'] ) ) {
					add_settings_error( 
						'ga4_oauth', 
						'connection_success', 
						__( 'Connessione a Google Analytics 4 completata con successo!', 'fp-digital-marketing' ),
						'success'
					);
				} else {
					add_settings_error( 
						'ga4_oauth', 
						'connection_error', 
						__( 'Errore durante la connessione a Google Analytics 4.', 'fp-digital-marketing' ),
						'error'
					);
				}
			} elseif ( isset( $_GET['error'] ) ) {
				add_settings_error( 
					'ga4_oauth', 
					'oauth_error', 
					sprintf( 
						__( 'Errore OAuth: %s', 'fp-digital-marketing' ),
						esc_html( $_GET['error'] )
					),
					'error'
				);
			}

			// Redirect to clean URL
			wp_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
			exit;
		}

		// Handle disconnect with enhanced security
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'ga4_disconnect' ) {
			if ( ! Security::verify_capability_with_logging( 'manage_options' ) || 
				 ! Security::verify_nonce_with_logging( 'ga4_disconnect', 'ga4_disconnect_nonce' ) ) {
				wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
			}

			$oauth = new GoogleOAuth();
			if ( $oauth->revoke_access() ) {
				add_settings_error( 
					'ga4_oauth', 
					'disconnect_success', 
					__( 'Disconnessione da Google Analytics 4 completata.', 'fp-digital-marketing' ),
					'success'
				);
			}

			wp_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
			exit;
		}

		// Handle manual sync trigger with enhanced security
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'trigger_manual_sync' ) {
			if ( ! Security::verify_capability_with_logging( 'manage_options' ) || 
				 ! Security::verify_nonce_with_logging( 'trigger_manual_sync', 'sync_nonce' ) ) {
				wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
			}

			$results = SyncEngine::trigger_manual_sync();
			
			if ( $results['status'] === 'success' ) {
				add_settings_error( 
					'manual_sync', 
					'sync_success', 
					sprintf(
						__( 'Sync manuale completato: %d sorgenti, %d record aggiornati in %ss', 'fp-digital-marketing' ),
						$results['sources_count'],
						$results['records_updated'],
						$results['duration']
					),
					'success'
				);
			} else {
				add_settings_error( 
					'manual_sync', 
					'sync_error', 
					sprintf( __( 'Errore sync manuale: %s', 'fp-digital-marketing' ), $results['message'] ),
					'error'
				);
			}

			wp_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
			exit;
		}
	}

	/**
	 * Render sync section description
	 *
	 * @return void
	 */
	public function render_sync_section(): void {
		?>
		<p><?php esc_html_e( 'Configura la sincronizzazione automatica dei dati dalle sorgenti collegate.', 'fp-digital-marketing' ); ?></p>
		<?php
	}

	/**
	 * Render sync settings field
	 *
	 * @return void
	 */
	public function render_sync_settings_field(): void {
		$sync_settings = get_option( self::OPTION_SYNC, [] );
		$sync_enabled = $sync_settings['enable_sync'] ?? false;
		$sync_frequency = $sync_settings['sync_frequency'] ?? 'hourly';
		?>
		<div class="sync-configuration">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Abilita Sync Automatico', 'fp-digital-marketing' ); ?></th>
					<td>
						<label>
							<input 
								type="checkbox" 
								name="<?php echo esc_attr( self::OPTION_SYNC ); ?>[enable_sync]"
								value="1"
								<?php checked( $sync_enabled ); ?>
							/>
							<?php esc_html_e( 'Attiva la sincronizzazione automatica', 'fp-digital-marketing' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Quando attivo, il sistema sincronizzerà automaticamente i dati dalle sorgenti collegate.', 'fp-digital-marketing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Frequenza Sync', 'fp-digital-marketing' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( self::OPTION_SYNC ); ?>[sync_frequency]">
							<option value="every_15_minutes" <?php selected( $sync_frequency, 'every_15_minutes' ); ?>>
								<?php esc_html_e( 'Ogni 15 minuti', 'fp-digital-marketing' ); ?>
							</option>
							<option value="every_30_minutes" <?php selected( $sync_frequency, 'every_30_minutes' ); ?>>
								<?php esc_html_e( 'Ogni 30 minuti', 'fp-digital-marketing' ); ?>
							</option>
							<option value="hourly" <?php selected( $sync_frequency, 'hourly' ); ?>>
								<?php esc_html_e( 'Ogni ora (Demo)', 'fp-digital-marketing' ); ?>
							</option>
							<option value="twice_daily" <?php selected( $sync_frequency, 'twice_daily' ); ?>>
								<?php esc_html_e( 'Due volte al giorno', 'fp-digital-marketing' ); ?>
							</option>
							<option value="daily" <?php selected( $sync_frequency, 'daily' ); ?>>
								<?php esc_html_e( 'Giornaliero', 'fp-digital-marketing' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Frequenza con cui eseguire la sincronizzazione automatica. Per la demo è consigliato "Ogni ora".', 'fp-digital-marketing' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="sync-status" style="margin-top: 20px;">
				<h4><?php esc_html_e( 'Stato Sincronizzazione', 'fp-digital-marketing' ); ?></h4>
				<?php if ( SyncEngine::is_scheduled() && SyncEngine::is_sync_enabled() ) : ?>
					<p><span style="color: #00a32a;">●</span> <?php esc_html_e( 'Sincronizzazione Attiva', 'fp-digital-marketing' ); ?></p>
					<p><strong><?php esc_html_e( 'Prossima sincronizzazione:', 'fp-digital-marketing' ); ?></strong><br>
					<?php echo esc_html( SyncEngine::get_next_scheduled_time() ?? 'Non programmata' ); ?></p>
				<?php elseif ( SyncEngine::is_scheduled() ) : ?>
					<p><span style="color: #dba617;">●</span> <?php esc_html_e( 'Programmata ma Disabilitata', 'fp-digital-marketing' ); ?></p>
				<?php else : ?>
					<p><span style="color: #d63638;">●</span> <?php esc_html_e( 'Non Programmata', 'fp-digital-marketing' ); ?></p>
				<?php endif; ?>

				<form method="post" style="margin-top: 10px;">
					<?php wp_nonce_field( 'trigger_manual_sync', 'sync_nonce' ); ?>
					<input type="hidden" name="action" value="trigger_manual_sync">
					<button type="submit" class="button button-secondary">
						<?php esc_html_e( 'Esegui Sync Manuale', 'fp-digital-marketing' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize sync settings data
	 *
	 * @param mixed $input The input data.
	 * @return array Sanitized sync settings array.
	 */
	public function sanitize_sync_settings( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$sanitized = [];
		
		// Enable sync checkbox
		$sanitized['enable_sync'] = ! empty( $input['enable_sync'] );
		
		// Sync frequency
		$allowed_frequencies = [ 'every_15_minutes', 'every_30_minutes', 'hourly', 'twice_daily', 'daily' ];
		$sanitized['sync_frequency'] = in_array( $input['sync_frequency'] ?? '', $allowed_frequencies, true ) 
			? $input['sync_frequency'] 
			: 'hourly';

		// If sync settings changed, reschedule the sync
		$current_settings = get_option( self::OPTION_SYNC, [] );
		if ( $sanitized !== $current_settings ) {
			// Unschedule and reschedule with new settings
			SyncEngine::unschedule_sync();
			if ( $sanitized['enable_sync'] ) {
				SyncEngine::schedule_sync();
			}
		}

		return $sanitized;
	}

	/**
	 * Get sync settings
	 *
	 * @return array The sync settings array.
	 */
	public function get_sync_settings(): array {
		return get_option( self::OPTION_SYNC, [] );
	}
}