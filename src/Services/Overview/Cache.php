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
     * Clear all overview caches for a specific client
     * This is useful after syncing data sources to ensure fresh data is loaded
     */
    public function clearAllForClient(int $clientId): void
    {
        global $wpdb;
        
        // Clear all transients that match the pattern for this client
        $pattern = $wpdb->esc_like($this->prefix . '_' . $clientId . '_') . '%';
        
        // Delete from options table (non-persistent transients)
        $sql1 = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $pattern
        );
        
        if ($sql1 !== false) {
            $wpdb->query($sql1);
        }
        
        $sql2 = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . $pattern
        );
        
        if ($sql2 !== false) {
            $wpdb->query($sql2);
        }
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
