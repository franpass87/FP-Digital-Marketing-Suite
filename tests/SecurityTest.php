<?php
/**
 * Security Helper Test
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\Security;

/**
 * Test class for Security helper
 */
class SecurityTest extends TestCase {

	/**
	 * Test encryption and decryption
	 */
	public function test_encryption_decryption(): void {
		$original_data = 'test_api_key_12345';
		
		// Test encryption
		$encrypted = Security::encrypt_sensitive_data( $original_data );
		$this->assertNotEmpty( $encrypted );
		$this->assertNotEquals( $original_data, $encrypted );
		
		// Test decryption
		$decrypted = Security::decrypt_sensitive_data( $encrypted );
		$this->assertEquals( $original_data, $decrypted );
	}

	/**
	 * Test empty data handling
	 */
	public function test_empty_data_handling(): void {
		$encrypted_empty = Security::encrypt_sensitive_data( '' );
		$this->assertEquals( '', $encrypted_empty );
		
		$decrypted_empty = Security::decrypt_sensitive_data( '' );
		$this->assertEquals( '', $decrypted_empty );
	}

	/**
	 * Test security audit structure
	 */
	public function test_security_audit_structure(): void {
		$audit_results = Security::run_security_audit();
		
		$this->assertIsArray( $audit_results );
		$this->assertArrayHasKey( 'timestamp', $audit_results );
		$this->assertArrayHasKey( 'plugin_version', $audit_results );
		$this->assertArrayHasKey( 'checks', $audit_results );
		$this->assertArrayHasKey( 'overall_score', $audit_results );
		$this->assertArrayHasKey( 'critical_issues', $audit_results );
		$this->assertArrayHasKey( 'warnings', $audit_results );
		
		// Check that audit includes required checks
		$this->assertArrayHasKey( 'wp_version', $audit_results['checks'] );
		$this->assertArrayHasKey( 'php_version', $audit_results['checks'] );
		$this->assertArrayHasKey( 'encryption_support', $audit_results['checks'] );
	}

	/**
	 * Test security logs functionality
	 */
	public function test_security_logs(): void {
		// Clear existing logs
		Security::clear_security_logs();
		
		// Get logs (should be empty)
		$logs = Security::get_security_logs();
		$this->assertIsArray( $logs );
		
		// Note: We can't easily test log creation without mocking WordPress functions
		// This test mainly ensures the methods exist and return expected types
	}
}