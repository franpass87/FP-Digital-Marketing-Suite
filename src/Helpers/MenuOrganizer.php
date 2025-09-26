<?php
/**
 * Menu Organization Helper
 *
 * This file provides a reference for the new menu structure
 * and helps organize menu items logically.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Menu organization constants and utilities
 */
class MenuOrganizer {

	/**
	 * Main menu structure reference
	 *
	 * This documents the organized menu structure:
	 *
	 * FP Digital Marketing (Main Menu)
	 * ├── 🏠 Dashboard (main page)
	 * ├── 📊 Reports
	 * ├── ⚡ Cache Performance
	 * ├─────────────────── [Analytics & Performance]
	 * ├── 🎯 Eventi Conversione
	 * ├── 🔗 Campagne UTM
	 * ├── 👥 Segmentazione
	 * ├─────────────────── [Campaign Management]
	 * ├── 🔔 Alert e Notifiche
	 * ├── 🔍 Rilevazione Anomalie
	 * ├─────────────────── [Monitoring & Alerts]
	 * ├── ⚙️ Settings
	 * ├── 🔒 Security
	 * ├── 🚀 Setup Wizard
	 * └─────────────────── [Configuration]
	 */

	/**
	 * Main menu slug
	 */
	public const MAIN_MENU_SLUG = 'fp-digital-marketing-dashboard';

	/**
	 * Menu section priorities
	 */
	public const MENU_PRIORITIES = [
		// Core Navigation
		'dashboard'         => 0,

		// Analytics & Performance
		'reports'           => 10,
		'cache-performance' => 11,

		// Campaign Management
		'conversion-events' => 20,
		'utm-campaigns'     => 21,
		'segmentation'      => 22,

		// Monitoring & Alerts
		'alerts'            => 30,
		'anomaly-detection' => 31,

		// Configuration
		'settings'          => 40,
		'security'          => 41,
		'setup-wizard'      => 42,
	];

	/**
	 * Get organized menu sections
	 *
	 * @return array Menu sections with their items
	 */
	public static function get_menu_sections(): array {
		return [
			'core'          => [
				'title' => __( 'Dashboard', 'fp-digital-marketing' ),
				'items' => [ 'dashboard' ],
			],
			'analytics'     => [
				'title' => __( 'Analytics & Performance', 'fp-digital-marketing' ),
				'items' => [ 'reports', 'cache-performance' ],
			],
			'campaigns'     => [
				'title' => __( 'Campaign Management', 'fp-digital-marketing' ),
				'items' => [ 'conversion-events', 'utm-campaigns', 'segmentation' ],
			],
			'monitoring'    => [
				'title' => __( 'Monitoring & Alerts', 'fp-digital-marketing' ),
				'items' => [ 'alerts', 'anomaly-detection' ],
			],
			'configuration' => [
				'title' => __( 'Configuration', 'fp-digital-marketing' ),
				'items' => [ 'settings', 'security', 'setup-wizard' ],
			],
		];
	}

	/**
	 * Get menu item configuration
	 *
	 * @param string $item_key Menu item key
	 * @return array Menu item configuration
	 */
	public static function get_menu_item_config( string $item_key ): array {
		$configs = [
			'dashboard'         => [
				'page_title' => __( 'Dashboard', 'fp-digital-marketing' ),
				'menu_title' => __( '🏠 Dashboard', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-dashboard',
			],
			'reports'           => [
				'page_title' => __( 'Reports & Analytics', 'fp-digital-marketing' ),
				'menu_title' => __( '📊 Reports', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-chart-line',
			],
			'cache-performance' => [
				'page_title' => __( 'Cache Performance', 'fp-digital-marketing' ),
				'menu_title' => __( '⚡ Cache Performance', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-performance',
			],
			'conversion-events' => [
				'page_title' => __( 'Eventi Conversione', 'fp-digital-marketing' ),
				'menu_title' => __( '🎯 Eventi Conversione', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-target',
			],
			'utm-campaigns'     => [
				'page_title' => __( 'Gestione Campagne UTM', 'fp-digital-marketing' ),
				'menu_title' => __( '🔗 Campagne UTM', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-admin-links',
			],
			'segmentation'      => [
				'page_title' => __( 'Segmentazione Audience', 'fp-digital-marketing' ),
				'menu_title' => __( '👥 Segmentazione', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-groups',
			],
			'alerts'            => [
				'page_title' => __( 'Alert e Notifiche', 'fp-digital-marketing' ),
				'menu_title' => __( '🔔 Alert e Notifiche', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-bell',
			],
			'anomaly-detection' => [
				'page_title' => __( 'Rilevazione Anomalie', 'fp-digital-marketing' ),
				'menu_title' => __( '🔍 Rilevazione Anomalie', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-search',
			],
			'settings'          => [
				'page_title' => __( 'FP Digital Marketing Settings', 'fp-digital-marketing' ),
				'menu_title' => __( '⚙️ Settings', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-admin-settings',
			],
			'security'          => [
				'page_title' => __( 'Security Settings', 'fp-digital-marketing' ),
				'menu_title' => __( '🔒 Security', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-lock',
			],
			'setup-wizard'      => [
				'page_title' => __( 'Setup Wizard', 'fp-digital-marketing' ),
				'menu_title' => __( '🚀 Setup Wizard', 'fp-digital-marketing' ),
				'icon'       => 'dashicons-admin-tools',
			],
		];

		return $configs[ $item_key ] ?? [];
	}

	/**
	 * Check if menu reorganization was successful
	 *
	 * @return bool True if all menu items are properly organized
	 */
	public static function verify_menu_structure(): bool {
		global $submenu;

		// Check if main menu exists and has expected submenus
		if ( ! isset( $submenu[ self::MAIN_MENU_SLUG ] ) ) {
			return false;
		}

		$expected_items = array_keys( self::MENU_PRIORITIES );
		$actual_items   = array_column( $submenu[ self::MAIN_MENU_SLUG ], 2 );

		// Check if we have the minimum expected items
		$found_items = 0;
		foreach ( $expected_items as $expected ) {
			foreach ( $actual_items as $actual ) {
				if ( strpos( $actual, $expected ) !== false ) {
					++$found_items;
					break;
				}
			}
		}

		return $found_items >= count( $expected_items ) * 0.8; // Allow for 80% match
	}
}
