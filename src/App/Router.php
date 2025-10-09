<?php

declare(strict_types=1);

namespace FP\DMS\App;

use FP\DMS\App\Controllers\ApiController;
use FP\DMS\App\Controllers\AuthController;
use FP\DMS\App\Controllers\DashboardController;
use FP\DMS\App\Controllers\ClientsController;
use FP\DMS\App\Controllers\DataSourcesController;
use FP\DMS\App\Controllers\SchedulesController;
use FP\DMS\App\Controllers\TemplatesController;
use FP\DMS\App\Controllers\AnomaliesController;
use FP\DMS\App\Controllers\HealthController;
use FP\DMS\App\Controllers\SettingsController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class Router
{
    public function __construct(
        private App $app
    ) {
    }

    public function register(): void
    {
        // Public routes
        $this->app->get('/', [DashboardController::class, 'index']);
        $this->app->get('/login', [AuthController::class, 'showLogin']);
        $this->app->post('/login', [AuthController::class, 'login']);
        $this->app->post('/logout', [AuthController::class, 'logout']);

        // Protected routes
        $this->app->group('', function (RouteCollectorProxy $group) {
            // Dashboard
            $group->get('/dashboard', [DashboardController::class, 'index']);

            // Clients
            $group->get('/clients', [ClientsController::class, 'index']);
            $group->get('/clients/create', [ClientsController::class, 'create']);
            $group->post('/clients', [ClientsController::class, 'store']);
            $group->get('/clients/{id}', [ClientsController::class, 'edit']);
            $group->post('/clients/{id}', [ClientsController::class, 'update']);
            $group->post('/clients/{id}/delete', [ClientsController::class, 'delete']);

            // Data Sources
            $group->get('/datasources', [DataSourcesController::class, 'index']);
            $group->get('/datasources/create', [DataSourcesController::class, 'create']);
            $group->post('/datasources', [DataSourcesController::class, 'store']);
            $group->get('/datasources/{id}', [DataSourcesController::class, 'edit']);
            $group->post('/datasources/{id}', [DataSourcesController::class, 'update']);
            $group->post('/datasources/{id}/delete', [DataSourcesController::class, 'delete']);

            // Schedules
            $group->get('/schedules', [SchedulesController::class, 'index']);
            $group->get('/schedules/create', [SchedulesController::class, 'create']);
            $group->post('/schedules', [SchedulesController::class, 'store']);
            $group->get('/schedules/{id}', [SchedulesController::class, 'edit']);
            $group->post('/schedules/{id}', [SchedulesController::class, 'update']);
            $group->post('/schedules/{id}/delete', [SchedulesController::class, 'delete']);

            // Templates
            $group->get('/templates', [TemplatesController::class, 'index']);
            $group->get('/templates/create', [TemplatesController::class, 'create']);
            $group->post('/templates', [TemplatesController::class, 'store']);
            $group->get('/templates/{id}', [TemplatesController::class, 'edit']);
            $group->post('/templates/{id}', [TemplatesController::class, 'update']);
            $group->post('/templates/{id}/delete', [TemplatesController::class, 'delete']);

            // Anomalies
            $group->get('/anomalies', [AnomaliesController::class, 'index']);
            $group->get('/anomalies/{id}', [AnomaliesController::class, 'show']);

            // Health & Monitoring
            $group->get('/health', [HealthController::class, 'index']);
            $group->post('/health/tick', [HealthController::class, 'forceTick']);

            // Settings
            $group->get('/settings', [SettingsController::class, 'index']);
            $group->post('/settings', [SettingsController::class, 'update']);
        });

        // API routes
        $this->app->group('/api/v1', function (RouteCollectorProxy $group) {
            // Queue tick endpoint
            $group->post('/tick', [ApiController::class, 'tick']);

            // Anomalies
            $group->post('/anomalies/evaluate', [ApiController::class, 'evaluateAnomalies']);
            $group->post('/anomalies/notify', [ApiController::class, 'notifyAnomalies']);

            // QA endpoints
            $group->group('/qa', function (RouteCollectorProxy $qa) {
                $qa->post('/seed', [ApiController::class, 'qaSeed']);
                $qa->post('/run', [ApiController::class, 'qaRun']);
                $qa->post('/cleanup', [ApiController::class, 'qaCleanup']);
                $qa->get('/status', [ApiController::class, 'qaStatus']);
            });
        });
    }
}
