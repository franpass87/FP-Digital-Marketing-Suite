<?php

declare(strict_types=1);

namespace FP\DMS\Tests\Unit;

use FP\DMS\Infra\Notifiers\TwilioNotifier;
use PHPUnit\Framework\TestCase;

final class TwilioNotifierTest extends TestCase
{
    public function testSendReturnsFalseWhenConfigurationIncomplete(): void
    {
        $notifier = new TwilioNotifier([]);

        $this->assertFalse($notifier->send(['text' => 'hello']));
    }

    public function testSendReturnsFalseWhenMessageMissing(): void
    {
        $notifier = new TwilioNotifier([
            'sid' => 'AC123',
            'token' => 'secret',
            'from' => '+10000000000',
            'to' => '+19999999999',
        ]);

        $this->assertFalse($notifier->send(['text' => '   ']));
    }

    public function testSendDispatchesMessageToEveryRecipient(): void
    {
        $requests = [];
        $notifier = new TwilioNotifier([
            'sid' => 'AC123',
            'token' => 'secret',
            'from' => '+1234567890',
            'to' => "+10987654321\n+19876543210",
        ], function (string $url, array $args) use (&$requests) {
            $requests[] = ['url' => $url, 'args' => $args];

            return [
                'response' => ['code' => 201],
                'body' => json_encode(['sid' => 'SM123', 'error_code' => null], JSON_THROW_ON_ERROR),
            ];
        });

        $result = $notifier->send(['text' => 'Alert! Something happened.']);

        $this->assertTrue($result);
        $this->assertCount(2, $requests);

        foreach ($requests as $request) {
            $this->assertSame(
                'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json',
                $request['url']
            );
            $this->assertSame('Basic ' . base64_encode('AC123:secret'), $request['args']['headers']['Authorization']);
            $this->assertSame('application/x-www-form-urlencoded; charset=UTF-8', $request['args']['headers']['Content-Type']);
            $this->assertIsString($request['args']['body']);

            $parsed = [];
            parse_str($request['args']['body'], $parsed);

            $this->assertSame('+1234567890', $parsed['From']);
            $this->assertSame('Alert! Something happened.', $parsed['Body']);
        }

        $recipients = [];
        foreach ($requests as $request) {
            $parsed = [];
            parse_str($request['args']['body'], $parsed);
            $recipients[] = $parsed['To'];
        }
        $this->assertSame(['+10987654321', '+19876543210'], $recipients);
    }

    public function testSendUsesMessagingServiceSidWhenConfigured(): void
    {
        $requests = [];
        $notifier = new TwilioNotifier([
            'sid' => 'AC123',
            'token' => 'secret',
            'to' => '+10987654321',
            'messaging_service_sid' => 'MG999',
            'status_callback' => 'https://example.com/status',
        ], function (string $url, array $args) use (&$requests) {
            $requests[] = ['url' => $url, 'args' => $args];

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['sid' => 'SM123'], JSON_THROW_ON_ERROR),
            ];
        });

        $result = $notifier->send(['text' => 'Alert! Something happened.']);

        $this->assertTrue($result);
        $this->assertCount(1, $requests);
        $parsed = [];
        parse_str($requests[0]['args']['body'], $parsed);

        $this->assertArrayNotHasKey('From', $parsed);
        $this->assertSame('MG999', $parsed['MessagingServiceSid']);
        $this->assertSame('https://example.com/status', $parsed['StatusCallback']);
    }

    public function testSendReturnsFalseWhenTwilioReportsErrorCode(): void
    {
        $notifier = new TwilioNotifier([
            'sid' => 'AC123',
            'token' => 'secret',
            'from' => '+1234567890',
            'to' => '+10987654321',
        ], function () {
            return [
                'response' => ['code' => 201],
                'body' => json_encode(['error_code' => 21610, 'status' => 'failed'], JSON_THROW_ON_ERROR),
            ];
        });

        $this->assertFalse($notifier->send(['text' => 'Alert!']));
    }

    public function testSendReturnsFalseWhenNoFromOrMessagingServiceConfigured(): void
    {
        $notifier = new TwilioNotifier([
            'sid' => 'AC123',
            'token' => 'secret',
            'to' => '+10987654321',
        ]);

        $this->assertFalse($notifier->send(['text' => 'Alert!']));
    }

    public function testSendReturnsFalseWhenRequesterReportsWpError(): void
    {
        $notifier = new TwilioNotifier([
            'sid' => 'AC123',
            'token' => 'secret',
            'from' => '+1234567890',
            'to' => '+10987654321',
        ], function () {
            return ['error' => 'timeout', 'error_message' => 'Operation timed out'];
        });

        $this->assertFalse($notifier->send(['text' => 'Alert!']));
    }
}
