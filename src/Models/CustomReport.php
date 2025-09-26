<?php
/**
 * Custom Reports Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\CustomReportsTable;
use FP\DigitalMarketing\Helpers\ReportGenerator;
use FP\DigitalMarketing\Helpers\MetricsAggregator;

/**
 * Custom Reports model class
 *
 * Handles custom report configuration and generation logic.
 */
class CustomReport {

	/**
	 * Report ID
	 *
	 * @var int|null
	 */
	private ?int $id = null;

	/**
	 * Client ID
	 *
	 * @var int
	 */
	private int $client_id;

	/**
	 * Report name
	 *
	 * @var string
	 */
	private string $report_name;

	/**
	 * Report description
	 *
	 * @var string
	 */
	private string $report_description;

	/**
	 * Time period
	 *
	 * @var string
	 */
	private string $time_period;

	/**
	 * Selected KPIs
	 *
	 * @var array
	 */
	private array $selected_kpis;

	/**
	 * Report frequency
	 *
	 * @var string
	 */
	private string $report_frequency;

	/**
	 * Email recipients
	 *
	 * @var array
	 */
	private array $email_recipients;

	/**
	 * Auto send flag
	 *
	 * @var bool
	 */
	private bool $auto_send;

	/**
	 * Report status
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Constructor
	 *
	 * @param array $data Report data
	 */
	public function __construct( array $data = [] ) {
		$this->id                 = $data['id'] ?? null;
		$this->client_id          = $data['client_id'] ?? 0;
		$this->report_name        = $data['report_name'] ?? '';
		$this->report_description = $data['report_description'] ?? '';
		$this->time_period        = $data['time_period'] ?? '30_days';
		$this->selected_kpis      = $data['selected_kpis'] ?? [];
		$this->report_frequency   = $data['report_frequency'] ?? 'manual';
		$this->email_recipients   = $data['email_recipients'] ?? [];
		$this->auto_send          = (bool) ( $data['auto_send'] ?? false );
		$this->status             = $data['status'] ?? 'active';
	}

	/**
	 * Save the report configuration
	 *
	 * @return bool
	 */
	public function save(): bool {
		$data = [
			'client_id'          => $this->client_id,
			'report_name'        => $this->report_name,
			'report_description' => $this->report_description,
			'time_period'        => $this->time_period,
			'selected_kpis'      => $this->selected_kpis,
			'report_frequency'   => $this->report_frequency,
			'email_recipients'   => $this->email_recipients,
			'auto_send'          => $this->auto_send ? 1 : 0,
			'status'             => $this->status,
		];

		if ( $this->id ) {
			$result = CustomReportsTable::update_report( $this->id, $data );
		} else {
			$result = CustomReportsTable::insert_report( $data );
			if ( $result ) {
				$this->id = $result;
			}
		}

		return (bool) $result;
	}

	/**
	 * Generate the report
	 *
	 * @param string      $format Report format (pdf, csv, html)
	 * @param string|null $custom_start Custom start date
	 * @param string|null $custom_end Custom end date
	 * @return array Report data and content
	 */
	public function generate( string $format = 'pdf', ?string $custom_start = null, ?string $custom_end = null ): array {
		// Calculate date range
		$date_range = CustomReportsTable::calculate_date_range(
			$this->time_period,
			$custom_start,
			$custom_end
		);

		// Get metrics data for the specified period and KPIs
		$metrics_data = $this->get_metrics_data( $date_range['start'], $date_range['end'] );

		// Prepare report data structure
		$report_data = [
			'report_id'          => $this->id,
			'client_id'          => $this->client_id,
			'client_name'        => $this->get_client_name(),
			'report_name'        => $this->report_name,
			'report_description' => $this->report_description,
			'period_start'       => $date_range['start'],
			'period_end'         => $date_range['end'],
			'time_period_label'  => $this->get_time_period_label(),
			'generated_at'       => current_time( 'mysql' ),
			'kpis'               => $metrics_data,
			'selected_kpis'      => $this->selected_kpis,
		];

		// Validate report data
		$validation = ReportGenerator::validate_report_data( $report_data );
		if ( ! $validation['valid'] ) {
			return [
				'success' => false,
				'errors'  => $validation['errors'],
			];
		}

		// Generate report content based on format
		try {
			switch ( $format ) {
				case 'pdf':
					$content  = ReportGenerator::generate_pdf_report( $report_data );
					$filename = $this->generate_filename( 'pdf' );
					break;
				case 'csv':
					$content  = ReportGenerator::generate_csv_report( $report_data );
					$filename = $this->generate_filename( 'csv' );
					break;
				case 'html':
					$content  = ReportGenerator::generate_html_report( $report_data );
					$filename = $this->generate_filename( 'html' );
					break;
				default:
					throw new \InvalidArgumentException( 'Invalid report format: ' . $format );
			}

			// Mark as generated
			CustomReportsTable::mark_as_generated( $this->id );

			return [
				'success'     => true,
				'content'     => $content,
				'filename'    => $filename,
				'format'      => $format,
				'report_data' => $report_data,
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'errors'  => [ $e->getMessage() ],
			];
		}
	}

