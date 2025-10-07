<?php

declare(strict_types=1);

namespace FP\DMS\App;

use DI\Container;
use FP\DMS\App\Database\Database;
use FP\DMS\App\Middleware\AuthMiddleware;
use FP\DMS\App\Middleware\CorsMiddleware;
use FP\DMS\Infra\Config;
use FP\DMS\Infra\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Slim\App;
use Symfony\Component\Console\Application;

class Bootstrap
{
    public function __construct(
        private ?Container $container = null
    ) {
    }

    public function registerServices(): void
    {
        if ($this->container === null) {
            return;
        }

        // No-op: services are now defined via PHP-DI definitions in src/App/di.php
    }

    public function registerMiddleware(App $app): void
    {
        $app->add(new CorsMiddleware());
        $app->add(new AuthMiddleware());
    }

    public function registerRoutes(App $app): void
    {
        $router = new Router($app);
        $router->register();
    }

    public function registerCommands(Application $console): void
    {
        $commandRegistry = new CommandRegistry($console);
        $commandRegistry->register();
    }
}
