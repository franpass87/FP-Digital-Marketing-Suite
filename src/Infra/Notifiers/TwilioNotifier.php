<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

use FP\DMS\Infra\Logger;
use FP\DMS\Support\Wp;

final class TwilioNotifier implements BaseNotifier
{
    private string $sid;

    private string $token;

    private string $from;

    private ?string $messagingServiceSid;

    private ?string $statusCallback;

    /**
     * @var string[]
     */
    private array $recipients;

    /**
     * @var callable
     */
    private $requester;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config, ?callable $requester = null)
    {
        $this->sid = trim((string) ($config['sid'] ?? ''));
        $this->token = trim((string) ($config['token'] ?? ''));
        $this->from = trim((string) ($config['from'] ?? ''));
        $messagingSid = trim((string) ($config['messaging_service_sid'] ?? ''));
        $this->messagingServiceSid = $messagingSid !== '' ? $messagingSid : null;
        $callback = trim((string) ($config['status_callback'] ?? ''));
        $this->statusCallback = filter_var($callback, FILTER_VALIDATE_URL) ? $callback : null;
        $this->recipients = $this->parseRecipients($config['to'] ?? []);
        $this->requester = $requester ?? static fn (string $url, array $args) => Wp::remotePost($url, $args);
    }

    public function send(array $payload): bool
    {
        $text = $this->normaliseMessage($payload['text'] ?? '');
        if (
            $this->sid === ''
            || $this->token === ''
            || ($this->from === '' && $this->messagingServiceSid === null)
            || empty($this->recipients)
            || $text === ''
        ) {
            return false;
        }

        $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', rawurlencode($this->sid));
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->sid . ':' . $this->token),
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];

        $sent = false;
        foreach ($this->recipients as $recipient) {
            $body = $this->buildRequestBody($recipient, $text);
            $response = ($this->requester)($url, [
                'headers' => $headers,
                'timeout' => 10,
                'body' => http_build_query($body, '', '&', PHP_QUERY_RFC3986),
            ]);

            if (! $this->wasSuccessful($response)) {
                $this->logFailure($recipient, $response);
                continue;
            }

            $sent = true;
        }

        return $sent;
    }

    private function normaliseMessage(mixed $value): string
    {
        $message = trim((string) $value);
        if ($message === '') {
            return '';
        }

        $maxLength = 1600; // Twilio SMS limit.

        return $this->truncate($message, $maxLength, true);
    }

    /**
     * @return string[]
     */
    private function parseRecipients(mixed $value): array
    {
        $candidates = [];

        if (is_string($value)) {
            $candidates = preg_split('/[\s,]+/', $value) ?: [];
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item)) {
                    $parts = preg_split('/[\s,]+/', $item) ?: [];
                    foreach ($parts as $part) {
                        $candidates[] = $part;
                    }
                } elseif (is_scalar($item)) {
                    $candidates[] = (string) $item;
                }
            }
        }

        $clean = [];
        foreach ($candidates as $candidate) {
            $trimmed = trim((string) $candidate);
            if ($trimmed === '' || in_array($trimmed, $clean, true)) {
                continue;
            }
            $clean[] = $trimmed;
        }

        return $clean;
    }

    private function wasSuccessful(mixed $response): bool
    {
        if (Wp::isWpError($response)) {
            return false;
        }

        $code = Wp::remoteRetrieveResponseCode($response);
        if ($code < 200 || $code >= 300) {
            return false;
        }

        $body = Wp::remoteRetrieveBody($response);
        if ($body === '') {
            return true;
        }

        $decoded = json_decode($body, true);
        
        // Check for JSON errors
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return true;
        }

        if (array_key_exists('error_code', $decoded) && $decoded['error_code'] !== null && $decoded['error_code'] !== 0) {
            return false;
        }

        if (isset($decoded['status'])) {
            $status = strtolower((string) $decoded['status']);
            if (in_array($status, ['failed', 'undelivered', 'canceled', 'cancelled'], true)) {
                return false;
            }
        }

        return true;
    }

    private function logFailure(string $recipient, mixed $response): void
    {
        $code = Wp::remoteRetrieveResponseCode($response);
        $bodySummary = $this->summariseFailure($response);

        Logger::logChannel('ANOM', sprintf(
            'twilio_failed to=%s status=%d body=%s',
            md5($recipient),
            $code,
            $bodySummary
        ));
    }

    /**
     * @return array<string,string>
     */
    private function buildRequestBody(string $recipient, string $text): array
    {
        $body = [
            'To' => $recipient,
            'Body' => $text,
        ];

        if ($this->messagingServiceSid !== null) {
            $body['MessagingServiceSid'] = $this->messagingServiceSid;
        } else {
            $body['From'] = $this->from;
        }

        if ($this->statusCallback !== null) {
            $body['StatusCallback'] = $this->statusCallback;
        }

        return $body;
    }

    private function summariseFailure(mixed $response): string
    {
        if (Wp::isWpError($response)) {
            $message = Wp::wpErrorMessage($response);
            if ($message === '') {
                return 'wp_error';
            }

            return 'wp_error:' . $this->truncate($message, 120);
        }

        $body = Wp::remoteRetrieveBody($response);
        if ($body === '') {
            return 'empty';
        }

        $decoded = json_decode($body, true);
        
        // Check for valid JSON
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $code = $decoded['error_code'] ?? $decoded['code'] ?? null;
            $message = $decoded['message'] ?? $decoded['error_message'] ?? null;

            $parts = [];
            if ($code !== null && $code !== '') {
                $parts[] = sprintf('code=%s', (string) $code);
            }
            if ($message !== null && $message !== '') {
                $parts[] = sprintf('message=%s', $this->truncate((string) $message, 120));
            }

            if (! empty($parts)) {
                return implode(' ', $parts);
            }
        }

        return $this->truncate($body, 120);
    }

    private function truncate(string $value, int $limit, bool $trim = false): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') > $limit) {
                $slice = mb_substr($value, 0, $limit, 'UTF-8');

                return $trim ? trim($slice) : $slice;
            }

            return $trim ? trim($value) : $value;
        }

        if (strlen($value) > $limit) {
            $slice = substr($value, 0, $limit);

            $stringSlice = is_string($slice) ? $slice : '';

            return $trim ? trim($stringSlice) : $stringSlice;
        }

        return $trim ? trim($value) : $value;
    }
}
