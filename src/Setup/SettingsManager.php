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
         * Menu state schema version stored within the option payload.
         */
        private const MENU_STATE_SCHEMA_VERSION = 2;

		/**
		 * Legacy wizard completion flag option.
		 */
	private const LEGACY_OPTION_WIZARD_COMPLETED = 'fp_dms_setup_completed';

		/**
		 * Legacy wizard completion timestamp option.
		 */
	private const LEGACY_OPTION_WIZARD_COMPLETED_TIME = 'fp_dms_setup_completed_time';

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
         * Allowed wizard status values persisted within the menu state option.
         *
         * @var array<int, string>
         */
        private const MENU_STATE_ALLOWED_STATUSES = [ 'pending', 'completed', 'skipped', 'hidden' ];

		/**
		 * Legacy option fallbacks for backwards compatibility.
		 *
		 * @var array<string, array<int, string>>
		 */
	private const OPTION_FALLBACKS = [
		self::OPTION_OAUTH_STATE           => [ 'fp_dms_oauth_state' ],
		self::OPTION_GOOGLE_OAUTH_TOKENS   => [ 'fp_dms_google_oauth_tokens' ],
		self::OPTION_GOOGLE_OAUTH_SETTINGS => [ 'fp_dms_google_oauth_settings' ],
	];

		/**
		 * Migrate legacy option names into the canonical configuration keys.
		 *
		 * @return void
		 */
        public static function migrate_legacy_options(): void {
                if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
                                return;
                }

                        self::migrate_option_fallbacks_to_primary();
                        self::migrate_wizard_completion_state();
        }

        /**
         * Upgrade the persisted menu state to the current schema.
         *
         * @return void
         */
        public static function upgrade_menu_state_schema(): void {
                if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
                                return;
                }

                        $state = get_option( self::OPTION_WIZARD_MENU_STATE, [] );

                if ( ! is_array( $state ) ) {
                                update_option( self::OPTION_WIZARD_MENU_STATE, [], false );

                                return;
                }

                        $normalized = self::normalize_menu_state_payload( $state );

                if ( $normalized !== $state ) {
                                update_option( self::OPTION_WIZARD_MENU_STATE, $normalized, false );
                }
        }

		/**
		 * Get an option value with fallback support.
		 *
		 * @param string $option  Option name.
		 * @param mixed  $default Default value.
		 * @return mixed
		 */
	public static function get_option( string $option, $default = false ) {
			$sentinel = new \stdClass();
			$value    = get_option( $option, $sentinel );

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
		 * Determine whether the onboarding wizard has been completed.
		 *
		 * @return bool
		 */
	public static function is_wizard_completed(): bool {
			$state = self::get_option( self::OPTION_WIZARD_COMPLETED, null );

		if ( is_array( $state ) ) {
				return ! empty( $state['completed'] );
		}

			return (bool) get_option( self::LEGACY_OPTION_WIZARD_COMPLETED, false );
	}

		/**
		 * Enable the wizard menu entry and ensure the slug is tracked.
		 *
		 * @param string $slug Menu slug to register.
		 * @return void
		 */
        public static function enable_wizard_menu( string $slug ): void {
                        $slug  = self::sanitize_slug( $slug );
                        $state = self::get_menu_state();

                        $state[ self::MENU_STATE_WIZARD_ENABLED_KEY ] = true;
                        $state['wizard_status']                       = 'pending';

                        $registered = $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] ?? [];
                if ( ! in_array( $slug, $registered, true ) ) {
                                $registered[] = $slug;
                }

                        $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] = self::sanitize_slug_list( $registered );

                        self::persist_menu_state( $state );
        }

		/**
		 * Disable the wizard menu entry and remove its slug from the registry.
		 *
		 * @param string $slug   Menu slug to remove.
		 * @param string $status Final wizard status (completed, skipped, etc.).
		 * @return void
		 */
        public static function disable_wizard_menu( string $slug, string $status = 'completed' ): void {
                        $slug  = self::sanitize_slug( $slug );
                        $state = self::get_menu_state();

                        $state[ self::MENU_STATE_WIZARD_ENABLED_KEY ] = false;
                        $state['wizard_status']                       = $status;
                        $state['wizard_completed_at']                 = time();

                        $registered = $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] ?? [];
                        $registered = array_values(
                                array_filter(
                                        $registered,
					static function ( string $existing ) use ( $slug ) {
							return $existing !== $slug;
					}
				)
                        );

                        $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] = self::sanitize_slug_list( $registered );

                        self::persist_menu_state( $state );
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

                        self::persist_menu_state( $state );
        }

		/**
		 * Remove a slug from the registered menu list without altering other state.
		 *
		 * @param string $slug Menu slug to remove.
		 * @return void
		 */
	public static function remove_registered_menu_slug( string $slug ): void {
			$slug       = self::sanitize_slug( $slug );
			$state      = self::get_menu_state();
			$registered = $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] ?? [];

		if ( empty( $registered ) ) {
				return;
		}

			$filtered = array_values(
				array_filter(
					$registered,
					static function ( string $existing ) use ( $slug ) {
							return $existing !== $slug;
					}
				)
			);

                if ( $filtered === $registered ) {
                                return;
                }

                        $state[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] = self::sanitize_slug_list( $filtered );

                        self::persist_menu_state( $state );
        }

		/**
		 * Get the list of registered menu slugs.
		 *
		 * @return array<int, string>
		 */
	public static function get_registered_menu_slugs(): array {
			$state      = self::get_menu_state();
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

                        $normalized = self::normalize_menu_state_payload( $state );

                if ( $normalized !== $state ) {
                                update_option( self::OPTION_WIZARD_MENU_STATE, $normalized, false );
                }

                        return $normalized;
        }

		/**
		 * Migrate legacy fallback options into their canonical counterparts.
		 *
		 * @return void
		 */
	private static function migrate_option_fallbacks_to_primary(): void {
			$sentinel = new \stdClass();

		foreach ( self::OPTION_FALLBACKS as $primary => $fallbacks ) {
				$current = get_option( $primary, $sentinel );

			if ( $current !== $sentinel ) {
				continue;
			}

			foreach ( $fallbacks as $legacy_option ) {
					$legacy_value = get_option( $legacy_option, $sentinel );

				if ( $legacy_value === $sentinel ) {
					continue;
				}

					update_option( $primary, $legacy_value, false );
					delete_option( $legacy_option );
						break;
			}
		}
	}

		/**
		 * Normalize the wizard completion option when legacy metadata is detected.
		 *
		 * @return void
		 */
	private static function migrate_wizard_completion_state(): void {
			$sentinel = new \stdClass();
			$current  = get_option( self::OPTION_WIZARD_COMPLETED, $sentinel );

		if ( is_array( $current ) && array_key_exists( 'completed', $current ) ) {
				return;
		}

			$legacy_completed = get_option( self::LEGACY_OPTION_WIZARD_COMPLETED, $sentinel );

		if ( $legacy_completed === $sentinel ) {
				return;
		}

			$timestamp = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time();
			$payload   = is_array( $current ) ? $current : [];

			$payload['completed'] = (bool) $legacy_completed;

			$legacy_time = get_option( self::LEGACY_OPTION_WIZARD_COMPLETED_TIME, $sentinel );

		if ( $legacy_time !== $sentinel && is_numeric( $legacy_time ) ) {
				$payload['completed_at'] = (int) $legacy_time;
		} elseif ( ! isset( $payload['completed_at'] ) ) {
				$payload['completed_at'] = $timestamp;
		}

		if ( ! isset( $payload['migrated_at'] ) ) {
				$payload['migrated_at'] = $timestamp;
		}

			update_option( self::OPTION_WIZARD_COMPLETED, $payload, false );
	}

		/**
		 * Sanitize a list of slugs ensuring uniqueness.
		 *
		 * @param array<int, string> $slugs Slugs to sanitize.
		 * @return array<int, string>
		 */
	private static function sanitize_slug_list( array $slugs ): array {
			$sanitized = array_map( [ self::class, 'sanitize_slug' ], $slugs );
			$sanitized = array_filter(
				$sanitized,
				static function ( string $slug ): bool {
						return $slug !== '';
				}
			);

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

        /**
         * Persist a normalized menu state payload back into WordPress.
         *
         * @param array<string, mixed> $state Menu state to store.
         * @return void
         */
        private static function persist_menu_state( array $state ): void {
                        $timestamp          = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time();
                        $state['updated_at'] = $timestamp;

                        $normalized = self::normalize_menu_state_payload( $state );

                        update_option( self::OPTION_WIZARD_MENU_STATE, $normalized, false );
        }

        /**
         * Normalize the stored menu state ensuring schema metadata exists.
         *
         * @param array<string, mixed> $state Menu state data to normalize.
         * @return array<string, mixed> Normalized menu state payload.
         */
        private static function normalize_menu_state_payload( array $state ): array {
                $normalized = $state;

                $normalized[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] = self::sanitize_slug_list(
                        isset( $normalized[ self::MENU_STATE_REGISTERED_SLUGS_KEY ] )
                                ? (array) $normalized[ self::MENU_STATE_REGISTERED_SLUGS_KEY ]
                                : []
                );

                $normalized[ self::MENU_STATE_WIZARD_ENABLED_KEY ] = isset( $normalized[ self::MENU_STATE_WIZARD_ENABLED_KEY ] )
                        ? (bool) $normalized[ self::MENU_STATE_WIZARD_ENABLED_KEY ]
                        : true;

                $status = isset( $normalized['wizard_status'] ) ? (string) $normalized['wizard_status'] : '';

                if ( ! in_array( $status, self::MENU_STATE_ALLOWED_STATUSES, true ) ) {
                        $normalized['wizard_status'] = self::is_wizard_completed() ? 'completed' : 'pending';
                }

                if ( isset( $normalized['wizard_completed_at'] ) ) {
                        $normalized['wizard_completed_at'] = (int) $normalized['wizard_completed_at'];
                }

                $timestamp = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time();

                $normalized['updated_at'] = isset( $normalized['updated_at'] )
                        ? (int) $normalized['updated_at']
                        : $timestamp;

                $normalized['schema_version'] = self::MENU_STATE_SCHEMA_VERSION;

                return $normalized;
        }
}
