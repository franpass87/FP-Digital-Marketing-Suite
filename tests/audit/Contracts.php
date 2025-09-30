<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$requiredClasses = [
    'infra' => [
        'FP\\DMS\\Infra\\DB' => 'Database access layer',
        'FP\\DMS\\Infra\\Options' => 'Options manager',
        'FP\\DMS\\Infra\\Logger' => 'Logger service',
        'FP\\DMS\\Infra\\Mailer' => 'Mailer service',
        'FP\\DMS\\Infra\\Queue' => 'Queue dispatcher',
        'FP\\DMS\\Infra\\Lock' => 'Lock coordination',
    ],
    'services_reports' => [
        'FP\\DMS\\Services\\Reports\\ReportBuilder' => 'Report builder',
        'FP\\DMS\\Services\\Reports\\HtmlRenderer' => 'HTML report renderer',
        'FP\\DMS\\Services\\Reports\\TokenEngine' => 'Report token engine',
    ],
    'services_anomalies' => [
        'FP\\DMS\\Services\\Anomalies\\Detector' => 'Anomalies detector',
        'FP\\DMS\\Services\\Anomalies\\Engine' => 'Anomalies engine',
    ],
    'services_connectors' => [
        'FP\\DMS\\Services\\Connectors\\GoogleAdsProvider' => 'Google Ads connector',
        'FP\\DMS\\Services\\Connectors\\MetaAdsProvider' => 'Meta Ads connector',
        'FP\\DMS\\Services\\Connectors\\CsvGenericProvider' => 'CSV generic connector',
        'FP\\DMS\\Services\\Connectors\\GA4Provider' => 'GA4 connector',
        'FP\\DMS\\Services\\Connectors\\GSCProvider' => 'GSC connector',
    ],
    'qa' => [
        'FP\\DMS\\Services\\Qa\\Automation' => 'QA automation orchestrator',
        'FP\\DMS\\Admin\\Pages\\QaPage' => 'QA admin page',
        'FP\\DMS\\Http\\Routes' => 'HTTP routes registrar',
    ],
];

$requiredRoutes = [
    '/tick' => 'POST',
    '/run/(?P<client_id>\\d+)' => 'POST',
    '/report/(?P<report_id>\\d+)/download' => 'GET',
    '/qa/seed' => 'POST',
    '/qa/run' => 'POST',
    '/qa/anomalies' => 'POST',
    '/qa/all' => 'POST',
    '/qa/status' => 'GET',
    '/qa/cleanup' => 'POST',
];

$results = [
    'generated_at' => date(DATE_ATOM),
    'classes' => [],
    'routes' => [],
    'summary' => [
        'total_checks' => 0,
        'passed' => 0,
        'failed' => 0,
    ],
];

foreach ($requiredClasses as $group => $classes) {
    $results['classes'][$group] = [];
    foreach ($classes as $class => $description) {
        $relative = 'src/' . str_replace('\\', '/', preg_replace('/^FP\\\\DMS\\\\/', '', $class)) . '.php';
        $path = $root . '/' . $relative;
        $exists = is_file($path);
        $status = $exists ? 'PASS' : 'FAIL';

        $results['classes'][$group][] = [
            'class' => $class,
            'description' => $description,
            'file' => $relative,
            'status' => $status,
        ];

        $results['summary']['total_checks']++;
        if ($exists) {
            $results['summary']['passed']++;
        } else {
            $results['summary']['failed']++;
        }
    }
}

$routesFile = $root . '/src/Http/Routes.php';
$routeMap = [];
if (is_file($routesFile)) {
    $contents = file_get_contents($routesFile) ?: '';
    $pattern = "/register_rest_route\\('fpdms\\/v1',\\s*'([^']+)'\\s*,\\s*\\[(.*?)\\]\\);/s";
    if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $path = str_replace('\\\\', '\\', $match[1]);
            $config = $match[2];
            $method = null;
            if (preg_match("/'methods'\\s*=>\\s*'([^']+)'/i", $config, $methodMatch)) {
                $method = strtoupper($methodMatch[1]);
            }
            $routeMap[$path] = [
                'methods' => $method,
            ];
        }
    }
}

$results['routes'] = [];
foreach ($requiredRoutes as $path => $method) {
    $actual = $routeMap[$path] ?? null;
    $status = 'FAIL';
    $details = [
        'expected_method' => $method,
        'actual_method' => $actual['methods'] ?? null,
    ];
    if ($actual !== null && $actual['methods'] === $method) {
        $status = 'PASS';
    }

    $results['routes'][] = [
        'path' => $path,
        'status' => $status,
        'details' => $details,
    ];

    $results['summary']['total_checks']++;
    if ($status === 'PASS') {
        $results['summary']['passed']++;
    } else {
        $results['summary']['failed']++;
    }
}

$outFile = __DIR__ . '/contracts.json';
file_put_contents($outFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo sprintf(
    "Contract checks complete: %d passed / %d total (%d failed)\n",
    $results['summary']['passed'],
    $results['summary']['total_checks'],
    $results['summary']['failed']
);

echo "Output written to tests/audit/contracts.json\n";
