<?php
/**
 * Site Health integration for FP Digital Marketing Suite.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Database\AudienceSegmentTable;
use FP\DigitalMarketing\Database\CustomerJourneyTable;
use FP\DigitalMarketing\Database\CustomReportsTable;
use FP\DigitalMarketing\Database\DetectedAnomaliesTable;
use FP\DigitalMarketing\Database\FunnelTable;
use FP\DigitalMarketing\Database\MetricsCacheTable;
use FP\DigitalMarketing\Database\SocialSentimentTable;
use FP\DigitalMarketing\Database\ConversionEventsTable;
use FP\DigitalMarketing\Database\UTMCampaignsTable;
use FP\DigitalMarketing\Database\AlertRulesTable;
use FP\DigitalMarketing\Database\AnomalyRulesTable;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Registers custom Site Health checks that help administrators verify
 * that the plugin is correctly configured in production environments.
 */
class SiteHealth {

        /**
         * Prefix used for identifying Site Health tests registered by the plugin.
         */
        private const TEST_PREFIX = 'fp_dms_';

        /**
         * Initialize the Site Health integration.
         *
         * @return void
         */
        public static function init(): void {
                if ( ! function_exists( 'add_filter' ) ) {
                        return;
                }

                add_filter( 'site_status_tests', [ self::class, 'register_tests' ] );
        }

        /**
         * Register plugin specific Site Health tests.
         *
         * @param array<string, mixed> $tests Existing tests.
         * @return array<string, mixed>
         */
        public static function register_tests( array $tests ): array {
                $tests['direct'][ self::TEST_PREFIX . 'database' ] = [
                        'label' => __( 'FP Digital Marketing Suite database tables', 'fp-digital-marketing' ),
                        'test'  => [ self::class, 'test_database_tables' ],
                ];

                $tests['direct'][ self::TEST_PREFIX . 'scheduled_events' ] = [
                        'label' => __( 'FP Digital Marketing Suite scheduled events', 'fp-digital-marketing' ),
                        'test'  => [ self::class, 'test_scheduled_events' ],
                ];

                return $tests;
        }

        /**
         * Verify that required database tables exist.
         *
         * @return array<string, mixed>
         */
        public static function test_database_tables(): array {
                $missing_tables = [];

                $table_checks = [
                        'metrics_cache' => static function(): bool {
                                return class_exists( MetricsCacheTable::class ) && MetricsCacheTable::table_exists();
                        },
                        'conversion_events' => static function(): bool {
                                return class_exists( ConversionEventsTable::class ) && ConversionEventsTable::table_exists();
                        },
                        'audience_segments' => static function(): bool {
                                return class_exists( AudienceSegmentTable::class ) && AudienceSegmentTable::segments_table_exists();
                        },
                        'audience_membership' => static function(): bool {
                                return class_exists( AudienceSegmentTable::class ) && AudienceSegmentTable::membership_table_exists();
                        },
                        'utm_campaigns' => static function(): bool {
                                return class_exists( UTMCampaignsTable::class ) && UTMCampaignsTable::table_exists();
                        },
                        'funnels' => static function(): bool {
                                return class_exists( FunnelTable::class ) && FunnelTable::table_exists() && FunnelTable::stages_table_exists();
                        },
                        'customer_journeys' => static function(): bool {
                                return class_exists( CustomerJourneyTable::class ) && CustomerJourneyTable::table_exists() && CustomerJourneyTable::sessions_table_exists();
                        },
                        'custom_reports' => static function(): bool {
                                return class_exists( CustomReportsTable::class ) && CustomReportsTable::table_exists();
                        },
                        'social_sentiment' => static function(): bool {
                                return class_exists( SocialSentimentTable::class ) && SocialSentimentTable::table_exists();
                        },
                        'alert_rules' => static function(): bool {
                                return class_exists( AlertRulesTable::class ) && AlertRulesTable::table_exists();
                        },
                        'anomaly_rules' => static function(): bool {
                                return class_exists( AnomalyRulesTable::class ) && AnomalyRulesTable::table_exists();
                        },
                        'detected_anomalies' => static function(): bool {
                                return class_exists( DetectedAnomaliesTable::class ) && DetectedAnomaliesTable::table_exists();
                        },
                ];

                foreach ( $table_checks as $name => $callback ) {
                        try {
                                if ( ! $callback() ) {
                                        $missing_tables[] = $name;
                                }
                        } catch ( \Throwable $error ) {
                                $missing_tables[] = $name;
                        }
                }

                if ( empty( $missing_tables ) ) {
                        return self::build_result(
                                'database',
                                'good',
                                __( 'Tutte le tabelle richieste dal plugin sono state trovate.', 'fp-digital-marketing' )
                        );
                }

                $missing_list = implode( ', ', array_map( [ self::class, 'escape_text' ], $missing_tables ) );

                $description  = sprintf(
                        /* translators: %s: missing table names */
                        __( 'Le seguenti tabelle del plugin risultano mancanti: %s. Esegui nuovamente l\'attivazione del plugin per ricrearle oppure verifica i permessi del database.', 'fp-digital-marketing' ),
                        $missing_list
                );

                return self::build_result( 'database', 'critical', $description );
        }

