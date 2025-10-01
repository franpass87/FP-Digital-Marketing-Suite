<?php

declare(strict_types=1);

namespace FP\DMS\Tests\Unit;

use FP\DMS\Infra\Options;
use PHPUnit\Framework\TestCase;

final class OptionsTwilioRoutingTest extends TestCase
{
    public function testDefaultPolicyIncludesTwilioAdvancedFields(): void
    {
        $policy = Options::defaultAnomalyPolicy();
        $twilio = $policy['routing']['sms_twilio'];

        $this->assertArrayHasKey('messaging_service_sid', $twilio);
        $this->assertArrayHasKey('status_callback', $twilio);
        $this->assertSame('', $twilio['messaging_service_sid']);
        $this->assertSame('', $twilio['status_callback']);
    }

    public function testSanitizeTwilioRoutingPersistsAdvancedFields(): void
    {
        $input = [
            'routing' => [
                'sms_twilio' => [
                    'enabled' => '1',
                    'sid' => " AC123\n",
                    'token' => ' secret ',
                    'from' => ' +1234567890 ',
                    'to' => "+10987654321, +19876543210\n",
                    'messaging_service_sid' => " MG999 \n",
                    'status_callback' => ' https://example.com/callback?token=abc ',
                ],
            ],
        ];

        $current = Options::defaultAnomalyPolicy();
        $result = Options::sanitizeAnomalyPolicyInput($input, $current);
        $twilio = $result['policy']['routing']['sms_twilio'];

        $this->assertTrue($twilio['enabled']);
        $this->assertSame('MG999', $twilio['messaging_service_sid']);
        $this->assertSame('https://example.com/callback?token=abc', $twilio['status_callback']);
    }

    public function testSanitizeTwilioRoutingRejectsInvalidStatusCallback(): void
    {
        $input = [
            'routing' => [
                'sms_twilio' => [
                    'enabled' => '1',
                    'messaging_service_sid' => 'MG999',
                    'status_callback' => ' javascript:alert(1) ',
                ],
            ],
        ];

        $current = Options::defaultAnomalyPolicy();
        $result = Options::sanitizeAnomalyPolicyInput($input, $current);
        $twilio = $result['policy']['routing']['sms_twilio'];

        $this->assertSame('', $twilio['status_callback']);
    }
}
