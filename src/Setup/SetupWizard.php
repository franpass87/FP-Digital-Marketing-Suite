<?php
/**
 * Installation and Setup Wizard
 *
 * Handles first-time plugin setup and configuration
 *
 * @package FP_Digital_Marketing_Suite
 * @since 1.0.0
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Setup;

use FP\DigitalMarketing\Helpers\PerformanceCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installation Wizard class
 */
class SetupWizard {

	/**
	 * Wizard admin page slug.
	 */
	private const MENU_SLUG = 'fp-dms-setup';

	/**
	 * Total number of wizard steps.
	 */
	private const TOTAL_STEPS = 5;

	/**
	 * Initialize the setup wizard
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_setup_page' ) );
		add_action( 'admin_init', array( $this, 'setup_redirect' ) );
		add_action( 'wp_ajax_fp_dms_setup_step', array( $this, 'handle_setup_step' ) );
	}

	/**
	 * Redirect to setup wizard on first activation
	 */
	public function setup_redirect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$needs_setup = ! SettingsManager::is_wizard_completed();

		// Only redirect on activation and if setup not completed
		if ( get_transient( 'fp_dms_activation_redirect' ) && $needs_setup ) {
			delete_transient( 'fp_dms_activation_redirect' );

			// Don't redirect if activating multiple plugins
			if ( isset( $_GET['activate-multi'] ) ) {
				return;
			}

			$redirect_url = add_query_arg(
				array( 'page' => self::MENU_SLUG ),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Add setup wizard page to admin menu
	 */
	public function add_setup_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( SettingsManager::is_wizard_completed() && ! SettingsManager::is_wizard_menu_enabled() ) {
			// Wizard finished and menu already hidden.
			return;
		}

		$hook_suffix = add_dashboard_page(
			__( 'FP Digital Marketing Suite Setup', 'fp-digital-marketing' ),
			__( 'Setup Wizard', 'fp-digital-marketing' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'setup_wizard_page' )
		);

		SettingsManager::enable_wizard_menu( self::MENU_SLUG );

		add_action( 'load-' . $hook_suffix, array( $this, 'maybe_hide_completed_notice' ) );
	}

	/**
	 * Hide menu entry if wizard already completed.
	 *
	 * @return void
	 */
	public function maybe_hide_completed_notice(): void {
		if ( ! SettingsManager::is_wizard_completed() ) {
			return;
		}

		SettingsManager::disable_wizard_menu( self::MENU_SLUG );
	}

	/**
	 * Display the setup wizard page
	 */
	public function setup_wizard_page() {
		if ( SettingsManager::is_wizard_completed() ) {
			$this->display_setup_completed();
			return;
		}

		$current_step = isset( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;
		$current_step = max( 1, min( self::TOTAL_STEPS, $current_step ) );

		$this->mark_progress( $current_step );

		?>
		<div class="wrap fp-dms-setup-wizard">
			<h1><?php esc_html_e( 'FP Digital Marketing Suite Setup', 'fp-digital-marketing' ); ?></h1>
			
			<div class="setup-progress">
				<?php $this->display_progress_bar( $current_step ); ?>
			</div>
			
			<div class="setup-content">
				<?php
				switch ( $current_step ) {
					case 1:
						$this->setup_step_welcome();
						break;
					case 2:
						$this->setup_step_analytics();
						break;
					case 3:
						$this->setup_step_seo();
						break;
					case 4:
						$this->setup_step_performance();
						break;
					case 5:
						$this->setup_step_complete();
						break;
				}
				?>
			</div>
		</div>
		
		<style>
		.fp-dms-setup-wizard {
			max-width: 800px;
			margin: 20px auto;
		}
		.setup-progress {
			margin: 20px 0;
			background: #f1f1f1;
			border-radius: 5px;
			height: 10px;
		}
		.setup-progress .progress-bar {
			background: #0073aa;
			height: 100%;
			border-radius: 5px;
			transition: width 0.3s ease;
		}
		.setup-content {
			background: #fff;
			padding: 30px;
			border: 1px solid #ddd;
			border-radius: 5px;
			box-shadow: 0 2px 5px rgba(0,0,0,0.1);
		}
		.setup-form {
			margin: 20px 0;
		}
		.setup-form label {
			display: block;
			margin: 15px 0 5px;
			font-weight: bold;
		}
		.setup-form input[type="text"],
		.setup-form textarea {
			width: 100%;
			padding: 8px;
			border: 1px solid #ddd;
			border-radius: 3px;
		}
		.setup-buttons {
			margin-top: 30px;
			text-align: right;
		}
		.setup-buttons .button {
			margin-left: 10px;
		}
		.feature-list {
			columns: 2;
			column-gap: 30px;
		}
		.feature-list li {
			margin-bottom: 8px;
			break-inside: avoid;
		}
		</style>
		
		<script>
		jQuery(document).ready(function($) {
			$('.setup-form').on('submit', function(e) {
				e.preventDefault();
				
				var form = $(this);
				var step = form.data('step');
				var formData = form.serialize();
				
				$.post(ajaxurl, {
					action: 'fp_dms_setup_step',
					step: step,
					nonce: '<?php echo wp_create_nonce( 'fp_dms_setup' ); ?>',
					data: formData
				}, function(response) {
					if (response.success) {
						window.location.href = response.data.redirect;
					} else {
						alert('Setup failed: ' + response.data);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Display progress bar
	 */
	private function display_progress_bar( $current_step ) {
		$progress = ( $current_step / self::TOTAL_STEPS ) * 100;
		echo '<div class="progress-bar" style="width: ' . esc_attr( $progress ) . '%"></div>';
	}

	/**
	 * Setup step 1: Welcome
	 */
	private function setup_step_welcome() {
		?>
		<h2><?php esc_html_e( 'Welcome to FP Digital Marketing Suite!', 'fp-digital-marketing' ); ?></h2>
		
		<p><?php esc_html_e( 'Thank you for choosing FP Digital Marketing Suite. This setup wizard will help you configure the plugin for your needs.', 'fp-digital-marketing' ); ?></p>
		
		<h3><?php esc_html_e( 'What you\'ll get:', 'fp-digital-marketing' ); ?></h3>
		<ul class="feature-list">
			<li><?php esc_html_e( 'Complete client management system', 'fp-digital-marketing' ); ?></li>
			<li><?php esc_html_e( 'Google Analytics 4 integration', 'fp-digital-marketing' ); ?></li>
			<li><?php esc_html_e( 'Google Ads integration', 'fp-digital-marketing' ); ?></li>
			<li><?php esc_html_e( 'Google Search Console integration', 'fp-digital-marketing' ); ?></li>
			<li><?php esc_html_e( 'Microsoft Clarity integration', 'fp-digital-marketing' ); ?></li>
			<li><?php esc_html_e( 'Advanced SEO tools', 'fp-digital-marketing' ); ?></li>
			<li><?php esc_html_e( 'Performance monitoring', 'fp-digital-marketing' ); ?></li>
			<li><?php esc_html_e( 'Marketing automation', 'fp-digital-marketing' ); ?></li>
			<li><?php esc_html_e( 'Alert and notification system', 'fp-digital-marketing' ); ?></li>
			<li><?php esc_html_e( 'Comprehensive analytics dashboard', 'fp-digital-marketing' ); ?></li>
		</ul>
		
		<div class="setup-buttons">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-dms-setup&step=2' ) ); ?>" 
				class="button button-primary">
				<?php esc_html_e( 'Get Started', 'fp-digital-marketing' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Setup step 2: Analytics Configuration
	 */
	private function setup_step_analytics() {
		?>
		<h2><?php esc_html_e( 'Analytics Integration', 'fp-digital-marketing' ); ?></h2>
		
		<p><?php esc_html_e( 'Configure your analytics integrations. You can skip any of these and set them up later.', 'fp-digital-marketing' ); ?></p>
		
		<form class="setup-form" data-step="2">
			<h3><?php esc_html_e( 'Google Analytics 4', 'fp-digital-marketing' ); ?></h3>
			<label for="ga4_measurement_id"><?php esc_html_e( 'Measurement ID (G-XXXXXXXXXX):', 'fp-digital-marketing' ); ?></label>
			<input type="text" id="ga4_measurement_id" name="ga4_measurement_id" placeholder="G-XXXXXXXXXX">
			
			<h3><?php esc_html_e( 'Google Ads', 'fp-digital-marketing' ); ?></h3>
			<label for="google_ads_id"><?php esc_html_e( 'Conversion ID:', 'fp-digital-marketing' ); ?></label>
			<input type="text" id="google_ads_id" name="google_ads_id" placeholder="AW-XXXXXXXXXX">
			
			<h3><?php esc_html_e( 'Microsoft Clarity', 'fp-digital-marketing' ); ?></h3>
			<label for="clarity_project_id"><?php esc_html_e( 'Project ID:', 'fp-digital-marketing' ); ?></label>
			<input type="text" id="clarity_project_id" name="clarity_project_id" placeholder="abcdefghij">
			
			<div class="setup-buttons">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-dms-setup&step=1' ) ); ?>" 
					class="button"><?php esc_html_e( 'Previous', 'fp-digital-marketing' ); ?></a>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Continue', 'fp-digital-marketing' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Setup step 3: SEO Configuration
	 */
	private function setup_step_seo() {
		?>
		<h2><?php esc_html_e( 'SEO Configuration', 'fp-digital-marketing' ); ?></h2>
		
		<form class="setup-form" data-step="3">
			<label for="default_meta_description"><?php esc_html_e( 'Default Meta Description:', 'fp-digital-marketing' ); ?></label>
			<textarea id="default_meta_description" name="default_meta_description" rows="3" 
						placeholder="<?php esc_attr_e( 'Enter default meta description for your site', 'fp-digital-marketing' ); ?>"></textarea>
			
			<label>
				<input type="checkbox" name="enable_xml_sitemap" value="1" checked>
				<?php esc_html_e( 'Enable XML Sitemap generation', 'fp-digital-marketing' ); ?>
			</label>
			
			<label>
				<input type="checkbox" name="enable_schema_markup" value="1" checked>
				<?php esc_html_e( 'Enable Schema markup', 'fp-digital-marketing' ); ?>
			</label>
			
			<div class="setup-buttons">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-dms-setup&step=2' ) ); ?>" 
					class="button"><?php esc_html_e( 'Previous', 'fp-digital-marketing' ); ?></a>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Continue', 'fp-digital-marketing' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Setup step 4: Performance Configuration
	 */
	private function setup_step_performance() {
		?>
		<h2><?php esc_html_e( 'Performance Settings', 'fp-digital-marketing' ); ?></h2>
		
		<form class="setup-form" data-step="4">
			<label>
				<input type="checkbox" name="enable_caching" value="1" checked>
				<?php esc_html_e( 'Enable performance caching', 'fp-digital-marketing' ); ?>
			</label>
			
			<label>
				<input type="checkbox" name="enable_core_web_vitals" value="1" checked>
				<?php esc_html_e( 'Enable Core Web Vitals monitoring', 'fp-digital-marketing' ); ?>
			</label>
			
			<label>
				<input type="checkbox" name="enable_email_alerts" value="1">
				<?php esc_html_e( 'Enable email alerts for performance issues', 'fp-digital-marketing' ); ?>
			</label>
			
			<div class="setup-buttons">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-dms-setup&step=3' ) ); ?>" 
					class="button"><?php esc_html_e( 'Previous', 'fp-digital-marketing' ); ?></a>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Complete Setup', 'fp-digital-marketing' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Setup step 5: Complete
	 */
	private function setup_step_complete() {
		// Mark setup as completed
		$this->mark_wizard_completed();

		?>
		<h2><?php esc_html_e( 'Setup Complete!', 'fp-digital-marketing' ); ?></h2>
		
		<p><?php esc_html_e( 'Congratulations! FP Digital Marketing Suite has been successfully configured.', 'fp-digital-marketing' ); ?></p>
		
		<h3><?php esc_html_e( 'Next Steps:', 'fp-digital-marketing' ); ?></h3>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-dashboard' ) ); ?>">
				<?php esc_html_e( 'Visit your Analytics Dashboard', 'fp-digital-marketing' ); ?>
			</a></li>
			<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cliente' ) ); ?>">
				<?php esc_html_e( 'Add your first client', 'fp-digital-marketing' ); ?>
			</a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-settings' ) ); ?>">
				<?php esc_html_e( 'Configure additional settings', 'fp-digital-marketing' ); ?>
			</a></li>
		</ul>
		
		<div class="setup-buttons">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-dashboard' ) ); ?>"
				class="button button-primary button-large">
				<?php esc_html_e( 'Go to Dashboard', 'fp-digital-marketing' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Display setup completed message
	 */
	private function display_setup_completed() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Setup Already Completed', 'fp-digital-marketing' ); ?></h1>
			
			<div class="notice notice-info">
				<p><?php esc_html_e( 'FP Digital Marketing Suite has already been set up.', 'fp-digital-marketing' ); ?></p>
			</div>
			
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-dashboard' ) ); ?>"
					class="button button-primary">
					<?php esc_html_e( 'Go to Dashboard', 'fp-digital-marketing' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-settings' ) ); ?>"
					class="button">
					<?php esc_html_e( 'Settings', 'fp-digital-marketing' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle AJAX setup step submissions
	 */
	public function handle_setup_step() {
		check_ajax_referer( 'fp_dms_setup', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'fp-digital-marketing' ) );
		}

		if ( empty( $_POST['step'] ) || empty( $_POST['data'] ) ) {
			wp_send_json_error( __( 'Invalid request', 'fp-digital-marketing' ) );
		}

		$step = intval( $_POST['step'] );

		if ( $step < 2 || $step > self::TOTAL_STEPS ) {
			wp_send_json_error( __( 'Unknown setup step.', 'fp-digital-marketing' ) );
		}

		parse_str( wp_unslash( (string) $_POST['data'] ), $form_data );

		if ( ! is_array( $form_data ) ) {
			wp_send_json_error( __( 'Invalid form data.', 'fp-digital-marketing' ) );
		}

		switch ( $step ) {
			case 2:
				$this->save_analytics_settings( $form_data );
				break;
			case 3:
				$this->save_seo_settings( $form_data );
				break;
			case 4:
				$this->save_performance_settings( $form_data );
				break;
			case self::TOTAL_STEPS:
				$this->mark_wizard_completed();
				break;
		}

		$this->mark_step_complete( $step );

		$next_step = $step + 1;
		$next_step = min( self::TOTAL_STEPS, $next_step );

		$redirect_url = add_query_arg(
			array(
				'page' => self::MENU_SLUG,
				'step' => $next_step,
			),
			admin_url( 'admin.php' )
		);

		wp_send_json_success( array( 'redirect' => $redirect_url ) );
	}

	/**
	 * Save analytics settings
	 */
	private function save_analytics_settings( array $data ): void {
		$api_keys = SettingsManager::get_option( SettingsManager::OPTION_API_KEYS, array() );

		if ( ! is_array( $api_keys ) ) {
			$api_keys = array();
		}

		if ( ! empty( $data['ga4_measurement_id'] ) ) {
			$api_keys['ga4_property_id'] = sanitize_text_field( (string) $data['ga4_measurement_id'] );
		}

		if ( ! empty( $data['google_ads_id'] ) ) {
			$api_keys['google_ads_id'] = sanitize_text_field( (string) $data['google_ads_id'] );
		}

		if ( ! empty( $data['clarity_project_id'] ) ) {
			$api_keys['clarity_project_id'] = sanitize_text_field( (string) $data['clarity_project_id'] );
		}

		SettingsManager::update_option( SettingsManager::OPTION_API_KEYS, $api_keys );
	}

	/**
	 * Save SEO settings
	 */
	private function save_seo_settings( array $data ): void {
		$settings = array(
			'default_meta_description' => sanitize_textarea_field( $data['default_meta_description'] ?? '' ),
			'enable_xml_sitemap'       => isset( $data['enable_xml_sitemap'] ),
			'enable_schema_markup'     => isset( $data['enable_schema_markup'] ),
		);

		$current_settings = get_option( 'fp_digital_marketing_seo_settings', array() );

		if ( ! is_array( $current_settings ) ) {
			$current_settings = array();
		}

		update_option( 'fp_digital_marketing_seo_settings', array_merge( $current_settings, $settings ) );
	}

	/**
	 * Save performance settings
	 */
	private function save_performance_settings( array $data ): void {
		$cache_settings = array(
			'enabled'           => isset( $data['enable_caching'] ),
			'benchmark_enabled' => isset( $data['enable_core_web_vitals'] ),
		);

		PerformanceCache::update_cache_settings( $cache_settings );

		$email_settings = get_option( 'fp_digital_marketing_email_settings', array() );

		if ( ! is_array( $email_settings ) ) {
			$email_settings = array();
		}

		$email_settings['alerts_enabled'] = isset( $data['enable_email_alerts'] );

		update_option( 'fp_digital_marketing_email_settings', $email_settings );
	}

	/**
	 * Persist wizard completion metadata.
	 *
	 * @return void
	 */
	private function mark_wizard_completed(): void {
		$completion_payload = array(
			'completed'    => true,
			'completed_at' => current_time( 'timestamp' ),
			'completed_by' => get_current_user_id(),
		);

		SettingsManager::update_option( SettingsManager::OPTION_WIZARD_COMPLETED, $completion_payload );
		SettingsManager::disable_wizard_menu( self::MENU_SLUG );

		// Maintain backwards compatibility with legacy option names.
		update_option( 'fp_dms_setup_completed', true );
		update_option( 'fp_dms_setup_completed_time', $completion_payload['completed_at'] );
	}

	/**
	 * Track wizard progress.
	 *
	 * @param int $current_step Current step number.
	 * @return void
	 */
	private function mark_progress( int $current_step ): void {
		$progress = SettingsManager::get_option( SettingsManager::OPTION_WIZARD_PROGRESS, array() );

		if ( ! is_array( $progress ) ) {
			$progress = array();
		}

		$progress['current_step'] = $current_step;
		$progress['updated_at']   = current_time( 'timestamp' );
		$progress['user_id']      = get_current_user_id();

		SettingsManager::update_option( SettingsManager::OPTION_WIZARD_PROGRESS, $progress );
	}

	/**
	 * Mark a step as complete.
	 *
	 * @param int $step Step number that has been completed.
	 * @return void
	 */
	private function mark_step_complete( int $step ): void {
		$progress = SettingsManager::get_option( SettingsManager::OPTION_WIZARD_PROGRESS, array() );

		if ( ! is_array( $progress ) ) {
			$progress = array();
		}

		$progress['last_completed_step'] = $step;
		$progress['updated_at']          = current_time( 'timestamp' );
		$progress['user_id']             = get_current_user_id();

		SettingsManager::update_option( SettingsManager::OPTION_WIZARD_PROGRESS, $progress );
	}
}