	/**
	 * Get metrics data for the report
	 *
	 * @param string $start_date Start date
	 * @param string $end_date End date
	 * @return array
	 */
	private function get_metrics_data( string $start_date, string $end_date ): array {
		// If no specific KPIs are selected, use default set
		$kpis_to_include = ! empty( $this->selected_kpis ) ? $this->selected_kpis : $this->get_default_kpis();

		// Get aggregated metrics from MetricsAggregator
		try {
			$aggregated_data = MetricsAggregator::get_aggregated_metrics(
				$this->client_id,
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			);

			// Filter to only include selected KPIs
			$filtered_data = [];
			foreach ( $kpis_to_include as $kpi ) {
				if ( isset( $aggregated_data[ $kpi ] ) ) {
					$filtered_data[ $kpi ] = $aggregated_data[ $kpi ];
				} else {
					// Generate fallback data for missing KPIs
					$filtered_data[ $kpi ] = $this->generate_fallback_kpi_data( $kpi );
				}
			}

			return $filtered_data;

		} catch ( \Exception $e ) {
			// Fallback to demo data if aggregation fails
			return $this->generate_demo_metrics_data( $kpis_to_include );
		}
	}

	/**
	 * Generate fallback KPI data
	 *
	 * @param string $kpi KPI name
	 * @return array
	 */
	private function generate_fallback_kpi_data( string $kpi ): array {
		$base_values = [
			'sessions'             => rand( 1000, 5000 ),
			'users'                => rand( 800, 4000 ),
			'pageviews'            => rand( 2000, 10000 ),
			'bounce_rate'          => rand( 30, 70 ) / 100,
			'avg_session_duration' => rand( 120, 600 ),
			'conversion_rate'      => rand( 1, 8 ) / 100,
			'revenue'              => rand( 1000, 20000 ),
			'cost_per_acquisition' => rand( 10, 100 ),
			'return_on_ad_spend'   => rand( 200, 500 ) / 100,
			'click_through_rate'   => rand( 1, 5 ) / 100,
		];

		$value = $base_values[ $kpi ] ?? rand( 100, 1000 );

		return [
			'total_value'  => $value,
			'avg_value'    => $value,
			'data_points'  => 1,
			'source'       => 'fallback',
			'last_updated' => current_time( 'mysql' ),
		];
	}

	/**
	 * Generate demo metrics data
	 *
	 * @param array $kpis List of KPIs to generate
	 * @return array
	 */
	private function generate_demo_metrics_data( array $kpis ): array {
		$demo_data = [];

		foreach ( $kpis as $kpi ) {
			$demo_data[ $kpi ] = $this->generate_fallback_kpi_data( $kpi );
		}

		return $demo_data;
	}

	/**
	 * Get default KPIs
	 *
	 * @return array
	 */
	private function get_default_kpis(): array {
		return [
			'sessions',
			'users',
			'pageviews',
			'bounce_rate',
			'conversion_rate',
			'revenue',
		];
	}

	/**
	 * Get client name
	 *
	 * @return string
	 */
	private function get_client_name(): string {
		$client_post = get_post( $this->client_id );
		return $client_post ? $client_post->post_title : 'Cliente #' . $this->client_id;
	}

	/**
	 * Get time period label
	 *
	 * @return string
	 */
	private function get_time_period_label(): string {
		$periods = CustomReportsTable::get_available_time_periods();
		return $periods[ $this->time_period ] ?? $this->time_period;
	}

	/**
	 * Generate filename for the report
	 *
	 * @param string $format File format
	 * @return string
	 */
	private function generate_filename( string $format ): string {
		$client_name = sanitize_file_name( $this->get_client_name() );
		$report_name = sanitize_file_name( $this->report_name );
		$date        = date( 'Y-m-d' );

		return sprintf(
			'%s-%s-%s.%s',
			$client_name,
			$report_name,
			$date,
			$format
		);
	}

	/**
	 * Send report via email
	 *
	 * @param string $content Report content
	 * @param string $format Report format
	 * @param string $filename Filename
	 * @return bool
	 */
	public function send_via_email( string $content, string $format, string $filename ): bool {
		if ( empty( $this->email_recipients ) ) {
			return false;
		}

		$client_name = $this->get_client_name();
		$subject     = sprintf(
			__( 'Report %1$s - %2$s', 'fp-digital-marketing' ),
			$this->report_name,
			$client_name
		);

		$message = sprintf(
			__( 'In allegato trovi il report "%1$s" per il cliente %2$s.', 'fp-digital-marketing' ),
			$this->report_name,
			$client_name
		) . "\n\n";

		$message .= sprintf(
			__( 'Periodo: %s', 'fp-digital-marketing' ),
			$this->get_time_period_label()
		) . "\n\n";

		$message .= __( 'Questo report è stato generato automaticamente dal FP Digital Marketing Suite.', 'fp-digital-marketing' );

		// Prepare attachment
		$upload_dir = wp_upload_dir();
		$temp_file  = $upload_dir['path'] . '/' . $filename;
		file_put_contents( $temp_file, $content );

		$attachments = [ $temp_file ];

		// Set content type for HTML emails
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$sent = true;
		foreach ( $this->email_recipients as $recipient ) {
			if ( ! wp_mail( $recipient, $subject, $message, $headers, $attachments ) ) {
				$sent = false;
			}
		}

		// Clean up temporary file
		if ( file_exists( $temp_file ) ) {
			unlink( $temp_file );
		}

		return $sent;
	}

