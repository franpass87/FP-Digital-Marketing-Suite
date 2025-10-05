<?php

declare(strict_types=1);

namespace FP\DMS\Tests\Unit;

use FP\DMS\Services\Connectors\ClientConnectorValidator;
use PHPUnit\Framework\TestCase;

final class ClientConnectorValidatorTest extends TestCase
{
    public function testSanitizeGa4PropertyIdAcceptsDigits(): void
    {
        $this->assertSame('123456', ClientConnectorValidator::sanitizeGa4PropertyId(' 123456 '));
    }

    public function testSanitizeGa4PropertyIdRemovesNonDigits(): void
    {
        $this->assertSame('400987654321', ClientConnectorValidator::sanitizeGa4PropertyId('ga4-00987654321'));
    }

    public function testSanitizeGa4PropertyIdReturnsEmptyForInvalid(): void
    {
        $this->assertSame('', ClientConnectorValidator::sanitizeGa4PropertyId('abc'));
    }

    public function testSanitizeGa4StreamIdKeepsZeroWhenAllZeros(): void
    {
        $this->assertSame('0', ClientConnectorValidator::sanitizeGa4StreamId('0000'));
    }

    public function testSanitizeGa4StreamIdStripsNonDigits(): void
    {
        $this->assertSame('876500', ClientConnectorValidator::sanitizeGa4StreamId('s8765-00'));
    }

    public function testSanitizeGa4MeasurementIdNormalizesUppercase(): void
    {
        $this->assertSame('G-ABCDEFG1', ClientConnectorValidator::sanitizeGa4MeasurementId('g-abcdefg1'));
    }

    public function testSanitizeGa4MeasurementIdRejectsInvalidFormat(): void
    {
        $this->assertSame('', ClientConnectorValidator::sanitizeGa4MeasurementId('G-abc_def'));
    }

    public function testSanitizeGscSitePropertyAllowsDomainFormat(): void
    {
        $this->assertSame('sc-domain:example.org', ClientConnectorValidator::sanitizeGscSiteProperty('sc-domain:Example.org'));
    }

    public function testSanitizeGscSitePropertyRejectsInvalidDomain(): void
    {
        $this->assertSame('', ClientConnectorValidator::sanitizeGscSiteProperty('sc-domain:???'));
    }

    public function testSanitizeGscSitePropertyValidatesUrls(): void
    {
        $this->assertSame('https://example.com/', ClientConnectorValidator::sanitizeGscSiteProperty('https://example.com/'));
    }

    public function testSanitizeGscSitePropertyRejectsInvalidUrls(): void
    {
        $this->assertSame('', ClientConnectorValidator::sanitizeGscSiteProperty('notaurl'));
    }
}
