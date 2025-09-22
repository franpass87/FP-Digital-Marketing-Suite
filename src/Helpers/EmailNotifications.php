<?php
/**
 * Email Notifications Helper Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Helpers\Security;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Email Notifications class for sending alerts and reports
 */
class EmailNotifications {

	/**
	 * Notification types
	 */
	public const TYPE_ALERT = 'alert';
	public const TYPE_REPORT = 'report';
	public const TYPE_SECURITY = 'security';
	public const TYPE_SYSTEM = 'system';

	/**
	 * Email priorities
	 */
	public const PRIORITY_LOW = 'low';
	public const PRIORITY_NORMAL = 'normal';
	public const PRIORITY_HIGH = 'high';
	public const PRIORITY_URGENT = 'urgent';

	/**
	 * Initialize email notifications
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_ajax_fp_test_email', [ __CLASS__, 'handle_test_email' ] );
		add_action( 'fp_dms_send_scheduled_report', [ __CLASS__, 'send_scheduled_report' ] );
		add_action( 'fp_dms_security_alert', [ __CLASS__, 'send_security_alert' ] );
		add_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ] );
		
		// Schedule daily digest
		self::schedule_daily_digest();
	}

	/**
	 * Send notification email
	 *
	 * @param string $type Notification type
	 * @param string $to Recipient email
	 * @param string $subject Email subject
	 * @param array $data Email data
	 * @param string $priority Email priority
	 * @return bool Success status
	 */
	public static function send_notification( 
		string $type, 
		string $to, 
		string $subject, 
		array $data, 
		string $priority = self::PRIORITY_NORMAL 
	): bool {
		// Check if notifications are enabled for this type
		if ( ! self::is_notification_enabled( $type ) ) {
			return false;
		}

		// Rate limiting for emails
		if ( ! self::check_rate_limit( $to, $type ) ) {
			return false;
		}

		// Generate email content
		$email_content = self::generate_email_content( $type, $data );
		$headers = self::get_email_headers( $priority );

		// Log email attempt
		Security::log_security_event( 'email_sent', [
			'type' => $type,
			'recipient' => $to,
			'subject' => $subject,
			'priority' => $priority,
		] );

		// Send email
		$sent = wp_mail( $to, $subject, $email_content, $headers );

		// Update rate limiting
		self::update_rate_limit( $to, $type );

		return $sent;
	}

