<?php
/**
 * Social Sentiment Table Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Database\SocialSentimentTable;

/**
 * Simple stub for the WordPress database layer used in social sentiment tests.
 */
class SocialSentimentWPDBStub {
		/**
		 * WordPress table prefix.
		 *
		 * @var string
		 */
		public $prefix = 'wp_';

		/**
		 * Captured query passed to prepare.
		 *
		 * @var string
		 */
		public $prepared_query = '';

		/**
		 * Captured query passed to get_results.
		 *
		 * @var string
		 */
		public $last_query = '';

		/**
		 * Captured parameters passed to prepare.
		 *
		 * @var array
		 */
		public $prepare_args = [];

		/**
		 * Stubbed results returned from get_results.
		 *
		 * @var array
		 */
		public $results = [];

		/**
		 * Capture prepared query and arguments.
		 *
		 * @param string $query SQL query.
		 * @param mixed  ...$args Query arguments.
		 * @return string
		 */
	public function prepare( string $query, ...$args ): string {
			$this->prepared_query = $query;
			$this->prepare_args   = $args;

			return $query;
	}

		/**
		 * Capture the executed query and return stubbed results.
		 *
		 * @param string $query SQL query.
		 * @param string $output Output format (unused).
		 * @return array
		 */
	public function get_results( string $query, string $output = 'ARRAY_A' ): array {
			$this->last_query = $query;

			return $this->results;
	}
}

/**
 * Tests for SocialSentimentTable ordering behaviour.
 */
class SocialSentimentTableTest extends TestCase {
		/**
		 * Previous $wpdb instance.
		 *
		 * @var mixed
		 */
		private $previous_wpdb;

		/**
		 * Stubbed wpdb instance.
		 *
		 * @var SocialSentimentWPDBStub
		 */
	private SocialSentimentWPDBStub $wpdb_stub;

		/**
		 * Set up a stubbed $wpdb before each test.
		 */
	protected function setUp(): void {
			parent::setUp();

			global $wpdb;

			$this->previous_wpdb = $wpdb ?? null;
			$this->wpdb_stub     = new SocialSentimentWPDBStub();

			$wpdb = $this->wpdb_stub;

			$this->wpdb_stub->results = [
				[
					'key_issues'       => wp_json_encode( [] ),
					'positive_aspects' => wp_json_encode( [] ),
				],
			];
	}

		/**
		 * Restore the previous $wpdb after each test.
		 */
	protected function tearDown(): void {
			global $wpdb;

		if ( null !== $this->previous_wpdb ) {
				$wpdb = $this->previous_wpdb;
		} else {
				unset( $GLOBALS['wpdb'] );
		}

			parent::tearDown();
	}

		/**
		 * Ensure valid ordering arguments use the sanitized values in the query.
		 */
	public function test_get_reviews_valid_ordering_uses_whitelist(): void {
			SocialSentimentTable::get_reviews(
				123,
				[
					'order_by' => 'sentiment_score',
					'order'    => 'asc',
				]
			);

			$this->assertStringContainsString( 'ORDER BY sentiment_score ASC', $this->wpdb_stub->prepared_query );
			$this->assertStringContainsString( 'ORDER BY sentiment_score ASC', $this->wpdb_stub->last_query );
	}

		/**
		 * Ensure invalid ordering arguments fall back to default ordering.
		 */
	public function test_get_reviews_invalid_ordering_falls_back_to_defaults(): void {
			SocialSentimentTable::get_reviews(
				456,
				[
					'order_by' => '1=1; DROP TABLE',
					'order'    => 'invalid',
				]
			);

			$this->assertStringContainsString( 'ORDER BY review_date DESC', $this->wpdb_stub->prepared_query );
			$this->assertStringContainsString( 'ORDER BY review_date DESC', $this->wpdb_stub->last_query );
	}
}
