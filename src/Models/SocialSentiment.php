<?php
/**
 * Social Sentiment Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\SocialSentimentTable;

/**
 * Social Sentiment model class
 * 
 * Handles social sentiment analysis and AI-powered review monitoring.
 */
class SocialSentiment {

	/**
	 * Sentiment ID
	 *
	 * @var int|null
	 */
	private ?int $id = null;

	/**
	 * Client ID
	 *
	 * @var int
	 */
	private int $client_id;

	/**
	 * Review source
	 *
	 * @var string
	 */
	private string $review_source;

	/**
	 * Review platform
	 *
	 * @var string
	 */
	private string $review_platform;

	/**
	 * Review URL
	 *
	 * @var string
	 */
	private string $review_url;

	/**
	 * Review text
	 *
	 * @var string
	 */
	private string $review_text;

	/**
	 * Review rating
	 *
	 * @var float|null
	 */
	private ?float $review_rating;

	/**
	 * Review date
	 *
	 * @var string|null
	 */
	private ?string $review_date;

	/**
	 * Sentiment score (-1 to 1)
	 *
	 * @var float|null
	 */
	private ?float $sentiment_score;

	/**
	 * Sentiment label
	 *
	 * @var string
	 */
	private string $sentiment_label;

	/**
	 * Sentiment confidence
	 *
	 * @var float|null
	 */
	private ?float $sentiment_confidence;

	/**
	 * Key issues identified
	 *
	 * @var array
	 */
	private array $key_issues;

	/**
	 * Positive aspects
	 *
	 * @var array
	 */
	private array $positive_aspects;

	/**
	 * AI summary
	 *
	 * @var string
	 */
	private string $ai_summary;

	/**
	 * Action required flag
	 *
	 * @var bool
	 */
	private bool $action_required;

	/**
	 * Responded flag
	 *
	 * @var bool
	 */
	private bool $responded;

	/**
	 * Response text
	 *
	 * @var string
	 */
	private string $response_text;

	/**
	 * Response date
	 *
	 * @var string|null
	 */
	private ?string $response_date;

	/**
	 * Constructor
	 *
	 * @param array $data Sentiment data
	 */
	public function __construct( array $data = [] ) {
		$this->id = $data['id'] ?? null;
		$this->client_id = $data['client_id'] ?? 0;
		$this->review_source = $data['review_source'] ?? '';
		$this->review_platform = $data['review_platform'] ?? '';
		$this->review_url = $data['review_url'] ?? '';
		$this->review_text = $data['review_text'] ?? '';
		$this->review_rating = isset( $data['review_rating'] ) ? (float) $data['review_rating'] : null;
		$this->review_date = $data['review_date'] ?? null;
		$this->sentiment_score = isset( $data['sentiment_score'] ) ? (float) $data['sentiment_score'] : null;
		$this->sentiment_label = $data['sentiment_label'] ?? '';
		$this->sentiment_confidence = isset( $data['sentiment_confidence'] ) ? (float) $data['sentiment_confidence'] : null;
		$this->key_issues = $data['key_issues'] ?? [];
		$this->positive_aspects = $data['positive_aspects'] ?? [];
		$this->ai_summary = $data['ai_summary'] ?? '';
		$this->action_required = (bool) ( $data['action_required'] ?? false );
		$this->responded = (bool) ( $data['responded'] ?? false );
		$this->response_text = $data['response_text'] ?? '';
		$this->response_date = $data['response_date'] ?? null;
	}

	/**
	 * Analyze sentiment using AI
	 *
	 * @return bool
	 */
	public function analyze_sentiment(): bool {
		if ( empty( $this->review_text ) ) {
			return false;
		}

		// For demo purposes, we'll use a simple rule-based approach
		// In production, this would integrate with OpenAI, Google NLP, or similar services
		$analysis = $this->perform_ai_sentiment_analysis( $this->review_text );

		$this->sentiment_score = $analysis['score'];
		$this->sentiment_label = $analysis['label'];
		$this->sentiment_confidence = $analysis['confidence'];
		$this->key_issues = $analysis['issues'];
		$this->positive_aspects = $analysis['positives'];
		$this->ai_summary = $analysis['summary'];
		$this->action_required = $analysis['action_required'];

		return true;
	}

