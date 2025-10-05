<?php

declare(strict_types=1);

namespace FP\DMS\Tests\Unit;

use FP\DMS\Services\Connectors\ConnectorException;
use FP\DMS\Services\Connectors\ErrorTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for ErrorTranslator.
 */
final class ErrorTranslatorTest extends TestCase
{
    public function testTranslateAuthenticationFailure(): void
    {
        $exception = ConnectorException::authenticationFailed(
            'ga4',
            'Invalid service account',
            ['has_credentials' => false]
        );

        $result = ErrorTranslator::translate($exception);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertStringContainsString('Invalid Credentials', $result['title']);
    }

    public function testTranslateRateLimitExceeded(): void
    {
        $exception = ConnectorException::rateLimitExceeded('meta_ads', 120);

        $result = ErrorTranslator::translate($exception);

        $this->assertArrayHasKey('title', $result);
        $this->assertStringContainsString('Too Many Requests', $result['title']);
        $this->assertArrayHasKey('actions', $result);
        $this->assertNotEmpty($result['actions']);
    }

    public function testTranslateNotFound(): void
    {
        $exception = ConnectorException::apiCallFailed(
            'ga4',
            'https://api.example.com',
            404,
            'Property not found',
            ['property_id' => '123456789']
        );

        $result = ErrorTranslator::translate($exception);

        $this->assertArrayHasKey('title', $result);
        $this->assertStringContainsString('Resource Not Found', $result['title']);
    }

    public function testTranslateValidationFailure(): void
    {
        $exception = ConnectorException::validationFailed(
            'google_ads',
            'customer_id',
            'Invalid format',
            ['provided_value' => '123']
        );

        $result = ErrorTranslator::translate($exception);

        $this->assertArrayHasKey('title', $result);
        $this->assertStringContainsString('Validation Error', $result['title']);
        $this->assertArrayHasKey('help', $result);
    }

    public function testTranslateGenericError(): void
    {
        $exception = new ConnectorException(
            'Generic error',
            ['provider' => 'test'],
            500
        );

        $result = ErrorTranslator::translate($exception);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('technical_details', $result);
        $this->assertEquals('Generic error', $result['technical_details']);
    }

    public function testActionsContainRelevantInformation(): void
    {
        $exception = ConnectorException::authenticationFailed('ga4', 'Test');

        $result = ErrorTranslator::translate($exception);

        $this->assertIsArray($result['actions']);
        
        foreach ($result['actions'] as $action) {
            $this->assertArrayHasKey('label', $action);
            $this->assertArrayHasKey('type', $action);
        }
    }
}
