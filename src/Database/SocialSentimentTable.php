<?php
/**
 * Social Sentiment Analysis Table Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

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

                $allowed_directions = ['ASC', 'DESC'];

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
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Check if table exists
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
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

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			client_id bigint(20) NOT NULL,
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
			INDEX idx_review_platform (review_platform)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

                return true;
        }

        /**
         * Drop the social sentiment table (for uninstall).
         *
         * @return bool True on success, false on failure
         */
        public static function drop_table(): bool {
                global $wpdb;

                $table_name = self::get_table_name();
                $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

                return $result !== false;
        }

	/**
	 * Insert a new sentiment analysis record
	 *
	 * @param array $data Sentiment data
	 * @return int|false Record ID on success, false on failure
	 */
	public static function insert_sentiment( array $data ) {
		global $wpdb;

		$defaults = [
			'review_source' => '',
			'review_platform' => '',
			'review_url' => '',
			'review_text' => '',
			'review_rating' => null,
			'review_date' => null,
			'sentiment_score' => null,
			'sentiment_label' => '',
			'sentiment_confidence' => null,
			'key_issues' => wp_json_encode( [] ),
			'positive_aspects' => wp_json_encode( [] ),
			'ai_summary' => '',
			'action_required' => 0,
			'responded' => 0,
			'response_text' => '',
			'response_date' => null,
		];

		$data = wp_parse_args( $data, $defaults );

		// Ensure JSON fields are properly encoded
		if ( is_array( $data['key_issues'] ) ) {
			$data['key_issues'] = wp_json_encode( $data['key_issues'] );
		}
		if ( is_array( $data['positive_aspects'] ) ) {
			$data['positive_aspects'] = wp_json_encode( $data['positive_aspects'] );
		}

		$result = $wpdb->insert(
			self::get_table_name(),
			$data,
			[
				'%d', // client_id
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
			]
		);

		return $result ? $wpdb->insert_id : false;
	}

        /**
         * Get sentiment analysis for a client
         *
         * @param int $client_id Client ID
         * @param array $args Optional query arguments
         * @return array
         */
        public static function get_client_sentiment( int $client_id, array $args = [] ): array {
                return self::get_reviews( $client_id, $args );
        }

        /**
         * Get sentiment reviews for a client
         *
         * @param int $client_id Client ID
         * @param array $args Optional query arguments
         * @return array
         */
        public static function get_reviews( int $client_id, array $args = [] ): array {
                global $wpdb;

                $defaults = [
                        'limit' => 50,
                        'offset' => 0,
                        'order_by' => 'review_date',
                        'order' => 'DESC',
                        'sentiment_label' => null,
                        'action_required' => null,
                        'date_from' => null,
                        'date_to' => null,
                ];

                $args = wp_parse_args( $args, $defaults );

                [ $order_by, $order_direction ] = self::sanitize_order_parameters(
                        (string) $args['order_by'],
                        (string) $args['order'],
                        $defaults['order_by'],
                        $defaults['order']
                );

                $where_clauses = ['client_id = %d'];
                $where_values = [$client_id];

                if ( ! empty( $args['sentiment_label'] ) ) {
                        $where_clauses[] = 'sentiment_label = %s';
                        $where_values[] = $args['sentiment_label'];
                }

                if ( $args['action_required'] !== null ) {
                        $where_clauses[] = 'action_required = %d';
                        $where_values[] = $args['action_required'];
                }

                if ( ! empty( $args['date_from'] ) ) {
                        $where_clauses[] = 'review_date >= %s';
                        $where_values[] = $args['date_from'];
                }

                if ( ! empty( $args['date_to'] ) ) {
                        $where_clauses[] = 'review_date <= %s';
                        $where_values[] = $args['date_to'];
                }

                $where_sql = implode( ' AND ', $where_clauses );
                $order_sql = sprintf( 'ORDER BY %s %s', $order_by, $order_direction );
                $limit_sql = sprintf( 'LIMIT %d OFFSET %d', max( 0, (int) $args['limit'] ), max( 0, (int) $args['offset'] ) );

                $query = $wpdb->prepare(
                        "SELECT * FROM " . self::get_table_name() . " WHERE {$where_sql} {$order_sql} {$limit_sql}",
                        ...$where_values
                );

                $results = $wpdb->get_results( $query, ARRAY_A );

                // Decode JSON fields
                foreach ( $results as &$result ) {
                        $result['key_issues'] = json_decode( $result['key_issues'], true ) ?: [];
                        $result['positive_aspects'] = json_decode( $result['positive_aspects'], true ) ?: [];
                }

                return $results;
        }

	/**
	 * Get sentiment summary for a client
	 *
	 * @param int $client_id Client ID
	 * @param array $args Optional arguments (date_from, date_to)
	 * @return array
	 */
	public static function get_sentiment_summary( int $client_id, array $args = [] ): array {
		global $wpdb;

		$where_clauses = ['client_id = %d'];
		$where_values = [$client_id];

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'review_date >= %s';
			$where_values[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'review_date <= %s';
			$where_values[] = $args['date_to'];
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
			$result['neutral_percentage'] = round( ( $result['neutral_reviews'] / $total ) * 100, 1 );
			$result['response_rate'] = round( ( $result['responded_count'] / $total ) * 100, 1 );
		} else {
			$result['positive_percentage'] = 0;
			$result['negative_percentage'] = 0;
			$result['neutral_percentage'] = 0;
			$result['response_rate'] = 0;
		}

		return $result;
	}

	/**
	 * Get top issues across all reviews for a client
	 *
	 * @param int $client_id Client ID
	 * @param array $args Optional arguments
	 * @return array
	 */
	public static function get_top_issues( int $client_id, array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'limit' => 10,
			'date_from' => null,
			'date_to' => null,
		];

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = ['client_id = %d', 'sentiment_label = %s'];
		$where_values = [$client_id, 'negative'];

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'review_date >= %s';
			$where_values[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'review_date <= %s';
			$where_values[] = $args['date_to'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$query = $wpdb->prepare(
			"SELECT key_issues FROM " . self::get_table_name() . " WHERE {$where_sql}",
			...$where_values
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Aggregate and count issues
		$issue_counts = [];
		foreach ( $results as $result ) {
			$issues = json_decode( $result['key_issues'], true ) ?: [];
			foreach ( $issues as $issue ) {
				if ( ! empty( $issue ) ) {
					$issue_counts[$issue] = ( $issue_counts[$issue] ?? 0 ) + 1;
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
	 * @param int $sentiment_id Sentiment ID
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
			['id' => $sentiment_id],
			null,
			['%d']
		);

		return $result !== false;
	}

	/**
	 * Mark sentiment as responded
	 *
	 * @param int $sentiment_id Sentiment ID
	 * @param string $response_text Response text
	 * @return bool
	 */
	public static function mark_as_responded( int $sentiment_id, string $response_text ): bool {
		global $wpdb;

		$result = $wpdb->update(
			self::get_table_name(),
			[
				'responded' => 1,
				'response_text' => $response_text,
				'response_date' => current_time( 'mysql' ),
			],
			['id' => $sentiment_id],
			['%d', '%s', '%s'],
			['%d']
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
			'google_reviews' => __( 'Google Reviews', 'fp-digital-marketing' ),
			'facebook' => __( 'Facebook', 'fp-digital-marketing' ),
			'trustpilot' => __( 'Trustpilot', 'fp-digital-marketing' ),
			'tripadvisor' => __( 'TripAdvisor', 'fp-digital-marketing' ),
			'yelp' => __( 'Yelp', 'fp-digital-marketing' ),
			'linkedin' => __( 'LinkedIn', 'fp-digital-marketing' ),
			'instagram' => __( 'Instagram', 'fp-digital-marketing' ),
			'twitter' => __( 'Twitter/X', 'fp-digital-marketing' ),
			'website_reviews' => __( 'Recensioni Sito Web', 'fp-digital-marketing' ),
			'other' => __( 'Altro', 'fp-digital-marketing' ),
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
		$platforms = array_keys( self::get_available_platforms() );
		$sentiment_labels = ['positive', 'negative', 'neutral'];
		
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
			'neutral' => [
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
			$platform = $platforms[ array_rand( $platforms ) ];
			$review_text = $sample_reviews[ $sentiment_label ][ array_rand( $sample_reviews[ $sentiment_label ] ) ];
			
			// Generate sentiment score based on label
			switch ( $sentiment_label ) {
				case 'positive':
					$sentiment_score = rand( 60, 100 ) / 100;
					$rating = rand( 4, 5 );
					$issues = [];
					$positives = array_slice( $sample_positives, 0, rand( 1, 3 ) );
					$action_required = 0;
					break;
				case 'negative':
					$sentiment_score = rand( 0, 40 ) / 100;
					$rating = rand( 1, 2 );
					$issues = array_slice( $sample_issues, 0, rand( 1, 4 ) );
					$positives = [];
					$action_required = 1;
					break;
				default: // neutral
					$sentiment_score = rand( 41, 59 ) / 100;
					$rating = 3;
					$issues = rand( 0, 1 ) ? array_slice( $sample_issues, 0, 1 ) : [];
					$positives = rand( 0, 1 ) ? array_slice( $sample_positives, 0, 1 ) : [];
					$action_required = 0;
			}

			$ai_summary = sprintf(
				'Recensione %s con punteggio %.2f. %s',
				$sentiment_label,
				$sentiment_score,
				$sentiment_label === 'negative' ? 'Richiede attenzione.' : 'Feedback positivo.'
			);

			$data = [
				'client_id' => $client_id,
				'review_source' => 'Demo Data',
				'review_platform' => $platform,
				'review_url' => 'https://example.com/review/' . wp_generate_password( 8, false ),
				'review_text' => $review_text,
				'review_rating' => $rating,
				'review_date' => date( 'Y-m-d H:i:s', strtotime( '-' . rand( 1, 90 ) . ' days' ) ),
				'sentiment_score' => $sentiment_score,
				'sentiment_label' => $sentiment_label,
				'sentiment_confidence' => rand( 70, 95 ) / 100,
				'key_issues' => $issues,
				'positive_aspects' => $positives,
				'ai_summary' => $ai_summary,
				'action_required' => $action_required,
				'responded' => rand( 0, 1 ),
			];

			self::insert_sentiment( $data );
		}

		return true;
	}
}