	/**
	 * Perform AI sentiment analysis (demo implementation)
	 *
	 * @param string $text Review text
	 * @return array Analysis results
	 */
	private function perform_ai_sentiment_analysis( string $text ): array {
		$text = strtolower( $text );
		
		// Define positive and negative keywords (Italian)
		$positive_keywords = [
			'eccellente', 'fantastico', 'ottimo', 'bravo', 'perfetto', 'soddisfatto',
			'consiglio', 'felice', 'contento', 'professionale', 'veloce', 'efficiente',
			'cordiale', 'disponibile', 'qualità', 'servizio', 'puntuale'
		];

		$negative_keywords = [
			'pessimo', 'orribile', 'deludente', 'lento', 'scortese', 'insoddisfatto',
			'sconsiglio', 'arrabbiato', 'deluso', 'problemi', 'difetti', 'errore',
			'ritardo', 'caro', 'costoso', 'non funziona', 'rotto', 'guasto'
		];

		$issue_keywords = [
			'tempo' => 'Tempi di attesa',
			'attesa' => 'Tempi di attesa',
			'lento' => 'Velocità del servizio',
			'veloce' => 'Velocità del servizio',
			'prezzo' => 'Prezzo',
			'caro' => 'Prezzo',
			'costoso' => 'Prezzo',
			'qualità' => 'Qualità del prodotto',
			'servizio' => 'Servizio clienti',
			'staff' => 'Personale',
			'consegna' => 'Consegna',
			'spedizione' => 'Spedizione',
			'comunicazione' => 'Comunicazione',
			'supporto' => 'Supporto tecnico',
		];

		$positive_aspects_keywords = [
			'professionale' => 'Staff professionale',
			'veloce' => 'Servizio veloce',
			'puntuale' => 'Puntualità',
			'qualità' => 'Alta qualità',
			'conveniente' => 'Prezzo conveniente',
			'cordiale' => 'Cordialità',
			'disponibile' => 'Disponibilità',
			'efficiente' => 'Efficienza',
		];

		// Count positive and negative keywords
		$positive_count = 0;
		$negative_count = 0;
		$identified_issues = [];
		$identified_positives = [];

		foreach ( $positive_keywords as $keyword ) {
			if ( strpos( $text, $keyword ) !== false ) {
				$positive_count++;
			}
		}

		foreach ( $negative_keywords as $keyword ) {
			if ( strpos( $text, $keyword ) !== false ) {
				$negative_count++;
			}
		}

		// Identify specific issues
		foreach ( $issue_keywords as $keyword => $issue ) {
			if ( strpos( $text, $keyword ) !== false ) {
				if ( ! in_array( $issue, $identified_issues ) ) {
					$identified_issues[] = $issue;
				}
			}
		}

		// Identify positive aspects
		foreach ( $positive_aspects_keywords as $keyword => $aspect ) {
			if ( strpos( $text, $keyword ) !== false ) {
				if ( ! in_array( $aspect, $identified_positives ) ) {
					$identified_positives[] = $aspect;
				}
			}
		}

		// Calculate sentiment score and label
		$total_sentiment_words = $positive_count + $negative_count;
		if ( $total_sentiment_words === 0 ) {
			$score = 0.0;
			$label = 'neutral';
			$confidence = 0.5;
		} else {
			$score = ( $positive_count - $negative_count ) / $total_sentiment_words;
			
			if ( $score > 0.2 ) {
				$label = 'positive';
			} elseif ( $score < -0.2 ) {
				$label = 'negative';
			} else {
				$label = 'neutral';
			}

			$confidence = min( 0.9, 0.6 + ( abs( $score ) * 0.3 ) );
		}

		// Generate AI summary
		$summary = $this->generate_ai_summary( $label, $score, $identified_issues, $identified_positives );

		// Determine if action is required
		$action_required = ( $label === 'negative' && $score < -0.3 ) || count( $identified_issues ) >= 2;

		return [
			'score' => $score,
			'label' => $label,
			'confidence' => $confidence,
			'issues' => $identified_issues,
			'positives' => $identified_positives,
			'summary' => $summary,
			'action_required' => $action_required,
		];
	}

