<?php
/**
 * Tests for Capabilities Management
 *
 * @package FP_Digital_Marketing_Suite
 */

require_once __DIR__ . '/bootstrap.php';

use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Helpers\Security;
use PHPUnit\Framework\TestCase;

/**
 * Test capabilities management functionality
 */
class CapabilitiesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		
		// Mock WordPress functions
                if ( ! function_exists( 'get_role' ) ) {
                        function get_role( $role ) {
                                static $roles = [];

                                $valid_roles = [ 'administrator', 'editor', 'author' ];
                                if ( ! in_array( $role, $valid_roles, true ) ) {
                                        return null;
                                }

                                if ( ! isset( $roles[ $role ] ) ) {
                                        $roles[ $role ] = new class {
                                                public $capabilities = [];

                                                public function add_cap( $cap, $grant = true ) {
                                                        $this->capabilities[ $cap ] = $grant;
                                                }

                                                public function remove_cap( $cap ) {
                                                        unset( $this->capabilities[ $cap ] );
                                                }

                                                public function has_cap( $cap ) {
                                                        return ! empty( $this->capabilities[ $cap ] );
                                                }
                                        };
                                }

                                return $roles[ $role ];
                        }
                }

		if ( ! function_exists( 'wp_roles' ) ) {
			function wp_roles() {
				return new class {
					public $roles = [
						'administrator' => [ 'capabilities' => [] ],
						'editor' => [ 'capabilities' => [] ],
						'author' => [ 'capabilities' => [] ],
					];
				};
			}
		}

                if ( ! function_exists( 'user_can' ) ) {
                        function user_can( $user_id, $capability, $object_id = 0 ) {
                                // Mock admin user always has capabilities
                                if ( $user_id === 1 ) {
                                        return true;
                                }
                                // Mock editor user has limited capabilities
                                if ( $user_id === 2 ) {
                                        $editor_caps = [
                                                Capabilities::VIEW_DASHBOARD,
                                                Capabilities::EXPORT_REPORTS,
                                                Capabilities::EXPORT_DATA,
                                                Capabilities::VIEW_REPORTS,
                                                Capabilities::VIEW_SEGMENTS,
                                                Capabilities::FUNNEL_ANALYSIS,
                                        ];
                                        return in_array( $capability, $editor_caps, true );
                                }
                                return false;
                        }
		}

		if ( ! function_exists( 'get_current_user_id' ) ) {
			function get_current_user_id() {
				return 1; // Mock admin user by default
			}
		}

		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				static $options = [];
				return $options[ $option ] ?? $default;
			}
		}

		if ( ! function_exists( 'update_option' ) ) {
			function update_option( $option, $value ) {
				static $options = [];
				$options[ $option ] = $value;
				return true;
			}
		}

		if ( ! function_exists( 'delete_option' ) ) {
			function delete_option( $option ) {
				static $options = [];
				$old_value = $options[ $option ] ?? false;
				unset( $options[ $option ] );
				return $old_value !== false;
			}
		}

		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain = 'default' ) {
				return $text;
			}
		}
	}

	public function testCustomCapabilitiesConstants(): void {
		$this->assertEquals( 'fp_dms_view_dashboard', Capabilities::VIEW_DASHBOARD );
		$this->assertEquals( 'fp_dms_manage_data_sources', Capabilities::MANAGE_DATA_SOURCES );
		$this->assertEquals( 'fp_dms_export_reports', Capabilities::EXPORT_REPORTS );
                $this->assertEquals( 'fp_dms_export_data', Capabilities::EXPORT_DATA );
                $this->assertEquals( 'fp_dms_manage_alerts', Capabilities::MANAGE_ALERTS );
                $this->assertEquals( 'fp_dms_manage_settings', Capabilities::MANAGE_SETTINGS );
                $this->assertEquals( 'fp_dms_manage_campaigns', Capabilities::MANAGE_CAMPAIGNS );
                $this->assertEquals( 'fp_dms_manage_conversions', Capabilities::MANAGE_CONVERSIONS );
                $this->assertEquals( 'fp_dms_view_segments', Capabilities::VIEW_SEGMENTS );
                $this->assertEquals( 'fp_dms_manage_segments', Capabilities::MANAGE_SEGMENTS );
                $this->assertEquals( 'fp_dms_funnel_analysis', Capabilities::FUNNEL_ANALYSIS );
                $this->assertEquals( 'fp_dms_view_reports', Capabilities::VIEW_REPORTS );
        }

        public function testGetCustomCapabilities(): void {
                $capabilities = Capabilities::get_custom_capabilities();

                $this->assertIsArray( $capabilities );
                $this->assertCount( 12, $capabilities );
                $this->assertContains( Capabilities::VIEW_DASHBOARD, $capabilities );
                $this->assertContains( Capabilities::MANAGE_DATA_SOURCES, $capabilities );
                $this->assertContains( Capabilities::EXPORT_REPORTS, $capabilities );
                $this->assertContains( Capabilities::EXPORT_DATA, $capabilities );
                $this->assertContains( Capabilities::MANAGE_ALERTS, $capabilities );
                $this->assertContains( Capabilities::MANAGE_SETTINGS, $capabilities );
                $this->assertContains( Capabilities::MANAGE_CAMPAIGNS, $capabilities );
                $this->assertContains( Capabilities::MANAGE_CONVERSIONS, $capabilities );
                $this->assertContains( Capabilities::VIEW_SEGMENTS, $capabilities );
                $this->assertContains( Capabilities::MANAGE_SEGMENTS, $capabilities );
                $this->assertContains( Capabilities::FUNNEL_ANALYSIS, $capabilities );
                $this->assertContains( Capabilities::VIEW_REPORTS, $capabilities );
        }

        public function testGetDefaultRoleCapabilities(): void {
                $role_capabilities = Capabilities::get_default_role_capabilities();

                $this->assertIsArray( $role_capabilities );
                $this->assertArrayHasKey( 'administrator', $role_capabilities );
                $this->assertArrayHasKey( 'editor', $role_capabilities );
                $this->assertArrayHasKey( 'author', $role_capabilities );

                // Administrator should have all capabilities
                $admin_caps = $role_capabilities['administrator'];
                $this->assertCount( 12, $admin_caps );
                $this->assertContains( Capabilities::VIEW_DASHBOARD, $admin_caps );
                $this->assertContains( Capabilities::MANAGE_DATA_SOURCES, $admin_caps );
                $this->assertContains( Capabilities::EXPORT_REPORTS, $admin_caps );
                $this->assertContains( Capabilities::EXPORT_DATA, $admin_caps );
                $this->assertContains( Capabilities::MANAGE_ALERTS, $admin_caps );
                $this->assertContains( Capabilities::MANAGE_SETTINGS, $admin_caps );
                $this->assertContains( Capabilities::MANAGE_CAMPAIGNS, $admin_caps );
                $this->assertContains( Capabilities::MANAGE_CONVERSIONS, $admin_caps );
                $this->assertContains( Capabilities::VIEW_SEGMENTS, $admin_caps );
                $this->assertContains( Capabilities::MANAGE_SEGMENTS, $admin_caps );
                $this->assertContains( Capabilities::FUNNEL_ANALYSIS, $admin_caps );
                $this->assertContains( Capabilities::VIEW_REPORTS, $admin_caps );

                // Editor should have limited capabilities
                $editor_caps = $role_capabilities['editor'];
                $this->assertCount( 6, $editor_caps );
                $this->assertContains( Capabilities::VIEW_DASHBOARD, $editor_caps );
                $this->assertContains( Capabilities::EXPORT_REPORTS, $editor_caps );
                $this->assertContains( Capabilities::EXPORT_DATA, $editor_caps );
                $this->assertContains( Capabilities::VIEW_REPORTS, $editor_caps );
                $this->assertContains( Capabilities::VIEW_SEGMENTS, $editor_caps );
                $this->assertContains( Capabilities::FUNNEL_ANALYSIS, $editor_caps );

                // Author should have read-only access to dashboards and reports
                $author_caps = $role_capabilities['author'];
                $this->assertCount( 2, $author_caps );
                $this->assertContains( Capabilities::VIEW_DASHBOARD, $author_caps );
                $this->assertContains( Capabilities::VIEW_REPORTS, $author_caps );
        }

        public function testUserCanFunction(): void {
                // Test admin user (user_id = 1)
                $this->assertTrue( Capabilities::user_can( Capabilities::VIEW_DASHBOARD, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::MANAGE_DATA_SOURCES, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::EXPORT_REPORTS, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::EXPORT_DATA, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::MANAGE_ALERTS, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::MANAGE_SETTINGS, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::MANAGE_CAMPAIGNS, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::MANAGE_CONVERSIONS, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::VIEW_SEGMENTS, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::MANAGE_SEGMENTS, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::FUNNEL_ANALYSIS, 0, 1 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::VIEW_REPORTS, 0, 1 ) );

                // Test editor user (user_id = 2)
                $this->assertTrue( Capabilities::user_can( Capabilities::VIEW_DASHBOARD, 0, 2 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_DATA_SOURCES, 0, 2 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::EXPORT_REPORTS, 0, 2 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::EXPORT_DATA, 0, 2 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_ALERTS, 0, 2 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_SETTINGS, 0, 2 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_CAMPAIGNS, 0, 2 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_CONVERSIONS, 0, 2 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::VIEW_SEGMENTS, 0, 2 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_SEGMENTS, 0, 2 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::FUNNEL_ANALYSIS, 0, 2 ) );
                $this->assertTrue( Capabilities::user_can( Capabilities::VIEW_REPORTS, 0, 2 ) );

                // Test non-privileged user (user_id = 3)
                $this->assertFalse( Capabilities::user_can( Capabilities::VIEW_DASHBOARD, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_DATA_SOURCES, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::EXPORT_REPORTS, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::EXPORT_DATA, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_ALERTS, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_SETTINGS, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_CAMPAIGNS, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_CONVERSIONS, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::VIEW_SEGMENTS, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::MANAGE_SEGMENTS, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::FUNNEL_ANALYSIS, 0, 3 ) );
                $this->assertFalse( Capabilities::user_can( Capabilities::VIEW_REPORTS, 0, 3 ) );
        }

        public function testCurrentUserCanFunction(): void {
                // With default mock user (admin)
                $this->assertTrue( Capabilities::current_user_can( Capabilities::VIEW_DASHBOARD ) );
                $this->assertTrue( Capabilities::current_user_can( Capabilities::MANAGE_DATA_SOURCES ) );
                $this->assertTrue( Capabilities::current_user_can( Capabilities::EXPORT_REPORTS ) );
                $this->assertTrue( Capabilities::current_user_can( Capabilities::EXPORT_DATA ) );
                $this->assertTrue( Capabilities::current_user_can( Capabilities::MANAGE_ALERTS ) );
                $this->assertTrue( Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) );
                $this->assertTrue( Capabilities::current_user_can( Capabilities::VIEW_REPORTS ) );
        }

	public function testRegisterCapabilities(): void {
		// Reset registration flag
		delete_option( 'fp_dms_capabilities_registered' );
		
		// Should register capabilities
		Capabilities::register_capabilities();
		
		// Should set the flag
		$this->assertTrue( get_option( 'fp_dms_capabilities_registered', false ) );
		
		// Should not register again
		Capabilities::register_capabilities();
	}

	public function testRemoveCapabilities(): void {
		// Set registration flag
		update_option( 'fp_dms_capabilities_registered', true );
		
		// Remove capabilities
		Capabilities::remove_capabilities();
		
		// Should clear the flag
		$this->assertFalse( get_option( 'fp_dms_capabilities_registered', false ) );
	}

	public function testAddRoleCapability(): void {
		// Test adding valid capability
		$this->assertTrue( Capabilities::add_role_capability( 'author', Capabilities::VIEW_DASHBOARD ) );
		
		// Test adding invalid capability
		$this->assertFalse( Capabilities::add_role_capability( 'author', 'invalid_capability' ) );
		
		// Test adding to non-existent role (should fail gracefully)
		$this->assertFalse( Capabilities::add_role_capability( 'non_existent_role', Capabilities::VIEW_DASHBOARD ) );
	}

	public function testRemoveRoleCapability(): void {
		// Test removing valid capability
		$this->assertTrue( Capabilities::remove_role_capability( 'editor', Capabilities::VIEW_DASHBOARD ) );
		
		// Test removing invalid capability
		$this->assertFalse( Capabilities::remove_role_capability( 'editor', 'invalid_capability' ) );
		
		// Test removing from non-existent role (should fail gracefully)
		$this->assertFalse( Capabilities::remove_role_capability( 'non_existent_role', Capabilities::VIEW_DASHBOARD ) );
	}

	public function testGetCapabilityLabel(): void {
		$this->assertEquals( 'View Dashboard', Capabilities::get_capability_label( Capabilities::VIEW_DASHBOARD ) );
                $this->assertEquals( 'Manage Data Sources', Capabilities::get_capability_label( Capabilities::MANAGE_DATA_SOURCES ) );
                $this->assertEquals( 'Export Reports', Capabilities::get_capability_label( Capabilities::EXPORT_REPORTS ) );
                $this->assertEquals( 'Export Data', Capabilities::get_capability_label( Capabilities::EXPORT_DATA ) );
                $this->assertEquals( 'Manage Alerts', Capabilities::get_capability_label( Capabilities::MANAGE_ALERTS ) );
                $this->assertEquals( 'Manage Settings', Capabilities::get_capability_label( Capabilities::MANAGE_SETTINGS ) );
                $this->assertEquals( 'Manage Campaigns', Capabilities::get_capability_label( Capabilities::MANAGE_CAMPAIGNS ) );
                $this->assertEquals( 'Manage Conversion Events', Capabilities::get_capability_label( Capabilities::MANAGE_CONVERSIONS ) );
                $this->assertEquals( 'View Segments', Capabilities::get_capability_label( Capabilities::VIEW_SEGMENTS ) );
                $this->assertEquals( 'Manage Segments', Capabilities::get_capability_label( Capabilities::MANAGE_SEGMENTS ) );
                $this->assertEquals( 'Access Funnel Analysis', Capabilities::get_capability_label( Capabilities::FUNNEL_ANALYSIS ) );
                $this->assertEquals( 'View Reports', Capabilities::get_capability_label( Capabilities::VIEW_REPORTS ) );

                // Test unknown capability
                $this->assertEquals( 'unknown_cap', Capabilities::get_capability_label( 'unknown_cap' ) );
        }

	public function testGetCapabilityDescription(): void {
                $dashboard_desc = Capabilities::get_capability_description( Capabilities::VIEW_DASHBOARD );
                $this->assertStringContainsString( 'dashboard', strtolower( $dashboard_desc ) );

                $datasources_desc = Capabilities::get_capability_description( Capabilities::MANAGE_DATA_SOURCES );
                $this->assertStringContainsString( 'data source', strtolower( $datasources_desc ) );

                $reports_desc = Capabilities::get_capability_description( Capabilities::EXPORT_REPORTS );
                $this->assertStringContainsString( 'report', strtolower( $reports_desc ) );

                $data_desc = Capabilities::get_capability_description( Capabilities::EXPORT_DATA );
                $this->assertStringContainsString( 'export', strtolower( $data_desc ) );

                $alerts_desc = Capabilities::get_capability_description( Capabilities::MANAGE_ALERTS );
                $this->assertStringContainsString( 'alert', strtolower( $alerts_desc ) );

                $settings_desc = Capabilities::get_capability_description( Capabilities::MANAGE_SETTINGS );
                $this->assertStringContainsString( 'settings', strtolower( $settings_desc ) );

                $campaigns_desc = Capabilities::get_capability_description( Capabilities::MANAGE_CAMPAIGNS );
                $this->assertStringContainsString( 'campaign', strtolower( $campaigns_desc ) );

                $conversions_desc = Capabilities::get_capability_description( Capabilities::MANAGE_CONVERSIONS );
                $this->assertStringContainsString( 'conversion', strtolower( $conversions_desc ) );

                $view_segments_desc = Capabilities::get_capability_description( Capabilities::VIEW_SEGMENTS );
                $this->assertStringContainsString( 'segment', strtolower( $view_segments_desc ) );

                $manage_segments_desc = Capabilities::get_capability_description( Capabilities::MANAGE_SEGMENTS );
                $this->assertStringContainsString( 'segment', strtolower( $manage_segments_desc ) );

                $funnel_desc = Capabilities::get_capability_description( Capabilities::FUNNEL_ANALYSIS );
                $this->assertStringContainsString( 'funnel', strtolower( $funnel_desc ) );

                $view_reports_desc = Capabilities::get_capability_description( Capabilities::VIEW_REPORTS );
                $this->assertStringContainsString( 'report', strtolower( $view_reports_desc ) );

                // Test unknown capability
                $this->assertEquals( '', Capabilities::get_capability_description( 'unknown_cap' ) );
        }

	public function testGetRoleCapabilities(): void {
		// Mock role with capabilities
		$role = get_role( 'administrator' );
		$role->add_cap( Capabilities::VIEW_DASHBOARD, true );
		$role->add_cap( Capabilities::MANAGE_DATA_SOURCES, true );
		$role->add_cap( 'edit_posts', true ); // Non-custom capability
		
		// Mock the capabilities array to include the added caps
		$role->capabilities = [
			Capabilities::VIEW_DASHBOARD => true,
			Capabilities::MANAGE_DATA_SOURCES => true,
			'edit_posts' => true,
		];
		
		$capabilities = Capabilities::get_role_capabilities( 'administrator' );
		
		// Should only return custom capabilities
		$this->assertContains( Capabilities::VIEW_DASHBOARD, $capabilities );
		$this->assertContains( Capabilities::MANAGE_DATA_SOURCES, $capabilities );
		$this->assertNotContains( 'edit_posts', $capabilities );
		
		// Test non-existent role
		$this->assertEmpty( Capabilities::get_role_capabilities( 'non_existent_role' ) );
	}
}