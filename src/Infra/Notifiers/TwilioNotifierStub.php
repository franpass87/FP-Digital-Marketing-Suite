<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

use FP\DMS\Infra\Logger;

class TwilioNotifierStub implements BaseNotifier
{
    public function __construct(private array $config)
    {
    }

    public function send(array $payload): bool
    {
        $to = $this->config['to'] ?? '';
        $from = $this->config['from'] ?? '';
        $text = (string) ($payload['text'] ?? '');

        if ($text === '' || $to === '' || $from === '') {
            return false;
        }

        Logger::logChannel('ANOM', sprintf('twilio_stub from=%s to=%s message=%s', $from, $to, substr($text, 0, 120)));

        return true;
    }
}
