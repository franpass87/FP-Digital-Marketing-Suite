<?php

declare(strict_types=1);

namespace FP\DMS\Services\Reports;

use FP\DMS\Support\Wp;

class TokenEngine
{
    /**
     * @param array<string,mixed> $context
     */
    public function render(string $template, array $context): string
    {
        $result = preg_replace_callback('/{{\s*([^}]+)\s*}}/', function (array $matches) use ($context): string {
            $expression = trim($matches[1]);
            [$path, $filter] = array_pad(explode('|', $expression, 2), 2, null);
            $value = $this->resolvePath($context, $path ?? '');
            $raw = false;

            if ($filter !== null) {
                $filter = trim($filter);
                if ($filter === 'raw') {
                    $raw = true;
                } else {
                    $value = $this->applyFilter($value, $filter);
                }
            }

            return $raw ? (string) $value : esc_html((string) $value);
        }, $template);
        
        // Check for PCRE errors
        if ($result === null || preg_last_error() !== PREG_NO_ERROR) {
            error_log('[FPDMS] Template rendering failed: ' . preg_last_error_msg());
            return $template;
        }
        
        return $result;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function resolvePath(array $context, string $path): string
    {
        $segments = array_filter(explode('.', $path));
        $value = $context;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return '';
            }
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return Wp::jsonEncode($value) ?: '';
        }

        return '';
    }

    private function applyFilter(string $value, string $filter): string
    {
        $filter = trim($filter);
        if ($filter === 'number') {
            return Wp::numberFormatI18n((float) $value);
        }

        if ($filter !== '') {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return Wp::date($filter, $timestamp);
            }
        }

        return $value;
    }
}
