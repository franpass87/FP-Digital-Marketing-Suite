<?php
/**
 * Enhanced Anomaly Suggestion Engine
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Helpers\MetricsSchema;
use FP\DigitalMarketing\Helpers\ConnectionManager;
use FP\DigitalMarketing\Models\DetectedAnomaly;

/**
 * AnomalySuggestionEngine class for providing actionable insights
 * 
 * This class analyzes detected anomalies and provides specific,
 * actionable suggestions for resolving issues or capitalizing on positive trends.
 */
class AnomalySuggestionEngine {

	/**
	 * Suggestion categories
	 */
	public const CATEGORY_TECHNICAL = 'technical';
	public const CATEGORY_CONTENT = 'content';
	public const CATEGORY_MARKETING = 'marketing';
	public const CATEGORY_PERFORMANCE = 'performance';
	public const CATEGORY_PLATFORM = 'platform';

	/**
	 * Suggestion priority levels
	 */
	public const PRIORITY_CRITICAL = 'critical';
	public const PRIORITY_HIGH = 'high';
	public const PRIORITY_MEDIUM = 'medium';
	public const PRIORITY_LOW = 'low';

	/**
	 * Generate comprehensive suggestions for an anomaly
	 *
	 * @param DetectedAnomaly $anomaly Detected anomaly
	 * @param array $context Additional context data
	 * @return array Array of suggestions
	 */
	public static function generate_suggestions( DetectedAnomaly $anomaly, array $context = [] ): array {
		$metric = $anomaly->get_metric();
		$deviation_type = $anomaly->get_severity_score() > 0 ? 'positive' : 'negative';
		$client_id = $anomaly->get_client_id();

		$suggestions = [];

		// Get metric-specific suggestions
		$metric_suggestions = self::get_metric_specific_suggestions( $metric, $deviation_type, $anomaly );
		$suggestions = array_merge( $suggestions, $metric_suggestions );

		// Add platform connection suggestions if relevant
		$platform_suggestions = self::get_platform_suggestions( $metric, $client_id );
		$suggestions = array_merge( $suggestions, $platform_suggestions );

		// Add contextual suggestions based on other metrics
		$contextual_suggestions = self::get_contextual_suggestions( $anomaly, $context );
		$suggestions = array_merge( $suggestions, $contextual_suggestions );

		// Sort by priority and return top suggestions
		usort( $suggestions, function( $a, $b ) {
			$priority_order = [
				self::PRIORITY_CRITICAL => 4,
				self::PRIORITY_HIGH => 3,
				self::PRIORITY_MEDIUM => 2,
				self::PRIORITY_LOW => 1,
			];
			return ( $priority_order[ $b['priority'] ] ?? 0 ) <=> ( $priority_order[ $a['priority'] ] ?? 0 );
		});

		return array_slice( $suggestions, 0, 5 ); // Return top 5 suggestions
	}

	/**
	 * Get metric-specific suggestions
	 *
	 * @param string $metric Metric name
	 * @param string $deviation_type Positive or negative deviation
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return array Suggestions
	 */
	private static function get_metric_specific_suggestions( string $metric, string $deviation_type, DetectedAnomaly $anomaly ): array {
		$suggestions = [];

		switch ( $metric ) {
			case MetricsSchema::KPI_SESSIONS:
				$suggestions = self::get_sessions_suggestions( $deviation_type, $anomaly );
				break;

			case MetricsSchema::KPI_PAGEVIEWS:
				$suggestions = self::get_pageviews_suggestions( $deviation_type, $anomaly );
				break;

			case MetricsSchema::KPI_BOUNCE_RATE:
				$suggestions = self::get_bounce_rate_suggestions( $deviation_type, $anomaly );
				break;

			case MetricsSchema::KPI_CONVERSION_RATE:
				$suggestions = self::get_conversion_rate_suggestions( $deviation_type, $anomaly );
				break;

			case MetricsSchema::KPI_ORGANIC_CLICKS:
				$suggestions = self::get_organic_clicks_suggestions( $deviation_type, $anomaly );
				break;

			case MetricsSchema::KPI_ORGANIC_IMPRESSIONS:
				$suggestions = self::get_organic_impressions_suggestions( $deviation_type, $anomaly );
				break;

			case MetricsSchema::KPI_LCP:
			case MetricsSchema::KPI_INP:
			case MetricsSchema::KPI_CLS:
				$suggestions = self::get_core_web_vitals_suggestions( $metric, $deviation_type, $anomaly );
				break;

			default:
				$suggestions = self::get_generic_suggestions( $metric, $deviation_type, $anomaly );
		}

		return $suggestions;
	}

