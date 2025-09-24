<?php
/**
 * Audience Segment Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\AudienceSegmentTable;

/**
 * Audience Segment model class
 * 
 * Represents a dynamic audience segment with rules and criteria for user/lead segmentation.
 */
class AudienceSegment {

	/**
	 * Segment ID
	 *
	 * @var int|null
	 */
	private ?int $id = null;

	/**
	 * Segment name
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Segment description
	 *
	 * @var string
	 */
	private string $description = '';

	/**
	 * Client ID
	 *
	 * @var int
	 */
	private int $client_id;

	/**
	 * Segment rules as JSON array
	 *
	 * @var array
	 */
	private array $rules = [];

	/**
	 * Whether the segment is active
	 *
	 * @var bool
	 */
	private bool $is_active = true;

	/**
	 * Last evaluation timestamp
	 *
	 * @var string|null
	 */
	private ?string $last_evaluated_at = null;

	/**
	 * Member count cache
	 *
	 * @var int
	 */
	private int $member_count = 0;

	/**
	 * Creation timestamp
	 *
	 * @var string|null
	 */
	private ?string $created_at = null;

	/**
	 * Last update timestamp
	 *
	 * @var string|null
	 */
	private ?string $updated_at = null;

	/**
	 * Constructor
	 *
	 * @param array $data Segment data array
	 */
	public function __construct( array $data = [] ) {
		if ( ! empty( $data ) ) {
			$this->populate_from_array( $data );
		}
	}

	/**
	 * Create a new segment from array data
	 *
	 * @param array $data Segment data
	 * @return self New AudienceSegment instance
	 */
	public static function create_from_array( array $data ): self {
		return new self( $data );
	}

	/**
	 * Load segment from database by ID
	 *
	 * @param int $segment_id Segment ID
	 * @return self|null AudienceSegment instance or null if not found
	 */
	public static function load_by_id( int $segment_id ): ?self {
		$segments = AudienceSegmentTable::get_segments( [ 'id' => $segment_id ], 1, 0 );
		
		if ( ! empty( $segments ) ) {
			return new self( $segments[0] );
		}

		return null;
	}

	/**
	 * Populate object from array data
	 *
	 * @param array $data Segment data array
	 * @return void
	 */
	private function populate_from_array( array $data ): void {
		$this->id = isset( $data['id'] ) ? (int) $data['id'] : null;
		$this->name = sanitize_text_field( $data['name'] ?? '' );
		$this->description = sanitize_textarea_field( $data['description'] ?? '' );
		$this->client_id = isset( $data['client_id'] ) ? (int) $data['client_id'] : 0;
                $raw_rules = isset( $data['rules'] ) && is_array( $data['rules'] )
                        ? $data['rules']
                        : ( isset( $data['rules'] ) ? json_decode( $data['rules'], true ) ?: [] : [] );

                $this->rules = $this->sanitize_rules_array( $raw_rules );
		$this->is_active = isset( $data['is_active'] ) ? (bool) $data['is_active'] : true;
		$this->last_evaluated_at = $data['last_evaluated_at'] ?? null;
		$this->member_count = isset( $data['member_count'] ) ? (int) $data['member_count'] : 0;
		$this->created_at = $data['created_at'] ?? null;
		$this->updated_at = $data['updated_at'] ?? null;
	}

	/**
	 * Save segment to database
	 *
	 * @return bool True on success, false on failure
	 */
	public function save(): bool {
		$data = $this->to_array();
		unset( $data['id'] ); // Don't include ID in insert/update data

		if ( $this->id ) {
			// Update existing segment
			$data['updated_at'] = current_time( 'mysql' );
			return AudienceSegmentTable::update_segment( $this->id, $data );
		} else {
			// Insert new segment
			$data['created_at'] = current_time( 'mysql' );
			$data['updated_at'] = current_time( 'mysql' );
			$insert_id = AudienceSegmentTable::insert_segment( $data );
			if ( $insert_id ) {
				$this->id = $insert_id;
				return true;
			}
			return false;
		}
	}

