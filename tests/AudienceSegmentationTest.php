<?php
/**
 * Audience Segmentation Test
 *
 * @package FP_Digital_Marketing_Suite
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

use FP\DigitalMarketing\Models\AudienceSegment;
use FP\DigitalMarketing\Database\AudienceSegmentTable;
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
			'name' => 'Test Segment',
			'description' => 'A test segment for unit testing',
			'client_id' => 1,
			'rules' => [
				'logic' => 'AND',
				'conditions' => [
					[
						'type' => 'event',
						'field' => 'signup',
						'operator' => 'greater_than',
						'value' => '0'
					]
				]
			],
			'is_active' => true,
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
			'logic' => 'OR',
			'conditions' => [
				[
					'type' => 'event',
					'field' => 'purchase',
					'operator' => 'greater_than',
					'value' => '2'
				],
				[
					'type' => 'utm',
					'field' => 'utm_source',
					'operator' => 'equals',
					'value' => 'google'
				]
			]
		];

		$segment = new AudienceSegment( [
			'name' => 'Multi-condition Segment',
			'client_id' => 1,
			'rules' => $rules,
		] );

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
		$operators = SegmentationEngine::get_operators();

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
	 * Test segment array conversion
	 */
	public function test_segment_to_array(): void {
		$original_data = [
			'name' => 'Array Test Segment',
			'description' => 'Testing array conversion',
			'client_id' => 2,
			'rules' => [
				'logic' => 'AND',
				'conditions' => []
			],
			'is_active' => false,
		];

		$segment = new AudienceSegment( $original_data );
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
		$segment = new AudienceSegment( [
			'name' => 'Rule Test',
			'client_id' => 1,
		] );

		// Add a rule
		$rule1 = [
			'type' => 'event',
			'field' => 'signup',
			'operator' => 'equals',
			'value' => '1'
		];
		$segment->add_rule( $rule1 );

		$rules = $segment->get_rules();
		$this->assertCount( 1, $rules );
		$this->assertEquals( $rule1, $rules[0] );

		// Add another rule
		$rule2 = [
			'type' => 'utm',
			'field' => 'utm_medium',
			'operator' => 'contains',
			'value' => 'email'
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
		$segment = new AudienceSegment( [
			'name' => 'Count Test',
			'client_id' => 1,
		] );

		$this->assertEquals( 0, $segment->get_member_count() );

		$segment->update_member_count( 150 );
		$this->assertEquals( 150, $segment->get_member_count() );

		$segment->update_evaluation_timestamp();
		$this->assertNotNull( $segment->get_last_evaluated_at() );
	}
}