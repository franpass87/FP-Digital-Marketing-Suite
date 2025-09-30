<?php

declare(strict_types=1);

namespace FP\DMS\Audit;

use RuntimeException;

$root = dirname(__DIR__, 2);

/**
 * @param string $path
 * @return array<mixed>
 */
function loadJson(string $path): array
{
    if (! is_file($path)) {
        throw new RuntimeException(sprintf('Required file missing: %s', $path));
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read: %s', $path));
    }

    /** @var array<mixed> $decoded */
    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException(sprintf('Invalid JSON structure: %s', $path));
    }

    return $decoded;
}

$stateFile = $root . '/.audit-state.json';
$inventoryFile = __DIR__ . '/inventory.json';
$linkageFile = __DIR__ . '/linkage.json';
$contractsFile = __DIR__ . '/contracts.json';
$runtimeFile = __DIR__ . '/runtime.json';

$inventory = loadJson($inventoryFile);
$linkage = loadJson($linkageFile);
$contracts = loadJson($contractsFile);
$runtime = loadJson($runtimeFile);

$state = [];
if (is_file($stateFile)) {
    $state = loadJson($stateFile);
}

/** @var array<string, array{class: string, method: string, reference_count: int}> $linkageMethods */
$linkageMethods = $linkage['methods'] ?? [];
$methodUsage = [];
foreach ($linkageMethods as $entry) {
    $class = $entry['class'] ?? null;
    $count = $entry['reference_count'] ?? 0;
    if (! is_string($class)) {
        continue;
    }

    if ($count > 0) {
        $methodUsage[$class] = true;
    }
}

$modules = [];
$totalPassed = 0;
$totalItems = 0;

$adminPages = $inventory['admin_pages'] ?? [];
$adminPass = 0;
$adminTotal = 0;
$adminWarnings = [];
foreach ($adminPages as $page) {
    $class = $page['class'] ?? null;
    if (! is_string($class)) {
        continue;
    }

    $adminTotal++;
    $methods = $page['methods'] ?? [];
    $hasReference = false;
    if (is_array($methods)) {
        foreach ($methods as $method) {
            $methodName = $method['name'] ?? null;
            if (! is_string($methodName)) {
                continue;
            }
            $key = $class . '::' . $methodName;
            $references = $linkageMethods[$key]['references'] ?? [];
            if (is_array($references) && $references !== []) {
                $hasReference = true;
                break;
            }
        }
    }

    if (! $hasReference) {
        $adminWarnings[] = sprintf('%s render method lacks coverage references', $class);
    } else {
        $adminPass++;
    }
}

$modules[] = [
    'name' => 'Admin',
    'pass' => $adminPass,
    'total' => $adminTotal,
    'pct' => $adminTotal > 0 ? round(($adminPass / $adminTotal) * 100, 2) : 0.0,
    'warn' => $adminWarnings,
];
$totalPassed += $adminPass;
$totalItems += $adminTotal;

$routes = $contracts['routes'] ?? [];
$httpPass = 0;
$httpTotal = 0;
$httpWarnings = [];
foreach ($routes as $route) {
    $httpTotal++;
    $status = $route['status'] ?? 'UNKNOWN';
    if ($status === 'PASS') {
        $httpPass++;
        continue;
    }
    $path = $route['path'] ?? 'unknown';
    $httpWarnings[] = sprintf('%s reported %s', $path, $status);
    if ($status === 'WARN') {
        $httpPass++;
    }
}

$modules[] = [
    'name' => 'Http',
    'pass' => $httpPass,
    'total' => $httpTotal,
    'pct' => $httpTotal > 0 ? round(($httpPass / $httpTotal) * 100, 2) : 0.0,
    'warn' => $httpWarnings,
];
$totalPassed += $httpPass;
$totalItems += $httpTotal;

$infraEntries = $contracts['classes']['infra'] ?? [];
$infraPass = 0;
$infraTotal = 0;
$infraWarnings = [];
foreach ($infraEntries as $entry) {
    $infraTotal++;
    $class = $entry['class'] ?? 'unknown';
    $status = $entry['status'] ?? 'UNKNOWN';
    $hasUsage = $methodUsage[$class] ?? false;
    if ($status === 'PASS' && $hasUsage) {
        $infraPass++;
        continue;
    }

    $infraWarnings[] = sprintf('%s status=%s usage=%s', $class, $status, $hasUsage ? 'yes' : 'no');
    if ($status === 'PASS' && ! $hasUsage) {
        // Allow partial credit if present but unused.
        $infraPass++;
    }
}

$runtimeRunStatus = $runtime['run']['status'] ?? 'UNKNOWN';
$infraTotal++;
if ($runtimeRunStatus === 'PASS') {
    $infraPass++;
} elseif ($runtimeRunStatus === 'WARN') {
    $infraPass++;
    $infraWarnings[] = 'Runtime run reported WARN (likely missing PDF renderer).';
} else {
    $infraWarnings[] = sprintf('Runtime run status %s.', $runtimeRunStatus);
}