        /**
         * Check that scheduled events are registered.
         *
         * @return array<string, mixed>
         */
        public static function test_scheduled_events(): array {
                if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
                        return self::build_result(
                                'scheduled_events',
                                'recommended',
                                __( 'WP-Cron risulta disabilitato. Alcune automazioni del plugin potrebbero non essere eseguite.', 'fp-digital-marketing' )
                        );
                }

                if ( ! function_exists( 'wp_next_scheduled' ) ) {
                        return self::build_result(
                                'scheduled_events',
                                'recommended',
                                __( 'Impossibile verificare gli eventi pianificati perché wp_next_scheduled() non è disponibile.', 'fp-digital-marketing' )
                        );
                }

                $scheduled_hooks = [
                        'fp_dms_sync_data_sources'  => [
                                'label'   => __( 'Sincronizzazione sorgenti dati', 'fp-digital-marketing' ),
                                'enabled' => static function(): bool {
                                        $settings = get_option( 'fp_digital_marketing_sync_settings', [] );
                                        return is_array( $settings ) && ! empty( $settings['enable_sync'] );
                                },
                        ],
                        'fp_dms_generate_reports'   => [
                                'label'   => __( 'Generazione report programmati', 'fp-digital-marketing' ),
                                'enabled' => static function(): bool {
                                        return true;
                                },
                        ],
                        'fp_dms_evaluate_all_segments' => [
                                'label'   => __( 'Valutazione segmenti audience', 'fp-digital-marketing' ),
                                'enabled' => static function(): bool {
                                        return true;
                                },
                        ],
                        'fp_dms_cache_warmup'       => [
                                'label'   => __( 'Pre-caricamento cache performance', 'fp-digital-marketing' ),
                                'enabled' => static function(): bool {
                                        return PerformanceCache::is_cache_enabled();
                                },
                        ],
                        'fp_dms_daily_digest'       => [
                                'label'   => __( 'Riepilogo email giornaliero', 'fp-digital-marketing' ),
                                'enabled' => static function(): bool {
                                        $settings = get_option( 'fp_digital_marketing_email_settings', [] );
                                        return is_array( $settings ) && ( $settings['daily_digest_enabled'] ?? false );
                                },
                        ],
                ];

                $missing_events = [];

                foreach ( $scheduled_hooks as $hook => $metadata ) {
                        try {
                                if ( is_callable( $metadata['enabled'] ) && ! $metadata['enabled']() ) {
                                        continue;
                                }

                                if ( false === wp_next_scheduled( $hook ) ) {
                                        $missing_events[] = $metadata['label'];
                                }
                        } catch ( \Throwable $error ) {
                                $missing_events[] = $metadata['label'];
                        }
                }

                if ( empty( $missing_events ) ) {
                        return self::build_result(
                                'scheduled_events',
                                'good',
                                __( 'Tutti gli eventi pianificati critici risultano programmati.', 'fp-digital-marketing' )
                        );
                }

                $missing_list = implode( ', ', array_map( [ self::class, 'escape_text' ], $missing_events ) );
                $description  = sprintf(
                        /* translators: %s: list of missing events */
                        __( 'Gli eventi pianificati mancanti sono: %s. Verifica che il cron di WordPress sia attivo e ri-salva le impostazioni del plugin per riprogrammarli.', 'fp-digital-marketing' ),
                        $missing_list
                );

                return self::build_result( 'scheduled_events', 'recommended', $description );
        }

        /**
         * Build a Site Health test response.
         *
         * @param string $slug        Test slug suffix.
         * @param string $status      Status (good|recommended|critical).
         * @param string $description Description text.
         * @return array<string, mixed>
         */
        private static function build_result( string $slug, string $status, string $description ): array {
                return [
                        'label'       => __( 'FP Digital Marketing Suite', 'fp-digital-marketing' ),
                        'status'      => $status,
                        'badge'       => [
                                'label' => __( 'FP DMS', 'fp-digital-marketing' ),
                                'color' => 'blue',
                        ],
                        'description' => sprintf( '<p>%s</p>', $description ),
                        'actions'     => [],
                        'test'        => self::TEST_PREFIX . $slug,
                ];
        }

        /**
         * Escape a string for safe HTML output even when esc_html() is unavailable.
         *
         * @param string $value Raw string value.
         * @return string Escaped string.
         */
        private static function escape_text( string $value ): string {
                if ( function_exists( 'esc_html' ) ) {
                        return esc_html( $value );
                }

                return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
        }
}
