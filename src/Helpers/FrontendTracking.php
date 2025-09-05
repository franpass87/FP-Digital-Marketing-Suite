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

		// NOTE: Microsoft Clarity tracking script is no longer automatically injected
		// This plugin now monitors CLIENT websites, not the agency website where it's installed
		// Clarity tracking for client websites should be handled separately on each client site
		
		// Output other tracking scripts here (if any in the future)
		// self::output_other_tracking( $api_keys );
	}

	/**
	 * Output Microsoft Clarity tracking script
	 * 
	 * @deprecated This method is no longer used as Clarity now monitors client websites, not the agency website.
	 * @param array $api_keys API keys configuration
	 * @return void
	 */
	private static function output_clarity_tracking( array $api_keys ): void {
		// This method is no longer used. Clarity tracking is now configured per-client
		// to monitor client websites, not the agency website where the plugin is installed.
		
		// If you need to track the agency website, configure Clarity directly on the website
		// rather than through this plugin.
		
		return;
		
		// Previous implementation (kept for reference):
		/*
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
		*/
	}
}