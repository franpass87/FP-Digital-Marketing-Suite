<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

use FP\DMS\Infra\Logger;

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

        $response = wp_remote_post($this->webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'timeout' => 5,
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 300) {
            Logger::logChannel('ANOM', sprintf('teams_failed url=%s', md5($this->webhookUrl)));

            return false;
        }

        return true;
    }
}
