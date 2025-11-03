<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Wp;

use function apply_filters;

abstract class BaseGoogleProvider implements DataSourceProviderInterface
{
    public function __construct(protected array $auth, protected array $config)
    {
    }

    /**
     * Resolve a Google service account JSON string, supporting `manual` and `constant` sources
     * and an overridable filter hook name.
     */
    protected function resolveServiceAccount(string $filterHook): string
    {
        $source = $this->auth['credential_source'] ?? 'manual';
        if ($source === 'constant') {
            $constant = $this->auth['service_account_constant'] ?? '';
            if (! is_string($constant) || $constant === '' || ! defined($constant)) {
                return '';
            }
            $value = constant($constant);
            return is_string($value) ? $value : '';
        }

        $serviceAccount = $this->auth['service_account'] ?? '';
        
        // Normalizza il service account: potrebbe essere già un array decodificato
        if (is_array($serviceAccount)) {
            // Se è un array, ri-codificalo come stringa JSON
            $serviceAccount = json_encode($serviceAccount);
            if ($serviceAccount === false) {
                return '';
            }
        } else {
            $serviceAccount = trim((string) $serviceAccount);
            
            // AUTO-FIX: Se mancano le parentesi graffe esterne, aggiungile
            if ($serviceAccount !== '') {
                if (!str_starts_with($serviceAccount, '{')) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[BaseGoogleProvider] Service Account senza parentesi iniziale, aggiungo {');
                    }
                    $serviceAccount = '{' . $serviceAccount;
                }
                if (!str_ends_with($serviceAccount, '}')) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[BaseGoogleProvider] Service Account senza parentesi finale, aggiungo }');
                    }
                    $serviceAccount = $serviceAccount . '}';
                }
                
                // Se sembra essere double-escaped, prova a pulirlo
                if (strpos($serviceAccount, '\\"') !== false) {
                    $serviceAccount = stripslashes($serviceAccount);
                }
            }
        }

        /**
         * Allow developers to customize service account sourcing.
         *
         * @param string $serviceAccount Raw JSON string
         * @param array<string,mixed> $auth
         * @param array<string,mixed> $config
         */
        return (string) apply_filters($filterHook, $serviceAccount, $this->auth, $this->config);
    }

    /**
     * Normalize a date value into Y-m-d or null if unparseable.
     */
    protected static function normalizeDate(string $value): ?string
    {
        $timestamp = strtotime(trim($value));
        if ($timestamp === false) {
            return null;
        }

        return Wp::date('Y-m-d', $timestamp);
    }
}
