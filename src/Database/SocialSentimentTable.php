<?php
/**
 * Social Sentiment Analysis Table Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

use FP\DigitalMarketing\Database\DatabaseUtils;
use FP\DigitalMarketing\DataSources\GoogleReviews;
use FP\DigitalMarketing\Models\SocialSentiment;

/**
 * Handles database operations for social sentiment analysis
 */
class SocialSentimentTable {

		/**
		 * Table name
		 */
	public const TABLE_NAME = 'fp_dms_social_sentiment';

		/**
		 * Allowed columns for ordering queries.
		 *
		 * @var string[]
		 */
	private const ORDERABLE_COLUMNS = [
		'id',
		'client_id',
		'review_source',
		'review_platform',
		'review_rating',
		'review_date',
		'sentiment_score',
		'sentiment_label',
		'sentiment_confidence',
		'action_required',
		'responded',
		'response_date',
		'created_at',
		'updated_at',
	];

		/**
		 * Insert/update format map for wpdb operations.
		 *
		 * @var string[]
		 */
	private const STORAGE_FORMATS = [
		'%d', // client_id
		'%s', // external_id
		'%s', // review_source
		'%s', // review_platform
		'%s', // review_url
		'%s', // review_text
		'%f', // review_rating
		'%s', // review_date
		'%f', // sentiment_score
		'%s', // sentiment_label
		'%f', // sentiment_confidence
		'%s', // key_issues
		'%s', // positive_aspects
		'%s', // ai_summary
		'%d', // action_required
		'%d', // responded
		'%s', // response_text
		'%s', // response_date
	];

		/**
		 * Sanitize ORDER BY parameters for SQL queries.
		 *
		 * @param string $order_by Column to order by.
		 * @param string $order_direction Order direction (ASC/DESC).
		 * @param string $default_order_by Default column when invalid column provided.
		 * @param string $default_order_direction Default direction when invalid direction provided.
		 * @return array{0:string,1:string}
		 */
	private static function sanitize_order_parameters( string $order_by, string $order_direction, string $default_order_by, string $default_order_direction ): array {
			$default_order_by = strtolower( $default_order_by );
		if ( ! in_array( $default_order_by, self::ORDERABLE_COLUMNS, true ) ) {
				$default_order_by = 'review_date';
		}

			$order_by = strtolower( $order_by );
		if ( ! in_array( $order_by, self::ORDERABLE_COLUMNS, true ) ) {
				$order_by = $default_order_by;
		}

			$allowed_directions = [ 'ASC', 'DESC' ];

			$default_order_direction = strtoupper( $default_order_direction );
		if ( ! in_array( $default_order_direction, $allowed_directions, true ) ) {
				$default_order_direction = 'DESC';
		}

			$order_direction = strtoupper( $order_direction );
		if ( ! in_array( $order_direction, $allowed_directions, true ) ) {
				$order_direction = $default_order_direction;
		}

			return [ $order_by, $order_direction ];
	}

	/**
	 * Get the full table name with WordPress prefix
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
			global $wpdb;
			return DatabaseUtils::resolve_table_name( $wpdb, self::TABLE_NAME );
	}

	/**
	 * Check if table exists
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		$query      = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		return $wpdb->get_var( $query ) === $table_name;
	}

	/**
	 * Create the social sentiment table
	 *
	 * @return bool
	 */
	public static function create_table(): bool {
			global $wpdb;

			$table_name = self::get_table_name();

			$charset_collate = DatabaseUtils::get_charset_collate( $wpdb );

		$sql = "CREATE TABLE {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
                        client_id bigint(20) NOT NULL,
                        external_id varchar(191) DEFAULT '',
			review_source varchar(100) NOT NULL,
			review_platform varchar(100) NOT NULL,
			review_url text,
			review_text longtext NOT NULL,
			review_rating decimal(3,2),
			review_date datetime,
			sentiment_score decimal(5,4),
			sentiment_label varchar(20),
			sentiment_confidence decimal(5,4),
			key_issues longtext,
			positive_aspects longtext,
			ai_summary text,
			action_required tinyint(1) DEFAULT 0,
			responded tinyint(1) DEFAULT 0,
			response_text longtext,
			response_date datetime,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_client_id (client_id),
			INDEX idx_sentiment_score (sentiment_score),
			INDEX idx_sentiment_label (sentiment_label),
			INDEX idx_review_date (review_date),
			INDEX idx_action_required (action_required),
                        INDEX idx_review_platform (review_platform),
                        INDEX idx_client_external (client_id, external_id)
                ) $charset_collate;";

