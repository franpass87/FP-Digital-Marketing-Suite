<?php
/**
 * Capabilities Management Helper Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Helpers\Security;

/**
 * Capabilities management class for granular permission control
 */
class Capabilities {

	/**
	 * Custom capabilities for the plugin
	 */
	public const VIEW_DASHBOARD      = 'fp_dms_view_dashboard';
	public const MANAGE_DATA_SOURCES = 'fp_dms_manage_data_sources';
	public const EXPORT_REPORTS      = 'fp_dms_export_reports';
	public const EXPORT_DATA         = 'fp_dms_export_data';
	public const MANAGE_ALERTS       = 'fp_dms_manage_alerts';
	public const MANAGE_SETTINGS     = 'fp_dms_manage_settings';
	public const MANAGE_CAMPAIGNS    = 'fp_dms_manage_campaigns';
	public const MANAGE_CONVERSIONS  = 'fp_dms_manage_conversions';
	public const VIEW_SEGMENTS       = 'fp_dms_view_segments';
	public const MANAGE_SEGMENTS     = 'fp_dms_manage_segments';
	public const FUNNEL_ANALYSIS     = 'fp_dms_funnel_analysis';
	public const VIEW_REPORTS        = 'fp_dms_view_reports';

	/**
	 * All custom capabilities
	 *
	 * @var array
	 */
	private static array $custom_capabilities = [
		self::VIEW_DASHBOARD,
		self::MANAGE_DATA_SOURCES,
		self::EXPORT_REPORTS,
		self::EXPORT_DATA,
		self::MANAGE_ALERTS,
		self::MANAGE_SETTINGS,
		self::MANAGE_CAMPAIGNS,
		self::MANAGE_CONVERSIONS,
		self::VIEW_SEGMENTS,
		self::MANAGE_SEGMENTS,
		self::FUNNEL_ANALYSIS,
		self::VIEW_REPORTS,
	];

	/**
	 * Default role capabilities mapping
	 *
	 * @var array
	 */
	private static array $default_role_capabilities = [
		'administrator' => [
			self::VIEW_DASHBOARD,
			self::MANAGE_DATA_SOURCES,
			self::EXPORT_REPORTS,
			self::EXPORT_DATA,
			self::MANAGE_ALERTS,
			self::MANAGE_SETTINGS,
			self::MANAGE_CAMPAIGNS,
			self::MANAGE_CONVERSIONS,
			self::VIEW_SEGMENTS,
			self::MANAGE_SEGMENTS,
			self::FUNNEL_ANALYSIS,
			self::VIEW_REPORTS,
		],
		'editor'        => [
			self::VIEW_DASHBOARD,
			self::EXPORT_REPORTS,
			self::EXPORT_DATA,
			self::VIEW_REPORTS,
			self::VIEW_SEGMENTS,
			self::FUNNEL_ANALYSIS,
		],
		'author'        => [
			self::VIEW_DASHBOARD,
			self::VIEW_REPORTS,
		],
	];

	/**
	 * Initialize capabilities system
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_capabilities' ] );
	}

	/**
	 * Register custom capabilities and assign to roles
	 *
	 * @return void
	 */
	public static function register_capabilities(): void {
				$is_registered = (bool) get_option( 'fp_dms_capabilities_registered', false );

		if ( ! $is_registered ) {
				self::assign_default_capabilities();
				update_option( 'fp_dms_capabilities_registered', true );

				Security::log_security_event(
					'capabilities_registered',
					[
						'capabilities' => self::$custom_capabilities,
						'roles'        => array_keys( self::$default_role_capabilities ),
					]
				);
				return;
		}

		if ( ! self::roles_have_expected_capabilities() ) {
				self::assign_default_capabilities();
		}
	}

		/**
		 * Ensure all default roles currently have the expected capabilities.
		 *
		 * @return bool
		 */
	private static function roles_have_expected_capabilities(): bool {
		foreach ( self::$default_role_capabilities as $role_name => $capabilities ) {
				$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
					$has_cap = false;

				if ( method_exists( $role, 'has_cap' ) ) {
					$has_cap = (bool) $role->has_cap( $capability );
				} elseif ( isset( $role->capabilities[ $capability ] ) ) {
						$has_cap = (bool) $role->capabilities[ $capability ];
				}

				if ( ! $has_cap ) {
						return false;
				}
			}
		}

