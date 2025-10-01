<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

use FP\DMS\Infra\Logger;
use FP\DMS\Support\Wp;

class TeamsNotifier implements BaseNotifier
{
    public function __construct(private string $webhookUrl)
    {
    }

    public function send(array $payload): bool
    {
        if ($this->webhookUrl === '') {
            return false;
        }

        $title = (string) ($payload['title'] ?? 'FP DMS Alert');
        $text = (string) ($payload['text'] ?? '');

        $body = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => 'E11D48',
            'summary' => $title,
            'sections' => [
                [
                    'activityTitle' => $title,
                    'text' => $text,
                ],
            ],
        ];

        $response = Wp::remotePost($this->webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => Wp::jsonEncode($body) ?: '[]',
            'timeout' => 5,
        ]);

        if (Wp::isWpError($response) || Wp::remoteRetrieveResponseCode($response) >= 300) {
            Logger::logChannel('ANOM', sprintf('teams_failed url=%s', md5($this->webhookUrl)));

            return false;
        }

        return true;
    }
}
