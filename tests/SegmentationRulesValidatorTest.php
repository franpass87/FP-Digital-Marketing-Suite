<?php
/**
 * Tests for the SegmentationRulesValidator helper.
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\SegmentationRulesValidator;

/**
 * @covers \FP\DigitalMarketing\Helpers\SegmentationRulesValidator
 */
final class SegmentationRulesValidatorTest extends TestCase {

	public function test_validate_accepts_well_formed_rules(): void {
			$rules = [
				'logic'      => 'and',
				'conditions' => [
					[
						'type'     => 'event',
						'field'    => 'signup',
						'operator' => 'greater_than',
						'value'    => '2',
					],
					[
						'type'     => 'geography',
						'field'    => 'country',
						'operator' => 'equals',
						'value'    => 'it',
					],
				],
			];

			$validated = SegmentationRulesValidator::validate( $rules );

			$this->assertIsArray( $validated );
			$this->assertSame( 'AND', $validated['logic'] );
			$this->assertCount( 2, $validated['conditions'] );
			$this->assertSame( 2.0, $validated['conditions'][0]['value'] );
			$this->assertSame( 'IT', $validated['conditions'][1]['value'] );
	}

	public function test_invalid_rule_type_returns_error(): void {
			$rules = [
				'logic'      => 'AND',
				'conditions' => [
					[
						'type'     => 'invalid',
						'field'    => 'signup',
						'operator' => 'equals',
						'value'    => 'test',
					],
				],
			];

			$result = SegmentationRulesValidator::validate( $rules );

			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'invalid_rule_type', $result->get_error_code() );
	}

	public function test_numeric_operator_requires_numeric_value(): void {
			$rules = [
				'logic'      => 'AND',
				'conditions' => [
					[
						'type'     => 'behavior',
						'field'    => 'total_events',
						'operator' => 'greater_than',
						'value'    => 'abc',
					],
				],
			];

			$result = SegmentationRulesValidator::validate( $rules );

			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'invalid_rule_value', $result->get_error_code() );
	}

	public function test_condition_limit_is_enforced(): void {
			$conditions = [];
		for ( $i = 0; $i < 30; $i++ ) {
				$conditions[] = [
					'type'     => 'event',
					'field'    => 'signup',
					'operator' => 'equals',
					'value'    => '1',
				];
		}

			$rules = [
				'logic'      => 'AND',
				'conditions' => $conditions,
			];

			$result = SegmentationRulesValidator::validate( $rules );

			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'too_many_conditions', $result->get_error_code() );
	}
}
