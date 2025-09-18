<?php
/**
 * Tests for admin menu fallback behavior when MenuManager state changes.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Admin\MenuManager;
use FP\DigitalMarketing\Admin\Settings;
use FP\DigitalMarketing\Admin\Reports;

if ( ! function_exists( 'add_submenu_page' ) ) {
        /**
         * Minimal stub for WordPress add_submenu_page() used during testing.
         *
         * @return string
         */
        function add_submenu_page(
                string $parent_slug,
                string $page_title,
                string $menu_title,
                string $capability,
                string $menu_slug,
                $callback = ''
        ) {
                global $fp_dms_submenu_calls;

                if ( ! is_array( $fp_dms_submenu_calls ) ) {
                        $fp_dms_submenu_calls = [];
                }

                $fp_dms_submenu_calls[] = [
                        'parent_slug' => $parent_slug,
                        'page_title'  => $page_title,
                        'menu_title'  => $menu_title,
                        'capability'  => $capability,
                        'menu_slug'   => $menu_slug,
                        'callback'    => $callback,
                ];

                return $menu_slug;
        }
}

/**
 * Ensure Settings/Reports submenus fall back correctly when MenuManager is inactive.
 */
class AdminMenuFallbackTest extends TestCase {

        /**
         * Reset submenu capture array before each test.
         */
        protected function setUp(): void {
                parent::setUp();

                global $fp_dms_submenu_calls;
                $fp_dms_submenu_calls = [];

                $this->set_menu_manager_initialized( false );
        }

        /**
         * Ensure we reset the MenuManager initialization flag after each test.
         */
        protected function tearDown(): void {
                $this->set_menu_manager_initialized( false );

                parent::tearDown();
        }

        /**
         * Helper to flip the private MenuManager initialization flag.
         */
        private function set_menu_manager_initialized( bool $state ): void {
                $reflection = new \ReflectionClass( MenuManager::class );
                $property   = $reflection->getProperty( 'initialized' );
                $property->setAccessible( true );
                $property->setValue( null, $state );
        }

        /**
         * When MenuManager is inactive the legacy submenus should still register.
         */
        public function test_submenus_register_when_menu_manager_inactive(): void {
                $settings = new Settings();
                $reports  = new Reports();

                $settings->add_admin_menu();
                $reports->add_admin_menu();

                global $fp_dms_submenu_calls;
                $menu_slugs = array_column( $fp_dms_submenu_calls, 'menu_slug' );

                $this->assertContains( 'fp-digital-marketing-settings', $menu_slugs );
                $this->assertContains( 'fp-digital-marketing-reports', $menu_slugs );
        }

        /**
         * When MenuManager is initialized it should prevent duplicate submenu registration.
         */
        public function test_submenus_not_registered_when_menu_manager_initialized(): void {
                $menu_manager = new MenuManager();
                $menu_manager->init();

                $settings = new Settings();
                $reports  = new Reports();

                $settings->add_admin_menu();
                $reports->add_admin_menu();

                global $fp_dms_submenu_calls;
                $this->assertSame( [], $fp_dms_submenu_calls );
        }
}
