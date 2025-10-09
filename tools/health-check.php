#!/usr/bin/env php
<?php
/**
 * Health Check & System Diagnostics
 * 
 * Verifica lo stato del sistema e identifica problemi comuni.
 * 
 * Usage:
 *   php tools/health-check.php
 *   php tools/health-check.php --verbose
 *   php tools/health-check.php --json
 */

declare(strict_types=1);

$verbose = in_array('--verbose', $argv ?? [], true) || in_array('-v', $argv ?? [], true);
$json = in_array('--json', $argv ?? [], true);

$results = [];
$errors = 0;
$warnings = 0;

// Colors per output
$colors = [
    'green' => "\033[0;32m",
    'yellow' => "\033[1;33m",
    'red' => "\033[0;31m",
    'blue' => "\033[0;34m",
    'reset' => "\033[0m",
];

function output(string $message, string $color = 'reset', bool $newline = true): void
{
    global $colors, $json;
    if (!$json) {
        echo $colors[$color] . $message . $colors['reset'];
        if ($newline) echo PHP_EOL;
    }
}

function checkItem(string $name, bool $status, string $message = '', string $level = 'error'): void
{
    global $results, $errors, $warnings, $verbose;
    
    $results[] = [
        'name' => $name,
        'status' => $status ? 'pass' : 'fail',
        'level' => $level,
        'message' => $message,
    ];
    
    if (!$status) {
        if ($level === 'error') {
            $errors++;
            output("  ✗ $name", 'red');
        } else {
            $warnings++;
            output("  ⚠ $name", 'yellow');
        }
        if ($message && $verbose) {
            output("    → $message", 'yellow');
        }
    } else {
        output("  ✓ $name", 'green');
        if ($message && $verbose) {
            output("    → $message", 'blue');
        }
    }
}

if (!$json) {
    output("\n🏥 FP Digital Marketing Suite - Health Check\n", 'blue');
    output("========================================\n", 'blue');
}

// 1. PHP Version
output("\n📌 PHP Environment", 'blue');
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.1.0', '>=');
checkItem(
    "PHP Version",
    $phpOk,
    $phpOk ? "Version $phpVersion (✓)" : "Version $phpVersion (min 8.1.0 required)",
    $phpOk ? 'info' : 'error'
);

// 2. Required Extensions
output("\n📦 PHP Extensions", 'blue');
$requiredExtensions = ['pdo', 'json', 'mbstring', 'curl'];
foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    checkItem(
        "Extension: $ext",
        $loaded,
        $loaded ? "Loaded" : "Missing - Install php-$ext",
        'error'
    );
}

// 3. Recommended Extensions
$recommendedExtensions = ['sodium' => 'Strong encryption', 'openssl' => 'Fallback encryption', 'zip' => 'Archive support'];
foreach ($recommendedExtensions as $ext => $purpose) {
    $loaded = extension_loaded($ext);
    checkItem(
        "Extension: $ext",
        $loaded,
        $loaded ? "$purpose available" : "$purpose not available",
        'warning'
    );
}

// 4. Encryption Support
output("\n🔐 Encryption", 'blue');
$sodiumOk = function_exists('sodium_crypto_secretbox');
$opensslOk = function_exists('openssl_encrypt');
checkItem(
    "Sodium Encryption",
    $sodiumOk,
    $sodiumOk ? "Primary encryption available" : "Not available - Install libsodium",
    'warning'
);
checkItem(
    "OpenSSL Encryption",
    $opensslOk,
    $opensslOk ? "Fallback encryption available" : "Not available",
    'warning'
);
$anyEncryption = $sodiumOk || $opensslOk;
checkItem(
    "Encryption Available",
    $anyEncryption,
    $anyEncryption ? "✓ Credentials can be encrypted" : "✗ No encryption available",
    'error'
);

// 5. File System
output("\n📁 File System", 'blue');
$baseDir = dirname(__DIR__);
$writableDirs = [
    'storage/logs',
    'storage/cache',
    'storage/pdfs',
    'storage/uploads',
];

foreach ($writableDirs as $dir) {
    $path = $baseDir . '/' . $dir;
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    
    if (!$exists) {
        @mkdir($path, 0775, true);
        $writable = is_writable($path);
    }
    
    checkItem(
        "Directory: $dir",
        $writable,
        $writable ? "Writable" : "Not writable - chmod 775 $dir",
        'error'
    );
}

// 6. Composer Dependencies
output("\n📚 Dependencies", 'blue');
$vendorDir = $baseDir . '/vendor';
$autoloadFile = $vendorDir . '/autoload.php';
$composerInstalled = file_exists($autoloadFile);
checkItem(
    "Composer Packages",
    $composerInstalled,
    $composerInstalled ? "Installed" : "Run: composer install",
    'error'
);

