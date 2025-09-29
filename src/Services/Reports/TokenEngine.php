<?php

declare(strict_types=1);

namespace FP\DMS\Services\Reports;

class TokenEngine
{
    /**
     * @param array<string,mixed> $context
     */
    public function render(string $template, array $context): string
    {
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function (array $matches) use ($context): string {
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
        }, $template) ?? $template;
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

        return is_array($value) ? wp_json_encode($value) : '';
    }

    private function applyFilter(string $value, string $filter): string
    {
        $filter = trim($filter);
        if ($filter === 'number') {
            return number_format_i18n((float) $value);
        }

        if ($filter !== '') {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return wp_date($filter, $timestamp);
            }
        }

        return $value;
    }
}
