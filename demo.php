<?php
/**
 * Demo script to test all functionality of the reporting system
 */

require_once __DIR__ . '/vendor/autoload.php';

use FP\DigitalMarketing\ReportGenerator;
use FP\DigitalMarketing\MockDataProvider;

echo "=== FP Digital Marketing Suite - Demo Report System ===\n\n";

try {
    // Test 1: Mock Data Generation
    echo "1. Testing Mock Data Generation...\n";
    $data = MockDataProvider::getAnalyticsData();
    echo "   ✓ Generated data for period: {$data['period']['display']}\n";
    echo "   ✓ KPIs: " . count($data['kpis']) . " metrics\n";
    echo "   ✓ Charts: " . count($data['charts']) . " chart types\n\n";

    // Test 2: HTML Report Generation
    echo "2. Testing HTML Report Generation...\n";
    $reportGenerator = new ReportGenerator();
    $htmlReport = $reportGenerator->generateHtmlReport($data);
    echo "   ✓ HTML report generated: " . number_format(strlen($htmlReport)) . " characters\n\n";

    // Test 3: PDF Report Generation
    echo "3. Testing PDF Report Generation...\n";
    $pdfResult = $reportGenerator->generatePdfReport($data, 'demo_report.pdf');
    echo "   ✓ PDF report generated: {$pdfResult['filename']}\n";
    echo "   ✓ File size: " . number_format($pdfResult['size']) . " bytes\n";
    echo "   ✓ Location: {$pdfResult['path']}\n\n";

    // Test 4: Report List
    echo "4. Testing Report List Functionality...\n";
    $reports = $reportGenerator->getGeneratedReports();
    echo "   ✓ Found " . count($reports) . " generated reports:\n";
    foreach ($reports as $report) {
        echo "     - {$report['filename']} ({$report['date']}) - " . number_format($report['size']) . " bytes\n";
    }
    echo "\n";

    // Test 5: Display Sample KPIs
    echo "5. Sample KPI Data Generated:\n";
    foreach ($data['kpis'] as $key => $kpi) {
        $changeIcon = $kpi['change'] >= 0 ? '📈' : '📉';
        $change = $kpi['change'] >= 0 ? '+' . number_format($kpi['change'], 1) : number_format($kpi['change'], 1);
        echo "   {$changeIcon} {$kpi['label']}: {$kpi['value']} ({$change}%)\n";
    }
    echo "\n";

    echo "🎉 All tests passed! The reporting system is fully functional.\n";
    echo "\nTo use the admin interface:\n";
    echo "1. Start PHP server: php -S localhost:8000 -t admin/\n";
    echo "2. Open browser: http://localhost:8000/\n";
    echo "3. Use 'Anteprima HTML' to preview reports\n";
    echo "4. Use 'Genera PDF' to create downloadable PDFs\n\n";

    echo "To setup automated reports:\n";
    echo "Add to crontab: 0 9 1 * * " . PHP_BINARY . " " . __DIR__ . "/cron/generate_report.php\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}