<?php
/**
 * Admin Menu Rationalization Utility
 * 
 * This utility helps to disable individual admin menu registrations
 * when the centralized MenuManager is active.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * MenuMigration class for handling menu rationalization
 */
class MenuMigration {

	/**
	 * Admin classes that register menus
	 */
	private const ADMIN_CLASSES_WITH_MENUS = [
		'AlertingAdmin',
		'AnomalyDetectionAdmin', 
		'AnomalyRadar',
		'CachePerformance',
		'ConversionEventsAdmin',
		'Dashboard',
		'FunnelAnalysisAdmin',
		'OnboardingWizard',
		'Reports',
		'SecurityAdmin',
		'SegmentationAdmin',
		'Settings',
		'UTMCampaignManager'
	];

	/**
	 * Update admin classes to prevent duplicate menu registration
	 *
	 * @return array Updated files count and errors
	 */
	public static function disable_individual_menu_registrations(): array {
		$updated_files = 0;
		$errors = [];

		foreach ( self::ADMIN_CLASSES_WITH_MENUS as $class_name ) {
			$file_path = FP_DIGITAL_MARKETING_PLUGIN_DIR . "src/Admin/{$class_name}.php";
			
			if ( ! file_exists( $file_path ) ) {
				$errors[] = "File not found: {$file_path}";
				continue;
			}

			$content = file_get_contents( $file_path );
			if ( $content === false ) {
				$errors[] = "Could not read file: {$file_path}";
				continue;
			}

			// Check if already updated
			if ( strpos( $content, 'MenuManager is active' ) !== false ) {
				continue; // Already updated
			}

			// Update add_admin_menu method
			$pattern = '/(\s+)public function add_admin_menu\(\): void \{[\s\n]*([^}]+)\}/';
			$replacement = '$1/**
$1 * Add admin menu page
$1 * 
$1 * Note: This method is disabled when MenuManager is active to prevent
$1 * duplicate menu registrations in the rationalized menu structure.
$1 *
$1 * @return void
$1 */
$1public function add_admin_menu(): void {
$1	// Check if centralized MenuManager is active
$1	if ( class_exists( \'\FP\DigitalMarketing\Admin\MenuManager\' ) ) {
$1		// MenuManager will handle menu registration
$1		return;
$1	}

$1	// Legacy menu registration (fallback)$2
$1}';

			$updated_content = preg_replace( $pattern, $replacement, $content );
			
			if ( $updated_content !== null && $updated_content !== $content ) {
				if ( file_put_contents( $file_path, $updated_content ) !== false ) {
					$updated_files++;
				} else {
					$errors[] = "Could not write to file: {$file_path}";
				}
			}
		}

		return [
			'updated_files' => $updated_files,
			'errors' => $errors
		];
	}

	/**
	 * Check if menu rationalization is needed
	 *
	 * @return bool
	 */
	public static function is_migration_needed(): bool {
		// Check if MenuManager exists and individual menus are still registering
		if ( ! class_exists( '\FP\DigitalMarketing\Admin\MenuManager' ) ) {
			return false;
		}

		foreach ( self::ADMIN_CLASSES_WITH_MENUS as $class_name ) {
			$file_path = FP_DIGITAL_MARKETING_PLUGIN_DIR . "src/Admin/{$class_name}.php";
			
			if ( file_exists( $file_path ) ) {
				$content = file_get_contents( $file_path );
				if ( $content && strpos( $content, 'MenuManager is active' ) === false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get menu structure summary for debugging
	 *
	 * @return array
	 */
	public static function get_menu_structure_summary(): array {
		$summary = [
			'menu_manager_active' => class_exists( '\FP\DigitalMarketing\Admin\MenuManager' ),
			'individual_menus_disabled' => 0,
			'total_admin_classes' => count( self::ADMIN_CLASSES_WITH_MENUS ),
			'classes_status' => []
		];

		foreach ( self::ADMIN_CLASSES_WITH_MENUS as $class_name ) {
			$file_path = FP_DIGITAL_MARKETING_PLUGIN_DIR . "src/Admin/{$class_name}.php";
			$status = [
				'file_exists' => file_exists( $file_path ),
				'menu_disabled' => false,
				'class_exists' => class_exists( "\\FP\\DigitalMarketing\\Admin\\{$class_name}" )
			];

			if ( $status['file_exists'] ) {
				$content = file_get_contents( $file_path );
				$status['menu_disabled'] = $content && strpos( $content, 'MenuManager is active' ) !== false;
				
				if ( $status['menu_disabled'] ) {
					$summary['individual_menus_disabled']++;
				}
			}

			$summary['classes_status'][$class_name] = $status;
		}

		return $summary;
	}
}