	/**
	 * Send alert notification
	 *
	 * @param string $alert_type Alert type
	 * @param array $alert_data Alert data
	 * @return bool Success status
	 */
	public static function send_alert( string $alert_type, array $alert_data ): bool {
		$recipients = self::get_alert_recipients( $alert_type );
		$subject = self::generate_alert_subject( $alert_type, $alert_data );
		
		$success = true;
		foreach ( $recipients as $recipient ) {
			$sent = self::send_notification(
				self::TYPE_ALERT,
				$recipient,
				$subject,
				array_merge( $alert_data, [ 'alert_type' => $alert_type ] ),
				$alert_data['priority'] ?? self::PRIORITY_NORMAL
			);
			
			if ( ! $sent ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Send report notification
	 *
	 * @param string $report_type Report type
	 * @param array $report_data Report data
	 * @param array $recipients Recipient emails
	 * @return bool Success status
	 */
	public static function send_report( string $report_type, array $report_data, array $recipients = [] ): bool {
		if ( empty( $recipients ) ) {
			$recipients = self::get_report_recipients( $report_type );
		}

		$subject = self::generate_report_subject( $report_type, $report_data );
		
		$success = true;
		foreach ( $recipients as $recipient ) {
			$sent = self::send_notification(
				self::TYPE_REPORT,
				$recipient,
				$subject,
				array_merge( $report_data, [ 'report_type' => $report_type ] )
			);
			
			if ( ! $sent ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Send security alert
	 *
	 * @param array $security_data Security incident data
	 * @return bool Success status
	 */
	public static function send_security_alert( array $security_data ): bool {
		$recipients = self::get_security_recipients();
		$subject = sprintf(
			__( '[SECURITY ALERT] %s - %s', 'fp-digital-marketing' ),
			get_bloginfo( 'name' ),
			$security_data['event_type'] ?? 'Security Event'
		);

		$success = true;
		foreach ( $recipients as $recipient ) {
			$sent = self::send_notification(
				self::TYPE_SECURITY,
				$recipient,
				$subject,
				$security_data,
				self::PRIORITY_HIGH
			);
			
			if ( ! $sent ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Handle test email AJAX request
	 *
	 * @return void
	 */
	public static function handle_test_email(): void {
		// Verify nonce and capabilities
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'fp_dms_settings_nonce' ) ) {
			wp_send_json_error( __( 'Richiesta di sicurezza non valida.', 'fp-digital-marketing' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permessi insufficienti.', 'fp-digital-marketing' ) );
		}

		$test_email = sanitize_email( $_POST['test_email'] ?? '' );
		if ( empty( $test_email ) ) {
			wp_send_json_error( __( 'Indirizzo email non valido.', 'fp-digital-marketing' ) );
		}

		$test_data = [
			'message' => __( 'Questo è un test del sistema di notifiche email di FP Digital Marketing Suite.', 'fp-digital-marketing' ),
			'timestamp' => current_time( 'mysql' ),
			'test_info' => [
				'site_url' => home_url(),
				'plugin_version' => FP_DIGITAL_MARKETING_VERSION,
				'php_version' => PHP_VERSION,
				'wp_version' => get_bloginfo( 'version' ),
			]
		];

		$sent = self::send_notification(
			self::TYPE_SYSTEM,
			$test_email,
			sprintf( __( 'Test Email da %s', 'fp-digital-marketing' ), get_bloginfo( 'name' ) ),
			$test_data
		);

		if ( $sent ) {
			wp_send_json_success( __( 'Email di test inviata con successo!', 'fp-digital-marketing' ) );
		} else {
			wp_send_json_error( __( 'Errore durante l\'invio dell\'email di test.', 'fp-digital-marketing' ) );
		}
	}

	/**
	 * Generate email content based on type
	 *
	 * @param string $type Email type
	 * @param array $data Email data
	 * @return string Email content
	 */
	private static function generate_email_content( string $type, array $data ): string {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
				.container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
				.header { background: #0073aa; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
				.content { padding: 30px; }
				.footer { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
				.alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
				.alert.high { background: #f8d7da; border-left: 4px solid #dc3545; }
				.alert.normal { background: #d1ecf1; border-left: 4px solid #0c5460; }
				.alert.low { background: #d4edda; border-left: 4px solid #155724; }
				.stats-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
				.stats-table th, .stats-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
				.stats-table th { background: #f8f9fa; font-weight: bold; }
				.button { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
					<p><?php esc_html_e( 'FP Digital Marketing Suite', 'fp-digital-marketing' ); ?></p>
				</div>
				
				<div class="content">
					<?php
					switch ( $type ) {
						case self::TYPE_ALERT:
							self::render_alert_content( $data );
							break;
						case self::TYPE_REPORT:
							self::render_report_content( $data );
							break;
						case self::TYPE_SECURITY:
							self::render_security_content( $data );
							break;
						case self::TYPE_SYSTEM:
							self::render_system_content( $data );
							break;
						default:
							self::render_default_content( $data );
					}
					?>
				</div>
				
				<div class="footer">
					<p><?php printf( esc_html__( 'Inviato da %s il %s', 'fp-digital-marketing' ), get_bloginfo( 'name' ), current_time( 'Y-m-d H:i:s' ) ); ?></p>
					<p><?php esc_html_e( 'Puoi gestire le notifiche email dalle impostazioni del plugin.', 'fp-digital-marketing' ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render alert email content
	 *
	 * @param array $data Alert data
	 * @return void
	 */
	private static function render_alert_content( array $data ): void {
		$alert_type = $data['alert_type'] ?? 'general';
		$priority = $data['priority'] ?? self::PRIORITY_NORMAL;
		?>
		<div class="alert <?php echo esc_attr( $priority ); ?>">
			<h2><?php esc_html_e( 'Alert Notification', 'fp-digital-marketing' ); ?></h2>
			<p><strong><?php esc_html_e( 'Alert Type:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( ucfirst( $alert_type ) ); ?></p>
			<p><strong><?php esc_html_e( 'Priority:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( ucfirst( $priority ) ); ?></p>
			
			<?php if ( isset( $data['message'] ) ): ?>
				<p><?php echo esc_html( $data['message'] ); ?></p>
			<?php endif; ?>

			<?php if ( isset( $data['details'] ) && is_array( $data['details'] ) ): ?>
				<h3><?php esc_html_e( 'Details:', 'fp-digital-marketing' ); ?></h3>
				<ul>
				<?php foreach ( $data['details'] as $key => $value ): ?>
					<li><strong><?php echo esc_html( $key ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
				<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-dashboard' ) ); ?>" class="button">
				<?php esc_html_e( 'View Dashboard', 'fp-digital-marketing' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render report email content
	 *
	 * @param array $data Report data
	 * @return void
	 */
	private static function render_report_content( array $data ): void {
		$report_type = $data['report_type'] ?? 'general';
		?>
		<h2><?php printf( esc_html__( '%s Report', 'fp-digital-marketing' ), ucfirst( $report_type ) ); ?></h2>
		
		<?php if ( isset( $data['summary'] ) ): ?>
			<p><?php echo esc_html( $data['summary'] ); ?></p>
		<?php endif; ?>

		<?php if ( isset( $data['metrics'] ) && is_array( $data['metrics'] ) ): ?>
			<h3><?php esc_html_e( 'Key Metrics:', 'fp-digital-marketing' ); ?></h3>
			<table class="stats-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Metric', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Current Value', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Previous Period', 'fp-digital-marketing' ); ?></th>
						<th><?php esc_html_e( 'Change', 'fp-digital-marketing' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $data['metrics'] as $metric_name => $metric_data ): ?>
					<tr>
						<td><?php echo esc_html( $metric_name ); ?></td>
						<td><?php echo esc_html( $metric_data['current'] ?? 'N/A' ); ?></td>
						<td><?php echo esc_html( $metric_data['previous'] ?? 'N/A' ); ?></td>
						<td><?php echo esc_html( $metric_data['change'] ?? 'N/A' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-reports' ) ); ?>" class="button">
				<?php esc_html_e( 'View Full Report', 'fp-digital-marketing' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render security email content
	 *
	 * @param array $data Security data
	 * @return void
	 */
	private static function render_security_content( array $data ): void {
		?>
		<div class="alert high">
			<h2><?php esc_html_e( 'Security Alert', 'fp-digital-marketing' ); ?></h2>
			<p><strong><?php esc_html_e( 'Event Type:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $data['event_type'] ?? 'Unknown' ); ?></p>
			<p><strong><?php esc_html_e( 'Timestamp:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $data['timestamp'] ?? current_time( 'mysql' ) ); ?></p>
			
			<?php if ( isset( $data['ip'] ) ): ?>
				<p><strong><?php esc_html_e( 'IP Address:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( $data['ip'] ); ?></p>
			<?php endif; ?>

			<?php if ( isset( $data['user_id'] ) ): ?>
				<p><strong><?php esc_html_e( 'User:', 'fp-digital-marketing' ); ?></strong> <?php echo esc_html( get_userdata( $data['user_id'] )->display_name ?? 'Unknown' ); ?></p>
			<?php endif; ?>

			<?php if ( isset( $data['description'] ) ): ?>
				<p><?php echo esc_html( $data['description'] ); ?></p>
			<?php endif; ?>
		</div>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-digital-marketing-security' ) ); ?>" class="button">
				<?php esc_html_e( 'Review Security Logs', 'fp-digital-marketing' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render system email content
	 *
	 * @param array $data System data
	 * @return void
	 */
	private static function render_system_content( array $data ): void {
		?>
		<h2><?php esc_html_e( 'System Notification', 'fp-digital-marketing' ); ?></h2>
		
		<?php if ( isset( $data['message'] ) ): ?>
			<p><?php echo esc_html( $data['message'] ); ?></p>
		<?php endif; ?>

		<?php if ( isset( $data['test_info'] ) && is_array( $data['test_info'] ) ): ?>
			<h3><?php esc_html_e( 'System Information:', 'fp-digital-marketing' ); ?></h3>
			<table class="stats-table">
				<?php foreach ( $data['test_info'] as $key => $value ): ?>
					<tr>
						<td><strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?></strong></td>
						<td><?php echo esc_html( $value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render default email content
	 *
	 * @param array $data Email data
	 * @return void
	 */
	private static function render_default_content( array $data ): void {
		?>
		<h2><?php esc_html_e( 'Notification', 'fp-digital-marketing' ); ?></h2>
		<p><?php echo esc_html( $data['message'] ?? __( 'You have received a notification from FP Digital Marketing Suite.', 'fp-digital-marketing' ) ); ?></p>
		<?php
	}

	/**
	 * Get email headers
	 *
	 * @param string $priority Email priority
	 * @return array Email headers
	 */
	private static function get_email_headers( string $priority ): array {
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		];

		switch ( $priority ) {
			case self::PRIORITY_URGENT:
				$headers[] = 'X-Priority: 1';
				$headers[] = 'X-MSMail-Priority: High';
				break;
			case self::PRIORITY_HIGH:
				$headers[] = 'X-Priority: 2';
				$headers[] = 'X-MSMail-Priority: High';
				break;
			case self::PRIORITY_LOW:
				$headers[] = 'X-Priority: 4';
				$headers[] = 'X-MSMail-Priority: Low';
				break;
		}

		return $headers;
	}

	/**
	 * Set HTML content type for emails
	 *
	 * @return string Content type
	 */
	public static function set_html_content_type(): string {
		return 'text/html';
	}

	/**
	 * Check if notification type is enabled
	 *
	 * @param string $type Notification type
	 * @return bool Whether notifications are enabled
	 */
	private static function is_notification_enabled( string $type ): bool {
		$settings = get_option( 'fp_digital_marketing_email_settings', [
			'alerts_enabled' => true,
			'reports_enabled' => true,
			'security_enabled' => true,
			'system_enabled' => true,
		] );

		switch ( $type ) {
			case self::TYPE_ALERT:
				return $settings['alerts_enabled'] ?? true;
			case self::TYPE_REPORT:
				return $settings['reports_enabled'] ?? true;
			case self::TYPE_SECURITY:
				return $settings['security_enabled'] ?? true;
			case self::TYPE_SYSTEM:
				return $settings['system_enabled'] ?? true;
			default:
				return true;
		}
	}

	/**
	 * Check rate limit for email sending
	 *
	 * @param string $recipient Recipient email
	 * @param string $type Email type
	 * @return bool Whether email can be sent
	 */
	private static function check_rate_limit( string $recipient, string $type ): bool {
		$cache_key = "email_rate_limit_{$recipient}_{$type}";
		$count = PerformanceCache::get( $cache_key, 'email_limits' ) ?: 0;
		
		// Allow max 10 emails per hour per type per recipient
		return $count < 10;
	}

	/**
	 * Update rate limit counter
	 *
	 * @param string $recipient Recipient email
	 * @param string $type Email type
	 * @return void
	 */
	private static function update_rate_limit( string $recipient, string $type ): void {
		$cache_key = "email_rate_limit_{$recipient}_{$type}";
		$count = PerformanceCache::get( $cache_key, 'email_limits' ) ?: 0;
                PerformanceCache::set( $cache_key, 'email_limits', $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Get alert recipients
	 *
	 * @param string $alert_type Alert type
	 * @return array Recipient emails
	 */
	private static function get_alert_recipients( string $alert_type ): array {
		$settings = get_option( 'fp_digital_marketing_email_settings', [] );
		return $settings['alert_recipients'] ?? [ get_option( 'admin_email' ) ];
	}

	/**
	 * Get report recipients
	 *
	 * @param string $report_type Report type
	 * @return array Recipient emails
	 */
	private static function get_report_recipients( string $report_type ): array {
		$settings = get_option( 'fp_digital_marketing_email_settings', [] );
		return $settings['report_recipients'] ?? [ get_option( 'admin_email' ) ];
	}

	/**
	 * Get security alert recipients
	 *
	 * @return array Recipient emails
	 */
	private static function get_security_recipients(): array {
		$settings = get_option( 'fp_digital_marketing_email_settings', [] );
		return $settings['security_recipients'] ?? [ get_option( 'admin_email' ) ];
	}

	/**
	 * Generate alert subject
	 *
	 * @param string $alert_type Alert type
	 * @param array $alert_data Alert data
	 * @return string Email subject
	 */
	private static function generate_alert_subject( string $alert_type, array $alert_data ): string {
		$priority_prefix = '';
		if ( isset( $alert_data['priority'] ) && $alert_data['priority'] === self::PRIORITY_HIGH ) {
			$priority_prefix = '[HIGH] ';
		}

		return sprintf(
			__( '%s[ALERT] %s - %s', 'fp-digital-marketing' ),
			$priority_prefix,
			get_bloginfo( 'name' ),
			ucfirst( $alert_type )
		);
	}

	/**
	 * Generate report subject
	 *
	 * @param string $report_type Report type
	 * @param array $report_data Report data
	 * @return string Email subject
	 */
	private static function generate_report_subject( string $report_type, array $report_data ): string {
		return sprintf(
			__( '%s - %s Report', 'fp-digital-marketing' ),
			get_bloginfo( 'name' ),
			ucfirst( $report_type )
		);
	}

	/**
	 * Schedule daily digest
	 *
	 * @return void
	 */
	private static function schedule_daily_digest(): void {
		if ( ! wp_next_scheduled( 'fp_dms_daily_digest' ) ) {
			wp_schedule_event( time(), 'daily', 'fp_dms_daily_digest' );
		}

		add_action( 'fp_dms_daily_digest', [ __CLASS__, 'send_daily_digest' ] );
	}

	/**
	 * Send daily digest email
	 *
	 * @return void
	 */
	public static function send_daily_digest(): void {
		// Check if daily digest is enabled
		$settings = get_option( 'fp_digital_marketing_email_settings', [] );
		if ( ! ( $settings['daily_digest_enabled'] ?? false ) ) {
			return;
		}

		// Gather daily statistics
		$cache_stats = PerformanceCache::get_cache_stats();

		$digest_data = [
			'report_type' => 'daily_digest',
			'date' => current_time( 'Y-m-d' ),
			'summary' => __( 'Here is your daily summary from FP Digital Marketing Suite.', 'fp-digital-marketing' ),
			'metrics' => [
				'Cache Performance' => [
					'current' => $cache_stats['hit_ratio'] ?? 0,
					'previous' => 'N/A',
					'change' => 'N/A',
				],
				'Active Clients' => [
					'current' => wp_count_posts( 'cliente' )->publish ?? 0,
					'previous' => 'N/A',
					'change' => 'N/A',
				],
				'Security Events' => [
					'current' => count( Security::get_security_logs( 10 ) ),
					'previous' => 'N/A',
					'change' => 'N/A',
				],
			]
		];

		$recipients = $settings['digest_recipients'] ?? [ get_option( 'admin_email' ) ];
		self::send_report( 'daily_digest', $digest_data, $recipients );
	}
}