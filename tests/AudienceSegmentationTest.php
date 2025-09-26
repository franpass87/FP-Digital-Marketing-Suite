<?php
/**
 * Audience Segmentation Test
 *
 * @package FP_Digital_Marketing_Suite
 */

// Load the test environment bootstrap.
require_once __DIR__ . '/bootstrap.php';

use FP\DigitalMarketing\Models\AudienceSegment;
use FP\DigitalMarketing\Database\AudienceSegmentTable;
use FP\DigitalMarketing\Database\ConversionEventsTable;
use FP\DigitalMarketing\Helpers\ConversionEventManager;
use FP\DigitalMarketing\Helpers\ConversionEventRegistry;
use FP\DigitalMarketing\Helpers\SegmentationEngine;
use PHPUnit\Framework\TestCase;

/**
 * Test audience segmentation functionality
 */
class AudienceSegmentationTest extends TestCase {

	/**
	 * Test segment creation
	 */
	public function test_segment_creation(): void {
		$segment_data = [
			'name'        => 'Test Segment',
			'description' => 'A test segment for unit testing',
			'client_id'   => 1,
			'rules'       => [
				'logic'      => 'AND',
				'conditions' => [
					[
						'type'     => 'event',
						'field'    => 'signup',
						'operator' => 'greater_than',
						'value'    => '0',
					],
				],
			],
			'is_active'   => true,
		];

		$segment = new AudienceSegment( $segment_data );

		$this->assertEquals( 'Test Segment', $segment->get_name() );
		$this->assertEquals( 'A test segment for unit testing', $segment->get_description() );
		$this->assertEquals( 1, $segment->get_client_id() );
		$this->assertTrue( $segment->is_active() );
		$this->assertIsArray( $segment->get_rules() );
		$this->assertEquals( 'AND', $segment->get_rules()['logic'] );
		$this->assertCount( 1, $segment->get_rules()['conditions'] );
	}

	/**
	 * Test segment rules validation
	 */
	public function test_segment_rules(): void {
		$rules = [
			'logic'      => 'OR',
			'conditions' => [
				[
					'type'     => 'event',
					'field'    => 'purchase',
					'operator' => 'greater_than',
					'value'    => '2',
				],
				[
					'type'     => 'utm',
					'field'    => 'utm_source',
					'operator' => 'equals',
					'value'    => 'google',
				],
			],
		];

		$segment = new AudienceSegment(
			[
				'name'      => 'Multi-condition Segment',
				'client_id' => 1,
				'rules'     => $rules,
			]
		);

		$this->assertEquals( 'OR', $segment->get_rules()['logic'] );
		$this->assertCount( 2, $segment->get_rules()['conditions'] );

		$conditions = $segment->get_rules()['conditions'];
		$this->assertEquals( 'event', $conditions[0]['type'] );
		$this->assertEquals( 'purchase', $conditions[0]['field'] );
		$this->assertEquals( 'utm', $conditions[1]['type'] );
		$this->assertEquals( 'utm_source', $conditions[1]['field'] );
	}

	/**
	 * Test rule types and operators availability
	 */
	public function test_rule_types_and_operators(): void {
		$rule_types = SegmentationEngine::get_rule_types();
		$operators  = SegmentationEngine::get_operators();

		$this->assertIsArray( $rule_types );
		$this->assertIsArray( $operators );

		$this->assertArrayHasKey( 'event', $rule_types );
		$this->assertArrayHasKey( 'utm', $rule_types );
		$this->assertArrayHasKey( 'device', $rule_types );
		$this->assertArrayHasKey( 'geography', $rule_types );
		$this->assertArrayHasKey( 'behavior', $rule_types );
		$this->assertArrayHasKey( 'value', $rule_types );

		$this->assertArrayHasKey( 'equals', $operators );
		$this->assertArrayHasKey( 'greater_than', $operators );
		$this->assertArrayHasKey( 'contains', $operators );
		$this->assertArrayHasKey( 'in_last_days', $operators );
	}

	/**
	 * Ensure value comparisons safely handle non-string inputs for contains operations.
	 */
	public function test_compare_values_handles_non_string_inputs(): void {
		$reflection = new \ReflectionClass( SegmentationEngine::class );
		$method     = $reflection->getMethod( 'compare_values' );
		$method->setAccessible( true );

		$this->assertFalse(
			$method->invoke( null, null, 'segment', SegmentationEngine::OP_CONTAINS )
		);

		$this->assertTrue(
			$method->invoke( null, [ 'unexpected' ], 'segment', SegmentationEngine::OP_NOT_CONTAINS )
		);
	}

	/**
	 * Test segment array conversion
	 */
	public function test_segment_to_array(): void {
		$original_data = [
			'name'        => 'Array Test Segment',
			'description' => 'Testing array conversion',
			'client_id'   => 2,
			'rules'       => [
				'logic'      => 'AND',
				'conditions' => [],
			],
			'is_active'   => false,
		];

		$segment    = new AudienceSegment( $original_data );
		$array_data = $segment->to_array();

		$this->assertIsArray( $array_data );
		$this->assertEquals( $original_data['name'], $array_data['name'] );
		$this->assertEquals( $original_data['description'], $array_data['description'] );
		$this->assertEquals( $original_data['client_id'], $array_data['client_id'] );
		$this->assertEquals( $original_data['is_active'], $array_data['is_active'] );
		$this->assertEquals( $original_data['rules'], $array_data['rules'] );
	}

