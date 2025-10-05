<?php

declare(strict_types=1);

namespace FP\DMS\Tests\Unit;

use FP\DMS\Services\Connectors\AutoDiscovery;
use FP\DMS\Services\Connectors\ConnectorException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for AutoDiscovery.
 */
final class AutoDiscoveryTest extends TestCase
{
    public function testValidateServiceAccountPermissionsWithValidJson(): void
    {
        $json = json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key' => 'test-key',
            'client_email' => 'test@test.iam.gserviceaccount.com',
        ]);

        $result = AutoDiscovery::validateServiceAccountPermissions($json, 'ga4');

        $this->assertTrue($result['valid']);
        $this->assertEquals('test@test.iam.gserviceaccount.com', $result['email']);
        $this->assertEquals('test-project', $result['project_id']);
    }

    public function testValidateServiceAccountPermissionsWithInvalidJson(): void
    {
        $result = AutoDiscovery::validateServiceAccountPermissions('invalid json', 'ga4');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testValidateServiceAccountPermissionsWithMissingFields(): void
    {
        $json = json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
        ]);

        $result = AutoDiscovery::validateServiceAccountPermissions($json, 'ga4');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Missing required fields', $result['error']);
    }

    public function testValidateServiceAccountPermissionsWithWrongType(): void
    {
        $json = json_encode([
            'type' => 'user_account',
            'project_id' => 'test-project',
            'private_key' => 'test-key',
            'client_email' => 'test@test.com',
        ]);

        $result = AutoDiscovery::validateServiceAccountPermissions($json, 'ga4');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Not a service account', $result['error']);
    }

    public function testDiscoverGA4PropertiesThrowsWithInvalidCredentials(): void
    {
        $this->expectException(ConnectorException::class);
        
        AutoDiscovery::discoverGA4Properties('invalid json');
    }

    public function testDiscoverGSCSitesThrowsWithInvalidCredentials(): void
    {
        $this->expectException(ConnectorException::class);
        
        AutoDiscovery::discoverGSCSites('invalid json');
    }

    public function testTestAndEnrichGA4ConnectionThrowsWithInvalidCredentials(): void
    {
        $this->expectException(ConnectorException::class);
        
        AutoDiscovery::testAndEnrichGA4Connection('invalid json', '123456789');
    }

    public function testGetGA4PropertyMetadataThrowsWithInvalidCredentials(): void
    {
        $this->expectException(ConnectorException::class);
        
        AutoDiscovery::getGA4PropertyMetadata('invalid json', '123456789');
    }
}
