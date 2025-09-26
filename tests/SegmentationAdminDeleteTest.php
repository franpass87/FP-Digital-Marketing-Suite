<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Admin\SegmentationAdmin;

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		$base  = $url ?: 'admin.php';
		$query = http_build_query( $args );
		return $base . ( strpos( $base, '?' ) === false ? '?' : '&' ) . $query;
	}
}

if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $keys, $url = '' ) {
		return $url ?: 'admin.php';
	}
}

if ( ! function_exists( 'wp_redirect' ) ) {
	function wp_redirect( $location ) {
		throw new Exception( $location );
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		return $nonce === 'valid' && $action === 'fp_delete_segment';
	}
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

class WPDB_SegmentationDeleteMock extends WPDB_Mock {
	public array $results = [];
	public function get_results( $query, $output = ARRAY_A ) {
		return $this->results;
	}
	public function delete( $table, $where ) {
		return 1;
	}
	public function prepare( $query, ...$args ) {
		return $query;
	}
}

class SegmentationAdminDeleteTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$_GET = [];
	}

	public function test_delete_without_segment_id_redirects_error(): void {
		$admin                 = new SegmentationAdmin();
		$_GET['segment_nonce'] = 'valid';
		try {
			$this->invoke_delete( $admin );
			$this->fail( 'Expected redirect' );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'message=error', $e->getMessage() );
		}
	}

	public function test_delete_with_segment_id_success(): void {
		global $wpdb;
		$wpdb          = new WPDB_SegmentationDeleteMock();
		$wpdb->results = [
			[
				'id'                => 5,
				'name'              => 'Test',
				'description'       => '',
				'client_id'         => 1,
				'rules'             => '[]',
				'is_active'         => 1,
				'last_evaluated_at' => null,
				'member_count'      => 0,
				'created_at'        => '2024-01-01 00:00:00',
				'updated_at'        => '2024-01-01 00:00:00',
			],
		];

		$_GET['segment_id']    = 5;
		$_GET['segment_nonce'] = 'valid';
		$admin                 = new SegmentationAdmin();

		try {
			$this->invoke_delete( $admin );
			$this->fail( 'Expected redirect' );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'message=deleted', $e->getMessage() );
		}
	}

	private function invoke_delete( SegmentationAdmin $admin ): void {
		$ref    = new ReflectionClass( $admin );
		$method = $ref->getMethod( 'handle_delete_segment' );
		$method->setAccessible( true );
		$method->invoke( $admin );
	}
}
