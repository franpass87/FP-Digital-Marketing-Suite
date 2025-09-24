<?php
/**
 * Centralized settings manager for option keys and menu state.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Setup;

/**
 * Provides constants and helpers for managing plugin options.
 */
class SettingsManager {

        /**
         * Wizard progress option.
         */
        public const OPTION_WIZARD_PROGRESS = 'fp_digital_marketing_wizard_progress';

        /**
         * Wizard completed option.
         */
        public const OPTION_WIZARD_COMPLETED = 'fp_digital_marketing_wizard_completed';

        /**
         * Wizard menu state option.
         */
        public const OPTION_WIZARD_MENU_STATE = 'fp_digital_marketing_menu_state';

        /**
         * API keys option.
         */
        public const OPTION_API_KEYS = 'fp_digital_marketing_api_keys';

        /**
         * Sync settings option.
         */
        public const OPTION_SYNC_SETTINGS = 'fp_digital_marketing_sync_settings';

        /**
         * Report configuration option.
         */
        public const OPTION_REPORT_CONFIG = 'fp_digital_marketing_report_config';

        /**
         * User feedback option.
         */
        public const OPTION_USER_FEEDBACK = 'fp_digital_marketing_user_feedback';

        /**
         * OAuth state option.
         */
        public const OPTION_OAUTH_STATE = 'fp_digital_marketing_oauth_state';

        /**
         * Google OAuth tokens option.
         */
        public const OPTION_GOOGLE_OAUTH_TOKENS = 'fp_digital_marketing_google_oauth_tokens';

        /**
         * Google OAuth settings option.
         */
        public const OPTION_GOOGLE_OAUTH_SETTINGS = 'fp_digital_marketing_google_oauth_settings';

        /**
         * Key used to track wizard availability inside the menu state option.
         */
        private const MENU_STATE_WIZARD_ENABLED_KEY = 'wizard_enabled';

        /**
         * Key used to store registered menu slugs inside the menu state option.
         */
        private const MENU_STATE_REGISTERED_SLUGS_KEY = 'registered_slugs';

        /**
         * Legacy option fallbacks for backwards compatibility.
         *
         * @var array<string, array<int, string>>
         */
        private const OPTION_FALLBACKS = [
                self::OPTION_OAUTH_STATE => [ 'fp_dms_oauth_state' ],
                self::OPTION_GOOGLE_OAUTH_TOKENS => [ 'fp_dms_google_oauth_tokens' ],
                self::OPTION_GOOGLE_OAUTH_SETTINGS => [ 'fp_dms_google_oauth_settings' ],
        ];

        /**
         * Get an option value with fallback support.
         *
         * @param string $option  Option name.
         * @param mixed  $default Default value.
         * @return mixed
         */
        public static function get_option( string $option, $default = false ) {
                $sentinel = new \stdClass();
                $value = get_option( $option, $sentinel );

                if ( $value !== $sentinel ) {
                        return $value;
                }

                foreach ( self::OPTION_FALLBACKS[ $option ] ?? [] as $legacy_option ) {
                        $legacy_value = get_option( $legacy_option, $sentinel );
                        if ( $legacy_value !== $sentinel ) {
                                return $legacy_value;
                        }
                }

                return $default;
        }

        /**
         * Update an option value.
         *
         * @param string $option   Option name.
         * @param mixed  $value    Option value.
         * @param bool   $autoload Whether to autoload the option.
         * @return bool True on success.
         */
        public static function update_option( string $option, $value, bool $autoload = true ): bool {
                return update_option( $option, $value, $autoload );
        }

        /**
         * Delete an option value, removing any legacy fallbacks as well.
         *
         * @param string $option Option name.
         * @return bool True if the option was deleted.
         */
        public static function delete_option( string $option ): bool {
                $deleted = delete_option( $option );

                foreach ( self::OPTION_FALLBACKS[ $option ] ?? [] as $legacy_option ) {
                        delete_option( $legacy_option );
                }

                return $deleted;
        }

        /**
         * Check if the wizard menu entry is enabled.
         *
         * @return bool
         */
        public static function is_wizard_menu_enabled(): bool {
                $state = self::get_menu_state();

                if ( array_key_exists( self::MENU_STATE_WIZARD_ENABLED_KEY, $state ) ) {
                        return (bool) $state[ self::MENU_STATE_WIZARD_ENABLED_KEY ];
                }

                return true;
        }

