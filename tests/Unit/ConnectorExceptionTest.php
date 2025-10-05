<?php

declare(strict_types=1);

namespace FP\DMS\Tests\Unit;

use FP\DMS\Services\Connectors\ConnectorException;
use PHPUnit\Framework\TestCase;

final class ConnectorExceptionTest extends TestCase
{
    public function testConstructorSetsMessageAndContext(): void
    {
        $exception = new ConnectorException(
            'Test message',
            ['key' => 'value'],
            500
        );

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(['key' => 'value'], $exception->getContext());
        $this->assertSame(500, $exception->getCode());
    }

    public function testAuthenticationFailedCreatesCorrectException(): void
    {
        $exception = ConnectorException::authenticationFailed(
            'ga4',
            'Invalid service account JSON',
            ['email' => 'test@example.com']
        );

        $this->assertStringContainsString('Authentication failed for ga4', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
        $this->assertSame('ga4', $exception->getContext()['provider']);
        $this->assertSame('Invalid service account JSON', $exception->getContext()['reason']);
        $this->assertSame('test@example.com', $exception->getContext()['email']);
    }

    public function testApiCallFailedCreatesCorrectException(): void
    {
        $exception = ConnectorException::apiCallFailed(
            'meta_ads',
            '/insights',
            429,
            'Too many requests',
            ['account_id' => 'act_123']
        );

        $this->assertStringContainsString('API call to meta_ads failed', $exception->getMessage());
        $this->assertStringContainsString('HTTP 429', $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
        $this->assertSame('meta_ads', $exception->getContext()['provider']);
        $this->assertSame('/insights', $exception->getContext()['endpoint']);
        $this->assertSame(429, $exception->getContext()['status_code']);
        $this->assertSame('act_123', $exception->getContext()['account_id']);
    }

    public function testInvalidConfigurationCreatesCorrectException(): void
    {
        $exception = ConnectorException::invalidConfiguration(
            'google_ads',
            'Missing customer ID',
            ['required_fields' => ['customer_id']]
        );

        $this->assertStringContainsString('Invalid configuration for google_ads', $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
        $this->assertSame('google_ads', $exception->getContext()['provider']);
        $this->assertSame('Missing customer ID', $exception->getContext()['reason']);
    }

    public function testRateLimitExceededCreatesCorrectException(): void
    {
        $exception = ConnectorException::rateLimitExceeded(
            'gsc',
            300,
            ['requests_made' => 100]
        );

        $this->assertStringContainsString('Rate limit exceeded for gsc', $exception->getMessage());
        $this->assertStringContainsString('300 seconds', $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
        $this->assertSame('gsc', $exception->getContext()['provider']);
        $this->assertSame(300, $exception->getContext()['retry_after']);
        $this->assertSame(100, $exception->getContext()['requests_made']);
    }

    public function testValidationFailedCreatesCorrectException(): void
    {
        $exception = ConnectorException::validationFailed(
            'meta_ads',
            'account_id',
            'Must start with act_',
            ['provided_value' => '123456']
        );

        $this->assertStringContainsString('Validation failed for meta_ads', $exception->getMessage());
        $this->assertStringContainsString('account_id', $exception->getMessage());
        $this->assertSame(422, $exception->getCode());
        $this->assertSame('meta_ads', $exception->getContext()['provider']);
        $this->assertSame('account_id', $exception->getContext()['field']);
        $this->assertSame('Must start with act_', $exception->getContext()['reason']);
        $this->assertSame('123456', $exception->getContext()['provided_value']);
    }

    public function testExceptionCanBeChained(): void
    {
        $previous = new \RuntimeException('Original error');
        $exception = new ConnectorException(
            'Wrapper error',
            ['context' => 'test'],
            500,
            $previous
        );

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame('Original error', $exception->getPrevious()->getMessage());
    }

    public function testGetContextReturnsEmptyArrayByDefault(): void
    {
        $exception = new ConnectorException('Test message');

        $this->assertSame([], $exception->getContext());
    }
}
