<?php
/**
 * Debug Form Submission
 * Metti questo all'inizio di buildPayload() per vedere cosa arriva dal form
 */

// Log per debug - da inserire temporaneamente in DataSourcesPage.php::buildPayload()
$debugData = [
    'POST_type' => $_POST['type'] ?? 'N/A',
    'POST_auth' => $_POST['auth'] ?? [],
    'POST_config' => $_POST['config'] ?? [],
    'POST_keys' => array_keys($_POST),
];

error_log('=== FORM SUBMISSION DEBUG ===');
error_log('Type: ' . ($debugData['POST_type']));
error_log('POST keys: ' . print_r($debugData['POST_keys'], true));
error_log('POST auth: ' . print_r($debugData['POST_auth'], true));
error_log('POST config: ' . print_r($debugData['POST_config'], true));
error_log('=== END DEBUG ===');

