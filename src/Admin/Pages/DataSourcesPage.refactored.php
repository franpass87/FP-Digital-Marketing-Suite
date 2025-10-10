<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\DataSources\ActionHandler;
use FP\DMS\Admin\Pages\DataSources\ClientSelector;
use FP\DMS\Admin\Pages\DataSources\NoticeManager;
use FP\DMS\Admin\Pages\DataSources\Renderer;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Services\Connectors\ProviderFactory;

/**
 * Refactored Data Sources Page with modular architecture.
 *
 * Responsibilities are now split into:
 * - ActionHandler: Handles save, test, delete operations
 * - PayloadValidator: Validates form data
 * - ClientSelector: Manages client selection
 * - NoticeManager: Handles notices and messages
 * - Renderer: Renders HTML components
 */
class DataSourcesPageRefactored
{
    private ClientsRepo $clientsRepo;
    private DataSourcesRepo $dataSourcesRepo;
    private ActionHandler $actionHandler;
    private ClientSelector $clientSelector;
    private NoticeManager $noticeManager;
    private Renderer $renderer;

    public function __construct()
    {
        $this->clientsRepo = new ClientsRepo();
        $this->dataSourcesRepo = new DataSourcesRepo();
        $this->actionHandler = new ActionHandler($this->clientsRepo, $this->dataSourcesRepo);
        $this->clientSelector = new ClientSelector();
        $this->noticeManager = new NoticeManager();
        $this->renderer = new Renderer();
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $page = new self();
        $page->renderPage();
    }

    private function renderPage(): void
    {
        // Handle POST/GET actions
        $this->actionHandler->handleActions();

        // Boot notices from transient
        $this->noticeManager->bootNotices();

        // Output inline CSS
        $this->renderer->outputInlineAssets();

        // Get clients
        $clients = $this->clientsRepo->all();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Data Sources', 'fp-dms') . '</h1>';

        // Check if we have clients
        if (empty($clients)) {
            $this->noticeManager->renderEmptyState();
            echo '</div>';
            return;
        }

        // Determine selected client
        $selectedClientId = $this->clientSelector->determineSelectedClientId($clients);
        $selectedClient = $this->clientSelector->findClientById($clients, $selectedClientId);

        // Render client selector
        $this->clientSelector->renderSelector($clients, $selectedClientId);

        // Display notices
        $this->noticeManager->displayNotices();

        // Client description
        if ($selectedClient) {
            echo '<p>' . esc_html(sprintf(
                __('Manage the data sources linked to %s. Configure direct connectors with API credentials or import custom files where needed.', 'fp-dms'),
                $selectedClient->name
            )) . '</p>';
        }

        // Guided intro
        $this->noticeManager->renderGuidedIntro();

        // Get data sources for selected client
        $dataSources = $selectedClientId ? $this->dataSourcesRepo->forClient($selectedClientId) : [];

        // Check if we're editing
        $editing = $this->getEditingDataSource($selectedClientId);

        // Get provider definitions
        $definitions = ProviderFactory::definitions();

        // Render form (if needed, implement FormRenderer)
        // $this->renderForm($selectedClientId, $editing, $definitions);

        // Render list
        $this->renderer->renderList($dataSources, $definitions, $selectedClientId);

        echo '</div>';
    }

    private function getEditingDataSource(?int $selectedClientId): ?object
    {
        if (!isset($_GET['action'], $_GET['source']) || $_GET['action'] !== 'edit') {
            return null;
        }

        $candidate = $this->dataSourcesRepo->find((int) $_GET['source']);

        if ($candidate && $candidate->clientId === $selectedClientId) {
            return $candidate;
        }

        return null;
    }
}