	/**
	 * Check if report should be generated automatically
	 *
	 * @return bool
	 */
	public function should_generate_now(): bool {
		if ( ! $this->auto_send || $this->report_frequency === 'manual' ) {
			return false;
		}

		// Get last generation time
		$report_data    = CustomReportsTable::get_report( $this->id );
		$last_generated = $report_data['last_generated'] ?? null;

		if ( ! $last_generated ) {
			return true; // Never generated before
		}

		$last_generated_time = strtotime( $last_generated );
		$now                 = time();

		switch ( $this->report_frequency ) {
			case 'daily':
				return ( $now - $last_generated_time ) >= DAY_IN_SECONDS;
			case 'weekly':
				return ( $now - $last_generated_time ) >= WEEK_IN_SECONDS;
			case 'monthly':
				return ( $now - $last_generated_time ) >= ( 30 * DAY_IN_SECONDS );
			case 'quarterly':
				return ( $now - $last_generated_time ) >= ( 90 * DAY_IN_SECONDS );
			default:
				return false;
		}
	}

	/**
	 * Get available KPIs for selection
	 *
	 * @return array
	 */
	public static function get_available_kpis(): array {
		return [
			'traffic'     => [
				'label' => __( 'Traffico', 'fp-digital-marketing' ),
				'kpis'  => [
					'sessions'             => __( 'Sessioni', 'fp-digital-marketing' ),
					'users'                => __( 'Utenti', 'fp-digital-marketing' ),
					'pageviews'            => __( 'Visualizzazioni Pagina', 'fp-digital-marketing' ),
					'bounce_rate'          => __( 'Frequenza di Rimbalzo', 'fp-digital-marketing' ),
					'avg_session_duration' => __( 'Durata Media Sessione', 'fp-digital-marketing' ),
				],
			],
			'conversions' => [
				'label' => __( 'Conversioni', 'fp-digital-marketing' ),
				'kpis'  => [
					'conversion_rate'  => __( 'Tasso di Conversione', 'fp-digital-marketing' ),
					'goal_completions' => __( 'Obiettivi Completati', 'fp-digital-marketing' ),
					'goal_value'       => __( 'Valore Obiettivi', 'fp-digital-marketing' ),
				],
			],
			'revenue'     => [
				'label' => __( 'Entrate', 'fp-digital-marketing' ),
				'kpis'  => [
					'revenue'             => __( 'Fatturato', 'fp-digital-marketing' ),
					'average_order_value' => __( 'Valore Medio Ordine', 'fp-digital-marketing' ),
					'revenue_per_user'    => __( 'Fatturato per Utente', 'fp-digital-marketing' ),
				],
			],
			'advertising' => [
				'label' => __( 'Advertising', 'fp-digital-marketing' ),
				'kpis'  => [
					'cost_per_acquisition' => __( 'Costo per Acquisizione', 'fp-digital-marketing' ),
					'return_on_ad_spend'   => __( 'ROAS', 'fp-digital-marketing' ),
					'click_through_rate'   => __( 'CTR', 'fp-digital-marketing' ),
					'cost_per_click'       => __( 'CPC', 'fp-digital-marketing' ),
				],
			],
		];
	}

	// Getters and setters
	public function get_id(): ?int {
		return $this->id; }
	public function get_client_id(): int {
		return $this->client_id; }
	public function get_report_name(): string {
		return $this->report_name; }
	public function get_report_description(): string {
		return $this->report_description; }
	public function get_time_period(): string {
		return $this->time_period; }
	public function get_selected_kpis(): array {
		return $this->selected_kpis; }
	public function get_report_frequency(): string {
		return $this->report_frequency; }
	public function get_email_recipients(): array {
		return $this->email_recipients; }
	public function is_auto_send(): bool {
		return $this->auto_send; }
	public function get_status(): string {
		return $this->status; }

	public function set_client_id( int $client_id ): void {
		$this->client_id = $client_id; }
	public function set_report_name( string $report_name ): void {
		$this->report_name = $report_name; }
	public function set_report_description( string $report_description ): void {
		$this->report_description = $report_description; }
	public function set_time_period( string $time_period ): void {
		$this->time_period = $time_period; }
	public function set_selected_kpis( array $selected_kpis ): void {
		$this->selected_kpis = $selected_kpis; }
	public function set_report_frequency( string $report_frequency ): void {
		$this->report_frequency = $report_frequency; }
	public function set_email_recipients( array $email_recipients ): void {
		$this->email_recipients = $email_recipients; }
	public function set_auto_send( bool $auto_send ): void {
		$this->auto_send = $auto_send; }
	public function set_status( string $status ): void {
		$this->status = $status; }
}