        /**
         * Enable the wizard menu entry and ensure the slug is tracked.
         *
         * @param string $slug Menu slug to register.
         * @return void
         */
        public static function enable_wizard_menu( string $slug ): void {
                $slug = self::sanitize_slug( $slug );
                $state = self::get_menu_state();

                $state[ self::MENU_STATE_WIZARD_ENABLED_KEY ] = true;
                $state['wizard_status'] = 'pending';
                $state['updated_at'] = time();

                $registered = $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] ?? [];
                if ( ! in_array( $slug, $registered, true ) ) {
                        $registered[] = $slug;
                }

                $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] = self::sanitize_slug_list( $registered );

                update_option( self::OPTION_WIZARD_MENU_STATE, $state, false );
        }

        /**
         * Disable the wizard menu entry and remove its slug from the registry.
         *
         * @param string $slug   Menu slug to remove.
         * @param string $status Final wizard status (completed, skipped, etc.).
         * @return void
         */
        public static function disable_wizard_menu( string $slug, string $status = 'completed' ): void {
                $slug = self::sanitize_slug( $slug );
                $state = self::get_menu_state();

                $state[ self::MENU_STATE_WIZARD_ENABLED_KEY ] = false;
                $state['wizard_status'] = $status;
                $state['wizard_completed_at'] = time();
                $state['updated_at'] = time();

                $registered = $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] ?? [];
                $registered = array_values( array_filter(
                        $registered,
                        static function ( string $existing ) use ( $slug ) {
                                return $existing !== $slug;
                        }
                ) );

                $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] = self::sanitize_slug_list( $registered );

                update_option( self::OPTION_WIZARD_MENU_STATE, $state, false );
        }

        /**
         * Persist the list of registered menu slugs.
         *
         * @param array<int, string> $slugs Menu slugs to store.
         * @return void
         */
        public static function set_registered_menu_slugs( array $slugs ): void {
                $state = self::get_menu_state();
                $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] = self::sanitize_slug_list( $slugs );
                $state['updated_at'] = time();

                update_option( self::OPTION_WIZARD_MENU_STATE, $state, false );
        }

        /**
         * Remove a slug from the registered menu list without altering other state.
         *
         * @param string $slug Menu slug to remove.
         * @return void
         */
        public static function remove_registered_menu_slug( string $slug ): void {
                $slug = self::sanitize_slug( $slug );
                $state = self::get_menu_state();
                $registered = $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] ?? [];

                if ( empty( $registered ) ) {
                        return;
                }

                $filtered = array_values( array_filter(
                        $registered,
                        static function ( string $existing ) use ( $slug ) {
                                return $existing !== $slug;
                        }
                ) );

                if ( $filtered === $registered ) {
                        return;
                }

                $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] = self::sanitize_slug_list( $filtered );
                $state['updated_at'] = time();

                update_option( self::OPTION_WIZARD_MENU_STATE, $state, false );
        }

        /**
         * Get the list of registered menu slugs.
         *
         * @return array<int, string>
         */
        public static function get_registered_menu_slugs(): array {
                $state = self::get_menu_state();
                $registered = $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] ?? [];

                return self::sanitize_slug_list( (array) $registered );
        }

        /**
         * Retrieve the persisted menu state array.
         *
         * @return array<string, mixed>
         */
        private static function get_menu_state(): array {
                $state = get_option( self::OPTION_WIZARD_MENU_STATE, [] );

                if ( ! is_array( $state ) ) {
                        return [];
                }

                return $state;
        }

        /**
         * Sanitize a list of slugs ensuring uniqueness.
         *
         * @param array<int, string> $slugs Slugs to sanitize.
         * @return array<int, string>
         */
        private static function sanitize_slug_list( array $slugs ): array {
                $sanitized = array_map( [ self::class, 'sanitize_slug' ], $slugs );
                $sanitized = array_filter( $sanitized, static function ( string $slug ): bool {
                        return $slug !== '';
                } );

                return array_values( array_unique( $sanitized ) );
        }

        /**
         * Sanitize a single slug value.
         *
         * @param string $slug Slug to sanitize.
         * @return string
         */
        private static function sanitize_slug( string $slug ): string {
                if ( function_exists( 'sanitize_key' ) ) {
                        return sanitize_key( $slug );
                }

                return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $slug ) ?? '' );
        }
}
