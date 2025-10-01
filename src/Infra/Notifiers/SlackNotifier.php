<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

use FP\DMS\Infra\Logger;
use FP\DMS\Support\Wp;

class SlackNotifier implements BaseNotifier
{
    public function __construct(private string $webhookUrl)
    {
    }

    public function send(array $payload): bool
    {
        if ($this->webhookUrl === '') {
            return false;
        }

        $text = (string) ($payload['text'] ?? '');
        if ($text === '') {
            return false;
        }

        $response = Wp::remotePost($this->webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => Wp::jsonEncode(['text' => $text]) ?: '[]',
            'timeout' => 5,
        ]);

        if (Wp::isWpError($response) || Wp::remoteRetrieveResponseCode($response) >= 300) {
            Logger::logChannel('ANOM', sprintf('slack_failed url=%s', md5($this->webhookUrl)));

            return false;
        }

        return true;
    }
}
