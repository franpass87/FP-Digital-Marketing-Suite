<?php
/**
 * Runtime logging utilities for development troubleshooting.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Captures PHP errors and exceptions and surfaces them to administrators.
 */
class RuntimeLogger {
	/**
	 * Indicates whether the logger has already been initialised.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Absolute path to the runtime log file.
	 *
	 * @var string|null
	 */
	private static ?string $log_file = null;

	/**
	 * Public URL pointing to the runtime log file when stored in uploads.
	 *
	 * @var string|null
	 */
	private static ?string $log_url = null;

	/**
	 * Previous PHP error handler reference.
	 *
	 * @var callable|null
	 */
	private static $previous_error_handler = null;

	/**
	 * Previous exception handler reference.
	 *
	 * @var callable|null
	 */
	private static $previous_exception_handler = null;

	/**
	 * Tracks whether an admin notice has already been printed during this request.
	 *
	 * @var bool
	 */
	private static bool $notice_printed = false;

	/**
	 * Bootstraps the runtime logger when debugging is enabled.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( self::$booted || ! function_exists( 'add_action' ) ) {
			return;
		}

		if ( defined( 'WP_DEBUG' ) && true !== WP_DEBUG ) {
			return;
		}

		$log_location = self::resolve_log_location();

		if ( empty( $log_location['file'] ) ) {
			return;
		}

		self::$log_file = $log_location['file'];
		self::$log_url  = $log_location['url'];

		self::$previous_error_handler     = set_error_handler( [ self::class, 'handle_error' ] );
		self::$previous_exception_handler = set_exception_handler( [ self::class, 'handle_exception' ] );

		add_action( 'shutdown', [ self::class, 'handle_shutdown' ], PHP_INT_MAX );
		add_action( 'admin_notices', [ self::class, 'render_admin_notice' ] );
		add_action( 'wp_footer', [ self::class, 'render_frontend_banner' ] );

		self::$booted = true;
	}

	/**
	 * PHP error handler used to capture warnings and notices.
	 *
	 * @param int    $severity Error severity.
	 * @param string $message  Error message.
	 * @param string $file     File path.
	 * @param int    $line     Line number.
	 *
	 * @return bool
	 */
	public static function handle_error( int $severity, string $message, string $file = '', int $line = 0 ): bool {
		if ( 0 === ( error_reporting() & $severity ) ) {
			return false;
		}
		self::write_log_entry( self::map_error_level( $severity ), $message, $file, $line );
		if ( is_callable( self::$previous_error_handler ) ) {
			return (bool) call_user_func( self::$previous_error_handler, $severity, $message, $file, $line );
		}
		return false;
	}

	/**
	 * Exception handler proxy that ensures uncaught throwables are logged.
	 *
	 * @param \Throwable $throwable Uncaught throwable instance.
	 *
	 * @return void
	 */
	public static function handle_exception( \Throwable $throwable ): void {
		self::write_log_entry( 'exception', $throwable->getMessage(), $throwable->getFile(), $throwable->getLine() );
		if ( is_callable( self::$previous_exception_handler ) ) {
			call_user_func( self::$previous_exception_handler, $throwable );
			return;
		}
		// Re-throw the exception after logging when no previous handler exists.
		throw $throwable;
	}

	/**
	 * Shutdown handler that inspects the last error to capture fatals.
	 *
	 * @return void
	 */
	public static function handle_shutdown(): void {
		$error = error_get_last();
		if ( empty( $error ) ) {
			return;
		}
		$level = self::map_error_level( (int) $error['type'] );
		self::write_log_entry( $level, (string) $error['message'], (string) $error['file'], (int) $error['line'] );
	}

