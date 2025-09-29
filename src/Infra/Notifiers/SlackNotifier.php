<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

use FP\DMS\Infra\Logger;

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

        $response = wp_remote_post($this->webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['text' => $text]),
            'timeout' => 5,
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 300) {
            Logger::logChannel('ANOM', sprintf('slack_failed url=%s', md5($this->webhookUrl)));

            return false;
        }

        return true;
    }
}
