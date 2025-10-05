<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use Exception;
use Throwable;

/**
 * Exception thrown when a connector encounters an error.
 *
 * This exception includes context information to help with debugging
 * and logging connector failures.
 */
class ConnectorException extends Exception
{
    /**
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * @param string $message Human-readable error message
     * @param array<string, mixed> $context Additional context for debugging
     * @param int $code Error code (0 by default)
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the context information associated with this exception.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create an exception for authentication failures.
     *
     * @param string $provider Name of the provider (e.g., 'ga4', 'meta_ads')
     * @param string $reason Human-readable reason for failure
     * @param array<string, mixed> $context Additional context
     */
    public static function authenticationFailed(
        string $provider,
        string $reason,
        array $context = []
    ): self {
        return new self(
            sprintf('Authentication failed for %s: %s', $provider, $reason),
            array_merge(['provider' => $provider, 'reason' => $reason], $context),
            401
        );
    }

    /**
     * Create an exception for API call failures.
     *
     * @param string $provider Name of the provider
     * @param string $endpoint API endpoint that failed
     * @param int $statusCode HTTP status code
     * @param string $message Error message from API
     * @param array<string, mixed> $context Additional context
     */
    public static function apiCallFailed(
        string $provider,
        string $endpoint,
        int $statusCode,
        string $message = '',
        array $context = []
    ): self {
        return new self(
            sprintf(
                'API call to %s failed (HTTP %d): %s',
                $provider,
                $statusCode,
                $message
            ),
            array_merge([
                'provider' => $provider,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
            ], $context),
            $statusCode
        );
    }

    /**
     * Create an exception for invalid configuration.
     *
     * @param string $provider Name of the provider
     * @param string $reason Human-readable reason
     * @param array<string, mixed> $context Additional context
     */
    public static function invalidConfiguration(
        string $provider,
        string $reason,
        array $context = []
    ): self {
        return new self(
            sprintf('Invalid configuration for %s: %s', $provider, $reason),
            array_merge(['provider' => $provider, 'reason' => $reason], $context),
            400
        );
    }

    /**
     * Create an exception for rate limiting.
     *
     * @param string $provider Name of the provider
     * @param int $retryAfterSeconds Seconds to wait before retrying
     * @param array<string, mixed> $context Additional context
     */
    public static function rateLimitExceeded(
        string $provider,
        int $retryAfterSeconds = 60,
        array $context = []
    ): self {
        return new self(
            sprintf(
                'Rate limit exceeded for %s. Retry after %d seconds.',
                $provider,
                $retryAfterSeconds
            ),
            array_merge([
                'provider' => $provider,
                'retry_after' => $retryAfterSeconds,
            ], $context),
            429
        );
    }

    /**
     * Create an exception for data validation failures.
     *
     * @param string $provider Name of the provider
     * @param string $field Field that failed validation
     * @param string $reason Human-readable reason
     * @param array<string, mixed> $context Additional context
     */
    public static function validationFailed(
        string $provider,
        string $field,
        string $reason,
        array $context = []
    ): self {
        return new self(
            sprintf('Validation failed for %s field "%s": %s', $provider, $field, $reason),
            array_merge([
                'provider' => $provider,
                'field' => $field,
                'reason' => $reason,
            ], $context),
            422
        );
    }
}
