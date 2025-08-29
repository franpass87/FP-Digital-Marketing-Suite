<?php
/**
 * UTM Generator Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\UTMGenerator;

/**
 * Test class for UTM Generator functionality
 */
class UTMGeneratorTest extends TestCase {

	/**
	 * Test URL generation with valid parameters
	 */
	public function test_generate_utm_url_valid_parameters(): void {
		$base_url = 'https://example.com/landing-page';
		$utm_params = [
			'source'   => 'google',
			'medium'   => 'cpc',
			'campaign' => 'summer_sale',
			'term'     => 'marketing_software',
			'content'  => 'ad_variant_a',
		];

		$result = UTMGenerator::generate_utm_url( $base_url, $utm_params );

		$this->assertStringContainsString( 'utm_source=google', $result );
		$this->assertStringContainsString( 'utm_medium=cpc', $result );
		$this->assertStringContainsString( 'utm_campaign=summer_sale', $result );
		$this->assertStringContainsString( 'utm_term=marketing_software', $result );
		$this->assertStringContainsString( 'utm_content=ad_variant_a', $result );
		$this->assertStringStartsWith( 'https://example.com/landing-page?', $result );
	}

	/**
	 * Test URL generation with only required parameters
	 */
	public function test_generate_utm_url_required_only(): void {
		$base_url = 'https://example.com';
		$utm_params = [
			'source'   => 'facebook',
			'medium'   => 'social',
			'campaign' => 'brand_awareness',
		];

		$result = UTMGenerator::generate_utm_url( $base_url, $utm_params );

		$this->assertStringContainsString( 'utm_source=facebook', $result );
		$this->assertStringContainsString( 'utm_medium=social', $result );
		$this->assertStringContainsString( 'utm_campaign=brand_awareness', $result );
		$this->assertStringNotContainsString( 'utm_term=', $result );
		$this->assertStringNotContainsString( 'utm_content=', $result );
	}

	/**
	 * Test URL generation with missing required parameters
	 */
	public function test_generate_utm_url_missing_required(): void {
		$base_url = 'https://example.com';
		$utm_params = [
			'source' => 'google',
			'medium' => 'cpc',
			// Missing campaign
		];

		$result = UTMGenerator::generate_utm_url( $base_url, $utm_params );

		$this->assertEmpty( $result );
	}

	/**
	 * Test URL generation with invalid base URL
	 */
	public function test_generate_utm_url_invalid_base_url(): void {
		$base_url = '';
		$utm_params = [
			'source'   => 'google',
			'medium'   => 'cpc',
			'campaign' => 'test',
		];

		$result = UTMGenerator::generate_utm_url( $base_url, $utm_params );

		$this->assertEmpty( $result );
	}

	/**
	 * Test URL generation with existing query parameters
	 */
	public function test_generate_utm_url_with_existing_params(): void {
		$base_url = 'https://example.com/page?existing=param&another=value';
		$utm_params = [
			'source'   => 'newsletter',
			'medium'   => 'email',
			'campaign' => 'monthly',
		];

		$result = UTMGenerator::generate_utm_url( $base_url, $utm_params );

		$this->assertStringContainsString( 'existing=param', $result );
		$this->assertStringContainsString( 'another=value', $result );
		$this->assertStringContainsString( 'utm_source=newsletter', $result );
		$this->assertStringContainsString( 'utm_medium=email', $result );
		$this->assertStringContainsString( 'utm_campaign=monthly', $result );
	}

	/**
	 * Test URL generation removes existing UTM parameters
	 */
	public function test_generate_utm_url_removes_existing_utm(): void {
		$base_url = 'https://example.com?utm_source=old&utm_medium=old&other=keep';
		$utm_params = [
			'source'   => 'new',
			'medium'   => 'new',
			'campaign' => 'new',
		];

		$result = UTMGenerator::generate_utm_url( $base_url, $utm_params );

		$this->assertStringNotContainsString( 'utm_source=old', $result );
		$this->assertStringNotContainsString( 'utm_medium=old', $result );
		$this->assertStringContainsString( 'utm_source=new', $result );
		$this->assertStringContainsString( 'utm_medium=new', $result );
		$this->assertStringContainsString( 'other=keep', $result );
	}

