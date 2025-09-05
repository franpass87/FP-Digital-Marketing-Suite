<?php
/**
 * Frontend Tracking Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\DataSources\MicrosoftClarity;

/**
 * Frontend Tracking class for handling analytics and tracking scripts
 */
class FrontendTracking {

	/**
	 * Initialize frontend tracking
	 *
	 * @return void
	 */
	public static function init(): void {
		// Hook into wp_head to output tracking scripts.
		add_action( 'wp_head', [ self::class, 'output_tracking_scripts' ], 20 );
	}

	/**
	 * Output all tracking scripts for configured data sources
	 *
	 * @return void
	 */
	public static function output_tracking_scripts(): void {
		// Only output on frontend (not admin)
		if ( is_admin() ) {
			return;
		}

		// Get API keys configuration
		$api_keys = get_option( 'fp_digital_marketing_api_keys', [] );

		// Output Microsoft Clarity tracking script
		self::output_clarity_tracking( $api_keys );
	}

	/**
	 * Output Microsoft Clarity tracking script
	 *
	 * @param array $api_keys API keys configuration
	 * @return void
	 */
	private static function output_clarity_tracking( array $api_keys ): void {
		$project_id = $api_keys['clarity_project_id'] ?? '';
		
		if ( empty( $project_id ) ) {
			return;
		}

		$clarity = new MicrosoftClarity( $project_id );
		$tracking_script = $clarity->get_tracking_script();
		
		if ( ! empty( $tracking_script ) ) {
			echo "\n<!-- Microsoft Clarity tracking -->\n";
			echo $tracking_script;
			echo "\n<!-- End Microsoft Clarity tracking -->\n";
		}
	}
}