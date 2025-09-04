<?php
/**
 * Report Generator for Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use Dompdf\Dompdf;
use Dompdf\Options;
use FP\DigitalMarketing\Models\MetricsCache;

/**
 * ReportGenerator class for generating HTML and PDF reports
 */
class ReportGenerator {

	/**
	 * Helper function to get translated text or fallback
	 *
	 * @param string $text Text to translate
	 * @param string $domain Text domain
	 * @return string Translated text or original text
	 */
	private static function __( string $text, string $domain = 'fp-digital-marketing' ): string {
		return function_exists( '__' ) ? __( $text, $domain ) : $text;
	}

	/**
	 * Helper function to get current time or fallback
	 *
	 * @param string $format Time format
	 * @return string Current time
	 */
	private static function current_time( string $format = 'mysql' ): string {
		if ( function_exists( 'current_time' ) ) {
			return current_time( $format );
		}
		return $format === 'mysql' ? date( 'Y-m-d H:i:s' ) : date( $format );
	}

	/**
	 * Helper function to get current user ID or fallback
	 *
	 * @return int User ID or 0
	 */
	private static function get_current_user_id(): int {
		return function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
	}

	/**
	 * Generate a demo report with mock data
	 *
	 * @param int $client_id Client ID for the report
	 * @return array Report data array
	 */
	public static function generate_demo_report_data( int $client_id = 1 ): array {
		// Generate mock KPI data
		$current_month = date( 'Y-m-01' );
		$previous_month = date( 'Y-m-01', strtotime( '-1 month' ) );
		
		return [
			'client_id'     => $client_id,
			'period_start'  => $previous_month,
			'period_end'    => date( 'Y-m-t', strtotime( $previous_month ) ),
			'generated_at'  => self::current_time( 'mysql' ),
			'kpis'          => [
				'sessions' => [
					'value'           => 12450,
					'previous_value'  => 11230,
					'change_percent'  => 10.9,
					'change_type'     => 'increase',
				],
				'users' => [
					'value'           => 8940,
					'previous_value'  => 8120,
					'change_percent'  => 10.1,
					'change_type'     => 'increase',
				],
				'conversion_rate' => [
					'value'           => 3.42,
					'previous_value'  => 3.15,
					'change_percent'  => 8.6,
					'change_type'     => 'increase',
				],
				'revenue' => [
					'value'           => 28500.75,
					'previous_value'  => 25200.40,
					'change_percent'  => 13.1,
					'change_type'     => 'increase',
				],
			],
			'channels'      => [
				[
					'name'     => self::__( 'Organic Search', 'fp-digital-marketing' ),
					'sessions' => 5680,
					'revenue'  => 12400.50,
				],
				[
					'name'     => self::__( 'Google Ads', 'fp-digital-marketing' ),
					'sessions' => 3250,
					'revenue'  => 8900.25,
				],
				[
					'name'     => self::__( 'Facebook Ads', 'fp-digital-marketing' ),
					'sessions' => 2100,
					'revenue'  => 4200.00,
				],
				[
					'name'     => self::__( 'Direct', 'fp-digital-marketing' ),
					'sessions' => 1420,
					'revenue'  => 3000.00,
				],
			],
		];
	}