	/**
	 * Test UTM parameter extraction from URL
	 */
	public function test_extract_utm_params(): void {
		$url = 'https://example.com?utm_source=google&utm_medium=cpc&utm_campaign=test&utm_term=keyword&utm_content=ad1&other=param';

		$result = UTMGenerator::extract_utm_params( $url );

		$expected = [
			'source'   => 'google',
			'medium'   => 'cpc',
			'campaign' => 'test',
			'term'     => 'keyword',
			'content'  => 'ad1',
		];

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test UTM parameter extraction from URL without UTM params
	 */
	public function test_extract_utm_params_no_utm(): void {
		$url = 'https://example.com?other=param&another=value';

		$result = UTMGenerator::extract_utm_params( $url );

		$this->assertEmpty( $result );
	}

	/**
	 * Test UTM parameter extraction from URL without query string
	 */
	public function test_extract_utm_params_no_query(): void {
		$url = 'https://example.com/page';

		$result = UTMGenerator::extract_utm_params( $url );

		$this->assertEmpty( $result );
	}

	/**
	 * Test get presets functionality
	 */
	public function test_get_presets(): void {
		$presets = UTMGenerator::get_presets();

		$this->assertIsArray( $presets );
		$this->assertNotEmpty( $presets );
		$this->assertArrayHasKey( 'email_newsletter', $presets );
		$this->assertArrayHasKey( 'social_facebook', $presets );
		$this->assertArrayHasKey( 'google_ads', $presets );

		// Test preset structure
		$email_preset = $presets['email_newsletter'];
		$this->assertArrayHasKey( 'name', $email_preset );
		$this->assertArrayHasKey( 'source', $email_preset );
		$this->assertArrayHasKey( 'medium', $email_preset );
	}

	/**
	 * Test get specific preset
	 */
	public function test_get_preset(): void {
		$preset = UTMGenerator::get_preset( 'email_newsletter' );

		$this->assertIsArray( $preset );
		$this->assertEquals( 'Email Newsletter', $preset['name'] );
		$this->assertEquals( 'newsletter', $preset['source'] );
		$this->assertEquals( 'email', $preset['medium'] );
	}

	/**
	 * Test get non-existent preset
	 */
	public function test_get_preset_nonexistent(): void {
		$preset = UTMGenerator::get_preset( 'nonexistent' );

		$this->assertNull( $preset );
	}

	/**
	 * Test UTM parameter validation with valid parameters
	 */
	public function test_validate_utm_params_valid(): void {
		$utm_params = [
			'source'   => 'google',
			'medium'   => 'cpc',
			'campaign' => 'summer_sale',
			'term'     => 'keyword',
			'content'  => 'ad_variant',
		];

		$result = UTMGenerator::validate_utm_params( $utm_params );

		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test UTM parameter validation with missing required parameters
	 */
	public function test_validate_utm_params_missing_required(): void {
		$utm_params = [
			'source' => 'google',
			// Missing medium and campaign
		];

		$result = UTMGenerator::validate_utm_params( $utm_params );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertCount( 2, $result['errors'] ); // Missing medium and campaign
	}

	/**
	 * Test UTM parameter validation with invalid characters
	 */
	public function test_validate_utm_params_invalid_characters(): void {
		$utm_params = [
			'source'   => 'google@#$%',
			'medium'   => 'cpc',
			'campaign' => 'test',
		];

		$result = UTMGenerator::validate_utm_params( $utm_params );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Test campaign name suggestion
	 */
	public function test_suggest_campaign_name(): void {
		$utm_params = [
			'source'   => 'facebook',
			'medium'   => 'social',
			'campaign' => 'summer_promo',
		];

		$result = UTMGenerator::suggest_campaign_name( $utm_params );

		$this->assertStringContainsString( 'Summer Promo', $result );
		$this->assertStringContainsString( 'Facebook', $result );
		$this->assertStringContainsString( 'Social', $result );
	}

	/**
	 * Test campaign name suggestion with empty parameters
	 */
	public function test_suggest_campaign_name_empty(): void {
		$utm_params = [];

		$result = UTMGenerator::suggest_campaign_name( $utm_params );

		$this->assertEquals( 'Nuova Campagna', $result );
	}

	/**
	 * Test campaign name suggestion with underscores and hyphens
	 */
	public function test_suggest_campaign_name_formatting(): void {
		$utm_params = [
			'source'   => 'email_newsletter',
			'medium'   => 'email',
			'campaign' => 'black-friday_sale',
		];

		$result = UTMGenerator::suggest_campaign_name( $utm_params );

		$this->assertStringContainsString( 'Black Friday Sale', $result );
		$this->assertStringContainsString( 'Email Newsletter', $result );
	}

	/**
	 * Test parameter sanitization
	 */
	public function test_utm_parameter_sanitization(): void {
		$base_url = 'https://example.com';
		$utm_params = [
			'source'   => 'Google Ads',
			'medium'   => 'CPC Campaign',
			'campaign' => 'Summer Sale 2024',
		];

		$result = UTMGenerator::generate_utm_url( $base_url, $utm_params );

		// Parameters should be lowercased and spaces replaced with underscores
		$this->assertStringContainsString( 'utm_source=google_ads', $result );
		$this->assertStringContainsString( 'utm_medium=cpc_campaign', $result );
		$this->assertStringContainsString( 'utm_campaign=summer_sale_2024', $result );
	}

	/**
	 * Test URL with fragment
	 */
	public function test_generate_utm_url_with_fragment(): void {
		$base_url = 'https://example.com/page#section';
		$utm_params = [
			'source'   => 'test',
			'medium'   => 'test',
			'campaign' => 'test',
		];

		$result = UTMGenerator::generate_utm_url( $base_url, $utm_params );

		$this->assertStringContainsString( '#section', $result );
		$this->assertStringContainsString( 'utm_source=test', $result );
	}
}