	/**
	 * Test segment getters and setters
	 */
	public function test_segment_getters_setters(): void {
		$segment = new AudienceSegment();

		$segment->set_name( 'Dynamic Segment' );
		$segment->set_description( 'Dynamically created segment' );
		$segment->set_client_id( 3 );
		$segment->set_active( true );

		$this->assertEquals( 'Dynamic Segment', $segment->get_name() );
		$this->assertEquals( 'Dynamically created segment', $segment->get_description() );
		$this->assertEquals( 3, $segment->get_client_id() );
		$this->assertTrue( $segment->is_active() );
	}

	/**
	 * Test rule manipulation methods
	 */
	public function test_rule_manipulation(): void {
		$segment = new AudienceSegment(
			[
				'name'      => 'Rule Test',
				'client_id' => 1,
			]
		);

		// Add a rule
		$rule1 = [
			'type'     => 'event',
			'field'    => 'signup',
			'operator' => 'equals',
			'value'    => '1',
		];
		$segment->add_rule( $rule1 );

		$rules = $segment->get_rules();
		$this->assertCount( 1, $rules );
		$this->assertEquals( $rule1, $rules[0] );

		// Add another rule
		$rule2 = [
			'type'     => 'utm',
			'field'    => 'utm_medium',
			'operator' => 'contains',
			'value'    => 'email',
		];
		$segment->add_rule( $rule2 );

		$rules = $segment->get_rules();
		$this->assertCount( 2, $rules );
		$this->assertEquals( $rule2, $rules[1] );

		// Get specific rule
		$this->assertEquals( $rule1, $segment->get_rule( 0 ) );
		$this->assertEquals( $rule2, $segment->get_rule( 1 ) );
		$this->assertNull( $segment->get_rule( 2 ) );

		// Remove a rule
		$segment->remove_rule( 0 );
		$rules = $segment->get_rules();
		$this->assertCount( 1, $rules );
		$this->assertEquals( $rule2, $rules[0] ); // Should be re-indexed
	}

	/**
	 * Test member count update
	 */
	public function test_member_count_update(): void {
		$segment = new AudienceSegment(
			[
				'name'      => 'Count Test',
				'client_id' => 1,
			]
		);

		$this->assertEquals( 0, $segment->get_member_count() );

		$segment->update_member_count( 150 );
		$this->assertEquals( 150, $segment->get_member_count() );

		$segment->update_evaluation_timestamp();
		$this->assertNotNull( $segment->get_last_evaluated_at() );
	}

	/**
	 * Test segment evaluation only considers events for the evaluated user.
	 */
        /**
         * @group integration
         */
        public function test_segment_evaluation_filters_events_by_user(): void {
		$client_id = 4321;

		ConversionEventsTable::create_table();

		try {
			$segment = new AudienceSegment(
				[
					'name'      => 'High Value Buyers',
					'client_id' => $client_id,
					'is_active' => true,
					'rules'     => [
						'logic'      => 'AND',
						'conditions' => [
							[
								'type'     => 'event',
								'field'    => ConversionEventRegistry::EVENT_PURCHASE,
								'operator' => 'greater_than',
								'value'    => '1',
							],
						],
					],
				]
			);

			$base_event = [
				'event_type' => 'purchase',
				'value'      => 125.0,
				'currency'   => 'EUR',
				'ip_address' => '203.0.113.10',
			];

			ConversionEventManager::ingest_event(
				'google_analytics_4',
				array_merge(
					$base_event,
					[
						'user_id'   => 'segment-user-a',
						'timestamp' => '2024-01-03 09:00:00',
					]
				),
				$client_id
			);

			ConversionEventManager::ingest_event(
				'google_analytics_4',
				array_merge(
					$base_event,
					[
						'user_id'   => 'segment-user-a',
						'timestamp' => '2024-01-03 09:10:00',
					]
				),
				$client_id
			);

			ConversionEventManager::ingest_event(
				'google_analytics_4',
				array_merge(
					$base_event,
					[
						'user_id'   => 'segment-user-b',
						'timestamp' => '2024-01-03 09:00:30',
					]
				),
				$client_id
			);

			$user_a_events = ConversionEventsTable::get_events(
				[
					'client_id' => $client_id,
					'user_id'   => 'segment-user-a',
				],
				10,
				0
			);
			$this->assertCount( 2, $user_a_events );

			$user_b_events = ConversionEventsTable::get_events(
				[
					'client_id' => $client_id,
					'user_id'   => 'segment-user-b',
				],
				10,
				0
			);
			$this->assertCount( 1, $user_b_events );
			$this->assertEquals( 0, $user_b_events[0]['is_duplicate'] );
			$this->assertEquals(
				1,
				ConversionEventsTable::get_events_count(
					[
						'client_id' => $client_id,
						'user_id'   => 'segment-user-b',
					]
				)
			);

			$this->assertTrue(
				SegmentationEngine::evaluate_user_against_segment( 'segment-user-a', $segment )
			);
			$this->assertFalse(
				SegmentationEngine::evaluate_user_against_segment( 'segment-user-b', $segment )
			);
		} finally {
			ConversionEventsTable::drop_table();
		}
	}
}