	/**
	 * Generate HTML report
	 *
	 * @param array $report_data Report data array
	 * @return string HTML content
	 */
	public static function generate_html_report( array $report_data ): string {
		$html = self::get_html_template();
		
		// Replace placeholders with actual data
		$period_start_timestamp = strtotime( $report_data['period_start'] );
		$period_end_timestamp = strtotime( $report_data['period_end'] );
		$generated_at_timestamp = strtotime( $report_data['generated_at'] );
		
		$replacements = [
			'{{REPORT_TITLE}}'     => sprintf( self::__( 'Digital Marketing Report - Client %d', 'fp-digital-marketing' ), $report_data['client_id'] ),
			'{{PERIOD_START}}'     => $period_start_timestamp ? date( 'd/m/Y', $period_start_timestamp ) : $report_data['period_start'],
			'{{PERIOD_END}}'       => $period_end_timestamp ? date( 'd/m/Y', $period_end_timestamp ) : $report_data['period_end'],
			'{{GENERATED_AT}}'     => $generated_at_timestamp ? date( 'd/m/Y H:i', $generated_at_timestamp ) : $report_data['generated_at'],
			'{{SESSIONS_VALUE}}'   => number_format( $report_data['kpis']['sessions']['value'] ),
			'{{SESSIONS_CHANGE}}'  => self::format_change( $report_data['kpis']['sessions'] ),
			'{{USERS_VALUE}}'      => number_format( $report_data['kpis']['users']['value'] ),
			'{{USERS_CHANGE}}'     => self::format_change( $report_data['kpis']['users'] ),
			'{{CONVERSION_VALUE}}' => number_format( $report_data['kpis']['conversion_rate']['value'], 2 ) . '%',
			'{{CONVERSION_CHANGE}}' => self::format_change( $report_data['kpis']['conversion_rate'] ),
			'{{REVENUE_VALUE}}'    => '€' . number_format( $report_data['kpis']['revenue']['value'], 2 ),
			'{{REVENUE_CHANGE}}'   => self::format_change( $report_data['kpis']['revenue'] ),
			'{{CHANNELS_TABLE}}'   => self::generate_channels_table( $report_data['channels'] ),
		];

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $html );
	}

	/**
	 * Generate PDF report
	 *
	 * @param array $report_data Report data array
	 * @return string PDF binary content
	 * @throws \Exception If PDF generation fails
	 */
	public static function generate_pdf_report( array $report_data ): string {
		// Check if dompdf is available
		if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
			// Try to load via composer autoload
			$plugin_dir = dirname( dirname( dirname( __FILE__ ) ) );
			$autoload_path = $plugin_dir . '/vendor/autoload.php';
			if ( file_exists( $autoload_path ) ) {
				require_once $autoload_path;
			}
			
			// If still not available, throw exception
			if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
				throw new \Exception( self::__( 'PDF generation non disponibile. Utilizzare formato HTML o installare le dipendenze.', 'fp-digital-marketing' ) );
			}
		}

		try {
			$html = self::generate_html_report( $report_data );

			$options = new Options();
			$options->set( 'defaultFont', 'Arial' );
			$options->set( 'isRemoteEnabled', true );

			$dompdf = new Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			return $dompdf->output();
		} catch ( \Exception $e ) {
			// If PDF generation fails, throw exception to be handled by caller
			throw new \Exception( 
				sprintf( 
					self::__( 'Errore nella generazione PDF: %s', 'fp-digital-marketing' ), 
					$e->getMessage() 
				) 
			);
		}
	}

	/**
	 * Generate CSV report
	 *
	 * @param array $report_data Report data array
	 * @param string $separator CSV separator (default: ',')
	 * @return string CSV content in UTF-8
	 */
	public static function generate_csv_report( array $report_data, string $separator = ',' ): string {
		$csv_data = [];
		
		// Add header information
		$csv_data[] = [
			self::__( 'Report Type', 'fp-digital-marketing' ),
			self::__( 'Digital Marketing Report', 'fp-digital-marketing' )
		];
		$csv_data[] = [
			self::__( 'Client ID', 'fp-digital-marketing' ),
			$report_data['client_id']
		];
		$csv_data[] = [
			self::__( 'Period Start', 'fp-digital-marketing' ),
			$report_data['period_start']
		];
		$csv_data[] = [
			self::__( 'Period End', 'fp-digital-marketing' ),
			$report_data['period_end']
		];
		$csv_data[] = [
			self::__( 'Generated At', 'fp-digital-marketing' ),
			$report_data['generated_at']
		];
		$csv_data[] = []; // Empty row for separation

		// Add KPIs section
		$csv_data[] = [
			self::__( 'KPIs', 'fp-digital-marketing' ),
			self::__( 'Current Value', 'fp-digital-marketing' ),
			self::__( 'Previous Value', 'fp-digital-marketing' ),
			self::__( 'Change %', 'fp-digital-marketing' ),
			self::__( 'Change Type', 'fp-digital-marketing' )
		];

		foreach ( $report_data['kpis'] as $kpi_name => $kpi_data ) {
			$csv_data[] = [
				self::get_kpi_label( $kpi_name ),
				$kpi_data['value'],
				$kpi_data['previous_value'],
				$kpi_data['change_percent'],
				$kpi_data['change_type']
			];
		}

		$csv_data[] = []; // Empty row for separation

		// Add channels section
		if ( ! empty( $report_data['channels'] ) ) {
			$csv_data[] = [
				self::__( 'Channel', 'fp-digital-marketing' ),
				self::__( 'Sessions', 'fp-digital-marketing' ),
				self::__( 'Revenue', 'fp-digital-marketing' )
			];

			foreach ( $report_data['channels'] as $channel ) {
				$csv_data[] = [
					$channel['name'],
					$channel['sessions'],
					$channel['revenue']
				];
			}
		}

		return self::array_to_csv( $csv_data, $separator );
	}

	/**
	 * Format change percentage with arrow and color
	 *
	 * @param array $kpi KPI data with change information
	 * @return string Formatted change string
	 */
	private static function format_change( array $kpi ): string {
		$arrow = $kpi['change_type'] === 'increase' ? '↗' : '↘';
		$class = $kpi['change_type'] === 'increase' ? 'positive' : 'negative';
		$sign = $kpi['change_type'] === 'increase' ? '+' : '-';
		
		return sprintf(
			'<span class="change %s">%s %s%.1f%%</span>',
			$class,
			$arrow,
			$sign,
			abs( $kpi['change_percent'] )
		);
	}

	/**
	 * Generate channels performance table
	 *
	 * @param array $channels Channels data
	 * @return string HTML table
	 */
	private static function generate_channels_table( array $channels ): string {
		$html = '<table class="channels-table">';
		$html .= '<thead>';
		$html .= '<tr>';
		$html .= '<th>' . self::__( 'Canale', 'fp-digital-marketing' ) . '</th>';
		$html .= '<th>' . self::__( 'Sessioni', 'fp-digital-marketing' ) . '</th>';
		$html .= '<th>' . self::__( 'Fatturato', 'fp-digital-marketing' ) . '</th>';
		$html .= '</tr>';
		$html .= '</thead>';
		$html .= '<tbody>';

		foreach ( $channels as $channel ) {
			$html .= '<tr>';
			$html .= '<td>' . ( function_exists( 'esc_html' ) ? esc_html( $channel['name'] ) : htmlspecialchars( $channel['name'], ENT_QUOTES, 'UTF-8' ) ) . '</td>';
			$html .= '<td>' . number_format( $channel['sessions'] ) . '</td>';
			$html .= '<td>€' . number_format( $channel['revenue'], 2 ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody>';
		$html .= '</table>';

		return $html;
	}

	/**
	 * Convert array to CSV string
	 *
	 * @param array $data Data array
	 * @param string $separator CSV separator
	 * @return string CSV content in UTF-8
	 */
	private static function array_to_csv( array $data, string $separator = ',' ): string {
		$output = fopen( 'php://temp', 'r+' );
		
		foreach ( $data as $row ) {
			fputcsv( $output, $row, $separator );
		}
		
		rewind( $output );
		$csv_content = stream_get_contents( $output );
		fclose( $output );
		
		// Ensure UTF-8 encoding
		return "\xEF\xBB\xBF" . $csv_content; // Add BOM for UTF-8
	}

	/**
	 * Get human-readable label for KPI
	 *
	 * @param string $kpi_name KPI identifier
	 * @return string Translated KPI label
	 */
	private static function get_kpi_label( string $kpi_name ): string {
		$labels = [
			'sessions' => self::__( 'Sessions', 'fp-digital-marketing' ),
			'users' => self::__( 'Users', 'fp-digital-marketing' ),
			'conversion_rate' => self::__( 'Conversion Rate', 'fp-digital-marketing' ),
			'revenue' => self::__( 'Revenue', 'fp-digital-marketing' ),
			'pageviews' => self::__( 'Page Views', 'fp-digital-marketing' ),
			'bounce_rate' => self::__( 'Bounce Rate', 'fp-digital-marketing' ),
			'impressions' => self::__( 'Impressions', 'fp-digital-marketing' ),
			'clicks' => self::__( 'Clicks', 'fp-digital-marketing' ),
			'ctr' => self::__( 'Click-Through Rate', 'fp-digital-marketing' ),
		];

		return $labels[ $kpi_name ] ?? ucfirst( str_replace( '_', ' ', $kpi_name ) );
	}

	/**
	 * Log report generation
	 *
	 * @param int $client_id Client ID
	 * @param string $format Report format (pdf, csv, html)
	 * @param int $file_size File size in bytes
	 * @param bool $success Generation success status
	 * @param string $error_message Error message if failed
	 * @return void
	 */
	public static function log_report_generation( int $client_id, string $format, int $file_size = 0, bool $success = true, string $error_message = '' ): void {
		$log_entry = [
			'timestamp' => self::current_time( 'mysql' ),
			'client_id' => $client_id,
			'format' => $format,
			'file_size' => $file_size,
			'success' => $success,
			'error_message' => $error_message,
			'user_id' => self::get_current_user_id(),
		];

		// Store in WordPress options table as a simple log
		$existing_logs = function_exists( 'get_option' ) ? get_option( 'fp_dms_report_logs', [] ) : [];
		
		// Keep only last 1000 entries to prevent bloating
		if ( count( $existing_logs ) >= 1000 ) {
			$existing_logs = array_slice( $existing_logs, -999 );
		}
		
		$existing_logs[] = $log_entry;
		
		if ( function_exists( 'update_option' ) ) {
			update_option( 'fp_dms_report_logs', $existing_logs );
		}

		// Also log to WordPress debug log if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message = sprintf(
				'FP DMS Report Generated - Client: %d, Format: %s, Size: %d bytes, Success: %s',
				$client_id,
				$format,
				$file_size,
				$success ? 'Yes' : 'No (' . $error_message . ')'
			);
			error_log( $log_message );
		}
	}

	/**
	 * Get report generation logs
	 *
	 * @param int $limit Number of logs to return (default: 50)
	 * @param int $client_id Optional client ID filter
	 * @return array Array of log entries
	 */
	public static function get_report_logs( int $limit = 50, int $client_id = 0 ): array {
		$logs = function_exists( 'get_option' ) ? get_option( 'fp_dms_report_logs', [] ) : [];
		
		// Filter by client if specified
		if ( $client_id > 0 ) {
			$logs = array_filter( $logs, function( $log ) use ( $client_id ) {
				return $log['client_id'] === $client_id;
			} );
		}
		
		// Sort by timestamp descending (newest first)
		usort( $logs, function( $a, $b ) {
			return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
		} );
		
		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Validate report data and check for errors
	 *
	 * @param array $report_data Report data array
	 * @param int $max_file_size Maximum allowed file size in bytes (default: 50MB)
	 * @return array Array with 'valid' boolean and 'errors' array
	 */
	public static function validate_report_data( array $report_data, int $max_file_size = 52428800 ): array {
		$errors = [];
		
		// Check required fields
		$required_fields = [ 'client_id', 'period_start', 'period_end', 'kpis' ];
		foreach ( $required_fields as $field ) {
			if ( ! isset( $report_data[ $field ] ) ) {
				$errors[] = sprintf( self::__( 'Missing required field: %s', 'fp-digital-marketing' ), $field );
			}
		}
		
		// Check if KPIs data is valid
		if ( isset( $report_data['kpis'] ) && is_array( $report_data['kpis'] ) ) {
			if ( empty( $report_data['kpis'] ) ) {
				$errors[] = self::__( 'No metrics data available for the selected period', 'fp-digital-marketing' );
			} else {
				foreach ( $report_data['kpis'] as $kpi_name => $kpi_data ) {
					if ( ! is_array( $kpi_data ) || ! isset( $kpi_data['value'] ) ) {
						$errors[] = sprintf( self::__( 'Invalid metric data for: %s', 'fp-digital-marketing' ), $kpi_name );
					}
				}
			}
		}
		
		// Estimate file size for different formats
		$estimated_sizes = self::estimate_report_sizes( $report_data );
		foreach ( $estimated_sizes as $format => $size ) {
			if ( $size > $max_file_size ) {
				$errors[] = sprintf( 
					self::__( 'Estimated %s file size (%s) exceeds maximum allowed size (%s)', 'fp-digital-marketing' ),
					strtoupper( $format ),
					function_exists( 'size_format' ) ? size_format( $size ) : number_format( $size ) . ' bytes',
					function_exists( 'size_format' ) ? size_format( $max_file_size ) : number_format( $max_file_size ) . ' bytes'
				);
			}
		}
		
		return [
			'valid' => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Estimate file sizes for different report formats
	 *
	 * @param array $report_data Report data array
	 * @return array Array with format => estimated_size_in_bytes
	 */
	private static function estimate_report_sizes( array $report_data ): array {
		$kpi_count = isset( $report_data['kpis'] ) ? count( $report_data['kpis'] ) : 0;
		$channel_count = isset( $report_data['channels'] ) ? count( $report_data['channels'] ) : 0;
		
		// Rough estimates based on typical data sizes
		$csv_size = 1024 + ( $kpi_count * 150 ) + ( $channel_count * 100 ); // Base + KPIs + channels
		$html_size = 15000 + ( $kpi_count * 300 ) + ( $channel_count * 200 ); // Larger due to HTML/CSS
		$pdf_size = $html_size * 1.5; // PDFs are typically larger than HTML
		
		return [
			'csv' => $csv_size,
			'html' => $html_size,
			'pdf' => (int) $pdf_size,
		];
	}

	/**
	 * Get HTML template for reports
	 *
	 * @return string HTML template
	 */
	private static function get_html_template(): string {
		return '
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{REPORT_TITLE}}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #0073aa;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #0073aa;
            margin: 0;
            font-size: 28px;
        }
        .period {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        .kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .kpi-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #0073aa;
        }
        .kpi-value {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            margin: 5px 0;
        }
        .kpi-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .change {
            font-size: 12px;
            font-weight: bold;
        }
        .change.positive {
            color: #00a32a;
        }
        .change.negative {
            color: #d63638;
        }
        .section {
            margin: 30px 0;
        }
        .section h2 {
            color: #0073aa;
            border-bottom: 2px solid #e2e4e7;
            padding-bottom: 10px;
        }
        .channels-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .channels-table th,
        .channels-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e4e7;
        }
        .channels-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #0073aa;
        }
        .channels-table tr:hover {
            background-color: #f8f9fa;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e4e7;
            color: #666;
            font-size: 12px;
        }
        @media print {
            body {
                background-color: white;
            }
            .container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{REPORT_TITLE}}</h1>
            <div class="period">{{PERIOD_START}} - {{PERIOD_END}}</div>
        </div>

        <div class="kpis">
            <div class="kpi-card">
                <div class="kpi-label">Sessioni</div>
                <div class="kpi-value">{{SESSIONS_VALUE}}</div>
                <div>{{SESSIONS_CHANGE}}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Utenti</div>
                <div class="kpi-value">{{USERS_VALUE}}</div>
                <div>{{USERS_CHANGE}}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Tasso di Conversione</div>
                <div class="kpi-value">{{CONVERSION_VALUE}}</div>
                <div>{{CONVERSION_CHANGE}}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Fatturato</div>
                <div class="kpi-value">{{REVENUE_VALUE}}</div>
                <div>{{REVENUE_CHANGE}}</div>
            </div>
        </div>

        <div class="section">
            <h2>Performance per Canale</h2>
            {{CHANNELS_TABLE}}
        </div>

        <div class="footer">
            Generato il {{GENERATED_AT}} da FP Digital Marketing Suite
        </div>
    </div>
</body>
</html>';
	}
}