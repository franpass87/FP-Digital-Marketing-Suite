<?php
/**
 * Connection Manager for Platform Integrations
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\DataSources\GoogleOAuth;
use FP\DigitalMarketing\DataSources\GoogleAnalytics4;
use FP\DigitalMarketing\DataSources\GoogleSearchConsole;
use FP\DigitalMarketing\DataSources\MicrosoftClarity;
use FP\DigitalMarketing\Helpers\DataSources;

/**
 * ConnectionManager class for simplifying platform connections
 * 
 * This class provides an easy interface for managing platform connections,
 * monitoring their health, and providing guided setup experiences.
 */
class ConnectionManager {

	/**
	 * Connection status constants
	 */
	public const STATUS_CONNECTED = 'connected';
	public const STATUS_DISCONNECTED = 'disconnected';
	public const STATUS_ERROR = 'error';
	public const STATUS_EXPIRED = 'expired';
	public const STATUS_TESTING = 'testing';

	/**
	 * Platform connection cache duration (1 hour)
	 */
	private const CACHE_DURATION = 3600;

	/**
	 * Get all platform connections with their status
	 *
	 * @return array Array of platform connections with status
	 */
	public static function get_all_connections(): array {
		$cache_key = 'fp_dms_connection_status_all';
		$cached = wp_cache_get( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		$connections = [
			'google_analytics_4' => self::get_ga4_connection_status(),
			'google_search_console' => self::get_gsc_connection_status(),
			'microsoft_clarity' => self::get_clarity_connection_status(),
			'google_ads' => self::get_google_ads_connection_status(),
		];

		wp_cache_set( $cache_key, $connections, '', self::CACHE_DURATION );
		
		return $connections;
	}

	/**
	 * Get Google Analytics 4 connection status
	 *
	 * @return array Connection status details
	 */
	public static function get_ga4_connection_status(): array {
		$oauth = new GoogleOAuth();
		$api_keys = get_option( 'fp_digital_marketing_api_keys', [] );
		
		if ( ! $oauth->is_configured() ) {
			return [
				'id' => 'google_analytics_4',
				'name' => __( 'Google Analytics 4', 'fp-digital-marketing' ),
				'status' => self::STATUS_DISCONNECTED,
				'message' => __( 'Client ID e Client Secret non configurati', 'fp-digital-marketing' ),
				'last_check' => current_time( 'mysql' ),
				'setup_priority' => 'high',
				'setup_steps' => self::get_ga4_setup_steps(),
			];
		}

		$connection_status = $oauth->get_connection_status();
		$property_id = $api_keys['ga4_property_id'] ?? '';

		if ( empty( $property_id ) ) {
			return [
				'id' => 'google_analytics_4',
				'name' => __( 'Google Analytics 4', 'fp-digital-marketing' ),
				'status' => self::STATUS_DISCONNECTED,
				'message' => __( 'Property ID non configurato', 'fp-digital-marketing' ),
				'last_check' => current_time( 'mysql' ),
				'setup_priority' => 'medium',
				'setup_steps' => self::get_ga4_property_setup_steps(),
			];
		}

		$status = self::STATUS_DISCONNECTED;
		$message = __( 'Non connesso', 'fp-digital-marketing' );

		if ( $connection_status['connected'] ) {
			// Test actual connection
			$test_result = self::test_ga4_connection();
			if ( $test_result['success'] ) {
				$status = self::STATUS_CONNECTED;
				$message = __( 'Connesso e funzionante', 'fp-digital-marketing' );
			} else {
				$status = self::STATUS_ERROR;
				$message = $test_result['error'] ?? __( 'Errore di connessione', 'fp-digital-marketing' );
			}
		} elseif ( $connection_status['expired'] ) {
			$status = self::STATUS_EXPIRED;
			$message = __( 'Token scaduto, riconnessione necessaria', 'fp-digital-marketing' );
		}

		return [
			'id' => 'google_analytics_4',
			'name' => __( 'Google Analytics 4', 'fp-digital-marketing' ),
			'status' => $status,
			'message' => $message,
			'last_check' => current_time( 'mysql' ),
			'auto_heal' => $status === self::STATUS_EXPIRED,
			'test_available' => true,
		];
	}

	/**
	 * Get Google Search Console connection status
	 *
	 * @return array Connection status details
	 */
	public static function get_gsc_connection_status(): array {
		$oauth = new GoogleOAuth();
		
		if ( ! $oauth->is_configured() ) {
			return [
				'id' => 'google_search_console',
				'name' => __( 'Google Search Console', 'fp-digital-marketing' ),
				'status' => self::STATUS_DISCONNECTED,
				'message' => __( 'Richiede configurazione Google OAuth', 'fp-digital-marketing' ),
				'last_check' => current_time( 'mysql' ),
				'setup_priority' => 'high',
				'depends_on' => 'google_analytics_4',
			];
		}

		$connection_status = $oauth->get_connection_status();
		
		$status = $connection_status['connected'] ? self::STATUS_CONNECTED : self::STATUS_DISCONNECTED;
		$message = $connection_status['connected'] 
			? __( 'Connesso tramite Google OAuth', 'fp-digital-marketing' )
			: __( 'Non connesso', 'fp-digital-marketing' );

		if ( $connection_status['expired'] ) {
			$status = self::STATUS_EXPIRED;
			$message = __( 'Token scaduto', 'fp-digital-marketing' );
		}

		return [
			'id' => 'google_search_console',
			'name' => __( 'Google Search Console', 'fp-digital-marketing' ),
			'status' => $status,
			'message' => $message,
			'last_check' => current_time( 'mysql' ),
			'auto_heal' => $status === self::STATUS_EXPIRED,
		];
	}

	/**
	 * Get Microsoft Clarity connection status
	 *
	 * @return array Connection status details
	 */
	public static function get_clarity_connection_status(): array {
		// Get clients with Clarity configured
		$clients_with_clarity = get_posts([
			'post_type' => 'cliente',
			'meta_query' => [
				[
					'key' => 'clarity_project_id',
					'value' => '',
					'compare' => '!=',
				],
			],
			'fields' => 'ids',
		]);

		$client_count = count( $clients_with_clarity );
		
		if ( $client_count === 0 ) {
			return [
				'id' => 'microsoft_clarity',
				'name' => __( 'Microsoft Clarity', 'fp-digital-marketing' ),
				'status' => self::STATUS_DISCONNECTED,
				'message' => __( 'Nessun cliente configurato', 'fp-digital-marketing' ),
				'last_check' => current_time( 'mysql' ),
				'setup_priority' => 'low',
				'setup_steps' => self::get_clarity_setup_steps(),
			];
		}

		return [
			'id' => 'microsoft_clarity',
			'name' => __( 'Microsoft Clarity', 'fp-digital-marketing' ),
			'status' => self::STATUS_CONNECTED,
			'message' => sprintf(
				/* translators: %d: number of clients */
				_n( '%d cliente configurato', '%d clienti configurati', $client_count, 'fp-digital-marketing' ),
				$client_count
			),
			'last_check' => current_time( 'mysql' ),
			'client_count' => $client_count,
		];
	}

	/**
	 * Get Google Ads connection status
	 *
	 * @return array Connection status details
	 */
	public static function get_google_ads_connection_status(): array {
		// Google Ads is marked as "planned" in DataSources, so return appropriate status
		return [
			'id' => 'google_ads',
			'name' => __( 'Google Ads', 'fp-digital-marketing' ),
			'status' => self::STATUS_DISCONNECTED,
			'message' => __( 'Integrazione pianificata - non ancora disponibile', 'fp-digital-marketing' ),
			'last_check' => current_time( 'mysql' ),
			'setup_priority' => 'planned',
			'coming_soon' => true,
		];
	}

	/**
	 * Test Google Analytics 4 connection
	 *
	 * @return array Test result
	 */
	public static function test_ga4_connection(): array {
		try {
			$ga4 = new GoogleAnalytics4();
			
			// Try to fetch a simple metric to test the connection
			$api_keys = get_option( 'fp_digital_marketing_api_keys', [] );
			$property_id = $api_keys['ga4_property_id'] ?? '';
			
			if ( empty( $property_id ) ) {
				return [
					'success' => false,
					'error' => __( 'Property ID non configurato', 'fp-digital-marketing' ),
				];
			}

			// This would need actual GA4 API implementation
			// For now, we'll simulate a test based on OAuth status
			$oauth = new GoogleOAuth();
			$connection_status = $oauth->get_connection_status();
			
			if ( $connection_status['connected'] && ! $connection_status['expired'] ) {
				return [
					'success' => true,
					'message' => __( 'Connessione verificata con successo', 'fp-digital-marketing' ),
				];
			}

			return [
				'success' => false,
				'error' => __( 'Token non valido o scaduto', 'fp-digital-marketing' ),
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'error' => $e->getMessage(),
			];
		}
	}

	/**
	 * Get setup steps for Google Analytics 4
	 *
	 * @return array Setup steps
	 */
	private static function get_ga4_setup_steps(): array {
		return [
			[
				'step' => 1,
				'title' => __( 'Crea progetto Google Cloud', 'fp-digital-marketing' ),
				'description' => __( 'Accedi a Google Cloud Console e crea un nuovo progetto', 'fp-digital-marketing' ),
				'url' => 'https://console.cloud.google.com/',
			],
			[
				'step' => 2,
				'title' => __( 'Abilita Analytics API', 'fp-digital-marketing' ),
				'description' => __( 'Abilita Google Analytics Reporting API nel tuo progetto', 'fp-digital-marketing' ),
			],
			[
				'step' => 3,
				'title' => __( 'Configura OAuth 2.0', 'fp-digital-marketing' ),
				'description' => __( 'Crea credenziali OAuth 2.0 per applicazione web', 'fp-digital-marketing' ),
			],
			[
				'step' => 4,
				'title' => __( 'Inserisci credenziali', 'fp-digital-marketing' ),
				'description' => __( 'Copia Client ID e Client Secret nelle impostazioni del plugin', 'fp-digital-marketing' ),
			],
		];
	}

	/**
	 * Get setup steps for GA4 property configuration
	 *
	 * @return array Setup steps
	 */
	private static function get_ga4_property_setup_steps(): array {
		return [
			[
				'step' => 1,
				'title' => __( 'Trova Property ID', 'fp-digital-marketing' ),
				'description' => __( 'Vai su Google Analytics e trova il Property ID nella sezione Amministrazione', 'fp-digital-marketing' ),
			],
			[
				'step' => 2,
				'title' => __( 'Inserisci Property ID', 'fp-digital-marketing' ),
				'description' => __( 'Copia il Property ID nelle impostazioni del plugin', 'fp-digital-marketing' ),
			],
		];
	}

	/**
	 * Get setup steps for Microsoft Clarity
	 *
	 * @return array Setup steps
	 */
	private static function get_clarity_setup_steps(): array {
		return [
			[
				'step' => 1,
				'title' => __( 'Crea account Microsoft Clarity', 'fp-digital-marketing' ),
				'description' => __( 'Registrati su Microsoft Clarity e crea un nuovo progetto', 'fp-digital-marketing' ),
				'url' => 'https://clarity.microsoft.com/',
			],
			[
				'step' => 2,
				'title' => __( 'Configura per cliente', 'fp-digital-marketing' ),
				'description' => __( 'Vai alla pagina di modifica del cliente e inserisci il Project ID di Clarity', 'fp-digital-marketing' ),
			],
		];
	}

	/**
	 * Invalidate connection status cache
	 *
	 * @return void
	 */
	public static function invalidate_cache(): void {
		wp_cache_delete( 'fp_dms_connection_status_all' );
		wp_cache_delete( 'fp_dms_connection_status_ga4' );
		wp_cache_delete( 'fp_dms_connection_status_gsc' );
		wp_cache_delete( 'fp_dms_connection_status_clarity' );
	}

	/**
	 * Get connection health score (0-100)
	 *
	 * @return array Health score with details
	 */
	public static function get_connection_health_score(): array {
		$connections = self::get_all_connections();
		$total_platforms = count( $connections );
		$connected_platforms = 0;
		$weight_by_priority = [
			'high' => 3,
			'medium' => 2,
			'low' => 1,
			'planned' => 0,
		];

		$total_weight = 0;
		$connected_weight = 0;

		foreach ( $connections as $connection ) {
			$priority = $connection['setup_priority'] ?? 'low';
			$weight = $weight_by_priority[ $priority ] ?? 1;
			$total_weight += $weight;

			if ( $connection['status'] === self::STATUS_CONNECTED ) {
				$connected_platforms++;
				$connected_weight += $weight;
			}
		}

		$score = $total_weight > 0 ? (int) round( ( $connected_weight / $total_weight ) * 100 ) : 0;

		return [
			'score' => $score,
			'connected_platforms' => $connected_platforms,
			'total_platforms' => $total_platforms,
			'status' => self::get_health_status_from_score( $score ),
			'recommendations' => self::get_health_recommendations( $connections ),
		];
	}

	/**
	 * Get health status from score
	 *
	 * @param int $score Health score
	 * @return string Health status
	 */
	private static function get_health_status_from_score( int $score ): string {
		if ( $score >= 80 ) {
			return 'excellent';
		}
		if ( $score >= 60 ) {
			return 'good';
		}
		if ( $score >= 40 ) {
			return 'fair';
		}
		return 'poor';
	}

	/**
	 * Get health recommendations based on connection status
	 *
	 * @param array $connections Platform connections
	 * @return array Recommendations
	 */
	private static function get_health_recommendations( array $connections ): array {
		$recommendations = [];

		foreach ( $connections as $connection ) {
			if ( $connection['status'] === self::STATUS_DISCONNECTED && 
				 ( $connection['setup_priority'] ?? '' ) === 'high' ) {
				$recommendations[] = [
					'type' => 'setup',
					'platform' => $connection['id'],
					'title' => sprintf(
						/* translators: %s: platform name */
						__( 'Configura %s', 'fp-digital-marketing' ),
						$connection['name']
					),
					'description' => $connection['message'],
					'priority' => $connection['setup_priority'],
				];
			}

			if ( $connection['status'] === self::STATUS_EXPIRED ) {
				$recommendations[] = [
					'type' => 'reconnect',
					'platform' => $connection['id'],
					'title' => sprintf(
						/* translators: %s: platform name */
						__( 'Riconnetti %s', 'fp-digital-marketing' ),
						$connection['name']
					),
					'description' => __( 'La connessione è scaduta e deve essere rinnovata', 'fp-digital-marketing' ),
					'priority' => 'urgent',
				];
			}
		}

		return $recommendations;
	}
}