	/**
	 * Generate AI summary
	 *
	 * @param string $label Sentiment label
	 * @param float $score Sentiment score
	 * @param array $issues Identified issues
	 * @param array $positives Positive aspects
	 * @return string
	 */
	private function generate_ai_summary( string $label, float $score, array $issues, array $positives ): string {
		$summary = '';

		switch ( $label ) {
			case 'positive':
				$summary = sprintf(
					'Recensione positiva (punteggio: %.2f). ',
					$score
				);
				if ( ! empty( $positives ) ) {
					$summary .= 'Aspetti apprezzati: ' . implode( ', ', $positives ) . '. ';
				}
				$summary .= 'Cliente soddisfatto del servizio.';
				break;

			case 'negative':
				$summary = sprintf(
					'Recensione negativa (punteggio: %.2f). ',
					$score
				);
				if ( ! empty( $issues ) ) {
					$summary .= 'Problematiche riscontrate: ' . implode( ', ', $issues ) . '. ';
				}
				$summary .= 'Richiede attenzione immediata e possibile intervento.';
				break;

			case 'neutral':
				$summary = sprintf(
					'Recensione neutra (punteggio: %.2f). ',
					$score
				);
				if ( ! empty( $issues ) ) {
					$summary .= 'Alcuni aspetti da migliorare: ' . implode( ', ', $issues ) . '. ';
				}
				if ( ! empty( $positives ) ) {
					$summary .= 'Aspetti positivi: ' . implode( ', ', $positives ) . '. ';
				}
				$summary .= 'Esperienza nella media.';
				break;
		}

		return $summary;
	}

	/**
	 * Save the sentiment analysis
	 *
	 * @return bool
	 */
	public function save(): bool {
		$data = [
			'client_id' => $this->client_id,
			'review_source' => $this->review_source,
			'review_platform' => $this->review_platform,
			'review_url' => $this->review_url,
			'review_text' => $this->review_text,
			'review_rating' => $this->review_rating,
			'review_date' => $this->review_date,
			'sentiment_score' => $this->sentiment_score,
			'sentiment_label' => $this->sentiment_label,
			'sentiment_confidence' => $this->sentiment_confidence,
			'key_issues' => $this->key_issues,
			'positive_aspects' => $this->positive_aspects,
			'ai_summary' => $this->ai_summary,
			'action_required' => $this->action_required ? 1 : 0,
			'responded' => $this->responded ? 1 : 0,
			'response_text' => $this->response_text,
			'response_date' => $this->response_date,
		];

		if ( $this->id ) {
			$result = SocialSentimentTable::update_sentiment( $this->id, $data );
		} else {
			$result = SocialSentimentTable::insert_sentiment( $data );
			if ( $result ) {
				$this->id = $result;
			}
		}

		return (bool) $result;
	}

	/**
	 * Mark as responded
	 *
	 * @param string $response_text Response text
	 * @return bool
	 */
	public function mark_as_responded( string $response_text ): bool {
		$this->responded = true;
		$this->response_text = $response_text;
		$this->response_date = current_time( 'mysql' );

		if ( $this->id ) {
			return SocialSentimentTable::mark_as_responded( $this->id, $response_text );
		}

		return $this->save();
	}

	/**
	 * Generate response suggestion based on sentiment
	 *
	 * @return string
	 */
	public function suggest_response(): string {
		switch ( $this->sentiment_label ) {
			case 'positive':
				return $this->generate_positive_response();
			case 'negative':
				return $this->generate_negative_response();
			case 'neutral':
				return $this->generate_neutral_response();
			default:
				return __( 'Grazie per il tuo feedback.', 'fp-digital-marketing' );
		}
	}

	/**
	 * Generate positive response
	 *
	 * @return string
	 */
	private function generate_positive_response(): string {
		$templates = [
			'Grazie mille per la sua recensione positiva! Siamo felici che sia soddisfatto del nostro servizio.',
			'La ringraziamo per il feedback positivo. È un piacere sapere che abbiamo soddisfatto le sue aspettative.',
			'Grazie per aver condiviso la sua esperienza positiva. Continueremo a impegnarci per offrire sempre il meglio.',
		];

		$response = $templates[ array_rand( $templates ) ];

		if ( ! empty( $this->positive_aspects ) ) {
			$response .= ' Siamo particolarmente contenti che abbia apprezzato: ' . implode( ', ', $this->positive_aspects ) . '.';
		}

		return $response;
	}

	/**
	 * Generate negative response
	 *
	 * @return string
	 */
	private function generate_negative_response(): string {
		$templates = [
			'Ci dispiace molto per la sua esperienza negativa. Prendiamo molto seriamente il suo feedback.',
			'La ringraziamo per aver condiviso le sue preoccupazioni. Desideriamo migliorare e fare meglio.',
			'Siamo spiacenti che non sia rimasto soddisfatto. Vorremmo avere l\'opportunità di rimediare.',
		];

		$response = $templates[ array_rand( $templates ) ];

		if ( ! empty( $this->key_issues ) ) {
			$response .= ' Prenderemo misure immediate per affrontare i problemi relativi a: ' . implode( ', ', $this->key_issues ) . '.';
		}

		$response .= ' La preghiamo di contattarci direttamente per discutere come possiamo migliorare la sua esperienza.';

		return $response;
	}

