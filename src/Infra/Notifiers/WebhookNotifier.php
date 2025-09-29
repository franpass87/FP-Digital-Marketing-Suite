<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

use FP\DMS\Infra\Logger;

class WebhookNotifier implements BaseNotifier
{
    public function __construct(private string $url, private ?string $secret = null)
    {
    }

    public function send(array $payload): bool
    {
        if ($this->url === '') {
            return false;
        }

        $body = wp_json_encode($payload['body'] ?? []);
        if (! is_string($body)) {
            return false;
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($this->secret) {
            $headers['X-FPDMS-Signature'] = hash_hmac('sha256', $body, $this->secret);
        }

        $response = wp_remote_post($this->url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 5,
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 300) {
            Logger::logChannel('ANOM', sprintf('webhook_failed url=%s', md5($this->url)));

            return false;
        }

        return true;
    }
}