	/**
	 * Delete segment from database
	 *
	 * @return bool True on success, false on failure
	 */
	public function delete(): bool {
		if ( ! $this->id ) {
			return false;
		}

		return AudienceSegmentTable::delete_segment( $this->id );
	}

	/**
	 * Add a rule to the segment
	 *
	 * @param array $rule Rule configuration
	 * @return void
	 */
        public function add_rule( array $rule ): void {
                $this->rules[] = $this->sanitize_rules_array( $rule );
        }

	/**
	 * Set all rules at once
	 *
	 * @param array $rules Array of rule configurations
	 * @return void
	 */
        public function set_rules( array $rules ): void {
                $this->rules = $this->sanitize_rules_array( $rules );
        }

	/**
	 * Get rule by index
	 *
	 * @param int $index Rule index
	 * @return array|null Rule configuration or null if not found
	 */
	public function get_rule( int $index ): ?array {
		return $this->rules[ $index ] ?? null;
	}

	/**
	 * Remove rule by index
	 *
	 * @param int $index Rule index
	 * @return void
	 */
	public function remove_rule( int $index ): void {
		if ( isset( $this->rules[ $index ] ) ) {
			unset( $this->rules[ $index ] );
			$this->rules = array_values( $this->rules ); // Re-index array
		}
	}

	/**
	 * Update last evaluation timestamp
	 *
	 * @return void
	 */
	public function update_evaluation_timestamp(): void {
		$this->last_evaluated_at = current_time( 'mysql' );
	}

	/**
	 * Update member count cache
	 *
	 * @param int $count New member count
	 * @return void
	 */
	public function update_member_count( int $count ): void {
		$this->member_count = $count;
	}

	/**
	 * Convert to array representation
	 *
	 * @return array Segment data as array
	 */
	public function to_array(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'description' => $this->description,
			'client_id' => $this->client_id,
			'rules' => $this->rules,
			'is_active' => $this->is_active,
			'last_evaluated_at' => $this->last_evaluated_at,
			'member_count' => $this->member_count,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}

	// Getters
	public function get_id(): ?int { return $this->id; }
	public function get_name(): string { return $this->name; }
	public function get_description(): string { return $this->description; }
	public function get_client_id(): int { return $this->client_id; }
	public function get_rules(): array { return $this->rules; }
	public function is_active(): bool { return $this->is_active; }
	public function get_last_evaluated_at(): ?string { return $this->last_evaluated_at; }
	public function get_member_count(): int { return $this->member_count; }
	public function get_created_at(): ?string { return $this->created_at; }
	public function get_updated_at(): ?string { return $this->updated_at; }

	// Setters
        public function set_name( string $name ): void { $this->name = $name; }
        public function set_description( string $description ): void { $this->description = $description; }
        public function set_client_id( int $client_id ): void { $this->client_id = $client_id; }
        public function set_active( bool $is_active ): void { $this->is_active = $is_active; }

        /**
         * Recursively sanitize rules array values.
         *
         * @param array $rules Rules array to sanitize.
         * @return array Sanitized rules array.
         */
        private function sanitize_rules_array( array $rules ): array {
                $sanitized_rules = [];

                foreach ( $rules as $key => $value ) {
                        $sanitized_key = is_string( $key ) ? sanitize_key( $key ) : $key;

                        if ( is_array( $value ) ) {
                                $sanitized_value = $this->sanitize_rules_array( $value );
                        } elseif ( is_string( $value ) ) {
                                $sanitized_value = sanitize_text_field( $value );
                        } elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
                                $sanitized_value = $value;
                        } else {
                                $sanitized_value = sanitize_text_field( (string) $value );
                        }

                        $sanitized_rules[ $sanitized_key ] = $sanitized_value;
                }

                return $sanitized_rules;
        }
}