			return DatabaseUtils::run_schema_delta( $sql, $wpdb );
	}

		/**
		 * Drop the social sentiment table (for uninstall).
		 *
		 * @return bool True on success, false on failure
		 */
	public static function drop_table(): bool {
			global $wpdb;

			$table_name = self::get_table_name();
			$result     = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

			return $result !== false;
	}

	/**
	 * Insert a new sentiment analysis record
	 *
	 * @param array $data Sentiment data
	 * @return int|false Record ID on success, false on failure
	 */
	public static function insert_sentiment( array $data ) {
			$prepared = self::normalize_sentiment_data( $data );

			return self::insert_prepared_data( $prepared );
	}

		/**
		 * Insert or update a sentiment record based on external identifier.
		 *
		 * @param array $data Sentiment data.
		 * @return array{status:string,id?:int}
		 */
	public static function upsert_sentiment( array $data ): array {
			global $wpdb;

			$prepared = self::normalize_sentiment_data( $data );
			$table    = self::get_table_name();

			$external_id = $prepared['external_id'] ?? '';

		if ( '' === $external_id ) {
				$insert_id = self::insert_prepared_data( $prepared );

				return $insert_id ? [
					'status' => 'inserted',
					'id'     => $insert_id,
				] : [ 'status' => 'error' ];
		}

			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE client_id = %d AND external_id = %s",
					$prepared['client_id'],
					$external_id
				)
			);

		if ( $existing_id ) {
				$update_data = $prepared;
				unset( $update_data['client_id'], $update_data['external_id'] );

				$update_formats = self::STORAGE_FORMATS;
				array_shift( $update_formats );
				array_shift( $update_formats );

				$updated = $wpdb->update(
					$table,
					$update_data,
					[ 'id' => (int) $existing_id ],
					$update_formats,
					[ '%d' ]
				);

			if ( false === $updated ) {
				return [ 'status' => 'error' ];
			}

				return [
					'status' => 'updated',
					'id'     => (int) $existing_id,
				];
		}

			$insert_id = self::insert_prepared_data( $prepared );

			return $insert_id ? [
				'status' => 'inserted',
				'id'     => $insert_id,
			] : [ 'status' => 'error' ];
	}

		/**
		 * Get sentiment analysis for a client
		 *
		 * @param int   $client_id Client ID
		 * @param array $args Optional query arguments
		 * @return array
		 */
	public static function get_client_sentiment( int $client_id, array $args = [] ): array {
			return self::get_reviews( $client_id, $args );
	}

		/**
		 * Sync sentiment data from Google Reviews.
		 *
		 * @param int    $client_id Client identifier.
		 * @param string $place_id  Google Place ID.
		 * @param string $api_key   Google Places API key.
		 * @param int    $limit     Maximum number of reviews to ingest.
		 * @return array{success:bool,inserted?:int,updated?:int,skipped?:int,total?:int,error?:string}
		 */
	public static function sync_google_reviews( int $client_id, string $place_id, string $api_key, int $limit = 20 ): array {
			$client_id = (int) $client_id;
			$place_id  = trim( sanitize_text_field( $place_id ) );
			$api_key   = trim( $api_key );

		if ( $client_id <= 0 ) {
				return [
					'success' => false,
					'error'   => __( 'Cliente non valido per la sincronizzazione delle recensioni.', 'fp-digital-marketing' ),
				];
		}

		if ( '' === $place_id || '' === $api_key ) {
				return [
					'success' => false,
					'error'   => __( 'Chiave API o Place ID mancanti per recuperare le recensioni Google.', 'fp-digital-marketing' ),
				];
		}

			$limit = max( 1, $limit );

			$reviews_client = new GoogleReviews( $api_key );
			$response       = $reviews_client->fetch_reviews( $place_id, $limit );

		if ( empty( $response['success'] ) ) {
				return [
					'success' => false,
					'error'   => $response['error'] ?? __( 'Impossibile recuperare le recensioni da Google.', 'fp-digital-marketing' ),
				];
		}

			$inserted     = 0;
			$updated      = 0;
			$skipped      = 0;
			$reviews      = $response['reviews'] ?? [];
			$fallback_url = 'https://search.google.com/local/reviews?placeid=' . rawurlencode( $place_id );

		foreach ( $reviews as $review ) {
				$text = isset( $review['text'] ) ? trim( (string) $review['text'] ) : '';

			if ( '' === $text ) {
					++$skipped;
					continue;
			}

				$rating      = isset( $review['rating'] ) ? (float) $review['rating'] : null;
				$review_date = $review['date'] ?? ( $review['time'] ?? null );
				$review_url  = isset( $review['url'] ) ? esc_url_raw( (string) $review['url'] ) : $fallback_url;
				$external_id = isset( $review['external_id'] ) ? (string) $review['external_id'] : md5( $place_id . '|' . ( $review['review_id'] ?? '' ) . '|' . ( $review['time'] ?? '' ) );

				$sentiment_model = new SocialSentiment(
					[
						'client_id'       => $client_id,
						'review_source'   => 'Google Reviews',
						'review_platform' => 'google_reviews',
						'review_url'      => $review_url,
						'review_text'     => $text,
						'review_rating'   => $rating,
						'review_date'     => $review_date,
					]
				);

				$sentiment_model->analyze_sentiment();

				$text_score   = $sentiment_model->get_sentiment_score() ?? 0.0;
				$rating_score = null;

			if ( null !== $rating ) {
				$rating_score = max( -1.0, min( 1.0, ( $rating - 3.0 ) / 2.0 ) );
			}

				$combined_score = null !== $rating_score ? round( ( $text_score + $rating_score ) / 2, 4 ) : $text_score;
				$label          = self::determine_sentiment_label_from_score( $combined_score );

				$confidence = $sentiment_model->get_sentiment_confidence() ?? 0.7;
			if ( null !== $rating_score ) {
					$confidence = max( $confidence, min( 0.99, 0.6 + abs( $rating_score ) * 0.35 ) );
			}

				$summary = trim( $sentiment_model->get_ai_summary() );
			if ( null !== $rating ) {
					$summary = trim( sprintf( '%s %s', $summary, sprintf( __( 'Valutazione Google: %.1f/5.', 'fp-digital-marketing' ), $rating ) ) );
			}

				$upsert = self::upsert_sentiment(
					[
						'client_id'            => $client_id,
						'external_id'          => $external_id,
						'review_source'        => 'Google Reviews',
						'review_platform'      => 'google_reviews',
						'review_url'           => $review_url,
						'review_text'          => $text,
						'review_rating'        => $rating,
						'review_date'          => $review_date,
						'sentiment_score'      => $combined_score,
						'sentiment_label'      => $label,
						'sentiment_confidence' => $confidence,
						'key_issues'           => $sentiment_model->get_key_issues(),
						'positive_aspects'     => $sentiment_model->get_positive_aspects(),
						'ai_summary'           => $summary,
						'action_required'      => ( 'negative' === $label || ( null !== $rating && $rating <= 2.0 ) ),
						'responded'            => 0,
					]
				);

			if ( 'inserted' === $upsert['status'] ) {
				++$inserted;
			} elseif ( 'updated' === $upsert['status'] ) {
					++$updated;
			} else {
					++$skipped;
			}
		}

			return [
				'success'  => true,
				'inserted' => $inserted,
				'updated'  => $updated,
				'skipped'  => $skipped,
				'total'    => count( $reviews ),
			];
	}

		/**
		 * Normalize sentiment data for storage.
		 *
		 * @param array $data Raw sentiment payload.
		 * @return array<string, mixed>
		 */
	private static function normalize_sentiment_data( array $data ): array {
			$defaults = [
				'client_id'            => 0,
				'external_id'          => '',
				'review_source'        => '',
				'review_platform'      => '',
				'review_url'           => '',
				'review_text'          => '',
				'review_rating'        => null,
				'review_date'          => null,
				'sentiment_score'      => null,
				'sentiment_label'      => '',
				'sentiment_confidence' => null,
				'key_issues'           => [],
				'positive_aspects'     => [],
				'ai_summary'           => '',
				'action_required'      => 0,
				'responded'            => 0,
				'response_text'        => '',
				'response_date'        => null,
			];

			$parsed = wp_parse_args( $data, $defaults );

			$normalized = [];
			foreach ( array_keys( $defaults ) as $key ) {
					$value = $parsed[ $key ];

				switch ( $key ) {
					case 'client_id':
						$normalized[ $key ] = (int) $value;
						break;
					case 'external_id':
							$normalized[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 190 );
						break;
					case 'review_url':
							$normalized[ $key ] = esc_url_raw( (string) $value );
						break;
					case 'review_text':
					case 'review_source':
					case 'review_platform':
					case 'sentiment_label':
					case 'ai_summary':
					case 'response_text':
							$normalized[ $key ] = is_string( $value ) ? $value : (string) $value;
						break;
					case 'review_rating':
					case 'sentiment_score':
					case 'sentiment_confidence':
						if ( null === $value || '' === $value ) {
								$normalized[ $key ] = null;
						} else {
								$normalized[ $key ] = (float) $value;
						}
						break;
					case 'action_required':
					case 'responded':
							$normalized[ $key ] = (int) (bool) $value;
						break;
					case 'key_issues':
					case 'positive_aspects':
						if ( is_string( $value ) ) {
								$decoded = json_decode( $value, true );
								$value   = is_array( $decoded ) ? $decoded : [];
						}

						if ( ! is_array( $value ) ) {
								$value = [];
						}

							$value              = array_values(
								array_unique(
									array_filter(
										array_map( 'trim', $value ),
										static function ( $item ) {
												return '' !== $item;
										}
									)
								)
							);
							$normalized[ $key ] = wp_json_encode( $value );
						break;
					case 'review_date':
					case 'response_date':
							$normalized[ $key ] = self::normalize_datetime_value( $value );
						break;
					default:
							$normalized[ $key ] = is_string( $value ) ? $value : ( null === $value ? null : (string) $value );
				}
			}

			return $normalized;
	}

		/**
		 * Normalize date/time values to MySQL compatible string.
		 *
		 * @param mixed $value Value to normalize.
		 * @return string|null
		 */
	private static function normalize_datetime_value( $value ): ?string {
		if ( $value instanceof \DateTimeInterface ) {
				return $value->format( 'Y-m-d H:i:s' );
		}

		if ( is_numeric( $value ) ) {
				return gmdate( 'Y-m-d H:i:s', (int) $value );
		}

		if ( is_string( $value ) ) {
				$trimmed = trim( $value );

				return '' === $trimmed ? null : $trimmed;
		}

			return null;
	}

		/**
		 * Execute database insert for prepared data set.
		 *
		 * @param array<string, mixed> $prepared Prepared data.
		 * @return int|false Insert ID on success.
		 */
	private static function insert_prepared_data( array $prepared ) {
			global $wpdb;

			$result = $wpdb->insert(
				self::get_table_name(),
				$prepared,
				self::STORAGE_FORMATS
			);

			return $result ? (int) $wpdb->insert_id : false;
	}

		/**
		 * Determine sentiment label from score.
		 *
		 * @param float $score Sentiment score (-1 to 1).
		 * @return string
		 */
	private static function determine_sentiment_label_from_score( float $score ): string {
		if ( $score > 0.2 ) {
				return 'positive';
		}

		if ( $score < -0.2 ) {
				return 'negative';
		}

			return 'neutral';
	}

		/**
		 * Get sentiment reviews for a client
		 *
		 * @param int   $client_id Client ID
		 * @param array $args Optional query arguments
		 * @return array
		 */
	public static function get_reviews( int $client_id, array $args = [] ): array {
			global $wpdb;

			$defaults = [
				'limit'           => 50,
				'offset'          => 0,
				'order_by'        => 'review_date',
				'order'           => 'DESC',
				'sentiment_label' => null,
				'action_required' => null,
				'date_from'       => null,
				'date_to'         => null,
			];

			$args = wp_parse_args( $args, $defaults );

			[ $order_by, $order_direction ] = self::sanitize_order_parameters(
				(string) $args['order_by'],
				(string) $args['order'],
				$defaults['order_by'],
				$defaults['order']
			);

			$where_clauses = [ 'client_id = %d' ];
			$where_values  = [ $client_id ];

		if ( ! empty( $args['sentiment_label'] ) ) {
				$where_clauses[] = 'sentiment_label = %s';
				$where_values[]  = $args['sentiment_label'];
		}

		if ( $args['action_required'] !== null ) {
				$where_clauses[] = 'action_required = %d';
				$where_values[]  = $args['action_required'];
		}

		if ( ! empty( $args['date_from'] ) ) {
				$where_clauses[] = 'review_date >= %s';
				$where_values[]  = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
				$where_clauses[] = 'review_date <= %s';
				$where_values[]  = $args['date_to'];
		}

			$where_sql = implode( ' AND ', $where_clauses );
			$order_sql = sprintf( 'ORDER BY %s %s', $order_by, $order_direction );
			$limit_sql = sprintf( 'LIMIT %d OFFSET %d', max( 0, (int) $args['limit'] ), max( 0, (int) $args['offset'] ) );

			$query = $wpdb->prepare(
				'SELECT * FROM ' . self::get_table_name() . " WHERE {$where_sql} {$order_sql} {$limit_sql}",
				...$where_values
			);

			$results = $wpdb->get_results( $query, ARRAY_A );

			// Decode JSON fields
		foreach ( $results as &$result ) {
				$result['key_issues']       = json_decode( $result['key_issues'], true ) ?: [];
				$result['positive_aspects'] = json_decode( $result['positive_aspects'], true ) ?: [];
		}

			return $results;
	}

	/**
	 * Get sentiment summary for a client
	 *
	 * @param int   $client_id Client ID
	 * @param array $args Optional arguments (date_from, date_to)
	 * @return array
	 */
	public static function get_sentiment_summary( int $client_id, array $args = [] ): array {
		global $wpdb;

		$where_clauses = [ 'client_id = %d' ];
		$where_values  = [ $client_id ];

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'review_date >= %s';
			$where_values[]  = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'review_date <= %s';
			$where_values[]  = $args['date_to'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$query = $wpdb->prepare(
			"
			SELECT 
				COUNT(*) as total_reviews,
				AVG(sentiment_score) as avg_sentiment_score,
				AVG(review_rating) as avg_rating,
				SUM(CASE WHEN sentiment_label = 'positive' THEN 1 ELSE 0 END) as positive_reviews,
				SUM(CASE WHEN sentiment_label = 'negative' THEN 1 ELSE 0 END) as negative_reviews,
				SUM(CASE WHEN sentiment_label = 'neutral' THEN 1 ELSE 0 END) as neutral_reviews,
				SUM(CASE WHEN action_required = 1 THEN 1 ELSE 0 END) as action_required_count,
				SUM(CASE WHEN responded = 1 THEN 1 ELSE 0 END) as responded_count
			FROM " . self::get_table_name() . " 
			WHERE {$where_sql}
			",
			...$where_values
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		// Calculate percentages
		$total = (int) $result['total_reviews'];
		if ( $total > 0 ) {
			$result['positive_percentage'] = round( ( $result['positive_reviews'] / $total ) * 100, 1 );
			$result['negative_percentage'] = round( ( $result['negative_reviews'] / $total ) * 100, 1 );
			$result['neutral_percentage']  = round( ( $result['neutral_reviews'] / $total ) * 100, 1 );
			$result['response_rate']       = round( ( $result['responded_count'] / $total ) * 100, 1 );
		} else {
			$result['positive_percentage'] = 0;
			$result['negative_percentage'] = 0;
			$result['neutral_percentage']  = 0;
			$result['response_rate']       = 0;
		}

		return $result;
	}

	/**
	 * Get top issues across all reviews for a client
	 *
	 * @param int   $client_id Client ID
	 * @param array $args Optional arguments
	 * @return array
	 */
	public static function get_top_issues( int $client_id, array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'limit'     => 10,
			'date_from' => null,
			'date_to'   => null,
		];

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = [ 'client_id = %d', 'sentiment_label = %s' ];
		$where_values  = [ $client_id, 'negative' ];

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'review_date >= %s';
			$where_values[]  = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'review_date <= %s';
			$where_values[]  = $args['date_to'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$query = $wpdb->prepare(
			'SELECT key_issues FROM ' . self::get_table_name() . " WHERE {$where_sql}",
			...$where_values
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Aggregate and count issues
		$issue_counts = [];
		foreach ( $results as $result ) {
			$issues = json_decode( $result['key_issues'], true ) ?: [];
			foreach ( $issues as $issue ) {
				if ( ! empty( $issue ) ) {
					$issue_counts[ $issue ] = ( $issue_counts[ $issue ] ?? 0 ) + 1;
				}
			}
		}

		// Sort by frequency and limit
		arsort( $issue_counts );
		return array_slice( $issue_counts, 0, $args['limit'], true );
	}

	/**
	 * Update sentiment analysis record
	 *
	 * @param int   $sentiment_id Sentiment ID
	 * @param array $data Update data
	 * @return bool
	 */
	public static function update_sentiment( int $sentiment_id, array $data ): bool {
		global $wpdb;

		// Ensure JSON fields are properly encoded
		if ( isset( $data['key_issues'] ) && is_array( $data['key_issues'] ) ) {
			$data['key_issues'] = wp_json_encode( $data['key_issues'] );
		}
		if ( isset( $data['positive_aspects'] ) && is_array( $data['positive_aspects'] ) ) {
			$data['positive_aspects'] = wp_json_encode( $data['positive_aspects'] );
		}

		$result = $wpdb->update(
			self::get_table_name(),
			$data,
			[ 'id' => $sentiment_id ],
			null,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Mark sentiment as responded
	 *
	 * @param int    $sentiment_id Sentiment ID
	 * @param string $response_text Response text
	 * @return bool
	 */
	public static function mark_as_responded( int $sentiment_id, string $response_text ): bool {
		global $wpdb;

		$result = $wpdb->update(
			self::get_table_name(),
			[
				'responded'     => 1,
				'response_text' => $response_text,
				'response_date' => current_time( 'mysql' ),
			],
			[ 'id' => $sentiment_id ],
			[ '%d', '%s', '%s' ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Get available platforms
	 *
	 * @return array
	 */
	public static function get_available_platforms(): array {
		return [
			'google_reviews'  => __( 'Google Reviews', 'fp-digital-marketing' ),
			'facebook'        => __( 'Facebook', 'fp-digital-marketing' ),
			'trustpilot'      => __( 'Trustpilot', 'fp-digital-marketing' ),
			'tripadvisor'     => __( 'TripAdvisor', 'fp-digital-marketing' ),
			'yelp'            => __( 'Yelp', 'fp-digital-marketing' ),
			'linkedin'        => __( 'LinkedIn', 'fp-digital-marketing' ),
			'instagram'       => __( 'Instagram', 'fp-digital-marketing' ),
			'twitter'         => __( 'Twitter/X', 'fp-digital-marketing' ),
			'website_reviews' => __( 'Recensioni Sito Web', 'fp-digital-marketing' ),
			'other'           => __( 'Altro', 'fp-digital-marketing' ),
		];
	}

	/**
	 * Generate sample sentiment data for demonstration
	 *
	 * @param int $client_id Client ID
	 * @param int $count Number of sample records to generate
	 * @return bool
	 */
	public static function generate_sample_data( int $client_id, int $count = 20 ): bool {
		$platforms        = array_keys( self::get_available_platforms() );
		$sentiment_labels = [ 'positive', 'negative', 'neutral' ];

		$sample_reviews = [
			'positive' => [
				'Servizio eccellente, molto soddisfatto della qualità!',
				'Staff cordiale e professionale, consiglio vivamente.',
				'Prodotto fantastico, supera le aspettative.',
				'Esperienza molto positiva, tornerò sicuramente.',
				'Ottimo rapporto qualità-prezzo, molto felice dell\'acquisto.',
			],
			'negative' => [
				'Servizio lento e poco professionale.',
				'Prodotto di scarsa qualità, molto deludente.',
				'Tempi di attesa troppo lunghi, pessima esperienza.',
				'Staff scortese e poco disponibile.',
				'Prezzo troppo alto per la qualità offerta.',
			],
			'neutral'  => [
				'Esperienza nella media, niente di speciale.',
				'Servizio ok, potrebbero migliorare alcuni aspetti.',
				'Prodotto discreto, fa il suo lavoro.',
				'Staff disponibile ma potrebbero essere più veloci.',
				'Prezzi nella norma, qualità accettabile.',
			],
		];

		$sample_issues = [
			'Tempi di attesa',
			'Qualità del prodotto',
			'Servizio clienti',
			'Prezzo',
			'Disponibilità',
			'Comunicazione',
			'Tempistiche di consegna',
			'Facilità d\'uso',
			'Supporto tecnico',
			'Accessibilità',
		];

		$sample_positives = [
			'Qualità eccellente',
			'Staff professionale',
			'Velocità del servizio',
			'Prezzo competitivo',
			'Facilità d\'uso',
			'Supporto clienti',
			'Consegna puntuale',
			'Prodotto innovativo',
			'Esperienza utente',
			'Attenzione ai dettagli',
		];

		for ( $i = 0; $i < $count; $i++ ) {
			$sentiment_label = $sentiment_labels[ array_rand( $sentiment_labels ) ];
			$platform        = $platforms[ array_rand( $platforms ) ];
			$review_text     = $sample_reviews[ $sentiment_label ][ array_rand( $sample_reviews[ $sentiment_label ] ) ];

			// Generate sentiment score based on label
			switch ( $sentiment_label ) {
				case 'positive':
					$sentiment_score = rand( 60, 100 ) / 100;
					$rating          = rand( 4, 5 );
					$issues          = [];
					$positives       = array_slice( $sample_positives, 0, rand( 1, 3 ) );
					$action_required = 0;
					break;
				case 'negative':
					$sentiment_score = rand( 0, 40 ) / 100;
					$rating          = rand( 1, 2 );
					$issues          = array_slice( $sample_issues, 0, rand( 1, 4 ) );
					$positives       = [];
					$action_required = 1;
					break;
				default: // neutral
					$sentiment_score = rand( 41, 59 ) / 100;
					$rating          = 3;
					$issues          = rand( 0, 1 ) ? array_slice( $sample_issues, 0, 1 ) : [];
					$positives       = rand( 0, 1 ) ? array_slice( $sample_positives, 0, 1 ) : [];
					$action_required = 0;
			}

			$ai_summary = sprintf(
				'Recensione %s con punteggio %.2f. %s',
				$sentiment_label,
				$sentiment_score,
				$sentiment_label === 'negative' ? 'Richiede attenzione.' : 'Feedback positivo.'
			);

						$data = [
							'client_id'            => $client_id,
							'external_id'          => 'demo-' . wp_generate_password( 12, false ),
							'review_source'        => 'Demo Data',
							'review_platform'      => $platform,
							'review_url'           => 'https://example.com/review/' . wp_generate_password( 8, false ),
							'review_text'          => $review_text,
							'review_rating'        => $rating,
							'review_date'          => date( 'Y-m-d H:i:s', strtotime( '-' . rand( 1, 90 ) . ' days' ) ),
							'sentiment_score'      => $sentiment_score,
							'sentiment_label'      => $sentiment_label,
							'sentiment_confidence' => rand( 70, 95 ) / 100,
							'key_issues'           => $issues,
							'positive_aspects'     => $positives,
							'ai_summary'           => $ai_summary,
							'action_required'      => $action_required,
							'responded'            => rand( 0, 1 ),
						];

						self::insert_sentiment( $data );
		}

		return true;
	}
}
