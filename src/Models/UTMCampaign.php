<?php
/**
 * UTM Campaign Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\UTMCampaignsTable;
use FP\DigitalMarketing\Helpers\UTMGenerator;

/**
 * UTM Campaign model for handling campaign data
 */
class UTMCampaign {

	/**
	 * Campaign ID
	 *
	 * @var int|null
	 */
	private ?int $id = null;

	/**
	 * Campaign name
	 *
	 * @var string
	 */
	private string $campaign_name = '';

	/**
	 * UTM source
	 *
	 * @var string
	 */
	private string $utm_source = '';

	/**
	 * UTM medium
	 *
	 * @var string
	 */
	private string $utm_medium = '';

	/**
	 * UTM campaign
	 *
	 * @var string
	 */
	private string $utm_campaign = '';

	/**
	 * UTM term
	 *
	 * @var string|null
	 */
	private ?string $utm_term = null;

	/**
	 * UTM content
	 *
	 * @var string|null
	 */
	private ?string $utm_content = null;

	/**
	 * Base URL
	 *
	 * @var string
	 */
	private string $base_url = '';

	/**
	 * Final URL with UTM parameters
	 *
	 * @var string
	 */
	private string $final_url = '';

	/**
	 * Short URL
	 *
	 * @var string|null
	 */
	private ?string $short_url = null;

	/**
	 * Preset used
	 *
	 * @var string|null
	 */
	private ?string $preset_used = null;

	/**
	 * Click count
	 *
	 * @var int
	 */
	private int $clicks = 0;

	/**
	 * Conversion count
	 *
	 * @var int
	 */
	private int $conversions = 0;

	/**
	 * Revenue
	 *
	 * @var float
	 */
	private float $revenue = 0.0;

	/**
	 * Campaign status
	 *
	 * @var string
	 */
	private string $status = 'active';

	/**
	 * Creation timestamp
	 *
	 * @var string|null
	 */
	private ?string $created_at = null;

	/**
	 * Update timestamp
	 *
	 * @var string|null
	 */
	private ?string $updated_at = null;

	/**
	 * Creator user ID
	 *
	 * @var int|null
	 */
	private ?int $created_by = null;

	/**
	 * Constructor
	 *
	 * @param array $data Campaign data.
	 */
	public function __construct( array $data = [] ) {
		$this->populate( $data );
	}

	/**
	 * Populate model with data
	 *
	 * @param array $data Campaign data.
	 * @return void
	 */
	public function populate( array $data ): void {
		$this->id = isset( $data['id'] ) ? (int) $data['id'] : null;
                $this->campaign_name = $this->sanitize_text_value( $data['campaign_name'] ?? '' );
                $this->utm_source = $this->sanitize_text_value( $data['utm_source'] ?? '' );
                $this->utm_medium = $this->sanitize_text_value( $data['utm_medium'] ?? '' );
                $this->utm_campaign = $this->sanitize_text_value( $data['utm_campaign'] ?? '' );
                $this->utm_term = ! empty( $data['utm_term'] ) ? $this->sanitize_text_value( $data['utm_term'] ) : null;
                $this->utm_content = ! empty( $data['utm_content'] ) ? $this->sanitize_text_value( $data['utm_content'] ) : null;
                $this->base_url = $this->sanitize_url( $data['base_url'] ?? '' );
		$this->final_url = esc_url_raw( $data['final_url'] ?? '' );
		$this->short_url = ! empty( $data['short_url'] ) ? esc_url_raw( $data['short_url'] ) : null;
		$this->preset_used = ! empty( $data['preset_used'] ) ? sanitize_text_field( $data['preset_used'] ) : null;
		$this->clicks = isset( $data['clicks'] ) ? (int) $data['clicks'] : 0;
		$this->conversions = isset( $data['conversions'] ) ? (int) $data['conversions'] : 0;
		$this->revenue = isset( $data['revenue'] ) ? (float) $data['revenue'] : 0.0;
                $this->status = $this->sanitize_text_value( $data['status'] ?? 'active' );
		$this->created_at = $data['created_at'] ?? null;
		$this->updated_at = $data['updated_at'] ?? null;
		$this->created_by = isset( $data['created_by'] ) ? (int) $data['created_by'] : null;

		// Generate final URL if not provided.
		if ( empty( $this->final_url ) && ! empty( $this->base_url ) ) {
			$this->generate_final_url();
		}
	}

