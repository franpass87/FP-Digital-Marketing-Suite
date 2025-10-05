<?php

declare(strict_types=1);

namespace FP\DMS\Tests\Unit;

use FP\DMS\Services\Connectors\ConnectionTemplate;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for ConnectionTemplate.
 */
final class ConnectionTemplateTest extends TestCase
{
    public function testGetTemplatesReturnsArray(): void
    {
        $templates = ConnectionTemplate::getTemplates();

        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);
    }

    public function testEachTemplateHasRequiredFields(): void
    {
        $templates = ConnectionTemplate::getTemplates();

        foreach ($templates as $templateId => $template) {
            $this->assertArrayHasKey('name', $template);
            $this->assertArrayHasKey('description', $template);
            $this->assertArrayHasKey('provider', $template);
            $this->assertArrayHasKey('metrics_preset', $template);
            $this->assertArrayHasKey('recommended_for', $template);
        }
    }

    public function testGetTemplatesByProviderFiltersCorrectly(): void
    {
        $ga4Templates = ConnectionTemplate::getTemplatesByProvider('ga4');

        $this->assertIsArray($ga4Templates);
        
        foreach ($ga4Templates as $template) {
            $this->assertEquals('ga4', $template['provider']);
        }
    }

    public function testGetTemplateReturnsSpecificTemplate(): void
    {
        $template = ConnectionTemplate::getTemplate('ga4_basic');

        $this->assertIsArray($template);
        $this->assertEquals('ga4', $template['provider']);
        $this->assertNotEmpty($template['metrics_preset']);
    }

    public function testGetTemplateReturnsNullForInvalidId(): void
    {
        $template = ConnectionTemplate::getTemplate('invalid_template_id');

        $this->assertNull($template);
    }

    public function testApplyTemplateAddsMetrics(): void
    {
        $baseConfig = ['name' => 'Test'];
        $result = ConnectionTemplate::applyTemplate('ga4_basic', $baseConfig);

        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('template_used', $result);
        $this->assertEquals('ga4_basic', $result['template_used']);
        $this->assertNotEmpty($result['metrics']);
    }

    public function testApplyTemplateAddsDimensionsIfPresent(): void
    {
        $baseConfig = [];
        $result = ConnectionTemplate::applyTemplate('ga4_ecommerce', $baseConfig);

        if (isset($result['dimensions'])) {
            $this->assertIsArray($result['dimensions']);
        }
    }

    public function testApplyTemplateThrowsForInvalidTemplate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        ConnectionTemplate::applyTemplate('invalid_id', []);
    }

    public function testSuggestTemplatesForEcommerce(): void
    {
        $suggestions = ConnectionTemplate::suggestTemplates([
            'keywords' => ['shop', 'ecommerce'],
            'site_type' => 'ecommerce',
        ]);

        $this->assertIsArray($suggestions);
        $this->assertContains('ga4_ecommerce', $suggestions);
    }

    public function testSuggestTemplatesForBlog(): void
    {
        $suggestions = ConnectionTemplate::suggestTemplates([
            'keywords' => ['blog', 'content'],
            'site_type' => 'blog',
        ]);

        $this->assertIsArray($suggestions);
        $this->assertContains('ga4_content', $suggestions);
    }

    public function testSuggestTemplatesReturnsDefaultForUnknownType(): void
    {
        $suggestions = ConnectionTemplate::suggestTemplates([]);

        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
    }

    public function testUsesTemplateReturnsTrueForMatchingTemplate(): void
    {
        $config = ['template_used' => 'ga4_basic'];

        $result = ConnectionTemplate::usesTemplate($config, 'ga4_basic');

        $this->assertTrue($result);
    }

    public function testUsesTemplateReturnsFalseForNonMatchingTemplate(): void
    {
        $config = ['template_used' => 'ga4_basic'];

        $result = ConnectionTemplate::usesTemplate($config, 'ga4_ecommerce');

        $this->assertFalse($result);
    }
}
