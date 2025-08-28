<?php
/**
 * Test PHP file for FP Digital Marketing Suite
 *
 * This file tests our coding standards and static analysis setup.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare( strict_types=1 );

/**
 * Simple test class to verify our tooling works.
 */
class Test_Setup {
	/**
	 * Test method to check coding standards.
	 *
	 * @return string
	 */
	public function get_test_message(): string {
		return 'Hello, FP Digital Marketing Suite!';
	}
}

// Create an instance and test it.
$test = new Test_Setup();
echo $test->get_test_message();