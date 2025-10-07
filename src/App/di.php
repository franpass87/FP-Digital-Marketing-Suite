<?php

declare(strict_types=1);

use FP\DMS\App\Database\Database;
use FP\DMS\Infra\Config;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;

return [
    Config::class => static function (): Config {
        return new Config();
    },

    LoggerInterface::class => static function (): LoggerInterface {
        $logger = new MonologLogger('fpdms');
        $logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/../../storage/logs';
        $logger->pushHandler(new StreamHandler($logPath . '/app.log', MonologLogger::INFO));
        return $logger;
    },

    Database::class => static function (): Database {
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
    },
];


