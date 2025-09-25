<?php
/**
 * Google Reviews Data Source Integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\DataSources;

/**
 * Simple Google Places API client for fetching business reviews.
 */
class GoogleReviews {

        /**
         * Google Places API endpoint for place details.
         */
        private const API_ENDPOINT = 'https://maps.googleapis.com/maps/api/place/details/json';

        /**
         * Google Places API key.
         *
         * @var string
         */
        private string $api_key;

        /**
         * Constructor.
         *
         * @param string $api_key Google Places API key.
         */
        public function __construct( string $api_key ) {
                $this->api_key = trim( $api_key );
        }

        /**
         * Fetch reviews for a Place ID.
         *
         * @param string $place_id Google Place ID.
         * @param int    $limit    Maximum number of reviews to return.
         * @return array{success:bool,reviews?:array<int,array<string,mixed>>,error?:string,place_rating?:float,total_reviews?:int}
         */
        public function fetch_reviews( string $place_id, int $limit = 20 ): array {
                $place_id = trim( $place_id );

                if ( '' === $this->api_key || '' === $place_id ) {
                        return [
                                'success' => false,
                                'error' => __( 'Impossibile richiedere le recensioni Google senza API key e Place ID.', 'fp-digital-marketing' ),
                        ];
                }

                $query_args = [
                        'place_id'     => $place_id,
                        'fields'       => 'reviews,url,rating,user_ratings_total',
                        'reviews_sort' => 'newest',
                        'language'     => $this->determine_language(),
                        'key'          => $this->api_key,
                ];

                $url = add_query_arg( $query_args, self::API_ENDPOINT );

                $response = wp_remote_get(
                        $url,
                        [
                                'timeout' => 20,
                                'headers' => [ 'Accept' => 'application/json' ],
                        ]
                );

                if ( is_wp_error( $response ) ) {
                        return [
                                'success' => false,
                                'error'   => sprintf( __( 'Errore di connessione a Google Reviews: %s', 'fp-digital-marketing' ), $response->get_error_message() ),
                        ];
                }

                $status_code = wp_remote_retrieve_response_code( $response );
                $body        = wp_remote_retrieve_body( $response );

                if ( 200 !== $status_code ) {
                        return [
                                'success' => false,
                                'error'   => sprintf( __( 'Google Reviews ha restituito il codice %d.', 'fp-digital-marketing' ), $status_code ),
                        ];
                }

                $data = json_decode( $body, true );
                if ( ! is_array( $data ) || ( $data['status'] ?? '' ) !== 'OK' ) {
                        $error_message = $data['error_message'] ?? __( 'Risposta non valida da Google Places API.', 'fp-digital-marketing' );

                        return [
                                'success' => false,
                                'error'   => $error_message,
                        ];
                }

                $result      = $data['result'] ?? [];
                $reviews     = is_array( $result['reviews'] ?? null ) ? $result['reviews'] : [];
                $place_url   = $result['url'] ?? ''; // Could be empty for some businesses.
                $limit       = max( 1, $limit );
                $normalized  = [];

                foreach ( $reviews as $review ) {
                        if ( empty( $review['text'] ) && ! isset( $review['rating'] ) ) {
                                continue;
                        }

                        $timestamp = isset( $review['time'] ) ? (int) $review['time'] : null;
                        $normalized[] = [
                                'external_id' => $review['review_id'] ?? md5( $place_id . '|' . ( $review['author_name'] ?? '' ) . '|' . (string) ( $review['time'] ?? '' ) ),
                                'review_id'   => $review['review_id'] ?? '',
                                'text'        => (string) ( $review['text'] ?? '' ),
                                'rating'      => isset( $review['rating'] ) ? (float) $review['rating'] : null,
                                'time'        => $timestamp,
                                'date'        => $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null,
                                'url'         => $review['author_url'] ?? $place_url,
                                'author_name' => $review['author_name'] ?? '',
                                'language'    => $review['language'] ?? $this->determine_language(),
                        ];
                }

                if ( $limit > 0 ) {
                        $normalized = array_slice( $normalized, 0, $limit );
                }

                return [
                        'success'       => true,
                        'reviews'       => $normalized,
                        'place_rating'  => isset( $result['rating'] ) ? (float) $result['rating'] : null,
                        'total_reviews' => isset( $result['user_ratings_total'] ) ? (int) $result['user_ratings_total'] : null,
                ];
        }

        /**
         * Determine preferred language for API requests.
         *
         * @return string
         */
        private function determine_language(): string {
                $locale = get_locale();

                if ( ! is_string( $locale ) || '' === $locale ) {
                        return 'it';
                }

                $parts = explode( '_', $locale );
                $language = strtolower( $parts[0] ?? $locale );

                return $language ?: 'it';
        }
}