	/**
	 * Generate neutral response
	 *
	 * @return string
	 */
	private function generate_neutral_response(): string {
		$templates = [
			'Grazie per il suo feedback. Apprezziamo il tempo dedicato a condividere la sua esperienza.',
			'La ringraziamo per la recensione. Il suo feedback ci aiuta a migliorare costantemente.',
			'Grazie per aver condiviso la sua opinione. Ogni feedback è prezioso per noi.',
		];

		$response = $templates[ array_rand( $templates ) ];

		if ( ! empty( $this->key_issues ) ) {
			$response .= ' Prenderemo in considerazione i suoi suggerimenti riguardo a: ' . implode( ', ', $this->key_issues ) . '.';
		}

		return $response;
	}

	/**
	 * Get sentiment color for UI display
	 *
	 * @return string
	 */
	public function get_sentiment_color(): string {
		switch ( $this->sentiment_label ) {
			case 'positive':
				return '#00a32a';
			case 'negative':
				return '#d63638';
			case 'neutral':
				return '#dba617';
			default:
				return '#666666';
		}
	}

	/**
	 * Get priority level based on sentiment
	 *
	 * @return string
	 */
	public function get_priority_level(): string {
		if ( $this->action_required ) {
			return 'high';
		}

		switch ( $this->sentiment_label ) {
			case 'negative':
				return 'medium';
			case 'positive':
				return 'low';
			default:
				return 'normal';
		}
	}

	/**
	 * Process review text from various platforms
	 *
	 * @param string $platform Platform name
	 * @param string $text Raw review text
	 * @return array Processed review data
	 */
	public static function process_review_from_platform( string $platform, string $text ): array {
		// Basic text cleaning and processing
		$cleaned_text = strip_tags( $text );
		$cleaned_text = html_entity_decode( $cleaned_text );
		$cleaned_text = trim( $cleaned_text );

		// Platform-specific processing could be added here
		switch ( $platform ) {
			case 'google_reviews':
				// Google-specific processing
				break;
			case 'facebook':
				// Facebook-specific processing
				break;
			case 'trustpilot':
				// Trustpilot-specific processing
				break;
		}

		return [
			'processed_text' => $cleaned_text,
			'character_count' => strlen( $cleaned_text ),
			'word_count' => str_word_count( $cleaned_text ),
		];
	}

	// Getters and setters
	public function get_id(): ?int { return $this->id; }
	public function get_client_id(): int { return $this->client_id; }
	public function get_review_source(): string { return $this->review_source; }
	public function get_review_platform(): string { return $this->review_platform; }
	public function get_review_url(): string { return $this->review_url; }
	public function get_review_text(): string { return $this->review_text; }
	public function get_review_rating(): ?float { return $this->review_rating; }
	public function get_review_date(): ?string { return $this->review_date; }
	public function get_sentiment_score(): ?float { return $this->sentiment_score; }
	public function get_sentiment_label(): string { return $this->sentiment_label; }
	public function get_sentiment_confidence(): ?float { return $this->sentiment_confidence; }
	public function get_key_issues(): array { return $this->key_issues; }
	public function get_positive_aspects(): array { return $this->positive_aspects; }
	public function get_ai_summary(): string { return $this->ai_summary; }
	public function is_action_required(): bool { return $this->action_required; }
	public function is_responded(): bool { return $this->responded; }
	public function get_response_text(): string { return $this->response_text; }
	public function get_response_date(): ?string { return $this->response_date; }

	public function set_client_id( int $client_id ): void { $this->client_id = $client_id; }
	public function set_review_source( string $review_source ): void { $this->review_source = $review_source; }
	public function set_review_platform( string $review_platform ): void { $this->review_platform = $review_platform; }
	public function set_review_url( string $review_url ): void { $this->review_url = $review_url; }
	public function set_review_text( string $review_text ): void { $this->review_text = $review_text; }
	public function set_review_rating( ?float $review_rating ): void { $this->review_rating = $review_rating; }
	public function set_review_date( ?string $review_date ): void { $this->review_date = $review_date; }
	public function set_action_required( bool $action_required ): void { $this->action_required = $action_required; }
}