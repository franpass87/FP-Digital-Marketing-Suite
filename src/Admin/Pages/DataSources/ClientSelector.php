<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\DataSources;

use FP\DMS\Domain\Entities\Client;

/**
 * Handles client selection logic for Data Sources page.
 */
class ClientSelector
{
    /**
     * Determine which client is currently selected.
     *
     * @param array<int,Client> $clients
     */
    public function determineSelectedClientId(array $clients): ?int
    {
        $requested = isset($_GET['client']) ? (int) $_GET['client'] : 0;

        if ($requested > 0) {
            foreach ($clients as $client) {
                if ($client->id === $requested) {
                    return $requested;
                }
            }
        }

        return $clients[0]->id ?? null;
    }

    /**
     * Find a client by ID from array.
     *
     * @param array<int,Client> $clients
     */
    public function findClientById(array $clients, ?int $id): ?Client
    {
        if (!$id) {
            return null;
        }

        foreach ($clients as $client) {
            if ($client->id === $id) {
                return $client;
            }
        }

        return null;
    }

    /**
     * Render client selector dropdown.
     *
     * @param array<int,Client> $clients
     */
    public function renderSelector(array $clients, ?int $selectedId): void
    {
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="fp-dms-datasources">';
        echo '<label class="screen-reader-text" for="fpdms-datasource-client">' . esc_html__('Select client', 'fp-dms') . '</label>';
        echo '<select name="client" id="fpdms-datasource-client" onchange="this.form.submit();" style="min-width:240px;">';

        foreach ($clients as $client) {
            $selected = $client->id === $selectedId ? ' selected="selected"' : '';
            echo '<option value="' . esc_attr((string) $client->id) . '"' . $selected . '>';
            echo esc_html($client->name);
            echo '</option>';
        }

        echo '</select>';
        echo '<noscript><button type="submit" class="button">' . esc_html__('Switch', 'fp-dms') . '</button></noscript>';
        echo '</form>';
    }
}