	/**
	 * Get suggestions for sessions anomalies
	 *
	 * @param string $deviation_type Positive or negative
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return array Suggestions
	 */
	private static function get_sessions_suggestions( string $deviation_type, DetectedAnomaly $anomaly ): array {
		if ( $deviation_type === 'negative' ) {
			return [
				[
					'title' => __( 'Verifica problemi tecnici del sito', 'fp-digital-marketing' ),
					'description' => __( 'Controlla se ci sono problemi di caricamento, errori server o problemi di accessibilità', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_TECHNICAL,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Testa la velocità del sito', 'fp-digital-marketing' ),
						__( 'Verifica i Core Web Vitals', 'fp-digital-marketing' ),
						__( 'Controlla i log degli errori del server', 'fp-digital-marketing' ),
					],
				],
				[
					'title' => __( 'Analizza i canali di traffico', 'fp-digital-marketing' ),
					'description' => __( 'Identifica quale canale di traffico ha subito il calo maggiore', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Verifica le campagne pubblicitarie attive', 'fp-digital-marketing' ),
						__( 'Controlla il posizionamento SEO', 'fp-digital-marketing' ),
						__( 'Analizza il traffico social media', 'fp-digital-marketing' ),
					],
				],
				[
					'title' => __( 'Controlla eventi esterni', 'fp-digital-marketing' ),
					'description' => __( 'Verifica se ci sono stati eventi esterni che potrebbero aver influenzato il traffico', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_MEDIUM,
					'actions' => [
						__( 'Controlla giorni festivi o eventi stagionali', 'fp-digital-marketing' ),
						__( 'Verifica cambiamenti nell\'algoritmo di Google', 'fp-digital-marketing' ),
						__( 'Analizza l\'attività dei concorrenti', 'fp-digital-marketing' ),
					],
				],
			];
		} else {
			return [
				[
					'title' => __( 'Capitalizza sul picco di traffico', 'fp-digital-marketing' ),
					'description' => __( 'Sfrutta l\'aumento del traffico per massimizzare le conversioni', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Ottimizza le landing page per le conversioni', 'fp-digital-marketing' ),
						__( 'Implementa pop-up strategici o banner promozionali', 'fp-digital-marketing' ),
						__( 'Monitora la capacità del server', 'fp-digital-marketing' ),
					],
				],
				[
					'title' => __( 'Analizza la fonte dell\'aumento', 'fp-digital-marketing' ),
					'description' => __( 'Identifica cosa ha causato l\'aumento per replicare il successo', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_MEDIUM,
					'actions' => [
						__( 'Analizza i referrer e le sorgenti di traffico', 'fp-digital-marketing' ),
						__( 'Verifica le campagne e contenuti recenti', 'fp-digital-marketing' ),
						__( 'Documenta le strategie di successo', 'fp-digital-marketing' ),
					],
				],
			];
		}
	}

	/**
	 * Get suggestions for bounce rate anomalies
	 *
	 * @param string $deviation_type Positive or negative
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return array Suggestions
	 */
	private static function get_bounce_rate_suggestions( string $deviation_type, DetectedAnomaly $anomaly ): array {
		if ( $deviation_type === 'positive' ) { // High bounce rate is negative
			return [
				[
					'title' => __( 'Ottimizza la velocità di caricamento', 'fp-digital-marketing' ),
					'description' => __( 'Un sito lento è una delle cause principali di alto bounce rate', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_PERFORMANCE,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Comprimi e ottimizza le immagini', 'fp-digital-marketing' ),
						__( 'Minimizza CSS e JavaScript', 'fp-digital-marketing' ),
						__( 'Implementa la cache del browser', 'fp-digital-marketing' ),
					],
				],
				[
					'title' => __( 'Migliora la rilevanza del contenuto', 'fp-digital-marketing' ),
					'description' => __( 'Assicurati che il contenuto corrisponda alle aspettative dei visitatori', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_CONTENT,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Rivedi i titoli e le meta description', 'fp-digital-marketing' ),
						__( 'Analizza le query di ricerca che portano traffico', 'fp-digital-marketing' ),
						__( 'Migliora la struttura e leggibilità del contenuto', 'fp-digital-marketing' ),
					],
				],
				[
					'title' => __( 'Ottimizza l\'esperienza mobile', 'fp-digital-marketing' ),
					'description' => __( 'Verifica che il sito sia completamente responsive e usabile su mobile', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_TECHNICAL,
					'priority' => self::PRIORITY_MEDIUM,
					'actions' => [
						__( 'Testa il sito su diversi dispositivi mobili', 'fp-digital-marketing' ),
						__( 'Verifica la dimensione dei pulsanti e link', 'fp-digital-marketing' ),
						__( 'Ottimizza i form per mobile', 'fp-digital-marketing' ),
					],
				],
			];
		} else {
			return [
				[
					'title' => __( 'Analizza il miglioramento', 'fp-digital-marketing' ),
					'description' => __( 'Identifica cosa ha contribuito alla riduzione del bounce rate', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_CONTENT,
					'priority' => self::PRIORITY_MEDIUM,
					'actions' => [
						__( 'Verifica le modifiche recenti al sito', 'fp-digital-marketing' ),
						__( 'Analizza le pagine con miglior engagement', 'fp-digital-marketing' ),
						__( 'Documenta le best practice implementate', 'fp-digital-marketing' ),
					],
				],
			];
		}
	}

	/**
	 * Get suggestions for conversion rate anomalies
	 *
	 * @param string $deviation_type Positive or negative
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return array Suggestions
	 */
	private static function get_conversion_rate_suggestions( string $deviation_type, DetectedAnomaly $anomaly ): array {
		if ( $deviation_type === 'negative' ) {
			return [
				[
					'title' => __( 'Verifica il funnel di conversione', 'fp-digital-marketing' ),
					'description' => __( 'Identifica in quale punto del processo gli utenti abbandonano', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_CRITICAL,
					'actions' => [
						__( 'Analizza il percorso utente step-by-step', 'fp-digital-marketing' ),
						__( 'Verifica il funzionamento dei form', 'fp-digital-marketing' ),
						__( 'Controlla i metodi di pagamento', 'fp-digital-marketing' ),
					],
				],
				[
					'title' => __( 'Ottimizza le call-to-action', 'fp-digital-marketing' ),
					'description' => __( 'Migliora la visibilità e efficacia dei pulsanti di conversione', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_CONTENT,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Testa diversi colori e posizioni per i CTA', 'fp-digital-marketing' ),
						__( 'Migliora il copy dei pulsanti', 'fp-digital-marketing' ),
						__( 'Riduce le distrazioni nelle pagine di conversione', 'fp-digital-marketing' ),
					],
				],
			];
		} else {
			return [
				[
					'title' => __( 'Scala le strategie vincenti', 'fp-digital-marketing' ),
					'description' => __( 'Identifica e replica gli elementi che hanno migliorato le conversioni', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Analizza le modifiche recenti che hanno avuto impatto', 'fp-digital-marketing' ),
						__( 'Applica le stesse ottimizzazioni ad altre pagine', 'fp-digital-marketing' ),
						__( 'Aumenta il budget delle campagne performanti', 'fp-digital-marketing' ),
					],
				],
			];
		}
	}

	/**
	 * Get suggestions for Core Web Vitals anomalies
	 *
	 * @param string $metric Specific Core Web Vital metric
	 * @param string $deviation_type Positive or negative
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return array Suggestions
	 */
	private static function get_core_web_vitals_suggestions( string $metric, string $deviation_type, DetectedAnomaly $anomaly ): array {
		$suggestions = [];

		if ( $deviation_type === 'positive' ) { // Worse performance
			switch ( $metric ) {
				case MetricsSchema::KPI_LCP:
					$suggestions[] = [
						'title' => __( 'Ottimizza il Largest Contentful Paint', 'fp-digital-marketing' ),
						'description' => __( 'Migliora i tempi di caricamento dell\'elemento principale della pagina', 'fp-digital-marketing' ),
						'category' => self::CATEGORY_PERFORMANCE,
						'priority' => self::PRIORITY_HIGH,
						'actions' => [
							__( 'Ottimizza e comprimi le immagini hero', 'fp-digital-marketing' ),
							__( 'Implementa lazy loading intelligente', 'fp-digital-marketing' ),
							__( 'Riduci il tempo di risposta del server', 'fp-digital-marketing' ),
							__( 'Elimina risorse che bloccano il rendering', 'fp-digital-marketing' ),
						],
					];
					break;

				case MetricsSchema::KPI_INP:
					$suggestions[] = [
						'title' => __( 'Migliora l\'Interaction to Next Paint', 'fp-digital-marketing' ),
						'description' => __( 'Ottimizza la reattività dell\'interfaccia utente', 'fp-digital-marketing' ),
						'category' => self::CATEGORY_PERFORMANCE,
						'priority' => self::PRIORITY_HIGH,
						'actions' => [
							__( 'Ottimizza il JavaScript per ridurre i tempi di esecuzione', 'fp-digital-marketing' ),
							__( 'Implementa code splitting per caricare solo il necessario', 'fp-digital-marketing' ),
							__( 'Riduci le operazioni DOM complesse', 'fp-digital-marketing' ),
							__( 'Usa web workers per operazioni intensive', 'fp-digital-marketing' ),
						],
					];
					break;

				case MetricsSchema::KPI_CLS:
					$suggestions[] = [
						'title' => __( 'Riduci il Cumulative Layout Shift', 'fp-digital-marketing' ),
						'description' => __( 'Elimina i movimenti inaspettati degli elementi della pagina', 'fp-digital-marketing' ),
						'category' => self::CATEGORY_TECHNICAL,
						'priority' => self::PRIORITY_HIGH,
						'actions' => [
							__( 'Specifica dimensioni per immagini e iframe', 'fp-digital-marketing' ),
							__( 'Riserva spazio per contenuti dinamici', 'fp-digital-marketing' ),
							__( 'Evita di inserire contenuti sopra contenuti esistenti', 'fp-digital-marketing' ),
							__( 'Usa font-display per i web fonts', 'fp-digital-marketing' ),
						],
					];
					break;
			}
		}

		return $suggestions;
	}

	/**
	 * Get platform-specific suggestions
	 *
	 * @param string $metric Metric name
	 * @param int $client_id Client ID
	 * @return array Suggestions
	 */
	private static function get_platform_suggestions( string $metric, int $client_id ): array {
		$suggestions = [];
		$connections = ConnectionManager::get_all_connections();

		// Check for platform connection issues
		foreach ( $connections as $connection ) {
			if ( $connection['status'] === ConnectionManager::STATUS_DISCONNECTED ||
				 $connection['status'] === ConnectionManager::STATUS_ERROR ||
				 $connection['status'] === ConnectionManager::STATUS_EXPIRED ) {
				
				$suggestions[] = [
					'title' => sprintf(
						/* translators: %s: platform name */
						__( 'Riconnetti %s', 'fp-digital-marketing' ),
						$connection['name']
					),
					'description' => __( 'Problemi di connessione potrebbero influenzare la raccolta dati e il monitoraggio', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_PLATFORM,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Verifica le credenziali API', 'fp-digital-marketing' ),
						__( 'Rinnova i token di accesso se scaduti', 'fp-digital-marketing' ),
						__( 'Controlla le impostazioni di connessione', 'fp-digital-marketing' ),
					],
				];
			}
		}

		return $suggestions;
	}

	/**
	 * Get contextual suggestions based on related metrics
	 *
	 * @param DetectedAnomaly $anomaly Primary anomaly
	 * @param array $context Additional context data
	 * @return array Suggestions
	 */
	private static function get_contextual_suggestions( DetectedAnomaly $anomaly, array $context ): array {
		$suggestions = [];

		// If we have context about related metrics, provide contextual advice
		if ( isset( $context['related_anomalies'] ) ) {
			$related_count = count( $context['related_anomalies'] );
			
			if ( $related_count > 2 ) {
				$suggestions[] = [
					'title' => __( 'Problemi sistemici rilevati', 'fp-digital-marketing' ),
					'description' => sprintf(
						/* translators: %d: number of anomalies */
						__( 'Rilevate %d anomalie correlate. Potrebbe esserci un problema sistemico.', 'fp-digital-marketing' ),
						$related_count
					),
					'category' => self::CATEGORY_TECHNICAL,
					'priority' => self::PRIORITY_CRITICAL,
					'actions' => [
						__( 'Verifica lo stato generale del sito web', 'fp-digital-marketing' ),
						__( 'Controlla i log del server per errori', 'fp-digital-marketing' ),
						__( 'Verifica le modifiche recenti al sito', 'fp-digital-marketing' ),
					],
				];
			}
		}

		return $suggestions;
	}

	/**
	 * Get organic clicks suggestions
	 *
	 * @param string $deviation_type Positive or negative
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return array Suggestions
	 */
	private static function get_organic_clicks_suggestions( string $deviation_type, DetectedAnomaly $anomaly ): array {
		if ( $deviation_type === 'negative' ) {
			return [
				[
					'title' => __( 'Verifica posizionamenti SEO', 'fp-digital-marketing' ),
					'description' => __( 'Controlla se ci sono stati cali nelle posizioni delle parole chiave principali', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Analizza i ranking per le keyword principali', 'fp-digital-marketing' ),
						__( 'Verifica eventuali penalizzazioni di Google', 'fp-digital-marketing' ),
						__( 'Controlla la concorrenza per le tue keyword', 'fp-digital-marketing' ),
					],
				],
				[
					'title' => __( 'Ottimizza snippet e CTR', 'fp-digital-marketing' ),
					'description' => __( 'Migliora title e meta description per aumentare il click-through rate', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_CONTENT,
					'priority' => self::PRIORITY_MEDIUM,
					'actions' => [
						__( 'Riscrivi title tag più accattivanti', 'fp-digital-marketing' ),
						__( 'Migliora le meta description con call-to-action', 'fp-digital-marketing' ),
						__( 'Implementa dati strutturati per rich snippet', 'fp-digital-marketing' ),
					],
				],
			];
		} else {
			return [
				[
					'title' => __( 'Mantieni il momentum SEO', 'fp-digital-marketing' ),
					'description' => __( 'Continua a ottimizzare per mantenere e migliorare i risultati', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_MEDIUM,
					'actions' => [
						__( 'Pubblica contenuti correlati alle keyword performanti', 'fp-digital-marketing' ),
						__( 'Migliora l\'internal linking', 'fp-digital-marketing' ),
						__( 'Monitora nuove opportunità di keyword', 'fp-digital-marketing' ),
					],
				],
			];
		}
	}

	/**
	 * Get generic suggestions for unknown metrics
	 *
	 * @param string $metric Metric name
	 * @param string $deviation_type Positive or negative
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return array Suggestions
	 */
	private static function get_generic_suggestions( string $metric, string $deviation_type, DetectedAnomaly $anomaly ): array {
		return [
			[
				'title' => __( 'Analisi approfondita richiesta', 'fp-digital-marketing' ),
				'description' => sprintf(
					/* translators: %s: metric name */
					__( 'Anomalia rilevata per la metrica %s. È consigliata un\'analisi dettagliata.', 'fp-digital-marketing' ),
					$metric
				),
				'category' => self::CATEGORY_MARKETING,
				'priority' => self::PRIORITY_MEDIUM,
				'actions' => [
					__( 'Analizza i dati storici per identificare pattern', 'fp-digital-marketing' ),
					__( 'Verifica correlazioni con altri eventi', 'fp-digital-marketing' ),
					__( 'Documenta le possibili cause', 'fp-digital-marketing' ),
				],
			],
		];
	}

	/**
	 * Get pageviews suggestions
	 *
	 * @param string $deviation_type Positive or negative
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return array Suggestions
	 */
	private static function get_pageviews_suggestions( string $deviation_type, DetectedAnomaly $anomaly ): array {
		if ( $deviation_type === 'negative' ) {
			return [
				[
					'title' => __( 'Migliora la navigazione interna', 'fp-digital-marketing' ),
					'description' => __( 'Incoraggia gli utenti a visitare più pagine migliorando la user experience', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_CONTENT,
					'priority' => self::PRIORITY_MEDIUM,
					'actions' => [
						__( 'Aggiungi related posts e contenuti suggeriti', 'fp-digital-marketing' ),
						__( 'Migliora il menu di navigazione', 'fp-digital-marketing' ),
						__( 'Implementa una ricerca interna efficace', 'fp-digital-marketing' ),
					],
				],
			];
		} else {
			return [
				[
					'title' => __( 'Ottimizza per il maggior engagement', 'fp-digital-marketing' ),
					'description' => __( 'Sfrutta l\'aumento delle pagine viste per migliorare le conversioni', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_MEDIUM,
					'actions' => [
						__( 'Posiziona strategicamente call-to-action', 'fp-digital-marketing' ),
						__( 'Traccia il percorso utente per ottimizzazioni future', 'fp-digital-marketing' ),
						__( 'Testa diverse strategie di monetizzazione', 'fp-digital-marketing' ),
					],
				],
			];
		}
	}

	/**
	 * Get organic impressions suggestions
	 *
	 * @param string $deviation_type Positive or negative
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return array Suggestions
	 */
	private static function get_organic_impressions_suggestions( string $deviation_type, DetectedAnomaly $anomaly ): array {
		if ( $deviation_type === 'negative' ) {
			return [
				[
					'title' => __( 'Espandi la copertura delle keyword', 'fp-digital-marketing' ),
					'description' => __( 'Lavora su nuove keyword per aumentare la visibilità nei risultati di ricerca', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_MARKETING,
					'priority' => self::PRIORITY_MEDIUM,
					'actions' => [
						__( 'Ricerca nuove keyword long-tail', 'fp-digital-marketing' ),
						__( 'Crea contenuti per query correlate', 'fp-digital-marketing' ),
						__( 'Ottimizza per featured snippets', 'fp-digital-marketing' ),
					],
				],
			];
		} else {
			return [
				[
					'title' => __( 'Converti visibilità in clic', 'fp-digital-marketing' ),
					'description' => __( 'Migliora il CTR per sfruttare al meglio l\'aumento delle impressioni', 'fp-digital-marketing' ),
					'category' => self::CATEGORY_CONTENT,
					'priority' => self::PRIORITY_HIGH,
					'actions' => [
						__( 'Ottimizza title e meta description', 'fp-digital-marketing' ),
						__( 'Implementa schema markup per rich results', 'fp-digital-marketing' ),
						__( 'Testa diversi approcci di copywriting', 'fp-digital-marketing' ),
					],
				],
			];
		}
	}
}