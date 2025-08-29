<?php
/**
 * Unit tests for Onboarding Wizard
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Admin\OnboardingWizard;

/**
 * Test class for OnboardingWizard
 */
class OnboardingWizardTest extends TestCase {

	/**
	 * Test wizard instance creation
	 */
	public function test_wizard_instantiation(): void {
		$wizard = new OnboardingWizard();
		$this->assertInstanceOf( OnboardingWizard::class, $wizard );
	}

	/**
	 * Test wizard completion status
	 */
	public function test_wizard_completion_status(): void {
		// Initially not completed
		$this->assertFalse( OnboardingWizard::is_completed() );

		// Simulate completion
		update_option( 'fp_digital_marketing_wizard_completed', true );
		$this->assertTrue( OnboardingWizard::is_completed() );

		// Reset
		OnboardingWizard::reset();
		$this->assertFalse( OnboardingWizard::is_completed() );
	}

	/**
	 * Test wizard progress saving
	 */
	public function test_wizard_progress_saving(): void {
		// Reset wizard first
		OnboardingWizard::reset();

		// Simulate saving progress
		$progress = [
			'services' => [ 'google_analytics_4' ],
			'metrics' => [ 'sessions', 'pageviews' ],
		];
		update_option( 'fp_digital_marketing_wizard_progress', $progress );

		$saved_progress = get_option( 'fp_digital_marketing_wizard_progress', [] );
		$this->assertEquals( $progress['services'], $saved_progress['services'] );
		$this->assertEquals( $progress['metrics'], $saved_progress['metrics'] );

		// Clean up
		OnboardingWizard::reset();
	}

	/**
	 * Test wizard reset functionality
	 */
	public function test_wizard_reset(): void {
		// Set some options
		update_option( 'fp_digital_marketing_wizard_completed', true );
		update_option( 'fp_digital_marketing_wizard_progress', [ 'test' => 'data' ] );

		// Reset
		OnboardingWizard::reset();

		// Verify options are cleared
		$this->assertFalse( get_option( 'fp_digital_marketing_wizard_completed', false ) );
		$this->assertEquals( [], get_option( 'fp_digital_marketing_wizard_progress', [] ) );
	}

	/**
	 * Mock WordPress functions for testing
	 */
	protected function setUp(): void {
		// Mock WordPress functions that don't exist in test environment
		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				static $options = [];
				return $options[ $option ] ?? $default;
			}
		}

		if ( ! function_exists( 'update_option' ) ) {
			function update_option( $option, $value ) {
				static $options = [];
				$options[ $option ] = $value;
				return true;
			}
		}

		if ( ! function_exists( 'delete_option' ) ) {
			function delete_option( $option ) {
				static $options = [];
				unset( $options[ $option ] );
				return true;
			}
		}

		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain = 'default' ) {
				return $text;
			}
		}

		if ( ! function_exists( 'esc_html__' ) ) {
			function esc_html__( $text, $domain = 'default' ) {
				return htmlspecialchars( $text );
			}
		}
	}
}