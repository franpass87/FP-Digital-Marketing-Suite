<?php
/**
 * Data Export Helper Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Helpers\Security;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Helpers\MetricsAggregator;

/**
 * Data Export utility class for exporting reports and data
 */
class DataExporter {

	/**
	 * Export formats
	 */
	public const FORMAT_CSV = 'csv';
	public const FORMAT_JSON = 'json';
	public const FORMAT_XML = 'xml';
	public const FORMAT_PDF = 'pdf';

	/**
	 * Maximum export size (rows)
	 */
	private const MAX_EXPORT_SIZE = 10000;

	/**
	 * Export timeout (seconds)
	 */
	private const EXPORT_TIMEOUT = 300;

	/**
	 * Initialize data exporter
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_ajax_fp_export_data', [ __CLASS__, 'handle_export_request' ] );
		add_action( 'wp_ajax_fp_download_export', [ __CLASS__, 'handle_download_request' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_export_assets' ] );
	}

	/**
	 * Handle AJAX export request
	 *
	 * @return void
	 */
	public static function handle_export_request(): void {
		// Verify nonce and capabilities
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'fp_dms_export_nonce' ) ) {
			wp_send_json_error( __( 'Richiesta di sicurezza non valida.', 'fp-digital-marketing' ) );
		}

		if ( ! Capabilities::current_user_can( Capabilities::EXPORT_DATA ) ) {
			wp_send_json_error( __( 'Permessi insufficienti per esportare i dati.', 'fp-digital-marketing' ) );
		}

		$export_type = sanitize_text_field( $_POST['export_type'] ?? '' );
		$format = sanitize_text_field( $_POST['format'] ?? self::FORMAT_CSV );
		$filters = array_map( 'sanitize_text_field', $_POST['filters'] ?? [] );

		// Validate export type
		$valid_types = [ 'analytics', 'clients', 'campaigns', 'reports', 'settings' ];
		if ( ! in_array( $export_type, $valid_types, true ) ) {
			wp_send_json_error( __( 'Tipo di esportazione non valido.', 'fp-digital-marketing' ) );
		}

		// Validate format
		$valid_formats = [ self::FORMAT_CSV, self::FORMAT_JSON, self::FORMAT_XML, self::FORMAT_PDF ];
		if ( ! in_array( $format, $valid_formats, true ) ) {
			wp_send_json_error( __( 'Formato di esportazione non valido.', 'fp-digital-marketing' ) );
		}

		try {
			// Generate export
			$export_result = self::generate_export( $export_type, $format, $filters );
			
			// Log export activity
			Security::log_security_event( 'data_export', [
				'export_type' => $export_type,
				'format' => $format,
				'user_id' => get_current_user_id(),
				'ip' => Security::get_client_ip(),
				'file_size' => $export_result['file_size'] ?? 0,
				'row_count' => $export_result['row_count'] ?? 0,
			] );

			wp_send_json_success( [
				'message' => __( 'Esportazione completata con successo.', 'fp-digital-marketing' ),
				'download_url' => $export_result['download_url'],
				'file_name' => $export_result['file_name'],
				'file_size' => $export_result['file_size'],
				'row_count' => $export_result['row_count'],
			] );

		} catch ( \Exception $e ) {
			Security::log_security_event( 'export_error', [
				'export_type' => $export_type,
				'error' => $e->getMessage(),
				'user_id' => get_current_user_id(),
			] );

			wp_send_json_error( 
				sprintf( 
					__( 'Errore durante l\'esportazione: %s', 'fp-digital-marketing' ), 
					$e->getMessage() 
				) 
			);
		}
	}

	/**
	 * Generate export file
	 *
	 * @param string $export_type Type of data to export
	 * @param string $format Export format
	 * @param array $filters Export filters
	 * @return array Export result with file info
	 * @throws \Exception If export fails
	 */
	private static function generate_export( string $export_type, string $format, array $filters ): array {
		// Set time limit for export
		set_time_limit( self::EXPORT_TIMEOUT );

		// Get data based on export type
		$data = self::get_export_data( $export_type, $filters );

		if ( empty( $data ) ) {
			throw new \Exception( __( 'Nessun dato da esportare trovato.', 'fp-digital-marketing' ) );
		}

		// Check size limit
		if ( count( $data ) > self::MAX_EXPORT_SIZE ) {
			throw new \Exception( 
				sprintf( 
					__( 'Troppi dati da esportare. Limite: %d righe.', 'fp-digital-marketing' ), 
					self::MAX_EXPORT_SIZE 
				) 
			);
		}

		// Generate file
		$file_info = self::create_export_file( $data, $export_type, $format );

		return [
			'download_url' => self::get_download_url( $file_info['token'] ),
			'file_name' => $file_info['file_name'],
			'file_size' => $file_info['file_size'],
			'row_count' => count( $data ),
		];
	}

	/**
	 * Get data for export based on type
	 *
	 * @param string $export_type Type of data to export
	 * @param array $filters Export filters
	 * @return array Export data
	 */
	private static function get_export_data( string $export_type, array $filters ): array {
		switch ( $export_type ) {
			case 'analytics':
				return self::get_analytics_export_data( $filters );
			
			case 'clients':
				return self::get_clients_export_data( $filters );
			
			case 'campaigns':
				return self::get_campaigns_export_data( $filters );
			
			case 'reports':
				return self::get_reports_export_data( $filters );
			
			case 'settings':
				return self::get_settings_export_data( $filters );
			
			default:
				return [];
		}
	}

	/**
	 * Get analytics data for export
	 *
	 * @param array $filters Export filters
	 * @return array Analytics data
	 */
	private static function get_analytics_export_data( array $filters ): array {
		$client_id = (int) ( $filters['client_id'] ?? 0 );
		$start_date = $filters['start_date'] ?? date( 'Y-m-01' );
		$end_date = $filters['end_date'] ?? date( 'Y-m-t' );

		// Get metrics data using the existing aggregator
		$metrics = MetricsAggregator::get_aggregated_metrics(
			$client_id,
			$start_date . ' 00:00:00',
			$end_date . ' 23:59:59'
		);

		$export_data = [];
		foreach ( $metrics as $metric_name => $metric_data ) {
			$export_data[] = [
				'Metric' => $metric_name,
				'Value' => $metric_data['total_value'] ?? 0,
				'Previous Period' => $metric_data['previous_period_value'] ?? 0,
				'Change %' => $metric_data['period_change_percent'] ?? 0,
				'Date Range' => $start_date . ' to ' . $end_date,
				'Client ID' => $client_id,
				'Export Date' => current_time( 'mysql' ),
			];
		}

		return $export_data;
	}

	/**
	 * Get clients data for export
	 *
	 * @param array $filters Export filters
	 * @return array Clients data
	 */
	private static function get_clients_export_data( array $filters ): array {
		$args = [
			'post_type' => 'cliente',
			'post_status' => 'publish',
			'posts_per_page' => self::MAX_EXPORT_SIZE,
			'meta_query' => [],
		];

		// Apply filters
		if ( ! empty( $filters['status'] ) ) {
			$args['meta_query'][] = [
				'key' => '_cliente_status',
				'value' => sanitize_text_field( $filters['status'] ),
				'compare' => '=',
			];
		}

		$clients = get_posts( $args );
		$export_data = [];

		foreach ( $clients as $client ) {
			$meta = get_post_meta( $client->ID );
			$export_data[] = [
				'ID' => $client->ID,
				'Nome' => $client->post_title,
				'Descrizione' => $client->post_content,
				'Status' => $meta['_cliente_status'][0] ?? '',
				'Settore' => $meta['_cliente_settore'][0] ?? '',
				'Paese' => $meta['_cliente_paese'][0] ?? '',
				'Sito Web' => $meta['_cliente_website'][0] ?? '',
				'Data Creazione' => $client->post_date,
				'Ultima Modifica' => $client->post_modified,
			];
		}

		return $export_data;
	}

	/**
	 * Get campaigns data for export
	 *
	 * @param array $filters Export filters
	 * @return array Campaigns data
	 */
	private static function get_campaigns_export_data( array $filters ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fp_dms_utm_campaigns';
		$where_conditions = [ '1=1' ];
		$values = [];

		if ( ! empty( $filters['campaign_name'] ) ) {
			$where_conditions[] = 'campaign_name LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $filters['campaign_name'] ) . '%';
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$where_conditions[] = 'created_at >= %s';
			$values[] = $filters['start_date'];
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where_conditions[] = 'created_at <= %s';
			$values[] = $filters['end_date'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where_conditions );
		$query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d";
		$values[] = self::MAX_EXPORT_SIZE;

		$campaigns = $wpdb->get_results(
			$wpdb->prepare( $query, ...$values ),
			ARRAY_A
		);

		return $campaigns ?: [];
	}

	/**
	 * Get reports data for export
	 *
	 * @param array $filters Export filters
	 * @return array Reports data
	 */
	private static function get_reports_export_data( array $filters ): array {
		// This would typically get data from a reports table
		// For now, return sample structure
		return [
			[
				'Report ID' => 1,
				'Report Name' => 'Monthly Analytics Report',
				'Generated Date' => current_time( 'mysql' ),
				'Client ID' => $filters['client_id'] ?? 0,
				'Status' => 'completed',
			]
		];
	}

	/**
	 * Get settings data for export
	 *
	 * @param array $filters Export filters
	 * @return array Settings data
	 */
	private static function get_settings_export_data( array $filters ): array {
		$export_data = [];
		
		// Export plugin settings (excluding sensitive data)
		$option_names = [
			'fp_digital_marketing_demo_option',
			'fp_digital_marketing_cache_settings',
			'fp_digital_marketing_seo_settings',
			'fp_digital_marketing_sitemap_settings',
			'fp_digital_marketing_schema_settings',
		];

		foreach ( $option_names as $option_name ) {
			$value = get_option( $option_name );
			if ( $value !== false ) {
				$export_data[] = [
					'Setting Name' => $option_name,
					'Setting Value' => is_array( $value ) ? json_encode( $value ) : $value,
					'Export Date' => current_time( 'mysql' ),
				];
			}
		}

		return $export_data;
	}

	/**
	 * Create export file
	 *
	 * @param array $data Export data
	 * @param string $export_type Export type
	 * @param string $format Export format
	 * @return array File information
	 * @throws \Exception If file creation fails
	 */
	private static function create_export_file( array $data, string $export_type, string $format ): array {
		// Sanitize export type to prevent directory traversal
		$export_type = sanitize_file_name( $export_type );
		$format = sanitize_file_name( $format );
		
		$timestamp = date( 'Y-m-d_H-i-s' );
		$file_name = "fp_dms_{$export_type}_{$timestamp}.{$format}";
		$upload_dir = wp_upload_dir();
		
		// Ensure we have a valid upload directory
		if ( empty( $upload_dir['basedir'] ) || ! is_writable( $upload_dir['basedir'] ) ) {
			throw new \Exception( __( 'Directory di upload non accessibile.', 'fp-digital-marketing' ) );
		}
		
		$export_dir = $upload_dir['basedir'] . '/fp-dms-exports';
		
		// Create export directory if it doesn't exist
		if ( ! file_exists( $export_dir ) ) {
			if ( ! wp_mkdir_p( $export_dir ) ) {
				throw new \Exception( __( 'Impossibile creare la directory di esportazione.', 'fp-digital-marketing' ) );
			}
			
			// Add .htaccess to protect directory
			$htaccess_content = "Order deny,allow\nDeny from all\n";
			$htaccess_result = file_put_contents( 
				$export_dir . '/.htaccess', 
				$htaccess_content 
			);
			
			if ( false === $htaccess_result ) {
				// Log warning but don't fail - security protection is preferred but not critical
				error_log( 'FP DMS: Could not create .htaccess protection for export directory' );
			}
		}

		$file_path = $export_dir . '/' . $file_name;

		// Generate file content based on format
		switch ( $format ) {
			case self::FORMAT_CSV:
				$content = self::generate_csv_content( $data );
				break;
			
			case self::FORMAT_JSON:
				$content = self::generate_json_content( $data );
				break;
			
			case self::FORMAT_XML:
				$content = self::generate_xml_content( $data );
				break;
			
			case self::FORMAT_PDF:
				$content = self::generate_pdf_content( $data, $export_type );
				break;
			
			default:
				throw new \Exception( 'Formato non supportato.' );
		}

		// Write file
		$result = file_put_contents( $file_path, $content );
		if ( $result === false ) {
			throw new \Exception( 'Impossibile creare il file di esportazione.' );
		}

		// Generate secure token for download
		$token = wp_generate_password( 32, false );
		$token_data = [
			'file_path' => $file_path,
			'file_name' => $file_name,
			'user_id' => get_current_user_id(),
			'created_at' => time(),
			'expires_at' => time() + ( 2 * HOUR_IN_SECONDS ), // 2 hours
		];

		set_transient( "fp_dms_export_{$token}", $token_data, 2 * HOUR_IN_SECONDS );

		return [
			'file_path' => $file_path,
			'file_name' => $file_name,
			'file_size' => filesize( $file_path ),
			'token' => $token,
		];
	}

	/**
	 * Generate CSV content
	 *
	 * @param array $data Export data
	 * @return string CSV content
	 */
	private static function generate_csv_content( array $data ): string {
		if ( empty( $data ) ) {
			return '';
		}

		$output = fopen( 'php://temp', 'r+' );
		
		// Add BOM for UTF-8
		fwrite( $output, "\xEF\xBB\xBF" );
		
		// Add header
		fputcsv( $output, array_keys( $data[0] ) );
		
		// Add data rows
		foreach ( $data as $row ) {
			fputcsv( $output, $row );
		}
		
		rewind( $output );
		$content = stream_get_contents( $output );
		fclose( $output );
		
		return $content;
	}

	/**
	 * Generate JSON content
	 *
	 * @param array $data Export data
	 * @return string JSON content
	 */
	private static function generate_json_content( array $data ): string {
		$export_info = [
			'export_date' => current_time( 'c' ),
			'plugin_version' => FP_DIGITAL_MARKETING_VERSION,
			'row_count' => count( $data ),
			'data' => $data,
		];

		return wp_json_encode( $export_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Generate XML content
	 *
	 * @param array $data Export data
	 * @return string XML content
	 */
	private static function generate_xml_content( array $data ): string {
		$xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><export></export>' );
		$xml->addAttribute( 'export_date', current_time( 'c' ) );
		$xml->addAttribute( 'plugin_version', FP_DIGITAL_MARKETING_VERSION );
		$xml->addAttribute( 'row_count', (string) count( $data ) );

		$records = $xml->addChild( 'records' );
		
		foreach ( $data as $index => $row ) {
			$record = $records->addChild( 'record' );
			$record->addAttribute( 'index', (string) $index );
			
			foreach ( $row as $key => $value ) {
				$field = $record->addChild( 'field', htmlspecialchars( (string) $value ) );
				$field->addAttribute( 'name', $key );
			}
		}

		return $xml->asXML();
	}

	/**
	 * Generate PDF content
	 *
	 * @param array $data Export data
	 * @param string $export_type Export type for title
	 * @return string PDF content
	 * @throws \Exception If PDF generation fails
	 */
	private static function generate_pdf_content( array $data, string $export_type ): string {
		// Increase memory limit temporarily for PDF generation
		$original_memory_limit = ini_get( 'memory_limit' );
		if ( function_exists( 'ini_set' ) ) {
			ini_set( 'memory_limit', '256M' );
		}

		// Check if dompdf is available
		if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
			// Try to load via composer autoload
			$plugin_dir = dirname( dirname( dirname( __FILE__ ) ) );
			$autoload_path = $plugin_dir . '/vendor/autoload.php';
			if ( file_exists( $autoload_path ) && is_readable( $autoload_path ) ) {
				try {
					require_once $autoload_path;
				} catch ( \Throwable $e ) {
					// Continue if autoload fails
				}
			}
			
			// If still not available, throw exception
			if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
				// Restore memory limit before throwing
				if ( function_exists( 'ini_set' ) && $original_memory_limit ) {
					ini_set( 'memory_limit', $original_memory_limit );
				}
				throw new \Exception( __( 'PDF generation non disponibile. Utilizzare formato HTML o installare le dipendenze dompdf.', 'fp-digital-marketing' ) );
			}
		}

		$html = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>FP Digital Marketing Export</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; }
		table { width: 100%; border-collapse: collapse; margin-top: 20px; }
		th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
		th { background-color: #f2f2f2; }
		.header { text-align: center; margin-bottom: 30px; }
	</style>
</head>
<body>
	<div class="header">
		<h1>FP Digital Marketing Suite</h1>
		<h2>' . ucfirst( $export_type ) . ' Export</h2>
		<p>Generated on: ' . current_time( 'Y-m-d H:i:s' ) . '</p>
	</div>';

		if ( ! empty( $data ) ) {
			$html .= '<table><thead><tr>';
			foreach ( array_keys( $data[0] ) as $header ) {
				$html .= '<th>' . esc_html( $header ) . '</th>';
			}
			$html .= '</tr></thead><tbody>';

			foreach ( $data as $row ) {
				$html .= '<tr>';
				foreach ( $row as $value ) {
					$html .= '<td>' . esc_html( (string) $value ) . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
		} else {
			$html .= '<p>No data available for export.</p>';
		}

		$html .= '</body></html>';

		try {
			// Check available memory before PDF generation
			$memory_usage = memory_get_usage( true );
			$memory_limit = ini_get( 'memory_limit' );
			$memory_limit_bytes = self::return_bytes( $memory_limit );
			
			if ( $memory_usage > ( $memory_limit_bytes * 0.8 ) ) {
				throw new \Exception( __( 'Memoria insufficiente per la generazione PDF.', 'fp-digital-marketing' ) );
			}

			// Generate PDF using dompdf with safer options
			$dompdf = new \Dompdf\Dompdf([
				'isPhpEnabled' => false,
				'isRemoteEnabled' => false,
				'isJavascriptEnabled' => false
			]);
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			
			$pdf_content = $dompdf->output();
			
			// Verify PDF was generated successfully
			if ( empty( $pdf_content ) || strlen( $pdf_content ) < 100 ) {
				throw new \Exception( __( 'PDF generato vuoto o corrotto.', 'fp-digital-marketing' ) );
			}
			
			return $pdf_content;
			
		} catch ( \Exception $e ) {
			// If PDF generation fails, throw exception to be handled by caller
			throw new \Exception( 
				sprintf( 
					__( 'Errore nella generazione PDF: %s', 'fp-digital-marketing' ), 
					$e->getMessage() 
				) 
			);
		} catch ( \Error $e ) {
			// Handle fatal errors during PDF generation
			throw new \Exception( 
				sprintf( 
					__( 'Errore fatale nella generazione PDF: %s', 'fp-digital-marketing' ), 
					$e->getMessage() 
				) 
			);
		} finally {
			// Restore original memory limit
			if ( function_exists( 'ini_set' ) && $original_memory_limit ) {
				ini_set( 'memory_limit', $original_memory_limit );
			}
		}
	}

	/**
	 * Convert memory limit string to bytes
	 *
	 * @param string $val Memory limit string (e.g., '128M')
	 * @return int Memory limit in bytes
	 */
	private static function return_bytes( string $val ): int {
		$val = trim( $val );
		$last = strtolower( $val[ strlen( $val ) - 1 ] );
		$val = (int) $val;
		
		switch( $last ) {
			case 'g':
				$val *= 1024;
				// Fall through
			case 'm':
				$val *= 1024;
				// Fall through
			case 'k':
				$val *= 1024;
		}
		
		return $val;
	}

	/**
	 * Get download URL for export token
	 *
	 * @param string $token Export token
	 * @return string Download URL
	 */
       private static function get_download_url( string $token ): string {
               return add_query_arg(
                       [
                               'action' => 'fp_download_export',
                               'token'  => $token,
                       ],
                       admin_url( 'admin-ajax.php' )
               );
       }

	/**
	 * Handle export download request
	 *
	 * @return void
	 */
	public static function handle_download_request(): void {
		$token = sanitize_text_field( $_GET['token'] ?? '' );
		
		if ( empty( $token ) ) {
			wp_die( esc_html__( 'Token di download mancante.', 'fp-digital-marketing' ) );
		}

		$token_data = get_transient( "fp_dms_export_{$token}" );
		if ( false === $token_data ) {
			wp_die( esc_html__( 'Token di download non valido o scaduto.', 'fp-digital-marketing' ) );
		}

		// Verify user can download this file
		if ( (int) $token_data['user_id'] !== get_current_user_id() ) {
			wp_die( esc_html__( 'Non autorizzato a scaricare questo file.', 'fp-digital-marketing' ) );
		}

		$file_path = $token_data['file_path'];
		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'File di esportazione non trovato.', 'fp-digital-marketing' ) );
		}

		// Set headers for download
		$file_name = $token_data['file_name'];
		$file_size = filesize( $file_path );
		$mime_type = self::get_mime_type( $file_path );

		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
		header( 'Content-Length: ' . $file_size );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Output file
		readfile( $file_path );

		// Clean up
		delete_transient( "fp_dms_export_{$token}" );
		unlink( $file_path );

		exit;
	}

	/**
	 * Get MIME type for file
	 *
	 * @param string $file_path File path
	 * @return string MIME type
	 */
	private static function get_mime_type( string $file_path ): string {
		$extension = pathinfo( $file_path, PATHINFO_EXTENSION );
		
		$mime_types = [
			'csv' => 'text/csv',
			'json' => 'application/json',
			'xml' => 'application/xml',
			'pdf' => 'application/pdf',
		];

		return $mime_types[ $extension ] ?? 'application/octet-stream';
	}

	/**
	 * Enqueue export assets
	 *
	 * @param string $hook_suffix Current admin page
	 * @return void
	 */
	public static function enqueue_export_assets( string $hook_suffix ): void {
		// Only load on specific admin pages
		$allowed_pages = [
			'fp-digital-marketing_page_fp-digital-marketing-dashboard',
			'fp-digital-marketing_page_fp-digital-marketing-reports',
		];

		if ( ! in_array( $hook_suffix, $allowed_pages, true ) ) {
			return;
		}

		wp_localize_script(
			'fp-dms-dashboard',
			'fpDmsExport',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'fp_dms_export_nonce' ),
				'strings' => [
					'exporting' => __( 'Esportazione in corso...', 'fp-digital-marketing' ),
					'exportComplete' => __( 'Esportazione completata!', 'fp-digital-marketing' ),
					'exportError' => __( 'Errore durante l\'esportazione.', 'fp-digital-marketing' ),
					'confirmExport' => __( 'Confermi l\'esportazione dei dati selezionati?', 'fp-digital-marketing' ),
				]
			]
		);
	}

	/**
	 * Cleanup old export files
	 *
	 * @return void
	 */
	public static function cleanup_old_exports(): void {
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/fp-dms-exports';
		
		if ( ! is_dir( $export_dir ) ) {
			return;
		}

		$files = glob( $export_dir . '/fp_dms_*' );
		$cutoff_time = time() - ( 24 * HOUR_IN_SECONDS ); // 24 hours

		foreach ( $files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $cutoff_time ) {
				unlink( $file );
			}
		}
	}
}