	/**
	 * Save campaign to database
	 *
	 * @return bool True on success, false on failure.
	 */
	public function save(): bool {
		global $wpdb;

		// Validate required fields.
		if ( ! $this->validate() ) {
			return false;
		}

		// Check for duplicates.
		if ( $this->is_duplicate() ) {
			return false;
		}

		// Generate final URL.
		$this->generate_final_url();

		// Set created_by if not set.
		if ( null === $this->created_by ) {
			$this->created_by = get_current_user_id();
		}

		$table_name = UTMCampaignsTable::get_table_name();
		$data = $this->to_array();

		// Remove timestamps for new records.
		if ( null === $this->id ) {
			unset( $data['id'], $data['created_at'], $data['updated_at'] );
		} else {
			unset( $data['created_at'] );
		}

		if ( null === $this->id ) {
			// Insert new record.
			$result = $wpdb->insert( $table_name, $data );
			if ( $result ) {
				$this->id = $wpdb->insert_id;
				return true;
			}
		} else {
			// Update existing record.
			$result = $wpdb->update( 
				$table_name, 
				$data, 
				[ 'id' => $this->id ] 
			);
			return $result !== false;
		}

		return false;
	}

	/**
	 * Delete campaign from database
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete(): bool {
		if ( null === $this->id ) {
			return false;
		}

		global $wpdb;
		$table_name = UTMCampaignsTable::get_table_name();

		$result = $wpdb->delete( 
			$table_name, 
			[ 'id' => $this->id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Load campaign by ID
	 *
	 * @param int $id Campaign ID.
	 * @return UTMCampaign|null Campaign object or null if not found.
	 */
	public static function find( int $id ): ?self {
		global $wpdb;
		$table_name = UTMCampaignsTable::get_table_name();

		$data = $wpdb->get_row( 
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		return $data ? new self( $data ) : null;
	}

	/**
	 * Get all campaigns with optional filters
	 *
	 * @param array $filters Optional filters.
	 * @param int   $limit   Results limit.
	 * @param int   $offset  Results offset.
	 * @return array Array of UTMCampaign objects.
	 */
	public static function get_campaigns( array $filters = [], int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		$table_name = UTMCampaignsTable::get_table_name();

		$where_conditions = [];
		$where_values = [];

		// Apply filters.
		if ( ! empty( $filters['status'] ) ) {
			$where_conditions[] = 'status = %s';
			$where_values[] = $filters['status'];
		}

		if ( ! empty( $filters['utm_source'] ) ) {
			$where_conditions[] = 'utm_source = %s';
			$where_values[] = $filters['utm_source'];
		}

		if ( ! empty( $filters['utm_medium'] ) ) {
			$where_conditions[] = 'utm_medium = %s';
			$where_values[] = $filters['utm_medium'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$where_conditions[] = '(campaign_name LIKE %s OR utm_campaign LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}

		// Build query.
		$where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';
		$limit_clause = $wpdb->prepare( 'LIMIT %d OFFSET %d', $limit, $offset );

		$query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC $limit_clause";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		return array_map( function( $data ) {
			return new self( $data );
		}, $results ?: [] );
	}

	/**
	 * Get total campaigns count
	 *
	 * @param array $filters Optional filters.
	 * @return int Total count.
	 */
	public static function get_campaigns_count( array $filters = [] ): int {
		global $wpdb;
		$table_name = UTMCampaignsTable::get_table_name();

		$where_conditions = [];
		$where_values = [];

		// Apply same filters as get_campaigns.
		if ( ! empty( $filters['status'] ) ) {
			$where_conditions[] = 'status = %s';
			$where_values[] = $filters['status'];
		}

		if ( ! empty( $filters['utm_source'] ) ) {
			$where_conditions[] = 'utm_source = %s';
			$where_values[] = $filters['utm_source'];
		}

		if ( ! empty( $filters['utm_medium'] ) ) {
			$where_conditions[] = 'utm_medium = %s';
			$where_values[] = $filters['utm_medium'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$where_conditions[] = '(campaign_name LIKE %s OR utm_campaign LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}

		$where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';
		$query = "SELECT COUNT(*) FROM $table_name $where_clause";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Validate campaign data
	 *
	 * @return bool True if valid, false otherwise.
	 */
	private function validate(): bool {
		// Required fields.
		if ( empty( $this->campaign_name ) || 
			 empty( $this->utm_source ) || 
			 empty( $this->utm_medium ) || 
			 empty( $this->utm_campaign ) ||
			 empty( $this->base_url ) ) {
			return false;
		}

		// Validate UTM parameters.
		$utm_params = [
			'source'   => $this->utm_source,
			'medium'   => $this->utm_medium,
			'campaign' => $this->utm_campaign,
			'term'     => $this->utm_term,
			'content'  => $this->utm_content,
		];

		$validation = UTMGenerator::validate_utm_params( $utm_params );
		return $validation['valid'];
	}

	/**
	 * Check if campaign is duplicate
	 *
	 * @return bool True if duplicate exists, false otherwise.
	 */
	private function is_duplicate(): bool {
		global $wpdb;
		$table_name = UTMCampaignsTable::get_table_name();

		$query = "SELECT id FROM $table_name 
				  WHERE utm_source = %s 
				  AND utm_medium = %s 
				  AND utm_campaign = %s 
				  AND base_url = %s";

		$params = [ $this->utm_source, $this->utm_medium, $this->utm_campaign, $this->base_url ];

		// Add optional parameters to uniqueness check.
		if ( $this->utm_term ) {
			$query .= " AND utm_term = %s";
			$params[] = $this->utm_term;
		} else {
			$query .= " AND utm_term IS NULL";
		}

		if ( $this->utm_content ) {
			$query .= " AND utm_content = %s";
			$params[] = $this->utm_content;
		} else {
			$query .= " AND utm_content IS NULL";
		}

		// Exclude current record if updating.
		if ( $this->id ) {
			$query .= " AND id != %d";
			$params[] = $this->id;
		}

		$existing_id = $wpdb->get_var( $wpdb->prepare( $query, $params ) );
		return ! empty( $existing_id );
	}

	/**
	 * Generate final URL with UTM parameters
	 *
	 * @return void
	 */
	private function generate_final_url(): void {
		$utm_params = [
			'source'   => $this->utm_source,
			'medium'   => $this->utm_medium,
			'campaign' => $this->utm_campaign,
			'term'     => $this->utm_term,
			'content'  => $this->utm_content,
		];

		$this->final_url = UTMGenerator::generate_utm_url( $this->base_url, $utm_params );
	}

	/**
	 * Convert model to array
	 *
	 * @return array Model data as array.
	 */
        public function to_array(): array {
                return [
                        'id'            => $this->id,
			'campaign_name' => $this->campaign_name,
			'utm_source'    => $this->utm_source,
			'utm_medium'    => $this->utm_medium,
			'utm_campaign'  => $this->utm_campaign,
			'utm_term'      => $this->utm_term,
			'utm_content'   => $this->utm_content,
			'base_url'      => $this->base_url,
			'final_url'     => $this->final_url,
			'short_url'     => $this->short_url,
			'preset_used'   => $this->preset_used,
			'clicks'        => $this->clicks,
			'conversions'   => $this->conversions,
			'revenue'       => $this->revenue,
			'status'        => $this->status,
			'created_at'    => $this->created_at,
			'updated_at'    => $this->updated_at,
                        'created_by'    => $this->created_by,
                ];
        }

        /**
         * Sanitize text fields using a multi-step approach that strips script content
         * and normalizes whitespace for safe storage.
         *
         * @param string $value Raw input value.
         * @return string
         */
        private function sanitize_text_value( string $value ): string {
                if ( '' === $value ) {
                        return '';
                }

                $value = preg_replace( '#<script\b[^>]*>(.*?)</script>#is', '', $value );
                $value = wp_strip_all_tags( $value, true );
                $value = sanitize_text_field( $value );

                return trim( $value );
        }

        /**
         * Sanitize URL values ensuring dangerous schemes are removed.
         *
         * @param string $url Raw URL value.
         * @return string
         */
        private function sanitize_url( string $url ): string {
                $url = trim( (string) $url );

                if ( '' === $url ) {
                        return '';
                }

                if ( preg_match( '#^(javascript|data):#i', $url ) ) {
                        return '';
                }

                return esc_url_raw( $url );
        }

	/**
	 * Get campaign ID
	 *
	 * @return int|null
	 */
	public function get_id(): ?int {
		return $this->id;
	}

	/**
	 * Get campaign name
	 *
	 * @return string
	 */
	public function get_campaign_name(): string {
		return $this->campaign_name;
	}

	/**
	 * Get final URL
	 *
	 * @return string
	 */
	public function get_final_url(): string {
		return $this->final_url;
	}

	/**
	 * Get short URL
	 *
	 * @return string|null
	 */
	public function get_short_url(): ?string {
		return $this->short_url;
	}

	/**
	 * Set short URL
	 *
	 * @param string $short_url Short URL.
	 * @return void
	 */
	public function set_short_url( string $short_url ): void {
		$this->short_url = esc_url_raw( $short_url );
	}

	/**
	 * Get clicks count
	 *
	 * @return int
	 */
	public function get_clicks(): int {
		return $this->clicks;
	}

	/**
	 * Get conversions count
	 *
	 * @return int
	 */
	public function get_conversions(): int {
		return $this->conversions;
	}

	/**
	 * Get revenue
	 *
	 * @return float
	 */
	public function get_revenue(): float {
		return $this->revenue;
	}

	/**
	 * Get status
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Update performance metrics
	 *
	 * @param int   $clicks      Additional clicks.
	 * @param int   $conversions Additional conversions.
	 * @param float $revenue     Additional revenue.
	 * @return bool True on success, false on failure.
	 */
	public function update_performance( int $clicks = 0, int $conversions = 0, float $revenue = 0.0 ): bool {
		$this->clicks += $clicks;
		$this->conversions += $conversions;
		$this->revenue += $revenue;

		return $this->save();
	}
}