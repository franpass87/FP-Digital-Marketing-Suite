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
			'generated_at'  => current_time( 'mysql' ),
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
					'name'     => 'Organic Search',
					'sessions' => 5680,
					'revenue'  => 12400.50,
				],
				[
					'name'     => 'Google Ads',
					'sessions' => 3250,
					'revenue'  => 8900.25,
				],
				[
					'name'     => 'Facebook Ads',
					'sessions' => 2100,
					'revenue'  => 4200.00,
				],
				[
					'name'     => 'Direct',
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
			'{{REPORT_TITLE}}'     => sprintf( __( 'Digital Marketing Report - Client %d', 'fp-digital-marketing' ), $report_data['client_id'] ),
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
	 */
	public static function generate_pdf_report( array $report_data ): string {
		$html = self::generate_html_report( $report_data );

		$options = new Options();
		$options->set( 'defaultFont', 'Arial' );
		$options->set( 'isRemoteEnabled', true );

		$dompdf = new Dompdf( $options );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		return $dompdf->output();
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
		$html .= '<th>' . __( 'Canale', 'fp-digital-marketing' ) . '</th>';
		$html .= '<th>' . __( 'Sessioni', 'fp-digital-marketing' ) . '</th>';
		$html .= '<th>' . __( 'Fatturato', 'fp-digital-marketing' ) . '</th>';
		$html .= '</tr>';
		$html .= '</thead>';
		$html .= '<tbody>';

		foreach ( $channels as $channel ) {
			$html .= '<tr>';
			$html .= '<td>' . esc_html( $channel['name'] ) . '</td>';
			$html .= '<td>' . number_format( $channel['sessions'] ) . '</td>';
			$html .= '<td>€' . number_format( $channel['revenue'], 2 ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody>';
		$html .= '</table>';

		return $html;
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