if ($composerInstalled) {
    require_once $autoloadFile;
    
    // Check specific packages
    $packages = [
        'mpdf/mpdf' => 'PDF generation',
        'monolog/monolog' => 'Logging',
        'nesbot/carbon' => 'Date handling',
    ];
    
    foreach ($packages as $package => $purpose) {
        $installed = class_exists(match($package) {
            'mpdf/mpdf' => 'Mpdf\\Mpdf',
            'monolog/monolog' => 'Monolog\\Logger',
            'nesbot/carbon' => 'Carbon\\Carbon',
            default => 'NonExistent',
        });
        
        checkItem(
            "Package: $package",
            $installed,
            $installed ? "$purpose ready" : "Missing - composer install",
            'warning'
        );
    }
}

// 7. Configuration
output("\n⚙️  Configuration", 'blue');
$envFile = $baseDir . '/.env';
$envExists = file_exists($envFile);
checkItem(
    "Environment File",
    $envExists,
    $envExists ? ".env configured" : "Copy env.example to .env",
    'warning'
);

// 8. Database (se .env esiste)
if ($envExists && $composerInstalled) {
    output("\n🗄️  Database", 'blue');
    
    // Load .env
    $envVars = parse_ini_file($envFile);
    $dbHost = $envVars['DB_HOST'] ?? 'localhost';
    $dbName = $envVars['DB_NAME'] ?? '';
    $dbUser = $envVars['DB_USER'] ?? '';
    $dbPass = $envVars['DB_PASS'] ?? '';
    
    try {
        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        checkItem("Database Connection", true, "Connected to $dbName@$dbHost", 'info');
        
        // Check tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $tableCount = count($tables);
        
        checkItem(
            "Database Tables",
            $tableCount > 0,
            $tableCount > 0 ? "$tableCount tables found" : "Run migrations: php cli.php db:migrate",
            $tableCount > 0 ? 'info' : 'warning'
        );
        
    } catch (PDOException $e) {
        checkItem(
            "Database Connection",
            false,
            "Failed: " . $e->getMessage(),
            'error'
        );
    }
}

// 9. Memory & Limits
output("\n💾 PHP Configuration", 'blue');
$memoryLimit = ini_get('memory_limit');
$memoryLimitBytes = parseMemoryLimit($memoryLimit);
$memoryOk = $memoryLimitBytes === -1 || $memoryLimitBytes >= 128 * 1024 * 1024;
checkItem(
    "Memory Limit",
    $memoryOk,
    "Current: $memoryLimit " . ($memoryOk ? "(OK)" : "(min 128M recommended)"),
    $memoryOk ? 'info' : 'warning'
);

$maxExecutionTime = (int) ini_get('max_execution_time');
$timeOk = $maxExecutionTime === 0 || $maxExecutionTime >= 60;
checkItem(
    "Max Execution Time",
    $timeOk,
    "Current: {$maxExecutionTime}s " . ($timeOk ? "(OK)" : "(min 60s recommended)"),
    $timeOk ? 'info' : 'warning'
);

// 10. Security
output("\n🔒 Security", 'blue');
$displayErrors = ini_get('display_errors');
$productionMode = $displayErrors === '0' || $displayErrors === 'Off';
checkItem(
    "Production Mode",
    $productionMode,
    $productionMode ? "display_errors OFF (secure)" : "display_errors ON (dev only!)",
    $productionMode ? 'info' : 'warning'
);

// Helper function
function parseMemoryLimit(string $limit): int
{
    if ($limit === '-1') {
        return -1;
    }
    
    $unit = strtoupper(substr($limit, -1));
    $value = (int) substr($limit, 0, -1);
    
    return match($unit) {
        'G' => $value * 1024 * 1024 * 1024,
        'M' => $value * 1024 * 1024,
        'K' => $value * 1024,
        default => $value,
    };
}

// Summary
if (!$json) {
    output("\n" . str_repeat("=", 40), 'blue');
    output("\n📊 Summary\n", 'blue');
    
    $total = count($results);
    $passed = count(array_filter($results, fn($r) => $r['status'] === 'pass'));
    $failed = $total - $passed;
    
    output("Total Checks: $total");
    output("Passed: $passed", 'green');
    if ($errors > 0) {
        output("Errors: $errors", 'red');
    }
    if ($warnings > 0) {
        output("Warnings: $warnings", 'yellow');
    }
    
    output("\n");
    
    if ($errors === 0 && $warnings === 0) {
        output("✅ System is healthy and ready!", 'green');
        exit(0);
    } elseif ($errors === 0) {
        output("⚠️  System is functional but has warnings", 'yellow');
        exit(0);
    } else {
        output("❌ System has critical errors - fix them before deployment", 'red');
        exit(1);
    }
} else {
    // JSON output
    echo json_encode([
        'status' => $errors === 0 ? 'healthy' : 'unhealthy',
        'timestamp' => date('c'),
        'summary' => [
            'total' => count($results),
            'passed' => count(array_filter($results, fn($r) => $r['status'] === 'pass')),
            'errors' => $errors,
            'warnings' => $warnings,
        ],
        'checks' => $results,
    ], JSON_PRETTY_PRINT);
    exit($errors > 0 ? 1 : 0);
}