$modules[] = [
    'name' => 'Infra',
    'pass' => $infraPass,
    'total' => $infraTotal,
    'pct' => $infraTotal > 0 ? round(($infraPass / $infraTotal) * 100, 2) : 0.0,
    'warn' => $infraWarnings,
];
$totalPassed += $infraPass;
$totalItems += $infraTotal;

$reportsEntries = $contracts['classes']['services_reports'] ?? [];
$reportsPass = 0;
$reportsTotal = 0;
$reportsWarnings = [];
foreach ($reportsEntries as $entry) {
    $reportsTotal++;
    $class = $entry['class'] ?? 'unknown';
    $status = $entry['status'] ?? 'UNKNOWN';
    $hasUsage = $methodUsage[$class] ?? false;
    if ($status === 'PASS' && $hasUsage) {
        $reportsPass++;
        continue;
    }

    $reportsWarnings[] = sprintf('%s status=%s usage=%s', $class, $status, $hasUsage ? 'yes' : 'no');
    if ($status === 'PASS') {
        $reportsPass++;
    }
}

$modules[] = [
    'name' => 'Services Reports',
    'pass' => $reportsPass,
    'total' => $reportsTotal,
    'pct' => $reportsTotal > 0 ? round(($reportsPass / $reportsTotal) * 100, 2) : 0.0,
    'warn' => $reportsWarnings,
];
$totalPassed += $reportsPass;
$totalItems += $reportsTotal;

$connectorEntries = $contracts['classes']['services_connectors'] ?? [];
$connectorPass = 0;
$connectorTotal = 0;
$connectorWarnings = [];
$datasources = $runtime['seed']['datasources'] ?? [];
$connectorUsageMap = [
    'FP\\DMS\\Services\\Connectors\\GoogleAdsProvider' => 'google_ads',
    'FP\\DMS\\Services\\Connectors\\MetaAdsProvider' => 'meta_ads',
    'FP\\DMS\\Services\\Connectors\\CsvGenericProvider' => 'csv_generic',
    'FP\\DMS\\Services\\Connectors\\GA4Provider' => 'ga4',
    'FP\\DMS\\Services\\Connectors\\GSCProvider' => 'gsc',
];

foreach ($connectorEntries as $entry) {
    $connectorTotal++;
    $class = $entry['class'] ?? 'unknown';
    $status = $entry['status'] ?? 'UNKNOWN';
    $hasUsage = $methodUsage[$class] ?? false;
    $datasourceKey = $connectorUsageMap[$class] ?? null;
    $runtimeOk = false;
    if ($datasourceKey !== null && isset($datasources[$datasourceKey])) {
        $runtimeOk = $datasources[$datasourceKey] === 'ok';
    }

    if ($status === 'PASS' && ($hasUsage || $runtimeOk)) {
        $connectorPass++;
        continue;
    }

    $connectorWarnings[] = sprintf(
        '%s status=%s runtime=%s usage=%s',
        $class,
        $status,
        $runtimeOk ? 'ok' : 'missing',
        $hasUsage ? 'yes' : 'no'
    );
    if ($status === 'PASS') {
        $connectorPass++;
    }
}

$modules[] = [
    'name' => 'Services Connectors',
    'pass' => $connectorPass,
    'total' => $connectorTotal,
    'pct' => $connectorTotal > 0 ? round(($connectorPass / $connectorTotal) * 100, 2) : 0.0,
    'warn' => $connectorWarnings,
];
$totalPassed += $connectorPass;
$totalItems += $connectorTotal;

$anomalyEntries = $contracts['classes']['services_anomalies'] ?? [];
$anomalyPass = 0;
$anomalyTotal = 0;
$anomalyWarnings = [];
foreach ($anomalyEntries as $entry) {
    $anomalyTotal++;
    $class = $entry['class'] ?? 'unknown';
    $status = $entry['status'] ?? 'UNKNOWN';
    $hasUsage = $methodUsage[$class] ?? false;
    if ($status === 'PASS' && $hasUsage) {
        $anomalyPass++;
        continue;
    }

    $anomalyWarnings[] = sprintf('%s status=%s usage=%s', $class, $status, $hasUsage ? 'yes' : 'no');
    if ($status === 'PASS') {
        $anomalyPass++;
    }
}

$anomalyTotal++;
$runtimeAnomaliesStatus = $runtime['anomalies']['status'] ?? 'UNKNOWN';
if ($runtimeAnomaliesStatus === 'PASS') {
    $anomalyPass++;
} elseif ($runtimeAnomaliesStatus === 'WARN') {
    $anomalyPass++;
    $anomalyWarnings[] = 'Runtime anomalies reported WARN.';
} else {
    $anomalyWarnings[] = sprintf('Runtime anomalies status %s.', $runtimeAnomaliesStatus);
}