			return true;
	}

		/**
		 * Assign the default capabilities to the configured roles.
		 *
		 * @return void
		 */
	private static function assign_default_capabilities(): void {
		foreach ( self::$default_role_capabilities as $role_name => $capabilities ) {
				$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				if ( method_exists( $role, 'add_cap' ) ) {
					$role->add_cap( $capability, true );
				} else {
						$role->capabilities[ $capability ] = true;
				}
			}
		}
	}

	/**
	 * Remove custom capabilities from all roles
	 *
	 * @return void
	 */
	public static function remove_capabilities(): void {
				$roles_object = wp_roles();
				$roles        = is_object( $roles_object ) && isset( $roles_object->roles ) ? $roles_object->roles : [];

		foreach ( $roles as $role_name => $role_info ) {
				$role = get_role( $role_name );
			if ( $role ) {
				foreach ( self::$custom_capabilities as $capability ) {
					if ( method_exists( $role, 'remove_cap' ) ) {
								$role->remove_cap( $capability );
					} elseif ( isset( $role->capabilities[ $capability ] ) ) {
										unset( $role->capabilities[ $capability ] );
					}
				}
			}
		}

				// Remove the registered flag
		delete_option( 'fp_dms_capabilities_registered' );

		// Log the capability removal
		Security::log_security_event(
			'capabilities_removed',
			[
				'capabilities' => self::$custom_capabilities,
			]
		);
	}

	/**
	 * Check if user has specific capability with logging
	 *
	 * @param string $capability Required capability.
	 * @param int    $object_id Optional object ID.
	 * @param int    $user_id Optional user ID (defaults to current user).
	 * @return bool True if user has capability.
	 */
	public static function user_can( string $capability, int $object_id = 0, int $user_id = 0 ): bool {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$has_cap = user_can( $user_id, $capability, $object_id );

		if ( ! $has_cap && in_array( $capability, self::$custom_capabilities, true ) ) {
			Security::log_security_event(
				'capability_denied',
				[
					'capability' => $capability,
					'object_id'  => $object_id,
					'user_id'    => $user_id,
					'ip'         => Security::get_client_ip(),
				]
			);
		}

		return $has_cap;
	}

	/**
	 * Check if current user has specific capability with enhanced security
	 *
	 * @param string $capability Required capability.
	 * @param int    $object_id Optional object ID.
	 * @return bool True if user has capability.
	 */
	public static function current_user_can( string $capability, int $object_id = 0 ): bool {
		return self::user_can( $capability, $object_id, get_current_user_id() );
	}

	/**
	 * Get all custom capabilities
	 *
	 * @return array Array of custom capabilities.
	 */
	public static function get_custom_capabilities(): array {
		return self::$custom_capabilities;
	}

	/**
	 * Get default role capabilities mapping
	 *
	 * @return array Array of role => capabilities mapping.
	 */
	public static function get_default_role_capabilities(): array {
		return self::$default_role_capabilities;
	}

	/**
	 * Get capabilities for a specific role
	 *
	 * @param string $role_name Role name.
	 * @return array Array of capabilities for the role.
	 */
	public static function get_role_capabilities( string $role_name ): array {
		$role = get_role( $role_name );
		if ( ! $role ) {
			return [];
		}

				$capabilities = [];

		if ( isset( $role->capabilities ) && is_array( $role->capabilities ) ) {
				$capabilities = array_keys( array_filter( $role->capabilities ) );
		} elseif ( method_exists( $role, 'has_cap' ) ) {
			foreach ( self::$custom_capabilities as $custom_capability ) {
				if ( $role->has_cap( $custom_capability ) ) {
					$capabilities[] = $custom_capability;
				}
			}
		}

				$role_caps = $capabilities;
				return array_intersect( $role_caps, self::$custom_capabilities );
	}

	/**
	 * Add capability to role
	 *
	 * @param string $role_name Role name.
	 * @param string $capability Capability to add.
	 * @return bool True if capability was added.
	 */
	public static function add_role_capability( string $role_name, string $capability ): bool {
		if ( ! in_array( $capability, self::$custom_capabilities, true ) ) {
			return false;
		}

				$role = get_role( $role_name );
		if ( ! $role ) {
				return false;
		}

		if ( method_exists( $role, 'add_cap' ) ) {
				$role->add_cap( $capability, true );
		} else {
				$role->capabilities[ $capability ] = true;
		}

		Security::log_security_event(
			'capability_added_to_role',
			[
				'role'       => $role_name,
				'capability' => $capability,
				'user_id'    => get_current_user_id(),
			]
		);

		return true;
	}

	/**
	 * Remove capability from role
	 *
	 * @param string $role_name Role name.
	 * @param string $capability Capability to remove.
	 * @return bool True if capability was removed.
	 */
	public static function remove_role_capability( string $role_name, string $capability ): bool {
		if ( ! in_array( $capability, self::$custom_capabilities, true ) ) {
			return false;
		}

		$role = get_role( $role_name );
		if ( ! $role ) {
			return false;
		}

		if ( method_exists( $role, 'remove_cap' ) ) {
				$role->remove_cap( $capability );
		} elseif ( isset( $role->capabilities[ $capability ] ) ) {
				unset( $role->capabilities[ $capability ] );
		}

		Security::log_security_event(
			'capability_removed_from_role',
			[
				'role'       => $role_name,
				'capability' => $capability,
				'user_id'    => get_current_user_id(),
			]
		);

		return true;
	}

	/**
	 * Get human-readable capability name
	 *
	 * @param string $capability Capability constant.
	 * @return string Human-readable name.
	 */
	public static function get_capability_label( string $capability ): string {
				$labels = [
					self::VIEW_DASHBOARD      => __( 'View Dashboard', 'fp-digital-marketing' ),
					self::MANAGE_DATA_SOURCES => __( 'Manage Data Sources', 'fp-digital-marketing' ),
					self::EXPORT_REPORTS      => __( 'Export Reports', 'fp-digital-marketing' ),
					self::EXPORT_DATA         => __( 'Export Data', 'fp-digital-marketing' ),
					self::MANAGE_ALERTS       => __( 'Manage Alerts', 'fp-digital-marketing' ),
					self::MANAGE_SETTINGS     => __( 'Manage Settings', 'fp-digital-marketing' ),
					self::MANAGE_CAMPAIGNS    => __( 'Manage Campaigns', 'fp-digital-marketing' ),
					self::MANAGE_CONVERSIONS  => __( 'Manage Conversion Events', 'fp-digital-marketing' ),
					self::VIEW_SEGMENTS       => __( 'View Segments', 'fp-digital-marketing' ),
					self::MANAGE_SEGMENTS     => __( 'Manage Segments', 'fp-digital-marketing' ),
					self::FUNNEL_ANALYSIS     => __( 'Access Funnel Analysis', 'fp-digital-marketing' ),
					self::VIEW_REPORTS        => __( 'View Reports', 'fp-digital-marketing' ),
				];

				return $labels[ $capability ] ?? $capability;
	}

	/**
	 * Get capability description
	 *
	 * @param string $capability Capability constant.
	 * @return string Capability description.
	 */
	public static function get_capability_description( string $capability ): string {
				$descriptions = [
					self::VIEW_DASHBOARD      => __( 'Access to view dashboard and metrics overview', 'fp-digital-marketing' ),
					self::MANAGE_DATA_SOURCES => __( 'Configure and manage data source connections (GA4, GSC, etc.)', 'fp-digital-marketing' ),
					self::EXPORT_REPORTS      => __( 'Export reports and data in various formats', 'fp-digital-marketing' ),
					self::EXPORT_DATA         => __( 'Export marketing data sets and configuration backups', 'fp-digital-marketing' ),
					self::MANAGE_ALERTS       => __( 'Create, modify and manage alert rules and notifications', 'fp-digital-marketing' ),
					self::MANAGE_SETTINGS     => __( 'Access plugin settings and configuration options', 'fp-digital-marketing' ),
					self::MANAGE_CAMPAIGNS    => __( 'Create and manage UTM marketing campaigns', 'fp-digital-marketing' ),
					self::MANAGE_CONVERSIONS  => __( 'Manage conversion events and tracking rules', 'fp-digital-marketing' ),
					self::VIEW_SEGMENTS       => __( 'View customer segments and audience breakdowns', 'fp-digital-marketing' ),
					self::MANAGE_SEGMENTS     => __( 'Create and manage customer audience segments', 'fp-digital-marketing' ),
					self::FUNNEL_ANALYSIS     => __( 'Access funnel analytics and journey exploration tools', 'fp-digital-marketing' ),
					self::VIEW_REPORTS        => __( 'View advanced analytics and scheduled reports', 'fp-digital-marketing' ),
				];

				return $descriptions[ $capability ] ?? '';
	}
}
