<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\Anomalies\AnomaliesActionHandler;
use FP\DMS\Admin\Pages\Anomalies\AnomaliesDataService;
use FP\DMS\Admin\Pages\Anomalies\AnomaliesRenderer;
use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Support\Wp;

use function current_user_can;

/**
 * Anomalies Page - Refactored with modular architecture
 *
 * Delegates to:
 * - AnomaliesDataService: Data retrieval and formatting
 * - AnomaliesRenderer: UI rendering
 * - AnomaliesActionHandler: Action handling
 *
 * Shared components:
 * - TabsRenderer: Tab navigation
 * - TableRenderer: Table display
 * - FormRenderer: Form elements
 */
class AnomaliesPageRefactored
{
    /**
     * Render the anomalies page
     */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle actions
        $repo = new AnomaliesRepo();
        $actionHandler = new AnomaliesActionHandler($repo);
        $actionHandler->handle();

        // Get data
        $clientsRepo = new ClientsRepo();
        $clients = $clientsRepo->all();
        $clientsMap = AnomaliesDataService::getClientsMap();

        $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        $tab = isset($_GET['tab']) ? Wp::sanitizeKey($_GET['tab']) : 'anomalies';

        // Render page
        AnomaliesRenderer::renderHeader($tab, $clientId);

        if ($tab === 'policy') {
            \settings_errors('fpdms_anomaly_policy');
            $policy = AnomaliesDataService::getPolicyConfig($clientId);
            AnomaliesRenderer::renderPolicyForm($clients, $clientId, $policy);
        } else {
            \settings_errors('fpdms_anomalies');
            AnomaliesRenderer::renderClientFilter($clients, $clientId, $tab);

            $anomalies = AnomaliesDataService::getRecentAnomalies($clientId, 50);
            AnomaliesRenderer::renderAnomaliesTable($anomalies, $clientsMap);
        }

        AnomaliesRenderer::renderFooter();
    }
}