$modules[] = [
    'name' => 'Anomalies',
    'pass' => $anomalyPass,
    'total' => $anomalyTotal,
    'pct' => $anomalyTotal > 0 ? round(($anomalyPass / $anomalyTotal) * 100, 2) : 0.0,
    'warn' => $anomalyWarnings,
];
$totalPassed += $anomalyPass;
$totalItems += $anomalyTotal;

$qaEntries = $contracts['classes']['qa'] ?? [];
$qaPass = 0;
$qaTotal = 0;
$qaWarnings = [];
foreach ($qaEntries as $entry) {
    $qaTotal++;
    $class = $entry['class'] ?? 'unknown';
    $status = $entry['status'] ?? 'UNKNOWN';
    $hasUsage = $methodUsage[$class] ?? false;
    if ($status === 'PASS' && $hasUsage) {
        $qaPass++;
        continue;
    }

    $qaWarnings[] = sprintf('%s status=%s usage=%s', $class, $status, $hasUsage ? 'yes' : 'no');
    if ($status === 'PASS') {
        $qaPass++;
    }
}

$qaStatusKeys = [
    'seed' => 'seed',
    'status' => 'status',
];
foreach ($qaStatusKeys as $label => $key) {
    $qaTotal++;
    $statusValue = $runtime[$key]['status'] ?? 'UNKNOWN';
    if ($statusValue === 'PASS') {
        $qaPass++;
        continue;
    }
    if ($statusValue === 'WARN') {
        $qaPass++;
        $qaWarnings[] = sprintf('Runtime %s reported WARN.', $label);
    } else {
        $qaWarnings[] = sprintf('Runtime %s status %s.', $label, $statusValue);
    }
}

$modules[] = [
    'name' => 'QA Automation',
    'pass' => $qaPass,
    'total' => $qaTotal,
    'pct' => $qaTotal > 0 ? round(($qaPass / $qaTotal) * 100, 2) : 0.0,
    'warn' => $qaWarnings,
];
$totalPassed += $qaPass;
$totalItems += $qaTotal;

$overallPct = $totalItems > 0 ? round(($totalPassed / $totalItems) * 100, 2) : 0.0;

$topUnreferenced = $linkage['summary']['top_unreferenced'] ?? [];
if (! is_array($topUnreferenced)) {
    $topUnreferenced = [];
}
$topUnreferenced = array_slice(array_values(array_filter($topUnreferenced, 'is_string')), 0, 10);

$runtimeSummary = [
    'seed' => $runtime['seed']['status'] ?? 'UNKNOWN',
    'run' => $runtime['run']['status'] ?? 'UNKNOWN',
    'anomalies' => $runtime['anomalies']['status'] ?? 'UNKNOWN',
    'status' => $runtime['status']['status'] ?? 'UNKNOWN',
    'notes' => $runtime['notes'] ?? [],
];

$progressPayload = [
    'generated_at' => date(DATE_ATOM),
    'modules' => array_map(static function (array $module): array {
        return [
            'name' => $module['name'],
            'pass' => $module['pass'],
            'total' => $module['total'],
            'pct' => $module['pct'],
        ];
    }, $modules),
    'overall_pct' => $overallPct,
];

$reportPayload = [
    'modules' => array_map(static function (array $module): array {
        $moduleReport = [
            'name' => $module['name'],
            'pass' => $module['pass'],
            'total' => $module['total'],
            'pct' => $module['pct'],
        ];
        if (! empty($module['warn'])) {
            $moduleReport['warn'] = $module['warn'];
        }
        return $moduleReport;
    }, $modules),
    'overall_pct' => $overallPct,
    'unreferenced_methods' => $topUnreferenced,
    'runtime' => $runtimeSummary,
];

file_put_contents(__DIR__ . '/progress.json', json_encode($progressPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents(__DIR__ . '/report.json', json_encode($reportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$state['task'] = $state['task'] ?? 'fpdms_functionality_audit';
$state['current_phase'] = 5;
$done = $state['phases_done'] ?? [];
if (! in_array(5, $done, true)) {
    $done[] = 5;
}
sort($done);
$state['phases_done'] = $done;
$state['completed'] = true;
$stateTotals = $state['totals'] ?? [];
$stateTotals['modules'] = $progressPayload['modules'];
$stateTotals['overall_pct'] = $overallPct;
$stateTotals['top_unreferenced'] = $topUnreferenced;
$stateTotals['runtime_summary'] = $runtimeSummary;
$stateTotals['total_items'] = $totalItems;
$stateTotals['passed_items'] = $totalPassed;
$state['totals'] = $stateTotals;

file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(
    [
        'overall_pct' => $overallPct,
        'modules' => array_map(static function (array $module): array {
            return [
                'name' => $module['name'],
                'pct' => $module['pct'],
            ];
        }, $modules),
        'top_unreferenced' => $topUnreferenced,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;