	/**
	 * Surface the log location within the WordPress admin for administrators.
	 *
	 * @return void
	 */
	public static function render_admin_notice(): void {
		if ( self::$notice_printed || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'esc_html' ) || empty( self::$log_file ) ) {
			return;
		}
		$message = sprintf(
			/* translators: %s: Path to the runtime log file. */
			__( 'FP Digital Marketing Suite runtime logging is active. Review "%s" for captured notices and warnings.', 'fp-digital-marketing' ),
			esc_html( self::$log_file )
		);
		echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
		self::$notice_printed = true;
	}

	/**
	 * Display a lightweight banner for site administrators on the frontend.
	 *
	 * @return void
	 */
	public static function render_frontend_banner(): void {
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
			return;
		}
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'esc_html' ) || empty( self::$log_file ) ) {
			return;
		}
		$label = esc_html__( 'Runtime log active', 'fp-digital-marketing' );

		$details = esc_html( self::$log_file );

		if ( ! empty( self::$log_url ) && function_exists( 'esc_url' ) ) {
			$details = '<a href="' . esc_url( self::$log_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( self::$log_url ) . '</a>';
		}
		echo '<div class="fp-dms-runtime-log-banner" style="position:fixed;bottom:16px;right:16px;background:#23282d;color:#fff;padding:12px 16px;font-size:13px;z-index:9999;border-radius:4px;box-shadow:0 2px 6px rgba(0,0,0,0.3);">';
		echo '<strong>' . $label . '</strong><br />';
		echo $details;
		echo '</div>';
	}

	/**
	 * Map PHP error severities to human readable log levels.
	 *
	 * @param int $severity Error severity constant.
	 *
	 * @return string
	 */
	private static function map_error_level( int $severity ): string {
		switch ( $severity ) {
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
			case E_PARSE:
				return 'error';
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				return 'warning';
			case E_NOTICE:
			case E_USER_NOTICE:
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
			case E_STRICT:
			default:
				return 'notice';
		}
	}

	/**
	 * Persist a runtime log entry to disk.
	 *
	 * @param string $level   Log level label.
	 * @param string $message Log message.
	 * @param string $file    Originating file path.
	 * @param int    $line    Line number.
	 *
	 * @return void
	 */
	private static function write_log_entry( string $level, string $message, string $file, int $line ): void {
		if ( empty( self::$log_file ) ) {
			return;
		}
		$timestamp = gmdate( 'c' );
		$request   = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : 'cli';
		$context   = sprintf( '%s:%d', $file, $line );
		$entry = sprintf( '[%s] [%s] [%s] %s - %s%s', $timestamp, strtoupper( $level ), $request, $context, self::normalize_message( $message ), PHP_EOL );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( self::$log_file, $entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Normalise log messages to keep them on a single line.
	 *
	 * @param string $message Raw log message.
	 *
	 * @return string
	 */
	private static function normalize_message( string $message ): string {
		$sanitized = preg_replace( '/\\s+/', ' ', $message );
		return is_string( $sanitized ) ? trim( $sanitized ) : trim( $message );
	}

	/**
	 * Determine where runtime logs should be written.
	 *
	 * @return array{file:string|null,url:string|null}
	 */
	private static function resolve_log_location(): array {
		$upload_basedir = null;
		$upload_baseurl = null;
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir();
			if ( empty( $uploads['error'] ) ) {
				$upload_basedir = (string) $uploads['basedir'];
				$upload_baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : null;
			}
		}
		$plugin_root = defined( 'FP_DIGITAL_MARKETING_PLUGIN_DIR' ) ? FP_DIGITAL_MARKETING_PLUGIN_DIR : dirname( __DIR__, 2 );

		$target_dir = $upload_basedir ? self::join_paths( $upload_basedir, 'fp-dms-logs' ) : self::join_paths( $plugin_root, 'runtime-logs' );
		self::ensure_directory_exists( $target_dir );
		$log_file = self::join_paths( $target_dir, 'runtime.log' );
		$log_url = null;
		if ( $upload_baseurl ) {
			$log_url = self::join_paths( $upload_baseurl, 'fp-dms-logs/runtime.log', '/' );
		}
		return [
			'file' => $log_file,
			'url'  => $log_url,
		];
	}

	/**
	 * Ensure the directory exists before writing logs.
	 *
	 * @param string $path Directory path.
	 *
	 * @return void
	 */
	private static function ensure_directory_exists( string $path ): void {
		if ( is_dir( $path ) ) {
			return;
		}
		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $path );
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		mkdir( $path, 0755, true );
	}

	/**
	 * Utility helper to join path fragments using the correct separator.
	 *
	 * @param string $base      Base path or URL.
	 * @param string $append    Path fragment to append.
	 * @param string $separator Optional custom separator.
	 *
	 * @return string
	 */
	private static function join_paths( string $base, string $append, string $separator = DIRECTORY_SEPARATOR ): string {
		$trimmed_base   = rtrim( $base, '/\\' );
		$trimmed_append = ltrim( $append, '/\\' );
		return $trimmed_base . $separator . $trimmed_append;
	}
}
