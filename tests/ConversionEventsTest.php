<?php
/**
 * Tests for Conversion Events functionality
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Models\ConversionEvent;
use FP\DigitalMarketing\Database\ConversionEventsTable;
use FP\DigitalMarketing\Helpers\ConversionEventRegistry;
use FP\DigitalMarketing\Helpers\ConversionEventManager;

/**
 * Test class for Conversion Events
 */
class ConversionEventsTest extends TestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create test table
		ConversionEventsTable::create_table();
	}

	/**
	 * Test event registry functionality
	 */
	public function test_event_registry(): void {
		// Test getting event types
		$event_types = ConversionEventRegistry::get_event_types();
		$this->assertIsArray( $event_types );
		$this->assertNotEmpty( $event_types );
		
		// Test specific event type exists
		$this->assertArrayHasKey( ConversionEventRegistry::EVENT_SIGNUP, $event_types );
		$this->assertArrayHasKey( ConversionEventRegistry::EVENT_PURCHASE, $event_types );
		
		// Test event type definition structure
		$signup_def = $event_types[ ConversionEventRegistry::EVENT_SIGNUP ];
		$this->assertArrayHasKey( 'name', $signup_def );
		$this->assertArrayHasKey( 'description', $signup_def );
		$this->assertArrayHasKey( 'category', $signup_def );
		$this->assertArrayHasKey( 'default_value', $signup_def );
		$this->assertArrayHasKey( 'tracked_attributes', $signup_def );
	}

	/**
	 * Test event type normalization
	 */
	public function test_event_type_normalization(): void {
		// Test GA4 event mapping
		$normalized = ConversionEventRegistry::normalize_event_type( 'google_analytics_4', 'sign_up' );
		$this->assertEquals( ConversionEventRegistry::EVENT_SIGNUP, $normalized );
		
		$normalized = ConversionEventRegistry::normalize_event_type( 'google_analytics_4', 'purchase' );
		$this->assertEquals( ConversionEventRegistry::EVENT_PURCHASE, $normalized );
		
		// Test Facebook Ads event mapping
		$normalized = ConversionEventRegistry::normalize_event_type( 'facebook_ads', 'CompleteRegistration' );
		$this->assertEquals( ConversionEventRegistry::EVENT_SIGNUP, $normalized );
		
		// Test unknown event type (should return original)
		$normalized = ConversionEventRegistry::normalize_event_type( 'unknown_source', 'unknown_event' );
		$this->assertEquals( 'unknown_event', $normalized );
	}

	/**
	 * Test creating event from source data
	 */
	public function test_create_event_from_source(): void {
		$source_data = [
			'event_type' => 'sign_up',
			'value' => 25.00,
			'currency' => 'EUR',
			'user_id' => 'user123',
			'utm_source' => 'google',
			'utm_medium' => 'cpc',
			'utm_campaign' => 'summer_sale',
			'email' => 'test@example.com',
			'registration_source' => 'homepage',
		];

		$event_data = ConversionEventRegistry::create_event_from_source( 'google_analytics_4', $source_data, 123 );

		$this->assertEquals( ConversionEventRegistry::EVENT_SIGNUP, $event_data['event_type'] );
		$this->assertEquals( 123, $event_data['client_id'] );
		$this->assertEquals( 'google_analytics_4', $event_data['source'] );
		$this->assertEquals( 25.00, $event_data['event_value'] );
		$this->assertEquals( 'EUR', $event_data['currency'] );
		$this->assertEquals( 'user123', $event_data['user_id'] );
		$this->assertEquals( 'google', $event_data['utm_source'] );
		$this->assertEquals( 'cpc', $event_data['utm_medium'] );
		$this->assertEquals( 'summer_sale', $event_data['utm_campaign'] );

		// Check event attributes
		$this->assertIsArray( $event_data['event_attributes'] );
		$this->assertEquals( 'test@example.com', $event_data['event_attributes']['email'] );
		$this->assertEquals( 'homepage', $event_data['event_attributes']['registration_source'] );
	}

	/**
	 * Ensure Unix timestamps are converted to MySQL datetime values.
	 */
	public function test_create_event_from_source_with_unix_timestamp(): void {
		$timestamp = 1704067200; // 2024-01-01 00:00:00 UTC.

		$source_data = [
			'event_type' => 'sign_up',
			'timestamp' => $timestamp,
		];

		$event_data = ConversionEventRegistry::create_event_from_source( 'google_analytics_4', $source_data, 456 );

		$this->assertEquals( gmdate( 'Y-m-d H:i:s', $timestamp ), $event_data['created_at'] );
	}

	/**
	 * Ensure MySQL datetime strings are sanitized before assignment.
	 */
	public function test_create_event_from_source_sanitizes_mysql_datetime(): void {
		$timestamp = ' 2024-05-01 12:34:56 ';

		$source_data = [
			'event_type' => 'sign_up',
			'timestamp' => $timestamp,
		];

		$event_data = ConversionEventRegistry::create_event_from_source( 'google_analytics_4', $source_data, 789 );

		$this->assertEquals( '2024-05-01 12:34:56', $event_data['created_at'] );
	}

	/**
	 * Ensure non-MySQL datetime strings are parsed when possible.
	 */
	public function test_create_event_from_source_parses_non_mysql_datetime_string(): void {
		$timestamp = '2024-05-01T12:34:56Z';

		$source_data = [
			'event_type' => 'sign_up',
			'timestamp' => $timestamp,
		];

		$event_data = ConversionEventRegistry::create_event_from_source( 'google_analytics_4', $source_data, 321 );

		$this->assertEquals( '2024-05-01 12:34:56', $event_data['created_at'] );
	}

	/**
	 * Ensure invalid timestamp strings fall back to the current time.
	 */
	public function test_create_event_from_source_with_invalid_timestamp_falls_back_to_current_time(): void {
		$before = current_time( 'mysql' );

		$source_data = [
			'event_type' => 'sign_up',
			'timestamp' => 'not-a-date',
		];

		$event_data = ConversionEventRegistry::create_event_from_source( 'google_analytics_4', $source_data, 654 );

		$after = current_time( 'mysql' );

		$this->assertMatchesRegularExpression( '/^\\d{4}-\\d{2}-\\d{2} \d{2}:\d{2}:\d{2}$/', $event_data['created_at'] );

		$actual_timestamp = strtotime( $event_data['created_at'] );
		$before_timestamp = strtotime( $before );
		$after_timestamp = strtotime( $after );

		$this->assertNotFalse( $actual_timestamp );
		$this->assertNotFalse( $before_timestamp );
		$this->assertNotFalse( $after_timestamp );

		$this->assertGreaterThanOrEqual( $before_timestamp - 2, $actual_timestamp );
		$this->assertLessThanOrEqual( $after_timestamp + 2, $actual_timestamp );
	}

	/**
	 * Test ConversionEvent model
	 */
	public function test_conversion_event_model(): void {
		$event_data = [
			'event_id' => 'test_event_123',
			'event_type' => ConversionEventRegistry::EVENT_PURCHASE,
			'event_name' => 'Test Purchase',
			'client_id' => 456,
			'source' => 'woocommerce',
			'event_value' => 99.99,
			'currency' => 'EUR',
			'user_id' => 'user456',
			'utm_campaign' => 'black_friday',
			'event_attributes' => [ 'product_id' => 'product123', 'quantity' => 2 ],
		];

		$event = new ConversionEvent( $event_data );

		// Test getters
		$this->assertEquals( 'test_event_123', $event->get_event_id() );
		$this->assertEquals( ConversionEventRegistry::EVENT_PURCHASE, $event->get_event_type() );
		$this->assertEquals( 'Test Purchase', $event->get_event_name() );
		$this->assertEquals( 456, $event->get_client_id() );
		$this->assertEquals( 'woocommerce', $event->get_source() );
		$this->assertEquals( 99.99, $event->get_event_value() );
		$this->assertEquals( 'EUR', $event->get_currency() );
		$this->assertEquals( 'user456', $event->get_user_id() );
		$this->assertEquals( 'black_friday', $event->get_utm_campaign() );
		$this->assertFalse( $event->is_duplicate() );

		// Test event attributes
		$attributes = $event->get_event_attributes();
		$this->assertEquals( 'product123', $attributes['product_id'] );
		$this->assertEquals( 2, $attributes['quantity'] );

		// Test specific attribute getter
		$this->assertEquals( 'product123', $event->get_attribute( 'product_id' ) );
		$this->assertEquals( 'default_value', $event->get_attribute( 'non_existent', 'default_value' ) );

		// Test setters
		$event->set_event_value( 199.99 );
		$this->assertEquals( 199.99, $event->get_event_value() );

		$event->set_attribute( 'new_attribute', 'new_value' );
		$this->assertEquals( 'new_value', $event->get_attribute( 'new_attribute' ) );

		// Test to_array
		$array_data = $event->to_array();
		$this->assertIsArray( $array_data );
		$this->assertEquals( 'test_event_123', $array_data['event_id'] );
		$this->assertEquals( 199.99, $array_data['event_value'] );
	}

	/**
	 * Test database operations
	 */
	public function test_database_operations(): void {
		// Test table creation
		$this->assertTrue( ConversionEventsTable::table_exists() );

		// Test event insertion
		$event_data = [
			'event_id' => 'db_test_event_1',
			'event_type' => ConversionEventRegistry::EVENT_LEAD_SUBMIT,
			'event_name' => 'Contact Form Submission',
			'client_id' => 789,
			'source' => 'contact_form_7',
			'event_value' => 0.0,
			'utm_source' => 'linkedin',
			'utm_medium' => 'social',
			'utm_campaign' => 'lead_gen_2024',
			'event_attributes' => [ 'form_name' => 'contact_us', 'lead_quality' => 'high' ],
		];

		$insert_id = ConversionEventsTable::insert_event( $event_data );
		$this->assertIsInt( $insert_id );
		$this->assertGreaterThan( 0, $insert_id );

		// Test event retrieval
		$events = ConversionEventsTable::get_events( [ 'client_id' => 789 ], 10, 0 );
		$this->assertIsArray( $events );
		$this->assertCount( 1, $events );

		$retrieved_event = $events[0];
		$this->assertEquals( 'db_test_event_1', $retrieved_event['event_id'] );
		$this->assertEquals( ConversionEventRegistry::EVENT_LEAD_SUBMIT, $retrieved_event['event_type'] );
		$this->assertEquals( 789, $retrieved_event['client_id'] );

		// Test event count
		$count = ConversionEventsTable::get_events_count( [ 'client_id' => 789 ] );
		$this->assertEquals( 1, $count );

		// Test event update
		$updated = ConversionEventsTable::update_event( $insert_id, [ 'event_value' => 50.0 ] );
		$this->assertTrue( $updated );

		// Test mark as duplicate
		$marked = ConversionEventsTable::mark_as_duplicate( $insert_id );
		$this->assertTrue( $marked );

		// Verify duplicate marking
		$events = ConversionEventsTable::get_events( [ 'client_id' => 789 ], 10, 0 );
		$this->assertEquals( 1, $events[0]['is_duplicate'] );

		// Test deletion
		$deleted = ConversionEventsTable::delete_event( $insert_id );
		$this->assertTrue( $deleted );

		// Verify deletion
		$count = ConversionEventsTable::get_events_count( [ 'client_id' => 789 ] );
		$this->assertEquals( 0, $count );
	}

	/**
	 * Test event ingestion and deduplication
	 */
        public function test_event_ingestion(): void {
                // Test event ingestion
                $source_data = [
                        'event_type' => 'purchase',
                        'value' => 150.00,
			'currency' => 'EUR',
			'transaction_id' => 'txn_123',
			'user_id' => 'user789',
			'utm_source' => 'facebook',
			'utm_medium' => 'ads',
			'utm_campaign' => 'holiday_sale',
			'product_id' => 'prod_456',
			'quantity' => 3,
		];

		$event = ConversionEventManager::ingest_event( 'facebook_ads', $source_data, 999 );

		$this->assertInstanceOf( ConversionEvent::class, $event );
		$this->assertEquals( ConversionEventRegistry::EVENT_PURCHASE, $event->get_event_type() );
		$this->assertEquals( 999, $event->get_client_id() );
		$this->assertEquals( 'facebook_ads', $event->get_source() );
		$this->assertEquals( 150.00, $event->get_event_value() );
		$this->assertFalse( $event->is_duplicate() );

		// Test duplicate detection - ingest same event again
		$duplicate_event = ConversionEventManager::ingest_event( 'facebook_ads', $source_data, 999 );
                $this->assertInstanceOf( ConversionEvent::class, $duplicate_event );
                // One of the events should be marked as duplicate
                $this->assertTrue( $duplicate_event->is_duplicate() || $event->is_duplicate() );
        }

        /**
         * Ensure duplicate detection works based on source_event_id even with distant timestamps.
         */
        public function test_duplicate_detection_by_source_event_id(): void {
                $client_id = 555;
                $source = 'google_analytics_4';
                $shared_source_event_id = 'shared-source-event-123';

                $base_source_data = [
                        'event_type' => 'purchase',
                        'value' => 129.99,
                        'currency' => 'EUR',
                        'source_event_id' => $shared_source_event_id,
                ];

                $first_event = ConversionEventManager::ingest_event(
                        $source,
                        array_merge(
                                $base_source_data,
                                [
                                        'timestamp' => '2024-01-01 10:00:00',
                                ]
                        ),
                        $client_id
                );

                $this->assertInstanceOf( ConversionEvent::class, $first_event );
                $this->assertFalse( $first_event->is_duplicate() );

                $second_event = ConversionEventManager::ingest_event(
                        $source,
                        array_merge(
                                $base_source_data,
                                [
                                        'timestamp' => '2024-03-01 10:00:00',
                                ]
                        ),
                        $client_id
                );

                $this->assertInstanceOf( ConversionEvent::class, $second_event );
                $this->assertTrue( $second_event->is_duplicate() );

                $stored_events = ConversionEventsTable::get_events(
                        [
                                'client_id' => $client_id,
                                'source' => $source,
                        ],
                        10,
                        0
                );

                $this->assertCount( 2, $stored_events );
                $this->assertEquals( $shared_source_event_id, $stored_events[0]['source_event_id'] );
                $this->assertEquals( 1, (int) $stored_events[0]['is_duplicate'] );
                $this->assertEquals( $shared_source_event_id, $stored_events[1]['source_event_id'] );
                $this->assertEquals( 0, (int) $stored_events[1]['is_duplicate'] );
        }

        /**
         * Ensure events with different user IDs are not marked as duplicates.
         */
        public function test_events_with_different_user_ids_are_not_marked_as_duplicates(): void {
                $client_id = 654;
		$source = 'facebook_ads';

		$base_event = [
			'event_type' => 'purchase',
			'value' => 199.99,
			'currency' => 'EUR',
			'ip_address' => '198.51.100.10',
			'timestamp' => '2024-01-05 12:00:00',
		];

		$first_event = ConversionEventManager::ingest_event(
			$source,
			array_merge(
				$base_event,
				[
					'user_id' => 'duplicate-user-a',
				]
			),
			$client_id
		);

		$this->assertInstanceOf( ConversionEvent::class, $first_event );
		$this->assertFalse( $first_event->is_duplicate() );

		$second_event = ConversionEventManager::ingest_event(
			$source,
			array_merge(
				$base_event,
				[
					'user_id' => 'duplicate-user-b',
					'timestamp' => '2024-01-05 12:00:30',
				]
			),
			$client_id
		);

		$this->assertInstanceOf( ConversionEvent::class, $second_event );
		$this->assertFalse( $second_event->is_duplicate() );

		$user_a_events = ConversionEventsTable::get_events(
			[
				'client_id' => $client_id,
				'user_id' => 'duplicate-user-a',
			],
			10,
			0
		);
		$this->assertCount( 1, $user_a_events );
		$this->assertSame( 'duplicate-user-a', $user_a_events[0]['user_id'] );

		$user_b_events = ConversionEventsTable::get_events(
			[
				'client_id' => $client_id,
				'user_id' => 'duplicate-user-b',
			],
			10,
			0
		);
		$this->assertCount( 1, $user_b_events );
		$this->assertSame( 'duplicate-user-b', $user_b_events[0]['user_id'] );
		$this->assertEquals(
			1,
			ConversionEventsTable::get_events_count(
				[
					'client_id' => $client_id,
					'user_id' => 'duplicate-user-b',
				]
			)
		);
	}

	/**
	 * Test bulk event ingestion
	 */
	public function test_bulk_ingestion(): void {
		$events_data = [
			[
				'event_type' => 'sign_up',
				'value' => 0.0,
				'user_id' => 'user001',
				'utm_campaign' => 'spring_signup',
			],
			[
				'event_type' => 'sign_up',
				'value' => 0.0,
				'user_id' => 'user002',
				'utm_campaign' => 'spring_signup',
			],
			[
				'event_type' => 'purchase',
				'value' => 75.00,
				'user_id' => 'user001',
				'utm_campaign' => 'spring_signup',
			],
		];

		$results = ConversionEventManager::bulk_ingest_events( 'google_analytics_4', $events_data, 888 );

		$this->assertEquals( 3, $results['total'] );
		$this->assertGreaterThanOrEqual( 2, $results['success'] );
		$this->assertEquals( 0, $results['failed'] );
	}

	/**
	 * Test event querying
	 */
	public function test_event_querying(): void {
		// Insert test events
		$test_events = [
			[
				'event_id' => 'query_test_1',
				'event_type' => ConversionEventRegistry::EVENT_SIGNUP,
				'event_name' => 'User Signup',
				'client_id' => 111,
				'source' => 'website',
				'event_value' => 0.0,
				'utm_campaign' => 'test_campaign',
			],
			[
				'event_id' => 'query_test_2',
				'event_type' => ConversionEventRegistry::EVENT_PURCHASE,
				'event_name' => 'Product Purchase',
				'client_id' => 111,
				'source' => 'website',
				'event_value' => 99.99,
				'utm_campaign' => 'test_campaign',
			],
		];

		foreach ( $test_events as $event_data ) {
			ConversionEventsTable::insert_event( $event_data );
		}

		// Test basic query
		$results = ConversionEventManager::query_events( [ 'client_id' => 111 ] );
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'events', $results );
		$this->assertArrayHasKey( 'total_count', $results );
		$this->assertCount( 2, $results['events'] );
		$this->assertEquals( 2, $results['total_count'] );

		// Test filtered query
		$results = ConversionEventManager::query_events( [
			'client_id' => 111,
			'event_type' => ConversionEventRegistry::EVENT_PURCHASE,
		] );
		$this->assertCount( 1, $results['events'] );
		$this->assertEquals( ConversionEventRegistry::EVENT_PURCHASE, $results['events'][0]['event_type'] );

		// Test query with summary
		$results = ConversionEventManager::query_events( 
			[ 'client_id' => 111 ],
			[ 'include_summary' => true ]
		);
		$this->assertArrayHasKey( 'summary', $results );
		$this->assertEquals( 2, $results['summary']['total_events'] );
		$this->assertEquals( 99.99, $results['summary']['total_value'] );
	}

	/**
	 * Test conversion funnel analysis
	 */
	public function test_conversion_funnel(): void {
		// Insert funnel test events
		$funnel_events = [
			[ 'event_type' => ConversionEventRegistry::EVENT_SIGNUP, 'client_id' => 222 ],
			[ 'event_type' => ConversionEventRegistry::EVENT_SIGNUP, 'client_id' => 222 ],
			[ 'event_type' => ConversionEventRegistry::EVENT_ADD_TO_CART, 'client_id' => 222 ],
			[ 'event_type' => ConversionEventRegistry::EVENT_PURCHASE, 'client_id' => 222 ],
		];

		foreach ( $funnel_events as $i => $event_data ) {
			$event_data['event_id'] = 'funnel_test_' . $i;
			$event_data['event_name'] = 'Funnel Test Event';
			$event_data['source'] = 'test';
			$event_data['event_value'] = 0.0;
			ConversionEventsTable::insert_event( $event_data );
		}

		$funnel_steps = [
			ConversionEventRegistry::EVENT_SIGNUP,
			ConversionEventRegistry::EVENT_ADD_TO_CART,
			ConversionEventRegistry::EVENT_PURCHASE,
		];

		$funnel_data = ConversionEventManager::get_conversion_funnel( 222, $funnel_steps );

		$this->assertIsArray( $funnel_data );
		$this->assertCount( 3, $funnel_data );

		// Check funnel step data
		$this->assertEquals( 1, $funnel_data[0]['step'] );
		$this->assertEquals( ConversionEventRegistry::EVENT_SIGNUP, $funnel_data[0]['event_type'] );
		$this->assertEquals( 2, $funnel_data[0]['count'] );
		$this->assertEquals( 100.0, $funnel_data[0]['conversion_rate'] );

		$this->assertEquals( 2, $funnel_data[1]['step'] );
		$this->assertEquals( ConversionEventRegistry::EVENT_ADD_TO_CART, $funnel_data[1]['event_type'] );
		$this->assertEquals( 1, $funnel_data[1]['count'] );
		$this->assertEquals( 50.0, $funnel_data[1]['conversion_rate'] );

		$this->assertEquals( 3, $funnel_data[2]['step'] );
		$this->assertEquals( ConversionEventRegistry::EVENT_PURCHASE, $funnel_data[2]['event_type'] );
		$this->assertEquals( 1, $funnel_data[2]['count'] );
		$this->assertEquals( 50.0, $funnel_data[2]['conversion_rate'] );
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up test data
		ConversionEventsTable::drop_table();
		parent::tearDown();
	}
}