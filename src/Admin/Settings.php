<?php
/**
 * Settings Page Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

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
	 * Demo option name
	 */
	private const OPTION_DEMO = 'fp_digital_marketing_demo_option';

	/**
	 * API Keys option name
	 */
	private const OPTION_API_KEYS = 'fp_digital_marketing_api_keys';

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
		?>
		<div class="api-keys-placeholder">
			<p class="description">
				<strong><?php esc_html_e( 'Sezione in sviluppo:', 'fp-digital-marketing' ); ?></strong><br>
				<?php esc_html_e( 'Qui saranno aggiunte le configurazioni per:', 'fp-digital-marketing' ); ?>
			</p>
			<ul style="margin-left: 20px;">
				<li><?php esc_html_e( '• Google Analytics API', 'fp-digital-marketing' ); ?></li>
				<li><?php esc_html_e( '• Facebook/Meta Business API', 'fp-digital-marketing' ); ?></li>
				<li><?php esc_html_e( '• Google Ads API', 'fp-digital-marketing' ); ?></li>
				<li><?php esc_html_e( '• Altri servizi di marketing', 'fp-digital-marketing' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Sanitize API keys data
	 *
	 * @param mixed $input The input data.
	 * @return array Sanitized API keys array.
	 */
	public function sanitize_api_keys( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$sanitized = [];
		foreach ( $input as $key => $value ) {
			$sanitized[ sanitize_key( $key ) ] = sanitize_text_field( $value );
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
	 * Get API keys
	 *
	 * @return array The API keys array.
	 */
	public function get_api_keys(): array {
		return get_option( self::OPTION_API_KEYS, [] );
	}
}