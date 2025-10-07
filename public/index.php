<?php
/**
 * FP Digital Marketing Suite - Standalone Application
 * Entry point for the web application
 */

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use FP\DMS\App\Bootstrap;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Create DI container with autowiring and definitions
$builder = new ContainerBuilder();
$definitions = __DIR__ . '/../src/App/di.php';
if (file_exists($definitions)) {
    $builder->addDefinitions($definitions);
}
$builder->useAutowiring(true);
$builder->useAnnotations(false);
$container = $builder->build();

// Set container to create App with DI
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: $_ENV['APP_DEBUG'] ?? false,
    logErrors: true,
    logErrorDetails: true
);

// Bootstrap application
$bootstrap = new Bootstrap($container);
$bootstrap->registerServices();
$bootstrap->registerMiddleware($app);
$bootstrap->registerRoutes($app);

// Run application
$app->run();
