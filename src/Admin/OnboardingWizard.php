<?php
/**
 * Onboarding Wizard for FP Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\DataSources\GoogleOAuth;
use FP\DigitalMarketing\DataSources\GoogleAnalytics4;
use FP\DigitalMarketing\Helpers\DataSources;
use FP\DigitalMarketing\Helpers\MetricsSchema;
use FP\DigitalMarketing\Helpers\Security;

/**
 * Onboarding Wizard class for first-time setup
 */
class OnboardingWizard {

	/**
	 * Page slug for the wizard
	 */
	private const PAGE_SLUG = 'fp-digital-marketing-onboarding';

	/**
	 * Option name for wizard progress
	 */
	private const WIZARD_PROGRESS_OPTION = 'fp_digital_marketing_wizard_progress';

	/**
	 * Option name for wizard completion status
	 */
	private const WIZARD_COMPLETED_OPTION = 'fp_digital_marketing_wizard_completed';

	/**
	 * Nonce action for wizard forms
	 */
	private const NONCE_ACTION = 'fp_digital_marketing_wizard_nonce';

	/**
	 * Total number of wizard steps
	 */
	private const TOTAL_STEPS = 5;

	/**
	 * Initialize the onboarding wizard
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_wizard_actions' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_wizard_scripts' ] );
		add_action( 'admin_notices', [ $this, 'show_wizard_notice' ] );
	}

	/**
	 * Add admin menu page for the wizard
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Only show if wizard is not completed
		if ( get_option( self::WIZARD_COMPLETED_OPTION, false ) ) {
			return;
		}

		add_menu_page(
			__( 'FP Digital Marketing Setup', 'fp-digital-marketing' ),
			__( 'DM Setup', 'fp-digital-marketing' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_wizard_page' ],
			'dashicons-admin-tools',
			2
		);
	}

	/**
	 * Show admin notice to encourage wizard completion
	 *
	 * @return void
	 */
	public function show_wizard_notice(): void {
		if ( get_option( self::WIZARD_COMPLETED_OPTION, false ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->id === 'toplevel_page_' . self::PAGE_SLUG ) {
			return;
		}

		$wizard_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		
		echo '<div class="notice notice-info is-dismissible">';
		echo '<p>';
		printf(
			__( 'Welcome to FP Digital Marketing Suite! Complete the <a href="%s">setup wizard</a> to get started.', 'fp-digital-marketing' ),
			esc_url( $wizard_url )
		);
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Handle wizard form submissions and navigation
	 *
	 * @return void
	 */
	public function handle_wizard_actions(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non autorizzato', 'fp-digital-marketing' ) );
		}

		// Handle POST requests
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			$this->handle_wizard_form_submission();
		}

		// Handle GA4 OAuth callback
		if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) ) {
			$this->handle_ga4_oauth_callback();
		}
	}

	/**
	 * Handle wizard form submission
	 *
	 * @return void
	 */
	private function handle_wizard_form_submission(): void {
		if ( ! Security::verify_nonce_with_logging( self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Nonce verification failed', 'fp-digital-marketing' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['wizard_action'] ?? '' ) );
		$step = intval( $_POST['current_step'] ?? 1 );

		switch ( $action ) {
			case 'next_step':
				$this->process_step_data( $step );
				$this->go_to_step( $step + 1 );
				break;

			case 'previous_step':
				$this->go_to_step( $step - 1 );
				break;

			case 'save_and_continue':
				$this->process_step_data( $step );
				$this->go_to_step( $step + 1 );
				break;

			case 'complete_wizard':
				$this->process_step_data( $step );
				$this->complete_wizard();
				break;

			case 'skip_wizard':
				$this->skip_wizard();
				break;
		}
	}

	/**
	 * Process and save step-specific data
	 *
	 * @param int $step Current step number.
	 * @return void
	 */
	private function process_step_data( int $step ): void {
		$progress = get_option( self::WIZARD_PROGRESS_OPTION, [] );

		switch ( $step ) {
			case 1:
				// Welcome step - no data to save
				break;

			case 2:
				// Services connection step
				$selected_services = array_map( 'sanitize_text_field', $_POST['selected_services'] ?? [] );
				$progress['services'] = $selected_services;
				break;

			case 3:
				// Metrics selection step
				$selected_metrics = array_map( 'sanitize_text_field', $_POST['selected_metrics'] ?? [] );
				$progress['metrics'] = $selected_metrics;
				break;

			case 4:
				// Report configuration step
				$report_frequency = sanitize_text_field( $_POST['report_frequency'] ?? 'weekly' );
				$report_recipients = sanitize_email( $_POST['report_recipients'] ?? '' );
				$progress['reports'] = [
					'frequency' => $report_frequency,
					'recipients' => $report_recipients,
				];
				break;

			case 5:
				// Feedback step
				$feedback = sanitize_textarea_field( $_POST['user_feedback'] ?? '' );
				$rating = intval( $_POST['wizard_rating'] ?? 5 );
				$progress['feedback'] = [
					'feedback' => $feedback,
					'rating' => $rating,
				];
				break;
		}

		update_option( self::WIZARD_PROGRESS_OPTION, $progress );
	}

	/**
	 * Navigate to a specific wizard step
	 *
	 * @param int $step Step number to navigate to.
	 * @return void
	 */
	private function go_to_step( int $step ): void {
		$step = max( 1, min( $step, self::TOTAL_STEPS ) );
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&step=' . $step );
		wp_redirect( $url );
		exit;
	}

	/**
	 * Complete the wizard and apply all settings
	 *
	 * @return void
	 */
	private function complete_wizard(): void {
		$progress = get_option( self::WIZARD_PROGRESS_OPTION, [] );

		// Apply service connections
		if ( ! empty( $progress['services'] ) ) {
			$this->apply_service_settings( $progress['services'] );
		}

		// Apply metric selections
		if ( ! empty( $progress['metrics'] ) ) {
			$this->apply_metric_settings( $progress['metrics'] );
		}

		// Apply report configurations
		if ( ! empty( $progress['reports'] ) ) {
			$this->apply_report_settings( $progress['reports'] );
		}

		// Save feedback
		if ( ! empty( $progress['feedback'] ) ) {
			$this->save_user_feedback( $progress['feedback'] );
		}

		// Mark wizard as completed
		update_option( self::WIZARD_COMPLETED_OPTION, true );
		delete_option( self::WIZARD_PROGRESS_OPTION );

		// Redirect to success page
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&completed=1' );
		wp_redirect( $url );
		exit;
	}

	/**
	 * Skip the wizard and mark as completed
	 *
	 * @return void
	 */
	private function skip_wizard(): void {
		update_option( self::WIZARD_COMPLETED_OPTION, true );
		delete_option( self::WIZARD_PROGRESS_OPTION );

		// Redirect to main reports page
		$url = admin_url( 'admin.php?page=fp-digital-marketing-reports' );
		wp_redirect( $url );
		exit;
	}

	/**
	 * Apply service connection settings
	 *
	 * @param array $services Selected services.
	 * @return void
	 */
	private function apply_service_settings( array $services ): void {
		$api_keys = get_option( 'fp_digital_marketing_api_keys', [] );

		foreach ( $services as $service ) {
			if ( $service === 'google_analytics_4' ) {
				// GA4 OAuth is handled separately via OAuth flow
				$api_keys['google_analytics_4']['enabled'] = true;
			}
		}

		update_option( 'fp_digital_marketing_api_keys', $api_keys );
	}

	/**
	 * Apply metric selection settings
	 *
	 * @param array $metrics Selected metrics.
	 * @return void
	 */
	private function apply_metric_settings( array $metrics ): void {
		$sync_settings = get_option( 'fp_digital_marketing_sync_settings', [] );
		$sync_settings['enabled_metrics'] = $metrics;
		update_option( 'fp_digital_marketing_sync_settings', $sync_settings );
	}

	/**
	 * Apply report configuration settings
	 *
	 * @param array $report_config Report configuration.
	 * @return void
	 */
	private function apply_report_settings( array $report_config ): void {
		// This would integrate with the existing ReportScheduler
		// For now, we'll save the settings for future use
		update_option( 'fp_digital_marketing_report_config', $report_config );
	}

	/**
	 * Save user feedback from the wizard
	 *
	 * @param array $feedback User feedback data.
	 * @return void
	 */
	private function save_user_feedback( array $feedback ): void {
		$existing_feedback = get_option( 'fp_digital_marketing_user_feedback', [] );
		$existing_feedback[] = array_merge( $feedback, [
			'timestamp' => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
		] );
		update_option( 'fp_digital_marketing_user_feedback', $existing_feedback );
	}

	/**
	 * Handle GA4 OAuth callback
	 *
	 * @return void
	 */
	private function handle_ga4_oauth_callback(): void {
		// Delegate to existing OAuth handler
		$oauth = new GoogleOAuth();
		if ( $oauth->handle_callback() ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>' . esc_html__( 'Google Analytics 4 connected successfully!', 'fp-digital-marketing' ) . '</p>';
				echo '</div>';
			} );
		}
	}

	/**
	 * Enqueue wizard-specific scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_wizard_scripts( string $hook ): void {
		$expected_hook = 'toplevel_page_' . self::PAGE_SLUG;
		if ( $hook !== $expected_hook ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		
		wp_add_inline_style( 'wp-admin', $this->get_wizard_css() );
		wp_add_inline_script( 'jquery', $this->get_wizard_js() );
	}

	/**
	 * Get wizard CSS styles
	 *
	 * @return string CSS styles for the wizard.
	 */
	private function get_wizard_css(): string {
		return '
			.fp-wizard-container {
				max-width: 800px;
				margin: 20px auto;
				background: #fff;
				border-radius: 8px;
				box-shadow: 0 2px 10px rgba(0,0,0,0.1);
				overflow: hidden;
			}
			
			.fp-wizard-header {
				background: linear-gradient(135deg, #0073aa, #00a0d2);
				color: #fff;
				padding: 30px;
				text-align: center;
			}
			
			.fp-wizard-progress {
				display: flex;
				justify-content: space-between;
				margin: 20px 0;
				padding: 0 20px;
			}
			
			.fp-wizard-step {
				flex: 1;
				text-align: center;
				padding: 10px;
				border-radius: 20px;
				margin: 0 5px;
				background: #f1f1f1;
				transition: all 0.3s ease;
			}
			
			.fp-wizard-step.active {
				background: #0073aa;
				color: #fff;
			}
			
			.fp-wizard-step.completed {
				background: #00a32a;
				color: #fff;
			}
			
			.fp-wizard-content {
				padding: 40px;
				min-height: 400px;
			}
			
			.fp-wizard-navigation {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px 40px;
				border-top: 1px solid #e1e1e1;
				background: #f9f9f9;
			}
			
			.fp-service-card {
				border: 2px solid #ddd;
				border-radius: 8px;
				padding: 20px;
				margin: 10px 0;
				cursor: pointer;
				transition: all 0.3s ease;
			}
			
			.fp-service-card:hover,
			.fp-service-card.selected {
				border-color: #0073aa;
				background: #f0f8ff;
			}
			
			.fp-metric-checkbox {
				margin: 10px 0;
				padding: 10px;
				border-radius: 4px;
				background: #f9f9f9;
			}
			
			.fp-wizard-success {
				text-align: center;
				padding: 60px 40px;
			}
			
			.fp-wizard-success .dashicons {
				font-size: 64px;
				color: #00a32a;
				margin-bottom: 20px;
			}
		';
	}

	/**
	 * Get wizard JavaScript
	 *
	 * @return string JavaScript for the wizard.
	 */
	private function get_wizard_js(): string {
		return '
			jQuery(document).ready(function($) {
				console.log("FP Digital Marketing Wizard JS loaded");
				console.log("jQuery version:", $.fn.jquery);
				console.log("Service cards found:", $(".fp-service-card").length);
				
				// Service card selection
				$(".fp-service-card").click(function() {
					console.log("Service card clicked");
					$(this).toggleClass("selected");
					var checkbox = $(this).find("input[type=checkbox]");
					var wasChecked = checkbox.prop("checked");
					checkbox.prop("checked", !wasChecked);
					console.log("Checkbox toggled from " + wasChecked + " to " + (!wasChecked));
					
					// Update debug info
					var selectedCount = $("input[name=\"selected_services[]\"]:checked").length;
					console.log("Total selected services:", selectedCount);
				});
				
				// Form validation
				$(".fp-wizard-form").submit(function(e) {
					console.log("Form submission started");
					var currentStep = parseInt($(this).find("input[name=current_step]").val());
					console.log("Current step:", currentStep);
					
					if (currentStep === 2) {
						// Validate service selection
						var selectedServices = $("input[name=\"selected_services[]\"]:checked");
						console.log("Selected services count:", selectedServices.length);
						
						if (selectedServices.length === 0) {
							console.log("No services selected, showing alert");
							alert("' . esc_js( __( 'Please select at least one service to connect.', 'fp-digital-marketing' ) ) . '");
							e.preventDefault();
							return false;
						} else {
							console.log("Services selected, allowing form submission");
							var serviceIds = [];
							selectedServices.each(function() {
								serviceIds.push($(this).val());
							});
							console.log("Selected service IDs:", serviceIds);
						}
					}
					
					if (currentStep === 3) {
						// Validate metric selection
						if ($("input[name=\"selected_metrics[]\"]:checked").length === 0) {
							alert("' . esc_js( __( 'Please select at least one metric to track.', 'fp-digital-marketing' ) ) . '");
							e.preventDefault();
							return false;
						}
					}
				});
				
				// Add debug button for testing
				$("body").append("<div style=\"position: fixed; top: 10px; right: 10px; background: #000; color: #fff; padding: 10px; z-index: 9999;\"><button id=\"fp-debug-btn\" style=\"color: #fff; background: #333; border: none; padding: 5px;\">Debug Info</button></div>");
				$("#fp-debug-btn").click(function() {
					var selectedCount = $("input[name=\"selected_services[]\"]:checked").length;
					var totalCards = $(".fp-service-card").length;
					alert("Debug Info:\\nTotal service cards: " + totalCards + "\\nSelected services: " + selectedCount);
				});
			});
		';
	}

	/**
	 * Render the main wizard page
	 *
	 * @return void
	 */
	public function render_wizard_page(): void {
		$current_step = intval( $_GET['step'] ?? 1 );
		$current_step = max( 1, min( $current_step, self::TOTAL_STEPS ) );

		// Check if wizard was just completed
		if ( isset( $_GET['completed'] ) ) {
			$this->render_completion_page();
			return;
		}

		echo '<div class="wrap">';
		echo '<div class="fp-wizard-container">';
		
		$this->render_wizard_header( $current_step );
		$this->render_wizard_progress( $current_step );
		
		echo '<div class="fp-wizard-content">';
		$this->render_wizard_step( $current_step );
		echo '</div>';
		
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render wizard header
	 *
	 * @param int $current_step Current step number.
	 * @return void
	 */
	private function render_wizard_header( int $current_step ): void {
		echo '<div class="fp-wizard-header">';
		echo '<h1>' . esc_html__( 'FP Digital Marketing Suite Setup', 'fp-digital-marketing' ) . '</h1>';
		echo '<p>' . esc_html__( 'Welcome! Let\'s get your digital marketing tracking set up in just a few steps.', 'fp-digital-marketing' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render wizard progress indicator
	 *
	 * @param int $current_step Current step number.
	 * @return void
	 */
	private function render_wizard_progress( int $current_step ): void {
		$steps = [
			1 => __( 'Welcome', 'fp-digital-marketing' ),
			2 => __( 'Services', 'fp-digital-marketing' ),
			3 => __( 'Metrics', 'fp-digital-marketing' ),
			4 => __( 'Reports', 'fp-digital-marketing' ),
			5 => __( 'Complete', 'fp-digital-marketing' ),
		];

		echo '<div class="fp-wizard-progress">';
		foreach ( $steps as $step_num => $step_label ) {
			$class = 'fp-wizard-step';
			if ( $step_num === $current_step ) {
				$class .= ' active';
			} elseif ( $step_num < $current_step ) {
				$class .= ' completed';
			}
			
			echo '<div class="' . esc_attr( $class ) . '">';
			echo '<strong>' . esc_html( $step_num ) . '</strong><br>';
			echo '<span>' . esc_html( $step_label ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Render specific wizard step content
	 *
	 * @param int $step Step number to render.
	 * @return void
	 */
	private function render_wizard_step( int $step ): void {
		switch ( $step ) {
			case 1:
				$this->render_welcome_step();
				break;
			case 2:
				$this->render_services_step();
				break;
			case 3:
				$this->render_metrics_step();
				break;
			case 4:
				$this->render_reports_step();
				break;
			case 5:
				$this->render_feedback_step();
				break;
		}
	}

	/**
	 * Render welcome step
	 *
	 * @return void
	 */
	private function render_welcome_step(): void {
		echo '<h2>' . esc_html__( 'Welcome to FP Digital Marketing Suite!', 'fp-digital-marketing' ) . '</h2>';
		echo '<p>' . esc_html__( 'This setup wizard will help you configure your digital marketing tracking in just a few minutes.', 'fp-digital-marketing' ) . '</p>';
		
		echo '<h3>' . esc_html__( 'What you\'ll set up:', 'fp-digital-marketing' ) . '</h3>';
		echo '<ul style="font-size: 16px; line-height: 1.6;">';
		echo '<li>📊 ' . esc_html__( 'Connect your analytics services (Google Analytics, etc.)', 'fp-digital-marketing' ) . '</li>';
		echo '<li>📈 ' . esc_html__( 'Choose which metrics to track', 'fp-digital-marketing' ) . '</li>';
		echo '<li>📧 ' . esc_html__( 'Configure automated reports', 'fp-digital-marketing' ) . '</li>';
		echo '<li>✅ ' . esc_html__( 'Complete your setup and start tracking', 'fp-digital-marketing' ) . '</li>';
		echo '</ul>';
		
		echo '<p style="margin-top: 30px;"><strong>' . esc_html__( 'Ready to get started?', 'fp-digital-marketing' ) . '</strong></p>';

		$this->render_wizard_navigation( 1, true );
	}

	/**
	 * Render services connection step
	 *
	 * @return void
	 */
	private function render_services_step(): void {
		echo '<form method="post" class="fp-wizard-form">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<input type="hidden" name="current_step" value="2">';
		
		echo '<h2>' . esc_html__( 'Connect Your Services', 'fp-digital-marketing' ) . '</h2>';
		echo '<p>' . esc_html__( 'Select the services you want to connect to track your digital marketing performance.', 'fp-digital-marketing' ) . '</p>';

		$progress = get_option( self::WIZARD_PROGRESS_OPTION, [] );
		$selected_services = $progress['services'] ?? [];

		// Get available data sources
		$data_sources = DataSources::get_data_sources();
		
		// Debug: Check if we have data sources
		$available_count = 0;
		foreach ( $data_sources as $source ) {
			if ( $source['status'] === 'available' ) {
				$available_count++;
			}
		}
		
		// Show debug info if no services available
		if ( $available_count === 0 ) {
			echo '<div style="background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3232;">';
			echo '<strong>Debug:</strong> No available services found. Total data sources: ' . count( $data_sources ) . '<br>';
			echo 'Available sources: ';
			foreach ( $data_sources as $source ) {
				echo $source['name'] . ' (status: ' . $source['status'] . '), ';
			}
			echo '</div>';
		}
		
		foreach ( $data_sources as $source ) {
			if ( $source['status'] !== 'available' ) {
				continue;
			}
			
			$checked = in_array( $source['id'], $selected_services, true ) ? 'checked' : '';
			$icon = $this->get_service_icon( $source['id'] );
			
			echo '<div class="fp-service-card' . ( $checked ? ' selected' : '' ) . '">';
			echo '<label>';
			echo '<input type="checkbox" name="selected_services[]" value="' . esc_attr( $source['id'] ) . '" ' . $checked . ' style="display:none;">';
			echo '<div style="display: flex; align-items: center;">';
			echo '<span style="font-size: 24px; margin-right: 15px;">' . $icon . '</span>';
			echo '<div>';
			echo '<h4 style="margin: 0;">' . esc_html( $source['name'] ) . '</h4>';
			echo '<p style="margin: 5px 0 0 0; color: #666;">' . esc_html( $source['description'] ?? '' ) . '</p>';
			echo '</div>';
			echo '</div>';
			echo '</label>';
			echo '</div>';
		}

		$this->render_wizard_navigation( 2 );
		echo '</form>';
	}

	/**
	 * Render metrics selection step
	 *
	 * @return void
	 */
	private function render_metrics_step(): void {
		echo '<form method="post" class="fp-wizard-form">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<input type="hidden" name="current_step" value="3">';
		
		echo '<h2>' . esc_html__( 'Choose Your Metrics', 'fp-digital-marketing' ) . '</h2>';
		echo '<p>' . esc_html__( 'Select the metrics you want to track. You can always change these later in the settings.', 'fp-digital-marketing' ) . '</p>';

		$progress = get_option( self::WIZARD_PROGRESS_OPTION, [] );
		$selected_metrics = $progress['metrics'] ?? [];

		// Get available metrics from schema
		$metrics = [
			'sessions' => __( 'Sessions', 'fp-digital-marketing' ),
			'pageviews' => __( 'Page Views', 'fp-digital-marketing' ),
			'users' => __( 'Users', 'fp-digital-marketing' ),
			'bounce_rate' => __( 'Bounce Rate', 'fp-digital-marketing' ),
			'avg_session_duration' => __( 'Average Session Duration', 'fp-digital-marketing' ),
			'conversion_rate' => __( 'Conversion Rate', 'fp-digital-marketing' ),
			'revenue' => __( 'Revenue', 'fp-digital-marketing' ),
			'transactions' => __( 'Transactions', 'fp-digital-marketing' ),
		];

		echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">';
		foreach ( $metrics as $metric_id => $metric_label ) {
			$checked = in_array( $metric_id, $selected_metrics, true ) ? 'checked' : '';
			
			echo '<div class="fp-metric-checkbox">';
			echo '<label>';
			echo '<input type="checkbox" name="selected_metrics[]" value="' . esc_attr( $metric_id ) . '" ' . $checked . '>';
			echo ' <strong>' . esc_html( $metric_label ) . '</strong>';
			echo '</label>';
			echo '</div>';
		}
		echo '</div>';

		$this->render_wizard_navigation( 3 );
		echo '</form>';
	}

	/**
	 * Render reports configuration step
	 *
	 * @return void
	 */
	private function render_reports_step(): void {
		echo '<form method="post" class="fp-wizard-form">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<input type="hidden" name="current_step" value="4">';
		
		echo '<h2>' . esc_html__( 'Configure Reports', 'fp-digital-marketing' ) . '</h2>';
		echo '<p>' . esc_html__( 'Set up how often you want to receive automated reports and where to send them.', 'fp-digital-marketing' ) . '</p>';

		$progress = get_option( self::WIZARD_PROGRESS_OPTION, [] );
		$report_config = $progress['reports'] ?? [];

		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">';
		echo '<label for="report_frequency">' . esc_html__( 'Report Frequency', 'fp-digital-marketing' ) . '</label>';
		echo '</th>';
		echo '<td>';
		echo '<select name="report_frequency" id="report_frequency">';
		$frequencies = [
			'daily' => __( 'Daily', 'fp-digital-marketing' ),
			'weekly' => __( 'Weekly', 'fp-digital-marketing' ),
			'monthly' => __( 'Monthly', 'fp-digital-marketing' ),
		];
		foreach ( $frequencies as $value => $label ) {
			$selected = selected( $report_config['frequency'] ?? 'weekly', $value, false );
			echo '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">';
		echo '<label for="report_recipients">' . esc_html__( 'Email Recipients', 'fp-digital-marketing' ) . '</label>';
		echo '</th>';
		echo '<td>';
		echo '<input type="email" name="report_recipients" id="report_recipients" value="' . esc_attr( $report_config['recipients'] ?? '' ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Enter email address to receive automated reports.', 'fp-digital-marketing' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';

		$this->render_wizard_navigation( 4 );
		echo '</form>';
	}

	/**
	 * Render feedback step
	 *
	 * @return void
	 */
	private function render_feedback_step(): void {
		echo '<form method="post" class="fp-wizard-form">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<input type="hidden" name="current_step" value="5">';
		
		echo '<h2>' . esc_html__( 'Almost Done!', 'fp-digital-marketing' ) . '</h2>';
		echo '<p>' . esc_html__( 'Help us improve the setup experience by sharing your feedback.', 'fp-digital-marketing' ) . '</p>';

		$progress = get_option( self::WIZARD_PROGRESS_OPTION, [] );
		$feedback = $progress['feedback'] ?? [];

		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">';
		echo '<label for="wizard_rating">' . esc_html__( 'How was your setup experience?', 'fp-digital-marketing' ) . '</label>';
		echo '</th>';
		echo '<td>';
		for ( $i = 1; $i <= 5; $i++ ) {
			$checked = checked( $feedback['rating'] ?? 5, $i, false );
			echo '<label style="margin-right: 10px;">';
			echo '<input type="radio" name="wizard_rating" value="' . $i . '" ' . $checked . '>';
			echo ' ' . str_repeat( '⭐', $i );
			echo '</label>';
		}
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">';
		echo '<label for="user_feedback">' . esc_html__( 'Additional Comments', 'fp-digital-marketing' ) . '</label>';
		echo '</th>';
		echo '<td>';
		echo '<textarea name="user_feedback" id="user_feedback" rows="4" cols="50" class="large-text">' . esc_textarea( $feedback['feedback'] ?? '' ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Optional: Share any suggestions or issues you encountered.', 'fp-digital-marketing' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';

		echo '<div class="fp-wizard-navigation">';
		echo '<button type="submit" name="wizard_action" value="previous_step" class="button">' . esc_html__( 'Previous', 'fp-digital-marketing' ) . '</button>';
		echo '<div>';
		echo '<button type="submit" name="wizard_action" value="complete_wizard" class="button button-primary button-large">' . esc_html__( 'Complete Setup', 'fp-digital-marketing' ) . '</button>';
		echo '</div>';
		echo '</div>';
		
		echo '</form>';
	}

	/**
	 * Render wizard navigation buttons
	 *
	 * @param int  $step Current step.
	 * @param bool $is_welcome Whether this is the welcome step.
	 * @return void
	 */
	private function render_wizard_navigation( int $step, bool $is_welcome = false ): void {
		echo '<div class="fp-wizard-navigation">';
		
		// Previous button
		if ( $step > 1 && ! $is_welcome ) {
			echo '<button type="submit" name="wizard_action" value="previous_step" class="button">' . esc_html__( 'Previous', 'fp-digital-marketing' ) . '</button>';
		} else {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-digital-marketing-reports' ) ) . '" class="button">' . esc_html__( 'Skip Setup', 'fp-digital-marketing' ) . '</a>';
		}
		
		// Next button
		echo '<div>';
		if ( $is_welcome ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&step=2' ) ) . '" class="button button-primary button-large">' . esc_html__( 'Get Started', 'fp-digital-marketing' ) . '</a>';
		} elseif ( $step < self::TOTAL_STEPS ) {
			echo '<button type="submit" name="wizard_action" value="next_step" class="button button-primary">' . esc_html__( 'Next', 'fp-digital-marketing' ) . '</button>';
		}
		echo '</div>';
		
		echo '</div>';
	}

	/**
	 * Render completion page
	 *
	 * @return void
	 */
	private function render_completion_page(): void {
		echo '<div class="wrap">';
		echo '<div class="fp-wizard-container">';
		echo '<div class="fp-wizard-success">';
		echo '<span class="dashicons dashicons-yes-alt"></span>';
		echo '<h1>' . esc_html__( 'Setup Complete!', 'fp-digital-marketing' ) . '</h1>';
		echo '<p>' . esc_html__( 'Your FP Digital Marketing Suite is now configured and ready to track your marketing performance.', 'fp-digital-marketing' ) . '</p>';
		echo '<div style="margin: 30px 0;">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-digital-marketing-reports' ) ) . '" class="button button-primary button-large">' . esc_html__( 'View Reports', 'fp-digital-marketing' ) . '</a>';
		echo ' ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-digital-marketing-settings' ) ) . '" class="button button-large">' . esc_html__( 'Manage Settings', 'fp-digital-marketing' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Get icon for a service
	 *
	 * @param string $service_id Service identifier.
	 * @return string Icon representation.
	 */
	private function get_service_icon( string $service_id ): string {
		$icons = [
			'google_analytics_4' => '📊',
			'facebook_ads' => '📘',
			'google_ads' => '🎯',
			'instagram' => '📷',
			'linkedin' => '💼',
			'twitter' => '🐦',
		];

		return $icons[ $service_id ] ?? '📈';
	}

	/**
	 * Check if wizard is completed
	 *
	 * @return bool True if wizard is completed.
	 */
	public static function is_completed(): bool {
		return (bool) get_option( self::WIZARD_COMPLETED_OPTION, false );
	}

	/**
	 * Reset wizard (for testing or re-running)
	 *
	 * @return void
	 */
	public static function reset(): void {
		delete_option( self::WIZARD_COMPLETED_OPTION );
		delete_option( self::WIZARD_PROGRESS_OPTION );
	}
}