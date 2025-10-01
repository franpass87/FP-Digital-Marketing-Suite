<?php

declare(strict_types=1);

namespace FP\DMS\Services\Overview;

use FP\DMS\Support\Wp;
use function delete_transient;
use function get_transient;
use function is_scalar;
use function ksort;
use function md5;
use function set_transient;
use function substr;

class Cache
{
    private string $prefix;

    public function __construct(?string $prefix = null)
    {
        $this->prefix = $prefix ? Wp::sanitizeKey($prefix) : 'fpdms_overview';
    }

    /**
     * @param array<string, scalar> $context
     */
    public function get(int $clientId, string $section, array $context = []): mixed
    {
        $key = $this->buildKey($clientId, $section, $context);
        $cached = get_transient($key);

        return $cached === false ? null : $cached;
    }

    /**
     * @param array<string, scalar> $context
     */
    public function set(int $clientId, string $section, mixed $value, int $ttl = 90, array $context = []): bool
    {
        $key = $this->buildKey($clientId, $section, $context);

        return set_transient($key, $value, $ttl);
    }

    /**
     * @param array<string, scalar> $context
     */
    public function clear(int $clientId, string $section, array $context = []): void
    {
        $key = $this->buildKey($clientId, $section, $context);
        delete_transient($key);
    }

    /**
     * @param array<string, scalar> $context
     */
    private function buildKey(int $clientId, string $section, array $context = []): string
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $normalized[Wp::sanitizeKey($key)] = (string) $value;
        }

        $payload = ['client' => $clientId, 'section' => Wp::sanitizeKey($section)] + $normalized;
        ksort($payload);
        $encoded = Wp::jsonEncode($payload) ?: '';
        $hash = md5($encoded);

        return substr($this->prefix . '_' . $clientId . '_' . $hash, 0, 172);
    }
}
