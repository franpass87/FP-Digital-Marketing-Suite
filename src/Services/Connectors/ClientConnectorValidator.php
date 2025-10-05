<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Wp;

class ClientConnectorValidator
{
    public static function sanitizeGa4PropertyId(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (! is_string($digits)) {
            return '';
        }

        $normalized = ltrim($digits, '0');

        return $normalized === '' ? '' : $normalized;
    }

    public static function sanitizeGa4StreamId(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (! is_string($digits)) {
            return '';
        }

        if ($digits === '') {
            return '';
        }

        $normalized = ltrim($digits, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    public static function sanitizeGa4MeasurementId(mixed $value): string
    {
        $candidate = strtoupper(trim((string) $value));
        if ($candidate === '') {
            return '';
        }

        return preg_match('/^G-[A-Z0-9]+$/', $candidate) === 1 ? $candidate : '';
    }

    public static function sanitizeGscSiteProperty(mixed $value): string
    {
        $candidate = trim((string) $value);
        if ($candidate === '') {
            return '';
        }

        if (str_starts_with($candidate, 'sc-domain:')) {
            $domain = substr($candidate, strlen('sc-domain:'));
            $domain = strtolower(trim((string) $domain));
            $domain = preg_replace('/[^a-z0-9\.-]/', '', $domain ?? '');
            if (! is_string($domain) || $domain === '') {
                return '';
            }

            return 'sc-domain:' . $domain;
        }

        $sanitized = Wp::escUrlRaw($candidate);
        if ($sanitized === '') {
            return '';
        }

        return filter_var($sanitized, FILTER_VALIDATE_URL) !== false ? $sanitized : '';
    }
}
