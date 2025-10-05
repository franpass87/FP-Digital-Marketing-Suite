<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use function apply_filters;
use function constant;
use function defined;
use function is_string;
use function trim;

class CentralServiceAccount
{
    public static function getJson(string $type): string
    {
        $candidates = [];
        if ($type === 'ga4') {
            $candidates[] = 'FPDMS_GA4_SERVICE_ACCOUNT';
        }
        if ($type === 'gsc') {
            $candidates[] = 'FPDMS_GSC_SERVICE_ACCOUNT';
        }

        $candidates[] = 'FPDMS_SERVICE_ACCOUNT_JSON';

        foreach ($candidates as $constant) {
            if (defined($constant)) {
                $value = constant($constant);
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        $filteredByType = apply_filters('fpdms/central_service_account/' . $type, '');
        if (is_string($filteredByType) && trim($filteredByType) !== '') {
            return trim($filteredByType);
        }

        $generic = apply_filters('fpdms/central_service_account', '', $type);
        if (is_string($generic) && trim($generic) !== '') {
            return trim($generic);
        }

        return '';
    }
}
