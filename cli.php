#!/usr/bin/env php
<?php
/**
 * FP Digital Marketing Suite - Standalone Application
 * Command Line Interface
 */

declare(strict_types=1);

use FP\DMS\App\Bootstrap;
use Symfony\Component\Console\Application;

require __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Create console application
$console = new Application('FP Digital Marketing Suite', '1.0.0');

// Bootstrap and register commands
$bootstrap = new Bootstrap();
$bootstrap->registerCommands($console);

// Run console application
$console->run();
