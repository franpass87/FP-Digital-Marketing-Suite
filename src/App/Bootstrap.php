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

        // Config
        $this->container->set(Config::class, function () {
            return new Config();
        });

        // Logger
        $this->container->set(LoggerInterface::class, function () {
            $logger = new MonologLogger('fpdms');
            $logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/../../storage/logs';
            $logger->pushHandler(new StreamHandler($logPath . '/app.log', MonologLogger::INFO));
            return $logger;
        });

        // Database
        $this->container->set(Database::class, function () {
            return new Database([
                'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'database' => $_ENV['DB_DATABASE'] ?? 'fpdms',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
                'prefix' => $_ENV['DB_PREFIX'] ?? 'fpdms_',
            ]);
        });
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
