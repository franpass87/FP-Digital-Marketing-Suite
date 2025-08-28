#!/usr/bin/env php
<?php
/**
 * Cron script for automatic report generation
 * Add to crontab: 0 9 1 * * /path/to/php /path/to/this/script
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FP\DigitalMarketing\ReportGenerator;

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting automatic report generation...\n";
    
    $reportGenerator = new ReportGenerator();
    
    // Generate PDF report with current date
    $filename = 'marketing_report_auto_' . date('Y-m-d') . '.pdf';
    $result = $reportGenerator->generatePdfReport(null, $filename);
    
    echo "[" . date('Y-m-d H:i:s') . "] Report generated successfully:\n";
    echo "  - Filename: {$result['filename']}\n";
    echo "  - Path: {$result['path']}\n";
    echo "  - Size: " . number_format($result['size']) . " bytes\n";
    
    // Optional: Send email notification (uncomment and configure as needed)
    /*
    $to = 'admin@yourcompany.com';
    $subject = 'Marketing Report Generated - ' . date('Y-m-d');
    $message = "Il report automatico è stato generato con successo.\n\n";
    $message .= "File: {$result['filename']}\n";
    $message .= "Dimensione: " . number_format($result['size']) . " bytes\n";
    $message .= "Data generazione: " . date('Y-m-d H:i:s') . "\n";
    
    mail($to, $subject, $message);
    echo "[" . date('Y-m-d H:i:s') . "] Email notification sent to $to\n";
    */
    
    echo "[" . date('Y-m-d H:i:s') . "] Process completed successfully.\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    
    // Log error to file
    $errorLog = __DIR__ . '/../output/cron_errors.log';
    $errorMessage = "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . "\n";
    file_put_contents($errorLog, $errorMessage, FILE_APPEND | LOCK_EX);
    
    exit(1);
}