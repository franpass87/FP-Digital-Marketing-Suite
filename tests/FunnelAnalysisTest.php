<?php
/**
 * Test for Funnel functionality
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Funnel functionality test class
 */
class FunnelTest extends TestCase {

    /**
     * Test Funnel model creation
     *
     * @return void
     */
    public function test_funnel_creation(): void {
        $funnel_data = [
            'name' => 'Test Funnel',
            'description' => 'A test funnel for unit testing',
            'client_id' => 1,
            'status' => 'draft',
            'conversion_window_days' => 30,
            'attribution_model' => 'last_click'
        ];

        $funnel = new \FP\DigitalMarketing\Models\Funnel( $funnel_data );

        $this->assertEquals( 'Test Funnel', $funnel->get_name() );
        $this->assertEquals( 'A test funnel for unit testing', $funnel->get_description() );
        $this->assertEquals( 1, $funnel->get_client_id() );
        $this->assertEquals( 'draft', $funnel->get_status() );
        $this->assertEquals( 30, $funnel->get_conversion_window_days() );
        $this->assertEquals( 'last_click', $funnel->get_attribution_model() );
    }

    /**
     * Test funnel array conversion
     *
     * @return void
     */
    public function test_funnel_to_array(): void {
        $funnel_data = [
            'name' => 'Test Funnel',
            'client_id' => 1,
            'status' => 'active'
        ];

        $funnel = new \FP\DigitalMarketing\Models\Funnel( $funnel_data );
        $array = $funnel->to_array();

        $this->assertIsArray( $array );
        $this->assertEquals( 'Test Funnel', $array['name'] );
        $this->assertEquals( 1, $array['client_id'] );
        $this->assertEquals( 'active', $array['status'] );
    }

    /**
     * Test attribution models
     *
     * @return void
     */
    public function test_attribution_models(): void {
        $models = \FP\DigitalMarketing\Models\Funnel::get_attribution_models();

        $this->assertIsArray( $models );
        $this->assertArrayHasKey( 'first_click', $models );
        $this->assertArrayHasKey( 'last_click', $models );
        $this->assertArrayHasKey( 'linear', $models );
        $this->assertArrayHasKey( 'time_decay', $models );
    }

    /**
     * Test funnel statuses
     *
     * @return void
     */
    public function test_funnel_statuses(): void {
        $statuses = \FP\DigitalMarketing\Models\Funnel::get_statuses();

        $this->assertIsArray( $statuses );
        $this->assertArrayHasKey( 'draft', $statuses );
        $this->assertArrayHasKey( 'active', $statuses );
        $this->assertArrayHasKey( 'inactive', $statuses );
    }
}

/**
 * Customer Journey functionality test class
 */
class CustomerJourneyTest extends TestCase {

    /**
     * Test CustomerJourney creation
     *
     * @return void
     */
    public function test_customer_journey_creation(): void {
        $journey = new \FP\DigitalMarketing\Models\CustomerJourney( 'test_session_123', 1, 'test_user_456' );

        $this->assertEquals( 'test_session_123', $journey->get_session_id() );
        $this->assertEquals( 1, $journey->get_client_id() );
        $this->assertEquals( 'test_user_456', $journey->get_user_id() );
    }

    /**
     * Test journey to array conversion
     *
     * @return void
     */
    public function test_journey_to_array(): void {
        $journey = new \FP\DigitalMarketing\Models\CustomerJourney( 'test_session_123', 1 );
        $array = $journey->to_array();

        $this->assertIsArray( $array );
        $this->assertEquals( 'test_session_123', $array['session_id'] );
        $this->assertEquals( 1, $array['client_id'] );
        $this->assertArrayHasKey( 'events', $array );
        $this->assertArrayHasKey( 'statistics', $array );
        $this->assertArrayHasKey( 'journey_path', $array );
        $this->assertArrayHasKey( 'touchpoints', $array );
    }

    /**
     * Test journey statistics
     *
     * @return void
     */
    public function test_journey_statistics(): void {
        $journey = new \FP\DigitalMarketing\Models\CustomerJourney( 'test_session_123', 1 );
        $stats = $journey->get_statistics();

        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total_events', $stats );
        $this->assertArrayHasKey( 'unique_pages', $stats );
        $this->assertArrayHasKey( 'total_value', $stats );
        $this->assertArrayHasKey( 'pageviews', $stats );
        $this->assertArrayHasKey( 'conversions', $stats );
        $this->assertArrayHasKey( 'duration_seconds', $stats );
        $this->assertArrayHasKey( 'bounce', $stats );
    }

    /**
     * Test behavior segments
     *
     * @return void
     */
    public function test_behavior_segments(): void {
        $journey = new \FP\DigitalMarketing\Models\CustomerJourney( 'test_session_123', 1 );
        $segments = $journey->get_behavior_segments();

        $this->assertIsArray( $segments );
        $this->assertContains( 'low_engagement', $segments );
        $this->assertContains( 'no_value', $segments );
        $this->assertContains( 'non_converter', $segments );
        $this->assertContains( 'short_session', $segments );
    }
}